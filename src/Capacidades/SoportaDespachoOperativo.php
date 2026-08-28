<?php

declare(strict_types=1);

namespace TaxiApp\Capacidades;

interface SoportaDespachoOperativo
{
    public function estadoDeCarrera(int $carreraId): array;

    public function candidatosDisponibles(int $empresaId, string $tipoServicio): array;
}
