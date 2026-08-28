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

$sentencia = $pdo->prepare('SELECT id FROM wa_conversaciones WHERE id = :id AND empresa_id = :empresa LIMIT 1');
$sentencia->execute(['id' => $conversacionId, 'empresa' => $empresaId]);
if ($sentencia->fetchColumn() === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Conversación no encontrada']);
    exit;
}

ConectorMotor::conectar($empresaId);
(new HumanHandoff(Engine::db()))->liberar($conversacionId);

echo json_encode(['ok' => true]);
