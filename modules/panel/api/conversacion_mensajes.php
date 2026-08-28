<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Database;

$conversacionId = (int) ($_GET['conversacion_id'] ?? 0);
if ($conversacionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'conversacion_id es requerido']);
    exit;
}

$pdo = Database::conexion();

$sentencia = $pdo->prepare('SELECT id FROM wa_conversaciones WHERE id = :id AND empresa_id = :empresa LIMIT 1');
$sentencia->execute(['id' => $conversacionId, 'empresa' => $usuarioActual['empresa_id']]);
if ($sentencia->fetchColumn() === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Conversación no encontrada']);
    exit;
}

$sentencia = $pdo->prepare(
    'SELECT direccion, tipo, contenido, transcripcion, created_at
     FROM wa_mensajes WHERE conversacion_id = :id ORDER BY id ASC LIMIT 100'
);
$sentencia->execute(['id' => $conversacionId]);

echo json_encode(['mensajes' => $sentencia->fetchAll()], JSON_UNESCAPED_UNICODE);
