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
