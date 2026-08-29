<?php

declare(strict_types=1);

// Punto de entrada de la raíz. login.php ya sabe mandar a cada quien a su
// sitio (SUPERADMIN a /modules/plataforma/, el resto al Centro de
// transmisión) si ya tiene sesión — así que basta con apuntar siempre aquí.
header('Location: /modules/panel/login.php');
exit;
