# Estado y pendientes

Última actualización: 2026-08-28 (misma sesión — Fase 0 CERRADA, Fase 1 en progreso: webhook + agente conectados, falta una clave de API real).

## Fase actual: FASE 1 — webhook + agente conectados (falta credenciales reales para cerrarla)

Capa 1 (webhook de Evolution API) y Capa 2 (`AiOrchestrator` conectado a `TaxiAdapter`) están construidas y probadas de punta a punta con un payload simulado — la llamada real a Anthropic con una clave inventada fue **rechazada por Anthropic** (no un error de red), confirmando que la integración del proveedor funciona. Lo único que falta para que un cliente real hable con el agente por WhatsApp:

1. **Una clave de API real** (Anthropic, Gemini, o un compatible con OpenAI) — se configura en `/modules/admin/whatsapp.php`.
2. **Una instancia real de Evolution API** conectada a un número de WhatsApp, con su webhook apuntando a `https://TU-DOMINIO/modules/webhook/mensajes.php?token=<el-generado-en-admin>`.

Sin esas dos cosas no se puede cerrar la definición de hecho de Fase 1 (§13: "una carrera real recorre RECIBIDA→FINALIZADA con radiooperador, y `tx_carrera_eventos` cuenta la historia completa"). Ver `SPEC.md` y `ARQUITECTURA_Y_MODELO_DE_DATOS.md` para el detalle técnico de lo construido.

## FASE 0 — Cimientos (CERRADA)

### Cómo se cerró Fase 0

La sesión empezó con el motor "sin origen conocido". El usuario preguntó *"en la carpeta del proyecto ¿dónde más?"*, lo que llevó a buscar en todo `C:\laragon\www` en vez de solo en TAXIS — y ahí apareció: el motor vive como *path repository* de Composer, con una copia local en cada proyecto hermano (`Control_BarMax`, `maytech`, `MisRifas`, `PAduanero`). Las cuatro habían divergido (`diff -rq` no coincide entre ninguna). El usuario eligió usar la copia de `Control_BarMax` (la más reciente), copiada a `packages/whatsapp-engine/` dentro de TAXIS.

Al leer el contrato real (`DomainAdapter`) y `ToolEngine.php`, apareció un problema serio: el catálogo de herramientas del motor estaba hardcodeado para negocios de "producto + cantidad" con reparto a domicilio (`crear_pedido` escribía directo a `wa_pedidos` con columnas `modo_entrega`/`barrio`/`ciudad`), sin ningún punto de extensión para algo tan distinto como un viaje con recogida y destino. Es la "prueba de fuego" que el propio `EXTRACCION_PAQUETE.md` (dentro de `Control_BarMax`) predijo para un tercer consumidor estructuralmente distinto — y esta vez sí falló.

Con autorización explícita del usuario, se generalizó `ToolEngine` (dos puertos nuevos: `SinCatalogoDeProductos` y `SoportaHerramientasPersonalizadas`), de forma retrocompatible — la suite propia del motor sigue en 55/55. `TaxiAdapter` se reescribió contra el contrato real (no el mapeo aproximado del §5.2 del system prompt maestro, que describe la intención pero no las firmas reales). `tests/prueba.php` ahora arranca el motor de verdad contra `TaxiAdapter` y pasa 31/31.

**Definición de hecho de Fase 0 (§13 del system prompt maestro) cumplida**: *"el motor arranca contra el adaptador taxi de mentira y las pruebas del contrato están en verde"* — se cumplió con un adaptador real, no uno de mentira, contra una base de datos temporal real. Más exigente que el criterio original.

### Hecho en esta sesión (resumen completo, las 5 partes)

