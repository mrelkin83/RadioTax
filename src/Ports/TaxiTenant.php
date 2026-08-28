<?php

declare(strict_types=1);

namespace TaxiApp\Ports;

use ElkinLinan\WhatsappAiEngine\Ports\TenantPort;
use TaxiApp\Core\Database;

/**
 * TAXIS guarda a todas las empresas en UNA base y las separa por la columna
 * empresa_id (el mismo patrón que MayTech POS, no el de ControlBarMax): por
 * eso scopeFila() devuelve la columna+valor en vez de null.
 *
 * Se construye por request con la empresa ya resuelta (por ahora, a mano en
 * los scripts que arrancan el motor; cuando exista el webhook de Capa 1, lo
 * resuelve TaxiTenant::resolverPorTokenWebhook() antes de instanciar esto).
 */
final class TaxiTenant implements TenantPort
{
    public function __construct(private readonly int $empresaId)
    {
    }

    public static function resolverPorTokenWebhook(string $token): ?array
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

    public function id(): ?int
    {
        return $this->empresaId;
    }

    public function nombre(): string
    {
        $sentencia = Database::conexion()->prepare('SELECT nombre FROM tx_empresas WHERE id = :id LIMIT 1');
        $sentencia->execute(['id' => $this->empresaId]);
        $nombre = $sentencia->fetchColumn();

        return $nombre !== false ? $nombre : 'Radio Tax';
    }

    public function baseDatos(): ?string
    {
        return null;
    }

    public function esMultiNegocio(): bool
    {
        return true;
    }

    public function scopeFila(): ?array
    {
        return ['columna' => 'empresa_id', 'valor' => $this->empresaId];
    }
}
