<?php

declare(strict_types=1);

namespace TaxiApp\Core;

final class Env
{
    private static bool $cargado = false;

    public static function cargar(?string $ruta = null): void
    {
        if (self::$cargado) {
            return;
        }

        self::$cargado = true;
        $ruta ??= dirname(__DIR__) . '/.env';

        if (!is_file($ruta)) {
            return;
        }

        foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
                continue;
            }

            [$clave, $valor] = explode('=', $linea, 2);
            $clave = trim($clave);
            $valor = trim($valor, " \t\n\r\0\x0B\"'");

            if (getenv($clave) === false) {
                putenv("{$clave}={$valor}");
                $_ENV[$clave] = $valor;
            }
        }
    }
}
