<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaxiApp\Core\Database;
use TaxiApp\Domain\TaxiAdapter;

$host = getenv('DB_HOST') ?: '127.0.0.1';
$puerto = getenv('DB_PORT') ?: '3306';
$usuario = getenv('DB_USERNAME') ?: 'root';
$clave = getenv('DB_PASSWORD') ?: '';
$baseTemporal = 'taxiapp_test_' . bin2hex(random_bytes(4));

$admin = new PDO("mysql:host={$host};port={$puerto};charset=utf8mb4", $usuario, $clave, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$admin->exec("CREATE DATABASE `{$baseTemporal}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

putenv("DB_DATABASE={$baseTemporal}");

$fallas = 0;

function afirmar(bool $condicion, string $mensaje): void
{
    global $fallas;
    if ($condicion) {
        echo "  OK  {$mensaje}\n";
        return;
    }

    $fallas++;
    echo "FALLA {$mensaje}\n";
}

try {
    $conexion = Database::conexion();

    $migraciones = glob(__DIR__ . '/../database/migrations/*.sql');
    sort($migraciones);
    foreach ($migraciones as $archivo) {
        $conexion->exec((string) file_get_contents($archivo));
    }

    $adapter = new TaxiAdapter($conexion);

    $conexion->exec("INSERT INTO tx_empresas (id, nombre, ciudad, modo_despacho_default) VALUES (1, 'Radio Tax', 'Arauca', 'HIBRIDO')");
    $conexion->exec("INSERT INTO tx_lineas (id, empresa_id, nombre, instancia_evolution, token_webhook) VALUES (1, 1, 'Linea 1', 'evo-1', 'token-1')");
    $conexion->exec("INSERT INTO tx_clientes (id, empresa_id, whatsapp, nombre) VALUES (1, 1, '3001234567', 'Cliente de prueba')");

    afirmar($adapter->buscarItems(1) !== [], 'buscarItems() devuelve el catálogo por defecto');

    $datosCarrera = [
        'empresa_id' => 1,
        'linea_id' => 1,
        'cliente_id' => 1,
        'conversacion_ref' => 'conv-1',
        'tipo_servicio' => 'TAXI',
        'recogida_texto' => 'Calle 10 # 5-20',
        'destino_texto' => 'Terminal de transportes',
    ];

    $carrera = $adapter->crearTransaccion($datosCarrera);
    afirmar($carrera['estado'] === 'RECIBIDA', 'crearTransaccion() inicia en estado RECIBIDA');

    $repetida = $adapter->crearTransaccion($datosCarrera);
    afirmar($repetida['id'] === $carrera['id'], 'crearTransaccion() es idempotente (misma clave -> misma carrera)');

    $adapter->confirmarTransaccion((int) $carrera['id']);
    $estado = $adapter->estadoTransaccion((int) $carrera['id']);
    afirmar($estado['estado'] === 'EN_DESPACHO', 'confirmarTransaccion() mueve la carrera a EN_DESPACHO');

    $total = $adapter->calcularTotal((int) $carrera['id']);
    afirmar($total['monto'] === null, 'calcularTotal() nunca inventa un monto');

    $adapter->cancelarTransaccion((int) $carrera['id'], 'Cliente desistió', 'IA');
    $estado = $adapter->estadoTransaccion((int) $carrera['id']);
    afirmar($estado['estado'] === 'CANCELADA', 'cancelarTransaccion() cancela con motivo');

    $eventos = (int) $conexion->query('SELECT COUNT(*) FROM tx_carrera_eventos WHERE carrera_id = ' . (int) $carrera['id'])->fetchColumn();
    afirmar($eventos >= 3, 'cada transición de la carrera queda registrada en tx_carrera_eventos');

    afirmar($adapter->capacidades()['pagos'] === false, 'capacidades() nunca declara pagos');
} finally {
    Database::reiniciar();
    $admin->exec("DROP DATABASE `{$baseTemporal}`");
}

if ($fallas > 0) {
    fwrite(STDERR, "\n{$fallas} prueba(s) fallaron.\n");
    exit(1);
}

echo "\nTodas las pruebas del TaxiAdapter pasaron.\n";
echo "Nota: integración con el motor real (elkinlinan/whatsapp-ai-engine) pendiente — ver docs/ESTADO_Y_PENDIENTES.md\n";
