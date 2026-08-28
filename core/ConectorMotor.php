<?php

declare(strict_types=1);

namespace TaxiApp\Core;

use ElkinLinan\WhatsappAiEngine\Engine;
use TaxiApp\Domain\TaxiAdapter;
use TaxiApp\Ports\TaxiAlmacen;
use TaxiApp\Ports\TaxiCifrado;
use TaxiApp\Ports\TaxiDb;
use TaxiApp\Ports\TaxiTenant;

/**
 * Conecta el motor con la empresa correcta de TAXIS. Mismo rol que
 * `waConectarMotor()` en MayTech POS: arranca el motor apuntando a UNA
 * empresa, explícita — nunca "la actual", porque un webhook llega sin sesión
 * y sin subdominio garantizado.
 */
final class ConectorMotor
{
    public static function conectar(int $empresaId): void
    {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('El motor necesita saber de qué empresa es el mensaje.');
        }

        Engine::arrancar([
            'db' => new TaxiDb(Database::conexion()),
            'secreto' => new TaxiCifrado(),
            'negocio' => new TaxiTenant($empresaId),
            'archivo' => new TaxiAlmacen(),
            'dominio' => new TaxiAdapter(Database::conexion()),
        ]);
    }
}
