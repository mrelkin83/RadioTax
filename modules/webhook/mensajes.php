<?php

declare(strict_types=1);

/**
 * ============================================================================
 * El borde del motor — mensajes entrantes de WhatsApp (Evolution API)
 * ============================================================================
 * Mismo orden que en MayTech POS (el primer consumidor que resolvió esto
 * contra una base compartida, no una por negocio):
 *
 *   1. resolver la EMPRESA por el token de la URL (404 seco si no casa),
 *   2. arrancar el motor apuntando a ESA empresa — antes de descifrar nada,
 *   3. deduplicar por message_id,
 *   4. responder 200 YA,
 *   5. y solo entonces pensar, que es lo que tarda segundos.
 *
 * v1 solo procesa TEXTO (ni audio ni imagen): STT/TTS/Visión del motor
 * necesitan credenciales de proveedor que todavía no existen para este
 * proyecto. Un mensaje que no es texto se contesta pidiendo que lo escriban.
 *
 * URL de webhook en Evolution: POST /modules/webhook/mensajes.php?token=...
 */

require __DIR__ . '/../../vendor/autoload.php';

use ElkinLinan\WhatsappAiEngine\Channel\EvolutionClient;
use ElkinLinan\WhatsappAiEngine\Core\AgentManager;
use ElkinLinan\WhatsappAiEngine\Core\AiOrchestrator;
use ElkinLinan\WhatsappAiEngine\Core\AuditLogger;
use ElkinLinan\WhatsappAiEngine\Core\ConversationManager;
use ElkinLinan\WhatsappAiEngine\Core\HumanHandoff;
use ElkinLinan\WhatsappAiEngine\Core\RateLimiter;
use ElkinLinan\WhatsappAiEngine\Core\WaConfig;
use ElkinLinan\WhatsappAiEngine\Engine;
use TaxiApp\Core\ConectorMotor;
use TaxiApp\Core\Database;
use TaxiApp\Core\Notificaciones;

if (session_status() === PHP_SESSION_ACTIVE) {
    @session_abort();
}

function responder200(array $cuerpo = ['ok' => true]): void
{
    $json = json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('Connection: close');
        header('Content-Length: ' . strlen((string) $json));
    }
    echo $json;
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        flush();
    }
}

function noEncontrado(): never
{
    http_response_code(404);
    exit;
}

/**
 * De qué empresa es este token. Compara el SHA-256, nunca el token en claro.
 * 0 si no casa con nadie — el llamador responde 404 seco.
 */
function empresaDelToken(string $token): int
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return 0;
    }

    $sentencia = Database::conexion()->prepare(
        'SELECT empresa_id FROM wa_config WHERE webhook_token_hash = :hash LIMIT 1'
    );
    $sentencia->execute(['hash' => hash('sha256', $token)]);
    $empresaId = $sentencia->fetchColumn();

    return $empresaId !== false ? (int) $empresaId : 0;
}

$token = (string) ($_GET['token'] ?? '');
$empresaId = empresaDelToken($token);
if ($empresaId === 0) {
    noEncontrado();
}

ConectorMotor::conectar($empresaId);

$db = Engine::db();
$log = new AuditLogger($db);

$crudo = file_get_contents('php://input');
$payload = json_decode((string) $crudo, true);
if (!is_array($payload)) {
    responder200(['ok' => false, 'error' => 'payload']);
    exit;
}

$cfg = WaConfig::cargar($db);
if (!$cfg || (int) $cfg['activo'] !== 1) {
    responder200(['ok' => true, 'ignorado' => 'motor apagado']);
    exit;
}

$canal = EvolutionClient::desdeConfig($db);
if (!$canal) {
    responder200(['ok' => false, 'error' => 'canal no configurado']);
    exit;
}

