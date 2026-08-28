<?php

declare(strict_types=1);

namespace TaxiApp\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $conexion = null;

    public static function conexion(): PDO
    {
        if (self::$conexion === null) {
            self::$conexion = self::conectar();
        }

        return self::$conexion;
    }

    public static function usar(PDO $conexion): void
    {
        self::$conexion = $conexion;
    }

    public static function reiniciar(): void
    {
        self::$conexion = null;
    }

    private static function conectar(): PDO
    {
        Env::cargar();

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $puerto = getenv('DB_PORT') ?: '3306';
        $base = getenv('DB_DATABASE') ?: 'taxiapp';
        $usuario = getenv('DB_USERNAME') ?: 'root';
        $clave = getenv('DB_PASSWORD') ?: '';

        $dsn = "mysql:host={$host};port={$puerto};dbname={$base};charset=utf8mb4";

        try {
            return new PDO($dsn, $usuario, $clave, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException("No se pudo conectar a la base de datos: {$e->getMessage()}", previous: $e);
        }
    }
}
