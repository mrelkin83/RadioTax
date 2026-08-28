# Estado y pendientes

Última actualización: 2026-08-28 (sesión de arranque — Fase 0 CERRADA).

## Fase actual: FASE 0 — Cimientos (CERRADA) → lista para arrancar FASE 1

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

1. **Decisión pendiente del usuario, sin urgencia**: ¿la generalización de `ToolEngine` se porta de vuelta a `Control_BarMax` y de ahí a `maytech`/`MisRifas`/`PAduanero`? Hoy las cinco copias (contando la de TAXIS) vuelven a divergir — esta vez de forma deliberada y documentada, no accidental.
2. Arrancar **Fase 1** de verdad: Capa 1 (webhook de Evolution API) + Capa 2 (`AiOrchestrator` con `AgentManager`, prompt de 9 capas) conectados a `TaxiAdapter`. Es lo único que falta para que un cliente real hable por WhatsApp y termine con una carrera en la cola del radiooperador.
3. Notificar al conductor/cliente por WhatsApp tras asignar (§7, hoy pendiente porque no hay canal conectado) — Fase 2.
4. Panel administrativo completo (empresas, líneas, agentes de IA, reportes) — Fase 3.

## Qué NO bloquea nada ahora mismo

A diferencia de las primeras cuatro partes de esta sesión, **ya no hay nada estructural bloqueando el resto del proyecto**. Fase 1 es trabajo real y sustancial (webhook, Evolution API, agentes), pero no depende de una decisión externa como el origen del motor — solo de tiempo y de las credenciales/infraestructura reales de Evolution API cuando llegue el momento de probar contra WhatsApp de verdad.
