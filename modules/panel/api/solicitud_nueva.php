<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;
use TaxiApp\Core\Database;
use TaxiApp\Domain\TaxiAdapter;

Auth::verificarCsrf();

$datos = entrada();
$clienteWhatsapp = trim((string) ($datos['cliente_whatsapp'] ?? ''));
$clienteNombre = trim((string) ($datos['cliente_nombre'] ?? ''));
$tipoServicio = trim((string) ($datos['tipo_servicio'] ?? ''));
$recogida = trim((string) ($datos['recogida_texto'] ?? ''));
$destino = trim((string) ($datos['destino_texto'] ?? ''));
$observaciones = trim((string) ($datos['observaciones'] ?? ''));

if ($clienteWhatsapp === '' || $tipoServicio === '' || $recogida === '' || $destino === '') {
    http_response_code(400);
    echo json_encode(['error' => 'cliente_whatsapp, tipo_servicio, recogida_texto y destino_texto son requeridos']);
    exit;
}

$pdo = Database::conexion();
$empresaId = $usuarioActual['empresa_id'];

$sentencia = $pdo->prepare('SELECT id FROM tx_lineas WHERE empresa_id = :empresa ORDER BY id ASC LIMIT 1');
$sentencia->execute(['empresa' => $empresaId]);
$lineaId = $sentencia->fetchColumn();
if ($lineaId === false) {
    http_response_code(422);
    echo json_encode(['error' => 'La empresa no tiene líneas configuradas']);
    exit;
}

$sentencia = $pdo->prepare('SELECT id FROM tx_clientes WHERE empresa_id = :empresa AND whatsapp = :whatsapp LIMIT 1');
$sentencia->execute(['empresa' => $empresaId, 'whatsapp' => $clienteWhatsapp]);
$clienteId = $sentencia->fetchColumn();

if ($clienteId === false) {
    $sentencia = $pdo->prepare(
        "INSERT INTO tx_clientes (empresa_id, whatsapp, nombre, creado_por) VALUES (:empresa, :whatsapp, :nombre, 'OPERADOR')"
    );
    $sentencia->execute([
        'empresa' => $empresaId,
        'whatsapp' => $clienteWhatsapp,
        'nombre' => $clienteNombre !== '' ? $clienteNombre : null,
    ]);
    $clienteId = (int) $pdo->lastInsertId();
}

try {
    $carrera = (new TaxiAdapter($pdo))->crearTransaccion([
        'empresa_id' => $empresaId,
        'linea_id' => (int) $lineaId,
        'cliente_id' => (int) $clienteId,
        'conversacion_ref' => 'panel-manual-' . bin2hex(random_bytes(8)),
        'tipo_servicio' => $tipoServicio,
        'recogida_texto' => $recogida,
        'destino_texto' => $destino,
        'observaciones' => $observaciones !== '' ? $observaciones : null,
        'actor_id' => $usuarioActual['id'],
    ], 'RADIOOPERADOR');
} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode(['ok' => true, 'carrera' => $carrera]);
