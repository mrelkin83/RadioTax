<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use ElkinLinan\WhatsappAiEngine\Channel\EvolutionClient;
use ElkinLinan\WhatsappAiEngine\Core\ConversationManager;
use ElkinLinan\WhatsappAiEngine\Engine;
use TaxiApp\Core\Auth;
use TaxiApp\Core\ConectorMotor;
use TaxiApp\Core\Database;

Auth::verificarCsrf();

$datos = entrada();
$conversacionId = (int) ($datos['conversacion_id'] ?? 0);
$texto = trim((string) ($datos['texto'] ?? ''));

if ($conversacionId <= 0 || $texto === '') {
    http_response_code(400);
    echo json_encode(['error' => 'conversacion_id y texto son requeridos']);
    exit;
}

$pdo = Database::conexion();
$empresaId = $usuarioActual['empresa_id'];

$sentencia = $pdo->prepare(
    "SELECT id, telefono, estado, atendida_por FROM wa_conversaciones WHERE id = :id AND empresa_id = :empresa LIMIT 1"
);
$sentencia->execute(['id' => $conversacionId, 'empresa' => $empresaId]);
$conv = $sentencia->fetch();
if ($conv === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Conversación no encontrada']);
    exit;
}
if ($conv['estado'] === 'CERRADA') {
    http_response_code(409);
    echo json_encode(['error' => 'Esta conversación está cerrada']);
    exit;
}

$atendidaPor = $conv['atendida_por'] !== null ? (int) $conv['atendida_por'] : null;
if ($atendidaPor !== null && $atendidaPor !== (int) $usuarioActual['id'] && $usuarioActual['rol'] !== 'ADMIN') {
    $nombreOperador = $pdo->prepare('SELECT nombre FROM tx_usuarios WHERE id = :id LIMIT 1');
    $nombreOperador->execute(['id' => $atendidaPor]);
    $nombre = (string) $nombreOperador->fetchColumn();
    http_response_code(403);
    echo json_encode(['error' => 'Esta conversación ya la está atendiendo ' . ($nombre !== '' ? $nombre : 'otro operador') . '.']);
    exit;
}

try {
    ConectorMotor::conectar($empresaId);
    $canal = EvolutionClient::desdeConfig(Engine::db());
    if ($canal === null) {
        http_response_code(422);
        echo json_encode(['error' => 'El canal de WhatsApp no está configurado para esta empresa']);
        exit;
    }

    $envio = $canal->enviarTexto($conv['telefono'], $texto);
    if (empty($envio['ok'])) {
        http_response_code(502);
        echo json_encode(['error' => 'No se pudo enviar por WhatsApp: ' . ($envio['error'] ?? 'error desconocido')]);
        exit;
    }

    (new ConversationManager(Engine::db()))->guardarMensaje($conversacionId, 'saliente', [
        'message_id' => $envio['message_id'] ?? null,
        'tipo' => 'texto',
        'contenido' => $texto,
    ]);
    $pdo->prepare('UPDATE wa_conversaciones SET atendida_por = :usuario WHERE id = :id')
        ->execute(['usuario' => $usuarioActual['id'], 'id' => $conversacionId]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode(['ok' => true]);
