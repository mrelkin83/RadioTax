# SPEC — lo construido

## Fase 0 — Cimientos (CERRADA, 28 ago 2026)

### Hecho

- Estructura de proyecto: `composer.json` (PSR-4, PHP `^8.2`), con `elkinlinan/whatsapp-ai-engine` declarado como *path repository* apuntando a `packages/whatsapp-engine/`.
- `core/Env.php`: loader mínimo de `.env` (sin dependencias) — parsea `KEY=VALUE`, ignora comentarios `#`, no pisa variables ya presentes en el entorno real. Se invoca perezosamente desde `Database::conectar()` y `TaxiCifrado`.
- `core/Database.php`: conexión PDO singleton vía variables de entorno, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, prepares emuladas desactivadas.
- `core/BaseModel.php`: helpers CRUD genéricos sobre `static::$tabla`.
- `core/Auth.php`: sesión reforzada, login, CSRF (usado por el panel — ver Capa 4 abajo).
- 14 migraciones idempotentes para el esquema `tx_*` completo de la v1 (13 de dominio + `tx_usuarios`).
- `database/migrate.php`: runner con tabla de control `tx_migraciones`.
- Puertos (`src/Ports/`), todos implementando el contrato **real** del motor (no una aproximación): `TaxiDb implements DbPort`, `TaxiCifrado implements SecretPort` (libsodium secretbox), `TaxiAlmacen implements StoragePort` (filesystem local), `TaxiTenant implements TenantPort` (multi-empresa "una base, una columna", como MayTech). `PesosColombianos` no se escribió — el motor trae su propio default (`Defecto\PesosColombianos`) y sirve tal cual.
- Capacidades propias (`src/Capacidades/`): `SoportaDireccionesFrecuentes`, `SoportaDespachoOperativo`, del §5.3 del system prompt maestro.
- `src/Domain/TaxiAdapter.php`: implementa `DomainAdapter` (el contrato real del motor, no el mapeo aproximado del §5.2 — ver `ARQUITECTURA_Y_MODELO_DE_DATOS.md`), más `SinCatalogoDeProductos` y `SoportaHerramientasPersonalizadas` (los dos puertos nuevos que generalizan el motor — ver abajo). `calcularTotalCarrera()` nunca devuelve monto. `capacidades()` nunca declara pagos.
- **El motor mismo (`packages/whatsapp-engine/`)**: copiado de `Control_BarMax`, generalizado con autorización explícita del usuario para que `ToolEngine` deje de tener el catálogo de "producto + cantidad" hardcodeado sin punto de extensión. Cambio backward-compatible: la suite propia del paquete sigue en **55/55**.
- `tests/prueba.php`: ya no es una prueba aislada del adaptador — **arranca el motor de verdad** (`Engine::arrancar()`) contra `TaxiAdapter` y una base de datos MySQL temporal, construye un `ToolEngine` real y ejecuta sus herramientas (`identificar_cliente`, `consultar_tipos_servicio`, `consultar_direcciones_frecuentes`, `registrar_solicitud`, `consultar_estado_carrera`, `cancelar_carrera`), confirma que las nueve herramientas de "producto + cantidad" no se ofrecen ni se pueden ejecutar, y que `transferir_a_humano` sigue disponible. **31/31 en verde.**
- Base de datos de desarrollo real (`taxiapp`, local Laragon): migrada, con empresa/línea/vehículo/conductor de prueba, y el flujo completo probado tanto por HTTP (curl) como en un navegador real (Playwright).

### Por qué se considera cerrada

La definición de hecho del system prompt maestro (§13) para Fase 0 es: *"el motor arranca contra el adaptador taxi de mentira y las pruebas del contrato están en verde"*. Se cumplió con un adaptador **real** (no uno de mentira) contra una base temporal real — más exigente que el criterio original, no menos. Lo único que falta para una integración de punta a punta es Capa 1 (el webhook real de Evolution API), que es explícitamente Fase 1, no Fase 0.

## Capa 4 — Centro de transmisión (panel del radiooperador)

No depende del motor, así que se construyó en paralelo mientras se resolvía el bloqueo original.

### Hecho

