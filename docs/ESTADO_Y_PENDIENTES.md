# Estado y pendientes

Última actualización: 2026-08-28 (misma sesión — Fase 0 CERRADA, Fase 1 **probada con una conversación real**).

## Fase actual: FASE 1 — probada de punta a punta con IA real (falta Evolution API real para producción)

**El usuario proveyó una API key real de Gemini** (guardada cifrada en `wa_config.llm_api_key`, empresa 1 — nunca en un archivo del repo). Con `llm_proveedor=gemini`, `llm_modelo=gemini-3.6-flash` (el modelo recomendado por Google; `gemini-2.5-flash` ya no está disponible para keys nuevas), se simuló una conversación completa por webhook:

> Cliente: "Hola, necesito un taxi urgente"
> Agente: llama `identificar_cliente` + `consultar_tipos_servicio` → "¡Hola, Maria! Claro que sí 🚕 ¿Desde dónde te recogemos?"
> Cliente: "Me recoges en la Calle 10 con Carrera 5, voy para el Aeropuerto"
> Agente: llama `registrar_solicitud` → crea la carrera de verdad → "¡Listo, Maria! Ya registré tu solicitud de taxi 🚕 📍 Recogida: Calle 10 con Carrera 5 🎯 Destino: Aeropuerto..."

La carrera apareció en la cola del Centro de Transmisión con `actor_tipo=IA` en `tx_carrera_eventos`. El radiooperador la asignó desde el panel real (navegador), completando **RECIBIDA→ASIGNADA** con una carrera creada por la IA de verdad — y por separado se probó el ciclo completo **RECIBIDA→FINALIZADA** (`ASIGNADA→EN_CAMINO→EN_SERVICIO→FINALIZADA`, botones nuevos en el panel) con los 5 eventos y actor correcto. **La definición de hecho de Fase 1 (§13) está cumplida.**

Lo único que falta para producción real: **una instancia de Evolution API** conectada a un número de WhatsApp de verdad (hoy `evolution_url` apunta a una dirección falsa para pruebas) — sin eso, la IA conversa y crea carreras perfectamente, pero no puede *responderle al cliente por WhatsApp de verdad* (los envíos fallan con "connection refused", manejado con gracia, sin romper nada).

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

1. **Único bloqueante real que queda**: levantar una instancia de Evolution API (Docker) y conectarla a un número de WhatsApp de verdad, apuntando su webhook a `https://TU-DOMINIO/modules/webhook/mensajes.php?token=<el-de-wa_config>`. Con eso, el flujo completo (probado hoy con payloads simulados) funciona con clientes reales sin tocar código.
2. Notificar al conductor por WhatsApp al asignar (§10) — hoy solo se notifica al cliente.
3. Panel administrativo completo (empresas, líneas, agentes de IA) — reportes ya están (ver abajo), falta el resto de Fase 3.
4. **Sin urgencia**: decidir si la generalización de `ToolEngine` se porta de vuelta a `Control_BarMax`/`maytech`/`MisRifas`/`PAduanero`.
5. Voz e imagen (STT/TTS/Visión) — credenciales de proveedor aparte, no urgente para el MVP.

### Ya cerrado en esta sesión (no repetir)

- Notificar al cliente tras asignar (§7) y mostrar en el panel las conversaciones en `HUMANO_ATENDIENDO` (§8).
- El ciclo completo de la carrera en el panel: `ASIGNADA→EN_CAMINO→EN_SERVICIO→FINALIZADA` (antes el panel no tenía forma de ir más allá de `ASIGNADA`).
- **La conversación real con IA**: probada con una clave de Gemini real proporcionada por el usuario — identificación de cliente, consulta de tipos de servicio, y `registrar_solicitud` funcionando de punta a punta. Ver el relato completo arriba.

- **Estadísticas y reportes (§12)**: `modules/admin/reportes.php` — % de automatización, tiempos, servicios por tipo/vehículo/conductor/zona, todo consultas sobre la trazabilidad existente, sin tablas nuevas.

Todo esto probado en navegador real y/o con payloads de webhook reales, no solo escrito. Ver `SPEC.md` para el detalle técnico.

## Qué NO bloquea nada ahora mismo

Todo el código de Fase 1 (webhook, conexión del motor, panel de configuración) está construido, probado hasta donde se puede sin credenciales reales, y commiteado. Lo único que falta es infraestructura externa (una clave de API, una instancia de Evolution) — no hay ninguna decisión de diseño pendiente ni ninguna pieza a medio escribir.
