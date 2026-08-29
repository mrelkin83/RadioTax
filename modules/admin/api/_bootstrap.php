<?php

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use TaxiApp\Core\Auth;

Auth::iniciar();
$usuarioActual = Auth::requerirSesionApiDeEmpresa();
header('Content-Type: application/json; charset=utf-8');

if ($usuarioActual['rol'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado, esta sección es solo para administradores.'], JSON_UNESCAPED_UNICODE);
    exit;
}
