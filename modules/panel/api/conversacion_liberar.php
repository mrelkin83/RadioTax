<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use ElkinLinan\WhatsappAiEngine\Core\HumanHandoff;
use ElkinLinan\WhatsappAiEngine\Engine;
use TaxiApp\Core\Auth;
use TaxiApp\Core\ConectorMotor;
use TaxiApp\Core\Database;

Auth::verificarCsrf();

$datos = entrada();
$conversacionId = (int) ($datos['conversacion_id'] ?? 0);
if ($conversacionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'conversacion_id es requerido']);
    exit;
}

$pdo = Database::conexion();
$empresaId = $usuarioActual['empresa_id'];

$sentencia = $pdo->prepare('SELECT id, atendida_por FROM wa_conversaciones WHERE id = :id AND empresa_id = :empresa LIMIT 1');
$sentencia->execute(['id' => $conversacionId, 'empresa' => $empresaId]);
$conv = $sentencia->fetch();
if ($conv === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Conversación no encontrada']);
    exit;
}

$atendidaPor = $conv['atendida_por'] !== null ? (int) $conv['atendida_por'] : null;
if ($atendidaPor !== null && $atendidaPor !== (int) $usuarioActual['id'] && $usuarioActual['rol'] !== 'ADMIN') {
    $nombreOperador = $pdo->prepare('SELECT nombre FROM tx_usuarios WHERE id = :id LIMIT 1');
    $nombreOperador->execute(['id' => $atendidaPor]);
    $nombre = (string) $nombreOperador->fetchColumn();
    http_response_code(403);
    echo json_encode(['error' => 'Esta conversación la está atendiendo ' . ($nombre !== '' ? $nombre : 'otro operador') . '; no podés liberarla.']);
    exit;
}

ConectorMotor::conectar($empresaId);
(new HumanHandoff(Engine::db()))->liberar($conversacionId);

echo json_encode(['ok' => true]);
