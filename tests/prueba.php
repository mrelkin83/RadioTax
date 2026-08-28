<?php

declare(strict_types=1);

/**
 * ============================================================================
 * La prueba que cierra la Fase 0: el motor arranca de verdad contra TaxiAdapter
 * ============================================================================
 * No usa un adaptador de mentira (el paquete ya trae el suyo, TiendaFalsa, y
 * se prueba solo en packages/whatsapp-engine/tests/prueba.php): usa el
 * TaxiAdapter real contra una base de datos MySQL temporal, con
 * Engine::arrancar() de por medio. Si esto pasa, el motor y el dominio taxi
 * encajan sin haber tocado ToolEngine.php para nada específico de taxi (solo
 * se generalizó de forma neutral: SinCatalogoDeProductos y
 * SoportaHerramientasPersonalizadas sirven para cualquier negocio que no sea
 * "producto + cantidad", no solo para taxis).
 */

require __DIR__ . '/../vendor/autoload.php';

use ElkinLinan\WhatsappAiEngine\Core\ToolEngine;
use ElkinLinan\WhatsappAiEngine\Engine;
use ElkinLinan\WhatsappAiEngine\Ports\DomainAdapter;
use ElkinLinan\WhatsappAiEngine\Ports\SinCatalogoDeProductos;
use ElkinLinan\WhatsappAiEngine\Ports\SoportaHerramientasPersonalizadas;
use TaxiApp\Core\Database;
use TaxiApp\Domain\TaxiAdapter;
use TaxiApp\Ports\TaxiAlmacen;
use TaxiApp\Ports\TaxiCifrado;
use TaxiApp\Ports\TaxiDb;
use TaxiApp\Ports\TaxiTenant;

