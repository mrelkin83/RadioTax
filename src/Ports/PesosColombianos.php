<?php

declare(strict_types=1);

namespace TaxiApp\Ports;

/**
 * Formateador de moneda para el puerto 'formato' de Engine::arrancar().
 * El proyecto no cobra carreras (regla §2), pero el motor puede necesitar
 * formatear montos en otros contextos (ej. planes, si se activan a futuro).
 */
final class PesosColombianos
{
    public function formatear(int $centavos): string
    {
        $pesos = intdiv($centavos, 100);

        return '$' . number_format($pesos, 0, ',', '.');
    }
}
