<?php

declare(strict_types=1);

/**
 * Crea el primer SUPERADMIN (dueño de la plataforma, sin empresa). No hay
 * forma de crear el primero desde el panel — el panel de plataforma ya
 * exige ser SUPERADMIN para entrar.
 *
 *     php database/seed_superadmin.php [usuario] [clave] [nombre]
 */

require __DIR__ . '/../vendor/autoload.php';

use TaxiApp\Core\Database;

$pdo = Database::conexion();

$usuario = $argv[1] ?? 'superadmin';
$clave = $argv[2] ?? bin2hex(random_bytes(6));
$nombre = $argv[3] ?? 'Dueño de la plataforma';

$sentencia = $pdo->prepare('SELECT id FROM tx_usuarios WHERE usuario = :usuario LIMIT 1');
$sentencia->execute(['usuario' => $usuario]);
if ($sentencia->fetchColumn() !== false) {
    fwrite(STDERR, "El usuario '{$usuario}' ya existe.\n");
    exit(1);
}

$sentencia = $pdo->prepare(
    "INSERT INTO tx_usuarios (empresa_id, nombre, usuario, clave_hash, rol) VALUES (NULL, :nombre, :usuario, :hash, 'SUPERADMIN')"
);
$sentencia->execute([
    'nombre' => $nombre,
    'usuario' => $usuario,
    'hash' => password_hash($clave, PASSWORD_DEFAULT),
]);

echo "SUPERADMIN creado:\n";
echo "  usuario: {$usuario}\n";
echo "  clave:   {$clave}\n";
echo "Guárdala ahora, no se vuelve a mostrar. Entra por /modules/panel/login.php.\n";