- `tx_usuarios` + `core/Auth.php`: login por sesión, CSRF, guardas de página y de API.
- `database/seed_dev.php`: crea la empresa "Radio Tax", su primera línea y el primer usuario (sin auto-registro).
- `modules/panel/`: `login.php`, `logout.php`, `index.php` (dashboard), `assets/panel.js` (vanilla JS, polling 4 s).
- `modules/panel/api/`: `cola.php` y `flota.php` (GET), `conductores.php` (GET), `asignar.php`, `cancelar.php`, `turno_abrir.php`, `turno_cerrar.php`, `vehiculo_estado.php`, `solicitud_nueva.php` (POST, con CSRF).
- **Probado de punta a punta por HTTP real** (curl con cookie jar) y **en navegador real** (Playwright): login, cola, cancelar con motivo, solicitud manual, asignar, abrir/cerrar turno, logout — todo verificado, sin errores de consola.
- Bug real encontrado probando en navegador y corregido: el polling automático le borraba al operador un input a medio escribir. Ver `usuarioEstaEditando()` en `assets/panel.js`.
- Los métodos de `TaxiAdapter` que usa el panel se llaman `crearCarrera()`, `estadoCarrera()`, `cancelarCarrera()` (no `crearTransaccion()`/`estadoTransaccion()`/`cancelarTransaccion()` — esos nombres los reclamó `DomainAdapter` con firmas distintas cuando se integró el motor de verdad).

## Panel administrativo (mínimo) — `modules/admin/`

Lo estrictamente necesario para que el Centro de Transmisión sea usable sin tocar SQL a mano: alta y edición de vehículos y conductores. Solo accesible con `rol=ADMIN` (`403` para `RADIOOPERADOR`, probado en navegador).

- `modules/admin/vehiculos.php`, `modules/admin/conductores.php`: formularios HTML clásicos (Post-Redirect-Get), CSRF, salida escapada.
- Explícitamente fuera de este alcance: empresas, líneas, agentes de IA, configuración de despacho, reportes — son parte del "panel administrativo aparte" de §8.

## Fase 1 — Capa 1 (webhook) + Capa 2 (agente conectado)

- Migraciones `0015`-`0019`: esquema `wa_*` (del motor, no de la plataforma) adaptado a multi-empresa por columna — ver `ARQUITECTURA_Y_MODELO_DE_DATOS.md` para el porqué de cada decisión.
- `core/ConectorMotor.php`: arranca `Engine::arrancar()` para una empresa concreta (equivalente a `waConectarMotor()` de MayTech).
- `modules/webhook/mensajes.php`: el borde del motor. Resuelve la empresa por el token de la URL (consultando `wa_config` directo, no `WaConfig::resolverPorToken()` del motor — esa función asume "una base por negocio"), deduplica, responde 200 rápido, procesa. Solo texto en v1.
- `modules/admin/whatsapp.php`: configura proveedor LLM, Evolution API y genera el token del webhook, por empresa. Usa `WaConfig::guardar()`/`regenerarWebhookToken()` del motor sin reimplementar nada.
- **Probado con un payload de Evolution simulado y una clave de Anthropic inventada**: la llamada real a la API de Anthropic la rechazó (confirma que la integración del proveedor está bien conectada, no que sea un fallo de red). El error se manejó con gracia: mensaje al cliente, `HumanHandoff`, todo registrado en `wa_eventos`. Confirmado también: token inválido → 404, motor apagado → 200 ignorado, mensaje repetido → deduplicado sin nuevas filas.

### No hecho / fuera de alcance de esta sesión

- **La conversación real**: falta una clave de API válida (Anthropic/Gemini/OpenAI) para probar que el agente entiende al cliente y llama a `registrar_solicitud`. Sin eso no se puede cerrar la definición de hecho de Fase 1 (§13: "una carrera real recorre RECIBIDA→FINALIZADA con radiooperador").
- Una instancia real de Evolution API conectada a un número de WhatsApp — necesaria para probar con un cliente de verdad, no solo con payloads simulados.
- Notificar al cliente por WhatsApp tras asignar (§7) — la pieza que falta en `modules/panel/api/asignar.php`, ahora que el canal ya existe (antes ni eso había).
- El panel del radiooperador no muestra todavía las conversaciones en `HUMANO_ATENDIENDO` (handoff) — están en `wa_conversaciones`, pero el panel solo lee `tx_carreras`. Gap real de §8, no cerrado esta sesión.
- Voz e imagen (STT/TTS/Visión) — necesitan credenciales de proveedor aparte.
- Panel administrativo completo (empresas, líneas, reportes) — Fase 3.
- Decidir si la generalización de `ToolEngine` se porta de vuelta a `Control_BarMax`/`maytech`/`MisRifas`/`PAduanero` — pendiente, es una decisión aparte de TAXIS.
