<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaxiApp\Core\Database;

$pdo = Database::conexion();

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS tx_migraciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    archivo VARCHAR(255) NOT NULL UNIQUE,
    aplicada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);

$aplicadas = $pdo->query('SELECT archivo FROM tx_migraciones')->fetchAll(PDO::FETCH_COLUMN);

$archivos = glob(__DIR__ . '/migrations/*.sql');
sort($archivos);

foreach ($archivos as $ruta) {
    $nombre = basename($ruta);
    if (in_array($nombre, $aplicadas, true)) {
        continue;
    }

    echo "Aplicando {$nombre}...\n";

    $pdo->beginTransaction();
    try {
        $pdo->exec((string) file_get_contents($ruta));
        $sentencia = $pdo->prepare('INSERT INTO tx_migraciones (archivo) VALUES (:archivo)');
        $sentencia->execute(['archivo' => $nombre]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "Error en {$nombre}: {$e->getMessage()}\n");
        exit(1);
    }
}

echo "Migraciones al día.\n";
