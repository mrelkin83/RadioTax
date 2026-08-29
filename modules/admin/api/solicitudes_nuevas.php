<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Database;
use TaxiApp\Domain\EstimadorLiberacion;

$pdo = Database::conexion();
$empresaId = $usuarioActual['empresa_id'];

// Las páginas de administración no tienen otro punto de refresco periódico
// — aprovechamos este poll (el único que corre mientras el admin navega
// fuera del panel de despacho) para que la liberación automática de
// vehículos siga funcionando aunque nadie tenga el panel abierto.
EstimadorLiberacion::liberarVencidos($pdo, $empresaId);

$sentencia = $pdo->prepare(
    "SELECT c.id, c.tipo_servicio, c.recogida_texto, c.destino_texto, c.estado, c.creado_en,
            cl.nombre AS cliente_nombre, cl.whatsapp AS cliente_whatsapp
     FROM tx_carreras c
     INNER JOIN tx_clientes cl ON cl.id = c.cliente_id
     WHERE c.empresa_id = :empresa
       AND c.estado NOT IN ('FINALIZADA', 'CANCELADA', 'NO_ATENDIDA')
       AND c.creado_en >= (NOW() - INTERVAL 3 MINUTE)
     ORDER BY c.creado_en DESC
     LIMIT 20"
);
$sentencia->execute(['empresa' => $empresaId]);

echo json_encode(['ok' => true, 'solicitudes' => $sentencia->fetchAll()], JSON_UNESCAPED_UNICODE);
