<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Database;

$pdo = Database::conexion();

$sentencia = $pdo->prepare(
    "SELECT c.id, c.telefono, c.nombre_contacto, c.estado, c.ultimo_mensaje_at, c.atendida_por,
            u.nombre AS atendida_por_nombre,
            (SELECT contenido FROM wa_mensajes m WHERE m.conversacion_id = c.id ORDER BY m.id DESC LIMIT 1) AS ultimo_mensaje
     FROM wa_conversaciones c
     LEFT JOIN tx_usuarios u ON u.id = c.atendida_por
     WHERE c.empresa_id = :empresa AND c.estado IN ('HUMANO_ATENDIENDO', 'IA_PAUSADA')
     ORDER BY c.ultimo_mensaje_at DESC"
);
$sentencia->execute(['empresa' => $usuarioActual['empresa_id']]);

echo json_encode(['conversaciones' => $sentencia->fetchAll()], JSON_UNESCAPED_UNICODE);
