<?php

declare(strict_types=1);

namespace TaxiApp\Core;

use PDO;

abstract class BaseModel
{
    protected static string $tabla;
    protected static string $llavePrimaria = 'id';

    protected static function db(): PDO
    {
        return Database::conexion();
    }

    public static function encontrar(int $id): ?array
    {
        $sentencia = self::db()->prepare(
            'SELECT * FROM ' . static::$tabla . ' WHERE ' . static::$llavePrimaria . ' = :id LIMIT 1'
        );
        $sentencia->execute(['id' => $id]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    public static function todos(array $condiciones = [], string $orden = ''): array
    {
        $sql = 'SELECT * FROM ' . static::$tabla;
        if ($condiciones !== []) {
            $sql .= ' WHERE ' . implode(' AND ', array_map(
                static fn (string $campo): string => "{$campo} = :{$campo}",
                array_keys($condiciones)
            ));
        }
        if ($orden !== '') {
            $sql .= " ORDER BY {$orden}";
        }

        $sentencia = self::db()->prepare($sql);
        $sentencia->execute($condiciones);

        return $sentencia->fetchAll();
    }

    public static function crear(array $datos): int
    {
        $campos = array_keys($datos);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            static::$tabla,
            implode(', ', $campos),
            implode(', ', array_map(static fn (string $c): string => ":{$c}", $campos))
        );

        $sentencia = self::db()->prepare($sql);
        $sentencia->execute($datos);

        return (int) self::db()->lastInsertId();
    }

    public static function actualizar(int $id, array $datos): bool
    {
        $asignaciones = implode(', ', array_map(
            static fn (string $c): string => "{$c} = :{$c}",
            array_keys($datos)
        ));

        $sentencia = self::db()->prepare(
            'UPDATE ' . static::$tabla . " SET {$asignaciones} WHERE " . static::$llavePrimaria . ' = :__id'
        );

        return $sentencia->execute([...$datos, '__id' => $id]);
    }
}
