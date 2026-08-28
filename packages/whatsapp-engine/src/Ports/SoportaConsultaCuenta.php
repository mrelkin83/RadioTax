<?php

namespace ElkinLinan\WhatsappAiEngine\Ports;

/**
 * Consultar el estado de LA CUENTA de quien escribe — no un cliente del
 * negocio, sino el negocio mismo cuando el consumidor del motor es la propia
 * plataforma (soporte hacia sus tenants, no hacia comensales).
 *
 * Nace con ControlBarMax Soporte: un dueño de bar escribe "¿cuándo vence mi
 * plan?" y el motor no tiene forma neutra de responder eso — no es un
 * producto, no es una garantía, es la relación comercial completa.
 */
interface SoportaConsultaCuenta
{
    /**
     * @param array $conversacion fila de wa_conversaciones
     * @return array Datos legibles para el modelo: plan, estado, fecha de
     *         vencimiento, lo que el negocio decida exponer. Sin estructura
     *         fija a propósito — cada proyecto sabe qué es "su cuenta".
     */
    public function consultarCuenta(array $conversacion): array;
}
