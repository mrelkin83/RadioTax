<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;
use TaxiApp\Core\Database;

Auth::verificarCsrf();

$datos = entrada();
$conductorId = (int) ($datos['conductor_id'] ?? 0);
$vehiculoId = (int) ($datos['vehiculo_id'] ?? 0);

if ($conductorId <= 0 || $vehiculoId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'conductor_id y vehiculo_id son requeridos']);
    exit;
}

$pdo = Database::conexion();
$empresaId = $usuarioActual['empresa_id'];

$sentencia = $pdo->prepare('SELECT id FROM tx_conductores WHERE id = :id AND empresa_id = :empresa LIMIT 1');
$sentencia->execute(['id' => $conductorId, 'empresa' => $empresaId]);
if ($sentencia->fetchColumn() === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Conductor no encontrado']);
    exit;
}

$sentencia = $pdo->prepare('SELECT id FROM tx_vehiculos WHERE id = :id AND empresa_id = :empresa LIMIT 1');
$sentencia->execute(['id' => $vehiculoId, 'empresa' => $empresaId]);
if ($sentencia->fetchColumn() === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Vehículo no encontrado']);
    exit;
}

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'UPDATE tx_vehiculo_conductor SET fecha_hasta = NOW()
         WHERE fecha_hasta IS NULL AND (vehiculo_id = :vehiculo OR conductor_id = :conductor)'
    )->execute(['vehiculo' => $vehiculoId, 'conductor' => $conductorId]);

    $pdo->prepare(
        'INSERT INTO tx_vehiculo_conductor (vehiculo_id, conductor_id, fecha_desde) VALUES (:vehiculo, :conductor, NOW())'
    )->execute(['vehiculo' => $vehiculoId, 'conductor' => $conductorId]);

    $pdo->prepare(
        "INSERT INTO tx_turnos (conductor_id, vehiculo_id, inicio, abierto_por) VALUES (:conductor, :vehiculo, NOW(), 'OPERADOR')"
    )->execute(['conductor' => $conductorId, 'vehiculo' => $vehiculoId]);

    $pdo->prepare("UPDATE tx_vehiculos SET estado_vehiculo = 'DISPONIBLE' WHERE id = :id")
        ->execute(['id' => $vehiculoId]);

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo abrir el turno: ' . $e->getMessage()]);
    exit;
}

echo json_encode(['ok' => true]);
