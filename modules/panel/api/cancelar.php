<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;
use TaxiApp\Core\Database;
use TaxiApp\Domain\TaxiAdapter;

Auth::verificarCsrf();

$datos = entrada();
$carreraId = (int) ($datos['carrera_id'] ?? 0);
$motivo = trim((string) ($datos['motivo'] ?? ''));

if ($carreraId <= 0 || $motivo === '') {
    http_response_code(400);
    echo json_encode(['error' => 'carrera_id y motivo son requeridos']);
    exit;
}

$pdo = Database::conexion();

$sentencia = $pdo->prepare('SELECT vehiculo_id FROM tx_carreras WHERE id = :id AND empresa_id = :empresa LIMIT 1');
$sentencia->execute(['id' => $carreraId, 'empresa' => $usuarioActual['empresa_id']]);
$carrera = $sentencia->fetch();

if ($carrera === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Carrera no encontrada']);
    exit;
}

try {
    (new TaxiAdapter($pdo))->cancelarCarrera($carreraId, $motivo, 'RADIOOPERADOR', $usuarioActual['id']);
} catch (\Throwable $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

if ($carrera['vehiculo_id'] !== null) {
    $pdo->prepare("UPDATE tx_vehiculos SET estado_vehiculo = 'DISPONIBLE' WHERE id = :id AND estado_vehiculo = 'SOLICITADO'")
        ->execute(['id' => $carrera['vehiculo_id']]);
}

echo json_encode(['ok' => true]);
