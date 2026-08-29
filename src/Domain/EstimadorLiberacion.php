<?php

declare(strict_types=1);

namespace TaxiApp\Domain;

use PDO;
use Throwable;

/**
 * Estima cuánto tarda un servicio completo (llegada del taxi al punto de
 * recogida + recorrido hasta el destino, con margen) para poder liberar el
 * vehículo automáticamente si nadie lo hizo a mano. v1 no tiene GPS del
 * conductor, así que esto es una red de seguridad basada en tiempo, no una
 * confirmación real de que el viaje terminó — por eso solo toca el estado
 * del vehículo, nunca el de la carrera (§ decisión: la carrera se cierra
 * aparte, a mano, para no dañar reportes si el viaje real se demoró más).
 */
final class EstimadorLiberacion
{
    private const MINUTOS_LLEGADA_DEFECTO = 10;
    private const VELOCIDAD_KMH_DEFECTO = 25;
    private const MINUTOS_RECORRIDO_SIN_COORDENADAS = 15;
    private const MARGEN_RECORRIDO = 1.25;

    public static function minutosEstimados(
        PDO $pdo,
        int $empresaId,
        ?float $recogidaLat,
        ?float $recogidaLng,
        ?float $destinoLat,
        ?float $destinoLng
    ): int {
        $sentencia = $pdo->prepare('SELECT tiempo_llegada_taxi_min, velocidad_promedio_kmh FROM tx_empresas WHERE id = :id LIMIT 1');
        $sentencia->execute(['id' => $empresaId]);
        $empresa = $sentencia->fetch();

        $minutosLlegada = (int) ($empresa['tiempo_llegada_taxi_min'] ?? 0) ?: self::MINUTOS_LLEGADA_DEFECTO;
        $velocidadKmh = (int) ($empresa['velocidad_promedio_kmh'] ?? 0) ?: self::VELOCIDAD_KMH_DEFECTO;

        if ($recogidaLat !== null && $recogidaLng !== null && $destinoLat !== null && $destinoLng !== null) {
            $distanciaKm = self::distanciaHaversine($recogidaLat, $recogidaLng, $destinoLat, $destinoLng);
            $minutosRecorrido = ($distanciaKm / $velocidadKmh) * 60 * self::MARGEN_RECORRIDO;
        } else {
            $minutosRecorrido = self::MINUTOS_RECORRIDO_SIN_COORDENADAS;
        }

        return (int) round($minutosLlegada + $minutosRecorrido);
    }

    private static function distanciaHaversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radioTierraKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $radioTierraKm * $c;
    }

    /**
     * Libera los vehículos cuyo tiempo estimado ya se cumplió y que nadie
     * liberó todavía. Se llama en cada refresco del panel — v1 no tiene cron.
     */
    public static function liberarVencidos(PDO $pdo, int $empresaId): void
    {
        $sentencia = $pdo->prepare(
            "SELECT id, vehiculo_id FROM tx_carreras
             WHERE empresa_id = :empresa
               AND estado IN ('ASIGNADA', 'ACEPTADA', 'EN_CAMINO', 'EN_SERVICIO')
               AND vehiculo_liberado_en IS NULL
               AND estimado_liberacion_en IS NOT NULL
               AND estimado_liberacion_en <= NOW()"
        );
        $sentencia->execute(['empresa' => $empresaId]);
        $vencidas = $sentencia->fetchAll();

        foreach ($vencidas as $carrera) {
            $pdo->beginTransaction();
            try {
                $marcado = $pdo->prepare(
                    "UPDATE tx_carreras SET vehiculo_liberado_en = NOW(), vehiculo_liberado_por = 'AUTOMATICO'
                     WHERE id = :id AND vehiculo_liberado_en IS NULL"
                );
                $marcado->execute(['id' => $carrera['id']]);

                if ($marcado->rowCount() > 0) {
                    $pdo->prepare("UPDATE tx_vehiculos SET estado_vehiculo = 'DISPONIBLE' WHERE id = :id")
                        ->execute(['id' => $carrera['vehiculo_id']]);

                    $pdo->prepare(
                        'INSERT INTO tx_carrera_eventos (carrera_id, evento, actor_tipo, actor_id, detalle)
                         VALUES (:carrera, "VEHICULO_LIBERADO_AUTOMATICO", "SISTEMA", NULL, :detalle)'
                    )->execute([
                        'carrera' => $carrera['id'],
                        'detalle' => json_encode(['vehiculo_id' => $carrera['vehiculo_id']], JSON_UNESCAPED_UNICODE),
                    ]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
            }
        }
    }
}
