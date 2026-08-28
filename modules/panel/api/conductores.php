<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Database;

$sentencia = Database::conexion()->prepare(
    "SELECT id, nombre, documento FROM tx_conductores WHERE empresa_id = :empresa AND estado = 'ACTIVO' ORDER BY nombre ASC"
);
$sentencia->execute(['empresa' => $usuarioActual['empresa_id']]);

echo json_encode(['conductores' => $sentencia->fetchAll()], JSON_UNESCAPED_UNICODE);
