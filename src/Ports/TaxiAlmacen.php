<?php

declare(strict_types=1);

namespace TaxiApp\Ports;

use ElkinLinan\WhatsappAiEngine\Ports\StoragePort;
use RuntimeException;

/**
 * Dónde se guardan las notas de voz y fotos que llegan por WhatsApp.
 * Almacén local en storage/media — sin cupo por empresa todavía (v1: cabe()
 * solo pone un techo razonable por archivo, no un total acumulado).
 */
final class TaxiAlmacen implements StoragePort
{
    private const CUPO_POR_ARCHIVO = 15_000_000;

    public function __construct(private readonly string $raiz = __DIR__ . '/../../storage/media')
    {
    }

    public function raiz(): string
    {
        return $this->raiz;
    }

    public function directorio(): string
    {
        if (!is_dir($this->raiz) && !mkdir($this->raiz, 0755, true) && !is_dir($this->raiz)) {
            throw new RuntimeException("No se pudo crear el directorio de almacenamiento: {$this->raiz}");
        }

        return $this->raiz;
    }

    public function url(string $rutaRelativa): string
    {
        return '/storage/media/' . ltrim($rutaRelativa, '/');
    }

    public function comprimirImagen(string $binario, int $maxLado = 1024, int $calidad = 78): ?array
    {
        // v1: sin recompresión (no hay GD/Imagick garantizado en el entorno de
        // desarrollo). El motor trata null como "no mejora o no aplica" y
        // guarda el binario tal cual.
        return null;
    }

    public function cabe(int $bytes): bool
    {
        return $bytes < self::CUPO_POR_ARCHIVO;
    }
}
