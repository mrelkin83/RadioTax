<?php
/**
 * Notificación de nuevas solicitudes: sonido + modal animado + barra
 * persistente, visible sin importar en qué pantalla esté el operador o el
 * administrador (§ toda la lógica vive en alertas-solicitud.js).
 *
 * El panel del operador (modules/panel/) ya refresca la cola cada 4s por su
 * cuenta y le pasa los datos a SolicitudAlertas.procesar() — no necesita
 * auto-poll propio. Las páginas de modules/admin/ no tienen ese refresco,
 * así que activan su propio poll pasando $alertasEndpoint antes de incluir
 * este partial.
 */
$alertasEndpointAttr = isset($alertasEndpoint) && $alertasEndpoint !== ''
    ? ' data-autopoll-endpoint="' . htmlspecialchars($alertasEndpoint, ENT_QUOTES, 'UTF-8') . '"'
    : '';
?>
<script src="/modules/panel/assets/alertas-solicitud.js"<?= $alertasEndpointAttr ?>></script>
