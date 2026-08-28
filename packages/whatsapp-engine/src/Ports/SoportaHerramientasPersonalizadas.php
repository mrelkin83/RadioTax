<?php
/**
 * Herramientas propias de un negocio que el motor no conoce ni podría conocer:
 * el vocabulario de "producto + cantidad" del catálogo fijo no le sirve a
 * cualquier dominio (un despacho de servicios, una agenda de citas...).
 *
 * El motor no sabe nada de lo que hay dentro: solo mezcla el catálogo que
 * declares aquí con el suyo (mismo formato que ToolEngine::catalogo() —
 * 'description', 'parameters', y opcionalmente 'capacidad' para que se
 * filtre igual que las herramientas internas por capacidades()), y cuando el
 * modelo llama a una que no reconoce en su propio switch, te la pasa a ti.
 */

namespace ElkinLinan\WhatsappAiEngine\Ports;

interface SoportaHerramientasPersonalizadas
{
    /** @return array<string,array{description:string,parameters:array,capacidad?:string}> */
    public function herramientasPersonalizadas(): array;

    /**
     * Ejecuta una herramienta propia. Devuelve null si no la reconoces —el
     * motor entonces responde "herramienta no implementada" en vez de asumir
     * que la ejecutaste en silencio.
     */
    public function ejecutarHerramientaPersonalizada(string $nombre, array $args, array $ctx): ?array;
}
