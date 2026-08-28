<?php
/**
 * Marca que este negocio NO vende productos con cantidad y no necesita el
 * paquete de herramientas de carrito de compra (consultar_menu, consultar_stock,
 * calcular_total, crear_pedido, consultar_pedido, cancelar_pedido,
 * consultar_estado_cocina, generar_pago, consultar_pago).
 *
 * Nace de un negocio que no es "producto + cantidad" en absoluto —un despacho
 * de servicios de transporte, por ejemplo, donde la unidad no es un producto
 * sino un viaje con recogida y destino—. Sin esta marca, esas nueve
 * herramientas quedaban SIEMPRE visibles (no tenían 'capacidad' que las
 * filtrara) y, si el modelo las llamaba, `crear_pedido` intentaba escribir en
 * `wa_pedidos` con columnas de reparto a domicilio que ese negocio ni siquiera
 * tiene en su esquema.
 *
 * Es solo una marca (sin métodos): implementarla en el DomainAdapter basta.
 * Los adaptadores existentes que no la implementan no cambian de comportamiento
 * — así el paquete sigue siendo compatible con quien ya lo usa.
 *
 * El negocio que la implemente casi siempre también implementará
 * SoportaHerramientasPersonalizadas para ofrecer las suyas.
 */

namespace ElkinLinan\WhatsappAiEngine\Ports;

interface SinCatalogoDeProductos
{
}
