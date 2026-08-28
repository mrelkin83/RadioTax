<?php

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use TaxiApp\Core\Auth;

Auth::iniciar();
$usuarioActual = Auth::requerirSesionApi();
header('Content-Type: application/json; charset=utf-8');

function entrada(): array
{
    $crudo = file_get_contents('php://input');
    if ($crudo === '' || $crudo === false) {
        return $_POST;
    }

    $decodificado = json_decode($crudo, true);

    return is_array($decodificado) ? $decodificado : $_POST;
}
