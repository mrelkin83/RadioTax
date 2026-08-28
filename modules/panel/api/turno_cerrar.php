<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;
use TaxiApp\Core\Database;

Auth::verificarCsrf();

$datos = entrada();
$turnoId = (int) ($datos['turno_id'] ?? 0);

if ($turnoId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'turno_id es requerido']);
    exit;
}

$pdo = Database::conexion();

$sentencia = $pdo->prepare(
    'SELECT t.id, t.vehiculo_id FROM tx_turnos t
     INNER JOIN tx_vehiculos v ON v.id = t.vehiculo_id
     WHERE t.id = :id AND v.empresa_id = :empresa AND t.fin IS NULL LIMIT 1'
);
$sentencia->execute(['id' => $turnoId, 'empresa' => $usuarioActual['empresa_id']]);
$turno = $sentencia->fetch();

if ($turno === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Turno no encontrado o ya cerrado']);
    exit;
}

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE tx_turnos SET fin = NOW() WHERE id = :id')->execute(['id' => $turnoId]);
    $pdo->prepare("UPDATE tx_vehiculos SET estado_vehiculo = 'FUERA_DE_TURNO' WHERE id = :id")
        ->execute(['id' => $turno['vehiculo_id']]);
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo cerrar el turno: ' . $e->getMessage()]);
    exit;
}

echo json_encode(['ok' => true]);
