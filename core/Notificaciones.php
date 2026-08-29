<?php

declare(strict_types=1);

namespace TaxiApp\Core;

use ElkinLinan\WhatsappAiEngine\Channel\EvolutionClient;
use ElkinLinan\WhatsappAiEngine\Engine;
use PDO;

/**
 * Mensajes salientes que NO pasan por el agente de IA (§10 del system prompt
 * maestro: "EvolutionClient directo, sin orquestador"). Compartida entre el
 * panel (al asignar) y el webhook (al recibir la respuesta del conductor).
 */
final class Notificaciones
{
    /** Envía un texto por WhatsApp. 'ok'=>false con el motivo si no se pudo. */
    public static function enviar(int $empresaId, string $whatsapp, string $texto): array
    {
        if ($whatsapp === '') {
            return ['ok' => false, 'error' => 'sin_whatsapp'];
        }

        try {
            ConectorMotor::conectar($empresaId);
            $canal = EvolutionClient::desdeConfig(Engine::db());
            if ($canal === null) {
                return ['ok' => false, 'error' => 'motor_no_configurado'];
            }

            $envio = $canal->enviarTexto($whatsapp, $texto);

            return !empty($envio['ok']) ? ['ok' => true] : ['ok' => false, 'error' => $envio['error'] ?? 'desconocido'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Notifica al cliente que su carrera fue asignada, y deja el resultado en tx_carrera_eventos. */
    public static function notificarClienteAsignacion(PDO $pdo, int $empresaId, int $carreraId, ?int $actorUsuarioId): void
    {
        $sentencia = $pdo->prepare(
            'SELECT cl.whatsapp, v.numero_interno, v.placa, cond.nombre AS conductor_nombre
             FROM tx_carreras c
             INNER JOIN tx_clientes cl ON cl.id = c.cliente_id
             LEFT JOIN tx_vehiculos v ON v.id = c.vehiculo_id
             LEFT JOIN tx_conductores cond ON cond.id = c.conductor_id
             WHERE c.id = :carrera'
        );
        $sentencia->execute(['carrera' => $carreraId]);
        $datos = $sentencia->fetch();
        if ($datos === false || empty($datos['whatsapp'])) {
            return;
        }

        $texto = "🚕 Tu servicio fue asignado al vehículo {$datos['numero_interno']}"
            . ($datos['placa'] ? " ({$datos['placa']})" : '')
            . ($datos['conductor_nombre'] ? ", conductor {$datos['conductor_nombre']}" : '')
            . '. Ya va en camino.';

        $envio = self::enviar($empresaId, $datos['whatsapp'], $texto);

        self::registrarEvento($pdo, $carreraId, 'NOTIFICACION_CLIENTE', $actorUsuarioId, $envio);
    }

    /**
     * Le pide confirmación al conductor por WhatsApp. Devuelve true si se
     * pudo enviar — el llamador decide qué hacer si no (v1: caer al modo
     * "ya confirmado por radio").
     */
    public static function pedirConfirmacionConductor(PDO $pdo, int $empresaId, int $carreraId, string $whatsappConductor): bool
    {
        $sentencia = $pdo->prepare('SELECT recogida_texto, destino_texto, tipo_servicio FROM tx_carreras WHERE id = :id');
        $sentencia->execute(['id' => $carreraId]);
        $carrera = $sentencia->fetch();
        if ($carrera === false) {
            return false;
        }

        $texto = "🚕 Nuevo servicio ({$carrera['tipo_servicio']})\n"
            . "📍 Recogida: {$carrera['recogida_texto']}\n"
            . "🎯 Destino: {$carrera['destino_texto']}\n\n"
            . 'Responde ACEPTO o RECHAZO.';

        $envio = self::enviar($empresaId, $whatsappConductor, $texto);
        self::registrarEvento($pdo, $carreraId, 'SOLICITUD_CONFIRMACION_CONDUCTOR', null, $envio);

        return $envio['ok'];
    }

    private static function registrarEvento(PDO $pdo, int $carreraId, string $evento, ?int $actorUsuarioId, array $envio): void
    {
        $pdo->prepare(
            'INSERT INTO tx_carrera_eventos (carrera_id, evento, actor_tipo, actor_id, detalle)
             VALUES (:carrera, :evento, "SISTEMA", :actor, :detalle)'
        )->execute([
            'carrera' => $carreraId,
            'evento' => $evento,
            'actor' => $actorUsuarioId,
            'detalle' => json_encode(
                ['resultado' => !empty($envio['ok']) ? 'enviado' : ('fallo: ' . ($envio['error'] ?? ''))],
                JSON_UNESCAPED_UNICODE
            ),
        ]);
    }
}
