<?php

declare(strict_types=1);

namespace TaxiApp\Capacidades;

interface SoportaDireccionesFrecuentes
{
    public function listar(int $clienteId): array;

    public function guardar(int $clienteId, string $etiqueta, string $texto, ?string $barrioZona = null): void;
}
