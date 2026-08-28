<?php

declare(strict_types=1);

namespace TaxiApp\Ports;

use TaxiApp\Core\Database;

/**
 * Pendiente: conformar al TenantPort real del motor (resolución de tenant
 * por token de webhook, con 404 seco si no casa — regla §14.4 del system
 * prompt maestro). Hoy resuelve directo contra tx_lineas/tx_empresas.
 */
final class TaxiTenant
{
    public function resolverPorTokenWebhook(string $token): ?array
    {
        $sentencia = Database::conexion()->prepare(
            'SELECT l.*, e.nombre AS empresa_nombre, e.config AS empresa_config
             FROM tx_lineas l
             INNER JOIN tx_empresas e ON e.id = l.empresa_id
             WHERE l.token_webhook = :token AND l.activa = 1 AND e.activa = 1
             LIMIT 1'
        );
        $sentencia->execute(['token' => $token]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }
}
