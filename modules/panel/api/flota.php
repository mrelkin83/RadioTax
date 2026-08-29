<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Database;
use TaxiApp\Domain\EstimadorLiberacion;

$pdo = Database::conexion();
EstimadorLiberacion::liberarVencidos($pdo, $usuarioActual['empresa_id']);

$sentencia = $pdo->prepare(
    "SELECT v.id, v.numero_interno, v.placa, v.tipo, v.estado_vehiculo,
            cond.id AS conductor_id, cond.nombre AS conductor_nombre,
            t.id AS turno_id, t.inicio AS turno_inicio
     FROM tx_vehiculos v
     LEFT JOIN tx_vehiculo_conductor vc ON vc.vehiculo_id = v.id AND vc.fecha_hasta IS NULL
     LEFT JOIN tx_conductores cond ON cond.id = vc.conductor_id
     LEFT JOIN tx_turnos t ON t.vehiculo_id = v.id AND t.fin IS NULL
     WHERE v.empresa_id = :empresa
     ORDER BY v.numero_interno ASC"
);
$sentencia->execute(['empresa' => $usuarioActual['empresa_id']]);

echo json_encode(['flota' => $sentencia->fetchAll()], JSON_UNESCAPED_UNICODE);