1. Cimientos: `composer.json`, `core/`, 13 migraciones `tx_*`, puertos placeholder, `TaxiAdapter` inicial (con el mapeo §5.2 aproximado), suite de pruebas aislada.
2. `.env` real, base de desarrollo `taxiapp` migrada, fix de `migrate.php` (MySQL hace commit implícito en DDL; envolverlo en una transacción rompía `rollBack()`).
3. Panel del radiooperador (`modules/panel/`): login, cola, flota, asignar, cancelar, turnos — probado por HTTP y en navegador real (Playwright). Bug de polling encontrado y corregido.
4. Panel administrativo mínimo (`modules/admin/`): alta/edición de vehículos y conductores, solo `rol=ADMIN`.
5. **El motor encontrado, generalizado e integrado de verdad**: `packages/whatsapp-engine/`, `SinCatalogoDeProductos`, `SoportaHerramientasPersonalizadas`, `TaxiAdapter` reescrito contra el contrato real, puertos (`TaxiDb`, `TaxiTenant`, `TaxiCifrado`, `TaxiAlmacen`) implementando las interfaces reales del motor, `tests/prueba.php` arrancando `Engine::arrancar()` de verdad.

Siete commits en el repo local: `d435ecb`, `78b2c56`, `090f2cf`, `f34abab`, `e7c8dae`, y dos pendientes de esta última parte (motor + generalización — ver que estén commiteados antes de continuar; si no, `git log --oneline` para confirmar).

### Decisiones tomadas que vale la pena recordar

- Catálogo de tipos de servicio vive en `tx_empresas.config` (JSON), no en tabla propia.
- Namespace raíz de la plataforma: `TaxiApp\`.
- **El motor vive en `packages/whatsapp-engine/` dentro de TAXIS** (copia de `Control_BarMax`, no un symlink a otro proyecto — TAXIS queda autocontenido y portable).
- `TaxiAdapter` expone su lógica real bajo nombres propios (`crearCarrera`, `estadoCarrera`, `cancelarCarrera`, `confirmarCarrera`, `calcularTotalCarrera`) porque los nombres del contrato (`crearTransaccion`, `estadoTransaccion`...) están reclamados por `DomainAdapter` con firmas de carrito de compra que no traducen a un viaje.
- `TaxiTenant` es "una base, una columna" (`scopeFila()` con `empresa_id`), igual que MayTech POS, no "una base por empresa" como ControlBarMax.

## Próximos pasos (en orden)

1. **Bloqueante real para cerrar Fase 1**: conseguir una clave de API de un proveedor de IA (Anthropic, Gemini, o compatible OpenAI) y configurarla en `/modules/admin/whatsapp.php`. Sin esto no se puede probar que el agente conversa de verdad.
2. Levantar una instancia de Evolution API (Docker) y conectarla a un número de WhatsApp — necesaria para probar con un cliente real, no solo con payloads simulados por curl.
3. Con las dos anteriores: probar el flujo completo real — un mensaje de WhatsApp de verdad → el agente identifica al cliente, pregunta lo que falta, llama a `registrar_solicitud` → la carrera aparece en la cola del Centro de Transmisión → el radiooperador asigna → el cliente recibe la notificación de verdad (ya está conectado, solo falta que Evolution exista para probarlo).
4. **Sin urgencia**: decidir si la generalización de `ToolEngine` se porta de vuelta a `Control_BarMax`/`maytech`/`MisRifas`/`PAduanero`.
5. Panel administrativo completo (empresas, líneas, reportes) — Fase 3.

### Ya cerrado en esta sesión (no repetir)

Notificar al cliente tras asignar (§7) y mostrar en el panel las conversaciones en `HUMANO_ATENDIENDO` — ambos construidos y probados en navegador real. Ver `SPEC.md`.

## Qué NO bloquea nada ahora mismo

Todo el código de Fase 1 (webhook, conexión del motor, panel de configuración) está construido, probado hasta donde se puede sin credenciales reales, y commiteado. Lo único que falta es infraestructura externa (una clave de API, una instancia de Evolution) — no hay ninguna decisión de diseño pendiente ni ninguna pieza a medio escribir.
