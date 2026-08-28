<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use TaxiApp\Core\Auth;

Auth::iniciar();
$usuarioActual = Auth::requerirSesion();

if ($usuarioActual['rol'] !== 'ADMIN') {
    http_response_code(403);
    echo 'No autorizado. Esta sección es solo para administradores.';
    exit;
}
