<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;
use TaxiApp\Core\Database;

Auth::verificarCsrf();

$estadosValidos = ['DISPONIBLE', 'SOLICITADO', 'EN_SERVICIO', 'EN_TURNO', 'FUERA_DE_TURNO', 'NO_DISPONIBLE', 'PENDIENTE_CONFIRMACION'];

$datos = entrada();
$vehiculoId = (int) ($datos['vehiculo_id'] ?? 0);
$estado = (string) ($datos['estado'] ?? '');

if ($vehiculoId <= 0 || !in_array($estado, $estadosValidos, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'vehiculo_id y un estado válido son requeridos']);
    exit;
}

$pdo = Database::conexion();

$sentencia = $pdo->prepare(
    'UPDATE tx_vehiculos SET estado_vehiculo = :estado WHERE id = :id AND empresa_id = :empresa'
);
$sentencia->execute(['estado' => $estado, 'id' => $vehiculoId, 'empresa' => $usuarioActual['empresa_id']]);

if ($sentencia->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Vehículo no encontrado']);
    exit;
}

// El operador puede liberar el vehículo a mano en cualquier momento (ej. el
// conductor avisa por radioteléfono que ya terminó) — cualquier estado que
// no sea "ocupado con la carrera" cuenta como liberación manual, para que
// el temporizador automático no lo vuelva a tocar después.
if (!in_array($estado, ['SOLICITADO', 'EN_SERVICIO'], true)) {
    $pdo->prepare(
        "UPDATE tx_carreras SET vehiculo_liberado_en = NOW(), vehiculo_liberado_por = 'MANUAL'
         WHERE vehiculo_id = :vehiculo AND vehiculo_liberado_en IS NULL
           AND estado NOT IN ('FINALIZADA', 'CANCELADA', 'NO_ATENDIDA')"
    )->execute(['vehiculo' => $vehiculoId]);
}

echo json_encode(['ok' => true]);
