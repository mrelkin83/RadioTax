<?php

declare(strict_types=1);

namespace TaxiApp\Ports;

use ElkinLinan\WhatsappAiEngine\Ports\DbPort;
use PDO;
use TaxiApp\Core\Database;

/**
 * TAXIS es "una base por empresa" en el sentido de DbPort::maestra()/conectarA():
 * una sola base de datos MySQL (multi-empresa por columna empresa_id, no por
 * base separada), así que ambas devuelven $this — no hay "otra base a la que
 * conectar" para el webhook.
 */
final class TaxiDb implements DbPort
{
    public function __construct(private readonly ?PDO $pdo = null)
    {
    }

    private function conexion(): PDO
    {
        return $this->pdo ?? Database::conexion();
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $sentencia = $this->conexion()->prepare($sql);
        $sentencia->execute($params);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $sentencia = $this->conexion()->prepare($sql);
        $sentencia->execute($params);

        return $sentencia->fetchAll();
    }

    public function insert(string $sql, array $params = []): int
    {
        $sentencia = $this->conexion()->prepare($sql);
        $sentencia->execute($params);

        return (int) $this->conexion()->lastInsertId();
    }

    public function query(string $sql, array $params = []): int
    {
        $sentencia = $this->conexion()->prepare($sql);
        $sentencia->execute($params);

        return $sentencia->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->conexion()->beginTransaction();
    }

    public function commit(): void
    {
        $this->conexion()->commit();
    }

    public function rollBack(): void
    {
        $this->conexion()->rollBack();
    }

    public function maestra(): DbPort
    {
        return $this;
    }

    public function conectarA(?string $baseDatos): DbPort
    {
        return $this;
    }
}
