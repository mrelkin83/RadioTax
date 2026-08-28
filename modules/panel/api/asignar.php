<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use ElkinLinan\WhatsappAiEngine\Channel\EvolutionClient;
use ElkinLinan\WhatsappAiEngine\Engine;
use TaxiApp\Core\Auth;
use TaxiApp\Core\ConectorMotor;
use TaxiApp\Core\Database;

Auth::verificarCsrf();

$datos = entrada();
$carreraId = (int) ($datos['carrera_id'] ?? 0);
$vehiculoId = (int) ($datos['vehiculo_id'] ?? 0);

if ($carreraId <= 0 || $vehiculoId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'carrera_id y vehiculo_id son requeridos']);
    exit;
}

$pdo = Database::conexion();
$empresaId = $usuarioActual['empresa_id'];

$sentencia = $pdo->prepare('SELECT * FROM tx_carreras WHERE id = :id AND empresa_id = :empresa LIMIT 1');
$sentencia->execute(['id' => $carreraId, 'empresa' => $empresaId]);
$carrera = $sentencia->fetch();

if ($carrera === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Carrera no encontrada']);
    exit;
}

if (in_array($carrera['estado'], ['ASIGNADA', 'ACEPTADA', 'EN_CAMINO', 'EN_SERVICIO', 'FINALIZADA', 'CANCELADA', 'NO_ATENDIDA'], true)) {
    http_response_code(409);
    echo json_encode(['error' => "La carrera ya no admite asignación (estado {$carrera['estado']})"]);
    exit;
}

$sentencia = $pdo->prepare(
    "SELECT * FROM tx_vehiculos WHERE id = :id AND empresa_id = :empresa AND estado_vehiculo = 'DISPONIBLE' LIMIT 1"
);
$sentencia->execute(['id' => $vehiculoId, 'empresa' => $empresaId]);
if ($sentencia->fetch() === false) {
    http_response_code(409);
    echo json_encode(['error' => 'El vehículo no está disponible']);
    exit;
}

$sentencia = $pdo->prepare(
    'SELECT conductor_id FROM tx_vehiculo_conductor WHERE vehiculo_id = :vehiculo AND fecha_hasta IS NULL LIMIT 1'
);
$sentencia->execute(['vehiculo' => $vehiculoId]);
$conductorId = $sentencia->fetchColumn();
$conductorId = $conductorId !== false ? (int) $conductorId : null;

// Nota: en v1 el radiooperador ya confirmó por radioteléfono antes de pulsar
// este botón (§7 del system prompt maestro), por eso resultado='ACEPTADA'.
$pdo->beginTransaction();
try {
    $pdo->prepare(
        'UPDATE tx_carreras SET estado = "ASIGNADA", vehiculo_id = :vehiculo, conductor_id = :conductor, asignada_en = NOW() WHERE id = :id'
    )->execute(['vehiculo' => $vehiculoId, 'conductor' => $conductorId, 'id' => $carreraId]);

    $pdo->prepare('UPDATE tx_vehiculos SET estado_vehiculo = "SOLICITADO" WHERE id = :id')
        ->execute(['id' => $vehiculoId]);

    $pdo->prepare(
        "INSERT INTO tx_asignaciones (carrera_id, vehiculo_id, propuesto_por, decidido_por, resultado, medio)
         VALUES (:carrera, :vehiculo, 'RADIOOPERADOR', 'RADIOOPERADOR', 'ACEPTADA', 'RADIO')"
    )->execute(['carrera' => $carreraId, 'vehiculo' => $vehiculoId]);

    $pdo->prepare(
        'INSERT INTO tx_carrera_eventos (carrera_id, evento, actor_tipo, actor_id, detalle)
         VALUES (:carrera, "CARRERA_ASIGNADA", "RADIOOPERADOR", :actor, :detalle)'
    )->execute([
        'carrera' => $carreraId,
        'actor' => $usuarioActual['id'],
        'detalle' => json_encode(['vehiculo_id' => $vehiculoId, 'conductor_id' => $conductorId], JSON_UNESCAPED_UNICODE),
    ]);

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo asignar: ' . $e->getMessage()]);
    exit;
}

// El bot le confirma al cliente (§7). Es "mejor esfuerzo": si el motor no
// está configurado o el envío falla, la asignación YA quedó hecha — no se
// revierte por esto. El resultado queda en tx_carrera_eventos, no en un
// error de la petición.
notificarClienteAsignacion($pdo, $empresaId, $carreraId, $vehiculoId, $usuarioActual['id']);

echo json_encode(['ok' => true]);

function notificarClienteAsignacion(PDO $pdo, int $empresaId, int $carreraId, int $vehiculoId, int $usuarioId): void
{
    $sentencia = $pdo->prepare(
        'SELECT c.whatsapp, v.numero_interno, v.placa, cond.nombre AS conductor_nombre
         FROM tx_carreras carr
         INNER JOIN tx_clientes c ON c.id = carr.cliente_id
         INNER JOIN tx_vehiculos v ON v.id = :vehiculo
         LEFT JOIN tx_vehiculo_conductor vc ON vc.vehiculo_id = v.id AND vc.fecha_hasta IS NULL
         LEFT JOIN tx_conductores cond ON cond.id = vc.conductor_id
         WHERE carr.id = :carrera'
    );
    $sentencia->execute(['vehiculo' => $vehiculoId, 'carrera' => $carreraId]);
    $datos = $sentencia->fetch();
    if ($datos === false || empty($datos['whatsapp'])) {
        return;
    }

    $texto = "🚕 Tu servicio fue asignado al vehículo {$datos['numero_interno']}"
        . ($datos['placa'] ? " ({$datos['placa']})" : '')
        . ($datos['conductor_nombre'] ? ", conductor {$datos['conductor_nombre']}" : '')
        . '. Ya va en camino.';

    $resultado = 'sin_intentar';
    try {
        ConectorMotor::conectar($empresaId);
        $canal = EvolutionClient::desdeConfig(Engine::db());
        if ($canal === null) {
            $resultado = 'motor_no_configurado';
        } else {
            $envio = $canal->enviarTexto($datos['whatsapp'], $texto);
            $resultado = !empty($envio['ok']) ? 'enviado' : ('fallo: ' . ($envio['error'] ?? ''));
        }
    } catch (\Throwable $e) {
        $resultado = 'excepcion: ' . $e->getMessage();
    }

    $pdo->prepare(
        'INSERT INTO tx_carrera_eventos (carrera_id, evento, actor_tipo, actor_id, detalle)
         VALUES (:carrera, "NOTIFICACION_CLIENTE", "SISTEMA", :actor, :detalle)'
    )->execute([
        'carrera' => $carreraId,
        'actor' => $usuarioId,
        'detalle' => json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE),
    ]);
}
