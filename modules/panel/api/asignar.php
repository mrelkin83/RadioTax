<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;
use TaxiApp\Core\Database;
use TaxiApp\Core\Notificaciones;

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
    'SELECT cond.id, cond.whatsapp FROM tx_vehiculo_conductor vc
     INNER JOIN tx_conductores cond ON cond.id = vc.conductor_id
     WHERE vc.vehiculo_id = :vehiculo AND vc.fecha_hasta IS NULL LIMIT 1'
);
$sentencia->execute(['vehiculo' => $vehiculoId]);
$conductor = $sentencia->fetch();
$conductorId = $conductor !== false ? (int) $conductor['id'] : null;
$whatsappConductor = $conductor !== false ? (string) ($conductor['whatsapp'] ?? '') : '';

// §10: si el conductor tiene WhatsApp registrado, se le pide confirmación
// (modo WhatsApp — medio pendiente hasta que responda ACEPTO/RECHAZO). Si
// no tiene, o el envío falla, se cae al modo v1: el radiooperador YA
// confirmó por radioteléfono antes de pulsar este botón, así que queda
// aceptada de una vez (medio=RADIO).
$usaConfirmacionWhatsapp = $whatsappConductor !== '';

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'UPDATE tx_carreras SET estado = "ASIGNADA", vehiculo_id = :vehiculo, conductor_id = :conductor, asignada_en = NOW() WHERE id = :id'
    )->execute(['vehiculo' => $vehiculoId, 'conductor' => $conductorId, 'id' => $carreraId]);

    $pdo->prepare('UPDATE tx_vehiculos SET estado_vehiculo = "SOLICITADO" WHERE id = :id')
        ->execute(['id' => $vehiculoId]);

    $pdo->prepare(
        'INSERT INTO tx_asignaciones (carrera_id, vehiculo_id, propuesto_por, decidido_por, resultado, medio)
         VALUES (:carrera, :vehiculo, "RADIOOPERADOR", "RADIOOPERADOR", :resultado, :medio)'
    )->execute([
        'carrera' => $carreraId,
        'vehiculo' => $vehiculoId,
        'resultado' => $usaConfirmacionWhatsapp ? 'SIN_RESPUESTA' : 'ACEPTADA',
        'medio' => $usaConfirmacionWhatsapp ? 'WHATSAPP' : 'RADIO',
    ]);

    $pdo->prepare(
        'INSERT INTO tx_carrera_eventos (carrera_id, evento, actor_tipo, actor_id, detalle)
         VALUES (:carrera, "CARRERA_ASIGNADA", "RADIOOPERADOR", :actor, :detalle)'
    )->execute([
        'carrera' => $carreraId,
        'actor' => $usuarioActual['id'],
        'detalle' => json_encode(['vehiculo_id' => $vehiculoId, 'conductor_id' => $conductorId, 'medio' => $usaConfirmacionWhatsapp ? 'WHATSAPP' : 'RADIO'], JSON_UNESCAPED_UNICODE),
    ]);

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo asignar: ' . $e->getMessage()]);
    exit;
}

if ($usaConfirmacionWhatsapp) {
    // Se le pregunta al conductor; si el envío falla de plano, no hay forma
    // de que responda — mejor esfuerzo, el radiooperador lo verá en el
    // evento SOLICITUD_CONFIRMACION_CONDUCTOR si algo salió mal.
    Notificaciones::pedirConfirmacionConductor($pdo, $empresaId, $carreraId, $whatsappConductor);
} else {
    // Modo radio: ya está aceptada, se le confirma al cliente de una vez (§7).
    Notificaciones::notificarClienteAsignacion($pdo, $empresaId, $carreraId, $usuarioActual['id']);
}

echo json_encode(['ok' => true, 'modo' => $usaConfirmacionWhatsapp ? 'whatsapp' : 'radio']);
