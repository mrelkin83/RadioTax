<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;

Auth::cerrarSesion();
header('Location: /modules/panel/login.php');
exit;
