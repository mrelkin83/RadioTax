<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TaxiApp\Core\Auth;
use TaxiApp\Core\Database;

Auth::verificarCsrf();

/**
 * El resto del ciclo de vida de la carrera después de asignada (§6 del
 * system prompt maestro). v1 no tiene app de conductor, así que el
 * radiooperador es quien marca cada paso por radioteléfono — igual que ya
 * hace con "Asignar". Cada transición es una llamada, un estado destino.
 */
$transicionesValidas = [
    'ASIGNADA' => 'EN_CAMINO',
    'EN_CAMINO' => 'EN_SERVICIO',
    'EN_SERVICIO' => 'FINALIZADA',
];
$estadosTerminales = ['FINALIZADA', 'CANCELADA', 'NO_ATENDIDA'];

$datos = entrada();
$carreraId = (int) ($datos['carrera_id'] ?? 0);
$estadoDestino = (string) ($datos['estado'] ?? '');

if ($carreraId <= 0 || !in_array($estadoDestino, $transicionesValidas, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'carrera_id y un estado destino válido son requeridos']);
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

// Además del paso a paso normal, "Finalizar manualmente" permite cerrar la
// carrera de una vez desde cualquier estado activo (§ pedido del radio-
// operador: no obligarlo a pasar por cada paso cuando ya sabe que terminó).
$transicionNormal = ($transicionesValidas[$carrera['estado']] ?? null) === $estadoDestino;
$finalizacionManual = $estadoDestino === 'FINALIZADA' && !in_array($carrera['estado'], $estadosTerminales, true);

if (!$transicionNormal && !$finalizacionManual) {
    http_response_code(409);
    echo json_encode(['error' => "No se puede pasar de {$carrera['estado']} a {$estadoDestino}"]);
    exit;
}

$pdo->beginTransaction();
try {
    if ($estadoDestino === 'FINALIZADA') {
        $pdo->prepare('UPDATE tx_carreras SET estado = :estado, finalizada_en = NOW() WHERE id = :id')
            ->execute(['estado' => $estadoDestino, 'id' => $carreraId]);

        // Si el temporizador automático no llegó a liberar el vehículo antes,
        // esta finalización manual cuenta como la liberación.
        $pdo->prepare(
            "UPDATE tx_carreras SET vehiculo_liberado_en = NOW(), vehiculo_liberado_por = 'MANUAL'
             WHERE id = :id AND vehiculo_liberado_en IS NULL"
        )->execute(['id' => $carreraId]);
    } else {
        $pdo->prepare('UPDATE tx_carreras SET estado = :estado WHERE id = :id')
            ->execute(['estado' => $estadoDestino, 'id' => $carreraId]);
    }

    // El vehículo refleja dónde va la carrera: SOLICITADO mientras se acerca
    // (asignada o en camino), EN_SERVICIO con el cliente a bordo, DISPONIBLE
    // otra vez al terminar.
    if ($carrera['vehiculo_id'] !== null) {
        $estadoVehiculo = match ($estadoDestino) {
            'EN_SERVICIO' => 'EN_SERVICIO',
            'FINALIZADA' => 'DISPONIBLE',
            default => null,
        };
        if ($estadoVehiculo !== null) {
            $pdo->prepare('UPDATE tx_vehiculos SET estado_vehiculo = :estado WHERE id = :id')
                ->execute(['estado' => $estadoVehiculo, 'id' => $carrera['vehiculo_id']]);
        }
    }

    $pdo->prepare(
        'INSERT INTO tx_carrera_eventos (carrera_id, evento, actor_tipo, actor_id, detalle)
         VALUES (:carrera, :evento, "RADIOOPERADOR", :actor, :detalle)'
    )->execute([
        'carrera' => $carreraId,
        'evento' => 'CARRERA_' . $estadoDestino,
        'actor' => $usuarioActual['id'],
        'detalle' => json_encode(['desde' => $carrera['estado']], JSON_UNESCAPED_UNICODE),
    ]);

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo actualizar el estado: ' . $e->getMessage()]);
    exit;
}

echo json_encode(['ok' => true]);
