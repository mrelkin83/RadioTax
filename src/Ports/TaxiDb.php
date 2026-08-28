<?php

declare(strict_types=1);

namespace TaxiApp\Ports;

use PDO;
use TaxiApp\Core\Database;

/**
 * Pendiente: conformar al puerto de base de datos real que exija
 * ElkinLinan\WhatsappAiEngine\Engine::arrancar(['db' => ...]) en cuanto
 * el paquete esté disponible. Hoy expone la conexión PDO de la plataforma.
 */
final class TaxiDb
{
    public function conexion(): PDO
    {
        return Database::conexion();
    }
}
