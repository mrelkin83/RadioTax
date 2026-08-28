<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaxiApp\Core\Database;

$pdo = Database::conexion();

$usuario = $argv[1] ?? 'operador1';
$clave = $argv[2] ?? bin2hex(random_bytes(6));
$nombre = $argv[3] ?? 'Operador de prueba';

$empresaId = (int) $pdo->query(
    "SELECT id FROM tx_empresas WHERE nombre = 'Radio Tax' LIMIT 1"
)->fetchColumn();

if ($empresaId === 0) {
    $pdo->exec(
        "INSERT INTO tx_empresas (nombre, ciudad, modo_despacho_default, config)
         VALUES ('Radio Tax', 'Arauca', 'HIBRIDO', JSON_OBJECT('tipos_servicio', JSON_ARRAY(
            JSON_OBJECT('codigo', 'TAXI', 'nombre', 'Taxi público'),
            JSON_OBJECT('codigo', 'CARGA', 'nombre', 'Transporte de carga')
         )))"
    );
    $empresaId = (int) $pdo->lastInsertId();
    echo "Empresa 'Radio Tax' creada (id {$empresaId}).\n";
} else {
    echo "Empresa 'Radio Tax' ya existía (id {$empresaId}).\n";
}

$lineaExiste = (int) $pdo->query(
    "SELECT COUNT(*) FROM tx_lineas WHERE empresa_id = {$empresaId}"
)->fetchColumn();

if ($lineaExiste === 0) {
    $sentencia = $pdo->prepare(
        'INSERT INTO tx_lineas (empresa_id, nombre, instancia_evolution, token_webhook, agentes_max)
         VALUES (:empresa, :nombre, :instancia, :token, 1)'
    );
    $sentencia->execute([
        'empresa' => $empresaId,
        'nombre' => 'Línea 1',
        'instancia' => 'radiotax-linea-1',
        'token' => bin2hex(random_bytes(16)),
    ]);
    echo "Línea 'Línea 1' creada.\n";
} else {
    echo "La empresa ya tiene líneas registradas, no se creó ninguna nueva.\n";
}

$sentencia = $pdo->prepare('SELECT id FROM tx_usuarios WHERE usuario = :usuario LIMIT 1');
$sentencia->execute(['usuario' => $usuario]);
if ($sentencia->fetchColumn() !== false) {
    fwrite(STDERR, "El usuario '{$usuario}' ya existe. Usa otro nombre de usuario si quieres crear uno nuevo.\n");
    exit(1);
}

$sentencia = $pdo->prepare(
    'INSERT INTO tx_usuarios (empresa_id, nombre, usuario, clave_hash, rol)
     VALUES (:empresa, :nombre, :usuario, :clave_hash, :rol)'
);
$sentencia->execute([
    'empresa' => $empresaId,
    'nombre' => $nombre,
    'usuario' => $usuario,
    'clave_hash' => password_hash($clave, PASSWORD_DEFAULT),
    'rol' => 'ADMIN',
]);

echo "\nUsuario creado:\n";
echo "  usuario: {$usuario}\n";
echo "  clave:   {$clave}\n";
echo "Guárdala ahora, no se vuelve a mostrar.\n";
