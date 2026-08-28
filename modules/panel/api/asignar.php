<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;
use TaxiApp\Core\Database;

Auth::verificarCsrf();

$datos = entrada();
$carreraId = (int) ($datos['carrera_id'] ?? 0);
$vehiculoId = (int) ($datos['vehiculo_id'] ?? 0);

if ($carreraId <= 0 || $vehiculoId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'carrera_id y vehiculo_id son requeridos']);
    exit;
}

$pdo = Database::conexion();
$empresaId = $usuarioActual['empresa_id'];

$sentencia = $pdo->prepare('SELECT * FROM tx_carreras WHERE id = :id AND empresa_id = :empresa LIMIT 1');
$sentencia->execute(['id' => $carreraId, 'empresa' => $empresaId]);
$carrera = $sentencia->fetch();

if ($carrera === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Carrera no encontrada']);
    exit;
}

if (in_array($carrera['estado'], ['ASIGNADA', 'ACEPTADA', 'EN_CAMINO', 'EN_SERVICIO', 'FINALIZADA', 'CANCELADA', 'NO_ATENDIDA'], true)) {
    http_response_code(409);
    echo json_encode(['error' => "La carrera ya no admite asignación (estado {$carrera['estado']})"]);
    exit;
}

$sentencia = $pdo->prepare(
    "SELECT * FROM tx_vehiculos WHERE id = :id AND empresa_id = :empresa AND estado_vehiculo = 'DISPONIBLE' LIMIT 1"
);
$sentencia->execute(['id' => $vehiculoId, 'empresa' => $empresaId]);
if ($sentencia->fetch() === false) {
    http_response_code(409);
    echo json_encode(['error' => 'El vehículo no está disponible']);
    exit;
}

$sentencia = $pdo->prepare(
    'SELECT conductor_id FROM tx_vehiculo_conductor WHERE vehiculo_id = :vehiculo AND fecha_hasta IS NULL LIMIT 1'
);
$sentencia->execute(['vehiculo' => $vehiculoId]);
$conductorId = $sentencia->fetchColumn();
$conductorId = $conductorId !== false ? (int) $conductorId : null;

// Nota: en v1 el radiooperador ya confirmó por radioteléfono antes de pulsar
// este botón (§7 del system prompt maestro), por eso resultado='ACEPTADA'.
// Falta el paso "el bot informa al cliente" — pendiente de motor conversacional.
$pdo->beginTransaction();
try {
    $pdo->prepare(
        'UPDATE tx_carreras SET estado = "ASIGNADA", vehiculo_id = :vehiculo, conductor_id = :conductor, asignada_en = NOW() WHERE id = :id'
    )->execute(['vehiculo' => $vehiculoId, 'conductor' => $conductorId, 'id' => $carreraId]);

    $pdo->prepare('UPDATE tx_vehiculos SET estado_vehiculo = "SOLICITADO" WHERE id = :id')
        ->execute(['id' => $vehiculoId]);

    $pdo->prepare(
        "INSERT INTO tx_asignaciones (carrera_id, vehiculo_id, propuesto_por, decidido_por, resultado, medio)
         VALUES (:carrera, :vehiculo, 'RADIOOPERADOR', 'RADIOOPERADOR', 'ACEPTADA', 'RADIO')"
    )->execute(['carrera' => $carreraId, 'vehiculo' => $vehiculoId]);

    $pdo->prepare(
        'INSERT INTO tx_carrera_eventos (carrera_id, evento, actor_tipo, actor_id, detalle)
         VALUES (:carrera, "CARRERA_ASIGNADA", "RADIOOPERADOR", :actor, :detalle)'
    )->execute([
        'carrera' => $carreraId,
        'actor' => $usuarioActual['id'],
        'detalle' => json_encode(['vehiculo_id' => $vehiculoId, 'conductor_id' => $conductorId], JSON_UNESCAPED_UNICODE),
    ]);

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo asignar: ' . $e->getMessage()]);
    exit;
}

echo json_encode(['ok' => true]);