$apikeyGuardada = WaConfig::secreto($cfg, 'evolution_apikey');
$apikeyRecibida = $_SERVER['HTTP_APIKEY'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
if ($apikeyGuardada !== '' && $apikeyRecibida !== '' && !hash_equals($apikeyGuardada, (string) $apikeyRecibida)) {
    $log->log('webhook', 'Webhook con apikey incorrecta', null);
    noEncontrado();
}

$msg = $canal->normalizarWebhook($payload);
if (!$msg) {
    responder200(['ok' => true, 'ignorado' => 'no es un mensaje entrante']);
    exit;
}

// §10: un conductor que escribe por el mismo número de la empresa no
// conversa con el agente — se le contesta ACEPTO/RECHAZO por un camino
// aparte, sin pasar por wa_conversaciones ni por el LLM. Se resuelve ANTES
// del flujo de cliente a propósito, para que un conductor jamás caiga en
// el agente y viceversa.
$conductorId = conductorDelTelefono($empresaId, $msg['telefono']);
if ($conductorId !== null) {
    responder200(['ok' => true]);
    try {
        procesarRespuestaConductor($empresaId, $canal, $conductorId, trim((string) $msg['texto']), $msg['telefono']);
    } catch (\Throwable $e) {
        $log->error('Fallo procesando respuesta de conductor: ' . $e->getMessage()
            . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']');
    }
    exit;
}

$cm = new ConversationManager($db);
$conv = $cm->obtenerOCrear($msg['telefono'], $msg['nombre']);

$msgId = $cm->guardarMensaje((int) $conv['id'], 'entrante', [
    'message_id' => $msg['message_id'] ?: null,
    'tipo' => $msg['tipo'],
    'contenido' => $msg['texto'],
    'media_mime' => $msg['media_mime'],
]);
if ($msgId === 0) {
    responder200(['ok' => true, 'duplicado' => true]);
    exit;
}
$cm->tocar((int) $conv['id']);

// A partir de aquí el cliente ya recibió su 200.
responder200(['ok' => true]);

try {
    procesarMensaje($db, $log, $canal, $cfg, $conv, $msg);
} catch (\Throwable $e) {
    $log->error('Fallo procesando el mensaje: ' . $e->getMessage()
        . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']', null, (int) $conv['id']);

    try {
        $canal->enviarTexto($msg['telefono'], (new AgentManager($db))->mensajeError());
    } catch (\Throwable $e2) {
    }

    try {
        (new HumanHandoff($db, $log))->transferir((int) $conv['id'], 'El motor falló procesando un mensaje', $canal, $cfg);
    } catch (\Throwable $e3) {
    }
}

function procesarMensaje($db, AuditLogger $log, $canal, array $cfg, array $conv, array $msg): void
{
    if (!HumanHandoff::iaPuedeResponder($conv)) {
        $log->log('mensaje', 'Mensaje recibido con la IA en pausa', ['estado' => $conv['estado']], (int) $conv['id']);
        return;
    }

    $lim = (new RateLimiter($db, $log))->comprobar($conv, $cfg);
    if (!$lim['permitido']) {
        if ($lim['avisar']) {
            $canal->enviarTexto($msg['telefono'], $lim['mensaje']);
            (new ConversationManager($db))->guardarMensaje((int) $conv['id'], 'saliente', [
                'tipo' => 'texto', 'contenido' => $lim['mensaje'],
            ]);
        }
        return;
    }

    if ($msg['tipo'] !== 'texto') {
        $texto = 'Por ahora solo puedo leer mensajes de texto por aquí 🙏 ¿me lo escribes?';
        $canal->enviarTexto($msg['telefono'], $texto);
        (new ConversationManager($db))->guardarMensaje((int) $conv['id'], 'saliente', [
            'tipo' => 'texto', 'contenido' => $texto,
        ]);
        return;
    }

    $texto = trim((string) $msg['texto']);
    if ($texto === '') {
        return;
    }

    // AiOrchestrator::procesar() ya envía la respuesta por el canal y la deja
    // guardada en wa_mensajes — el valor de retorno es solo informativo (lo
    // usaría, por ejemplo, para mandar la voz de vuelta si hubiera TTS).
    $orq = new AiOrchestrator($db, $canal, $log);
    $orq->procesar($conv, $texto);
}

/**
 * Id del conductor de esta empresa dueño de este número, o null.
 *
 * Compara por los últimos 10 dígitos (el celular colombiano sin el indicativo
 * de país), no el número completo: quien lo registra a mano en el panel no
 * siempre teclea el "57", y WhatsApp SIEMPRE lo manda en el JID. Comparar
 * exacto dejaba a todo conductor sin poder confirmar nunca — el mensaje caía
 * en el flujo de cliente en vez de en el de confirmación.
 */
function conductorDelTelefono(int $empresaId, string $telefono): ?int
{
    if (strpos($telefono, '@') !== false) {
        return null; // JID de un LID (@lid): no es un teléfono comparable, no puede ser un conductor registrado
    }

    $sentencia = Database::conexion()->prepare(
        "SELECT id FROM tx_conductores
         WHERE empresa_id = :empresa AND estado = 'ACTIVO'
           AND RIGHT(whatsapp, 10) = RIGHT(:whatsapp, 10) AND whatsapp IS NOT NULL AND whatsapp != ''
         LIMIT 1"
    );
    $sentencia->execute(['empresa' => $empresaId, 'whatsapp' => $telefono]);
    $id = $sentencia->fetchColumn();

    return $id !== false ? (int) $id : null;
}

/**
 * ACEPTO/RECHAZO de un conductor (§10, criterio de hecho de Fase 2: "un
 * rechazo devuelve la carrera a despacho sin intervención de código").
 * Idempotente a propósito: solo actúa si la asignación sigue SIN_RESPUESTA,
 * así que un reintento del webhook o un "ACEPTO" repetido no hacen nada raro.
 */
function procesarRespuestaConductor(int $empresaId, $canal, int $conductorId, string $texto, string $telefono): void
{
    $pdo = Database::conexion();

    $sentencia = $pdo->prepare(
        "SELECT a.id AS asignacion_id, c.id AS carrera_id, c.vehiculo_id
         FROM tx_asignaciones a
         INNER JOIN tx_carreras c ON c.id = a.carrera_id
         WHERE c.conductor_id = :conductor AND c.empresa_id = :empresa AND a.resultado = 'SIN_RESPUESTA'
         ORDER BY a.id DESC LIMIT 1"
    );
    $sentencia->execute(['conductor' => $conductorId, 'empresa' => $empresaId]);
    $pendiente = $sentencia->fetch();

    if ($pendiente === false) {
        Notificaciones::enviar($empresaId, $telefono, 'No tengo ningún servicio pendiente de tu confirmación en este momento.');
        return;
    }

    $textoNorm = mb_strtoupper(trim($texto));
    $esAceptacion = (bool) preg_match('/\b(ACEPTO|SI|SÍ|OK|VALE)\b/u', $textoNorm);
    $esRechazo = (bool) preg_match('/\b(RECHAZO|NO|CANCELO)\b/u', $textoNorm);

    if (!$esAceptacion && !$esRechazo) {
        Notificaciones::enviar($empresaId, $telefono, 'No entendí tu respuesta 🙏 Responde ACEPTO o RECHAZO.');
        return;
    }

    $carreraId = (int) $pendiente['carrera_id'];
    $vehiculoId = (int) $pendiente['vehiculo_id'];

    if ($esAceptacion) {
        $pdo->prepare('UPDATE tx_asignaciones SET resultado = "ACEPTADA" WHERE id = :id')
            ->execute(['id' => $pendiente['asignacion_id']]);
        $pdo->prepare(
            'INSERT INTO tx_carrera_eventos (carrera_id, evento, actor_tipo, detalle)
             VALUES (:carrera, "CONDUCTOR_ACEPTO", "CONDUCTOR", :detalle)'
        )->execute(['carrera' => $carreraId, 'detalle' => json_encode(['conductor_id' => $conductorId], JSON_UNESCAPED_UNICODE)]);

        Notificaciones::enviar($empresaId, $telefono, '👍 Confirmado, gracias.');
        Notificaciones::notificarClienteAsignacion($pdo, $empresaId, $carreraId, null);
        return;
    }

    // Rechazo: se libera el vehículo y la carrera vuelve a despacho SIN
    // tocar código — el radiooperador la vuelve a ver en la cola.
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE tx_asignaciones SET resultado = "RECHAZADA" WHERE id = :id')
            ->execute(['id' => $pendiente['asignacion_id']]);
        $pdo->prepare('UPDATE tx_carreras SET estado = "EN_DESPACHO", vehiculo_id = NULL, conductor_id = NULL WHERE id = :id')
            ->execute(['id' => $carreraId]);
        $pdo->prepare('UPDATE tx_vehiculos SET estado_vehiculo = "DISPONIBLE" WHERE id = :id')
            ->execute(['id' => $vehiculoId]);
        $pdo->prepare(
            'INSERT INTO tx_carrera_eventos (carrera_id, evento, actor_tipo, detalle)
             VALUES (:carrera, "CONDUCTOR_RECHAZO", "CONDUCTOR", :detalle)'
        )->execute(['carrera' => $carreraId, 'detalle' => json_encode(['conductor_id' => $conductorId], JSON_UNESCAPED_UNICODE)]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    Notificaciones::enviar($empresaId, $telefono, 'Entendido, gracias por avisar.');
}
