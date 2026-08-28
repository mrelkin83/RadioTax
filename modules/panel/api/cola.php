<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Database;

$empresaId = $usuarioActual['empresa_id'];
$pdo = Database::conexion();

$sentencia = $pdo->prepare(
    "SELECT c.*, cl.nombre AS cliente_nombre, cl.whatsapp AS cliente_whatsapp
     FROM tx_carreras c
     INNER JOIN tx_clientes cl ON cl.id = c.cliente_id
     WHERE c.empresa_id = :empresa
       AND c.estado NOT IN ('FINALIZADA','CANCELADA','NO_ATENDIDA')
     ORDER BY c.creado_en ASC"
);
$sentencia->execute(['empresa' => $empresaId]);
$cola = $sentencia->fetchAll();

$sentencia = $pdo->prepare(
    "SELECT c.*, cl.nombre AS cliente_nombre, cl.whatsapp AS cliente_whatsapp
     FROM tx_carreras c
     INNER JOIN tx_clientes cl ON cl.id = c.cliente_id
     WHERE c.empresa_id = :empresa
       AND c.estado IN ('FINALIZADA','CANCELADA','NO_ATENDIDA')
       AND DATE(c.creado_en) = CURDATE()
     ORDER BY c.creado_en DESC
     LIMIT 50"
);
$sentencia->execute(['empresa' => $empresaId]);
$finalizadas = $sentencia->fetchAll();

echo json_encode(['cola' => $cola, 'finalizadas' => $finalizadas], JSON_UNESCAPED_UNICODE);