putenv('APP_SECRET_KEY=clave-de-prueba-no-usar-en-produccion');

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

    $conexion->exec("INSERT INTO tx_empresas (id, nombre, ciudad, modo_despacho_default) VALUES (1, 'Radio Tax', 'Arauca', 'HIBRIDO')");
    $conexion->exec("INSERT INTO tx_lineas (id, empresa_id, nombre, instancia_evolution, token_webhook) VALUES (1, 1, 'Linea 1', 'evo-1', 'token-1')");

    /* ── El motor arranca con los puertos reales de TAXIS ────────────────── */
    echo "\n-- El motor arranca contra TaxiAdapter --\n";

    $adapter = new TaxiAdapter($conexion);
    Engine::reiniciar();
    Engine::arrancar([
        'db' => new TaxiDb($conexion),
        'secreto' => new TaxiCifrado(),
        'archivo' => new TaxiAlmacen(sys_get_temp_dir() . '/taxiapp_test_media'),
        'negocio' => new TaxiTenant(1),
        'dominio' => $adapter,
    ]);

    afirmar(Engine::dominio() === $adapter, 'Engine::dominio() devuelve el TaxiAdapter que se le pasó');
    afirmar($adapter instanceof DomainAdapter, 'TaxiAdapter implementa DomainAdapter');
    afirmar($adapter instanceof SinCatalogoDeProductos, 'TaxiAdapter declara que no vende productos con cantidad');
    afirmar($adapter instanceof SoportaHerramientasPersonalizadas, 'TaxiAdapter ofrece sus propias herramientas');
    afirmar(Engine::negocio()->id() === 1, 'El tenant activo es la empresa 1');
    afirmar(Engine::negocio()->scopeFila() === ['columna' => 'empresa_id', 'valor' => 1], 'TAXIS es "una base, una columna" (como MayTech)');

    /* ── Las herramientas de carrito de compra quedan apagadas ───────────── */
    echo "\n-- El catálogo de producto+cantidad está apagado --\n";

    $tools = new ToolEngine(new TaxiDb($conexion), $adapter);
    $nombres = array_column($tools->definiciones(), 'name');

    foreach (['consultar_menu', 'consultar_stock', 'calcular_total', 'crear_pedido', 'consultar_pedido', 'cancelar_pedido', 'consultar_estado_cocina', 'generar_pago', 'consultar_pago'] as $herramientaRetail) {
        afirmar(!in_array($herramientaRetail, $nombres, true), "{$herramientaRetail} no se le ofrece al modelo (TAXI no vende productos)");
    }

    $r = $tools->ejecutar('crear_pedido', ['items' => [['producto_id' => 'x', 'cantidad' => 1]]], ['conversacion' => [], 'config' => null, 'agente' => null, 'canal' => null]);
    afirmar($r['ok'] === false, 'Y si el modelo la llama igual, se le dice que no existe para este negocio');

    afirmar(in_array('transferir_a_humano', $nombres, true), 'transferir_a_humano sigue disponible (no se puede deshabilitar)');

    /* ── Las herramientas propias de taxi, ejecutadas de verdad ──────────── */
    echo "\n-- Las herramientas de TAXI, ejecutadas contra el contrato --\n";

    foreach (['identificar_cliente', 'consultar_tipos_servicio', 'consultar_direcciones_frecuentes', 'registrar_solicitud', 'consultar_estado_carrera', 'cancelar_carrera'] as $herramientaTaxi) {
        afirmar(in_array($herramientaTaxi, $nombres, true), "{$herramientaTaxi} se le ofrece al modelo");
    }

    $conv = ['id' => 1, 'estado' => 'IA_ACTIVA', 'telefono' => '3001234567', 'nombre_contacto' => 'Ana', 'linea_id' => 1];
    $ctx = ['conversacion' => $conv, 'config' => null, 'agente' => null, 'canal' => null];

    $r = $tools->ejecutar('identificar_cliente', [], $ctx);
    afirmar(!empty($r['ok']) && $r['cliente_id'] > 0, 'identificar_cliente crea/reconoce al cliente por WhatsApp');

    $r = $tools->ejecutar('consultar_tipos_servicio', [], $ctx);
    afirmar(!empty($r['ok']) && count($r['tipos_servicio']) === 2, 'consultar_tipos_servicio trae el catálogo por defecto');

    $r = $tools->ejecutar('consultar_direcciones_frecuentes', [], $ctx);
    afirmar(!empty($r['ok']) && $r['direcciones'] === [], 'consultar_direcciones_frecuentes empieza vacío para un cliente nuevo');

    $r = $tools->ejecutar('registrar_solicitud', [
        'tipo_servicio' => 'TAXI', 'recogida_texto' => 'Calle 10 # 5-20', 'destino_texto' => 'Terminal',
    ], $ctx);
    afirmar(!empty($r['ok']) && $r['estado'] === 'RECIBIDA', 'registrar_solicitud crea la carrera vía el motor, sin tocar tx_* directamente');
    $carreraId = (int) $r['carrera_id'];

    $r2 = $tools->ejecutar('registrar_solicitud', [
        'tipo_servicio' => 'TAXI', 'recogida_texto' => 'Calle 10 # 5-20', 'destino_texto' => 'Terminal',
    ], $ctx);
    afirmar($r2['carrera_id'] === $carreraId, 'registrar_solicitud es idempotente llamada desde el motor');

    $r = $tools->ejecutar('consultar_estado_carrera', ['carrera_id' => $carreraId], $ctx);
    afirmar(!empty($r['ok']) && $r['carrera']['estado'] === 'RECIBIDA', 'consultar_estado_carrera ve el estado real');

    $otraConv = ['id' => 2, 'estado' => 'IA_ACTIVA', 'telefono' => '3009998877', 'nombre_contacto' => 'Luis', 'linea_id' => 1];
    $r = $tools->ejecutar('consultar_estado_carrera', ['carrera_id' => $carreraId], ['conversacion' => $otraConv, 'config' => null, 'agente' => null, 'canal' => null]);
    afirmar($r['ok'] === false, 'Un cliente no puede consultar la carrera de otro');

    $r = $tools->ejecutar('cancelar_carrera', ['carrera_id' => $carreraId, 'motivo' => 'Ya no lo necesito'], $ctx);
    afirmar(!empty($r['ok']) && !empty($r['cancelada']), 'cancelar_carrera cancela con motivo, vía el motor');

    $eventos = (int) $conexion->query("SELECT COUNT(*) FROM tx_carrera_eventos WHERE carrera_id = {$carreraId} AND actor_tipo = 'IA'")->fetchColumn();
    afirmar($eventos >= 2, 'la trazabilidad queda con actor_tipo=IA cuando el motor la llama');

    /* ── Aislamiento: capacidades() no declara nada retail ───────────────── */
    afirmar($adapter->capacidades() === [], 'capacidades() no declara nada del vocabulario retail del motor');

    Engine::reiniciar();
} finally {
    Database::reiniciar();
    $admin->exec("DROP DATABASE `{$baseTemporal}`");
}

if ($fallas > 0) {
    fwrite(STDERR, "\n{$fallas} prueba(s) fallaron.\n");
    exit(1);
}

echo "\nEl motor arranca contra TaxiAdapter y las pruebas del contrato están en verde.\n";
echo "(Fase 0, definición de hecho §13 — falta solo lo que depende de Capa 1: el webhook real de WhatsApp.)\n";
