<?php

declare(strict_types=1);

namespace TaxiApp\Ports;

use RuntimeException;

/**
 * Pendiente: conformar al StoragePort real del motor. Hoy es un almacén
 * de archivos local en storage/media (audios, imágenes recibidos por WhatsApp).
 */
final class TaxiAlmacen
{
    public function __construct(private readonly string $directorioBase = __DIR__ . '/../../storage/media')
    {
        if (!is_dir($this->directorioBase) && !mkdir($this->directorioBase, 0755, true) && !is_dir($this->directorioBase)) {
            throw new RuntimeException("No se pudo crear el directorio de almacenamiento: {$this->directorioBase}");
        }
    }

    public function guardar(string $nombre, string $contenido): string
    {
        $ruta = $this->directorioBase . '/' . ltrim($nombre, '/');
        $carpeta = dirname($ruta);
        if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true) && !is_dir($carpeta)) {
            throw new RuntimeException("No se pudo crear el directorio: {$carpeta}");
        }

        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    public function leer(string $nombre): string
    {
        $ruta = $this->directorioBase . '/' . ltrim($nombre, '/');
        if (!is_file($ruta)) {
            throw new RuntimeException("Archivo no encontrado: {$nombre}");
        }

        return (string) file_get_contents($ruta);
    }
}
