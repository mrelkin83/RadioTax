<?php

namespace ElkinLinan\WhatsappAiEngine\Ports;

/**
 * Generar el cobro de la SUSCRIPCIÓN de quien escribe — no un pedido de un
 * cliente del negocio, sino la relación comercial completa (igual criterio
 * que SoportaConsultaCuenta, con el que casi siempre va de la mano).
 *
 * Nace con ControlBarMax Soporte: el dueño de un negocio quiere pagar su
 * plan desde el mismo chat donde ya preguntó "¿cuándo vence?".
 */
interface SoportaPagoSuscripcion
{
    /**
     * @param array $conversacion fila de wa_conversaciones
     * @return array{ok:bool,enlace?:string,monto?:float,plan?:string,error?:string}
     *         Reutiliza un enlace vivo si ya hay uno pendiente — no genera
     *         uno nuevo por cada "¿cómo pago?".
     */
    public function generarCobroSuscripcion(array $conversacion): array;
}
