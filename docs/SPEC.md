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

## Notificación al cliente y handoff visible en el panel

Dos gaps que quedaban documentados como pendientes de la parte anterior, cerrados en la misma sesión:

- **`modules/panel/api/asignar.php`** ahora notifica al cliente por WhatsApp tras asignar (§7): usa `ConectorMotor` + `EvolutionClient::enviarTexto()`. Es "mejor esfuerzo" a propósito — si el motor no está configurado o el envío falla, la asignación YA quedó hecha (no se revierte); el resultado (`enviado` / `motor_no_configurado` / `fallo: ...`) queda en `tx_carrera_eventos` como evento `NOTIFICACION_CLIENTE`, actor `SISTEMA`. **Probado en navegador real** con Evolution apuntando a una URL inexistente: la asignación se completó igual, el fallo de red quedó registrado sin romper nada.
- **El panel muestra ahora las conversaciones en `HUMANO_ATENDIENDO`/`IA_PAUSADA`** (`modules/panel/api/conversaciones.php`, `conversacion_mensajes.php`, `conversacion_responder.php`, `conversacion_liberar.php`, sección nueva en `index.php`): el radiooperador ve a quién transfirió el agente, puede responder manualmente por WhatsApp, o devolver el control a la IA (`HumanHandoff::liberar()` del motor, sin reimplementar nada). **Probado en navegador real**: dos conversaciones transferidas por fallo del LLM aparecieron en el panel, "Devolver a la IA" cambió el estado correctamente (verificado en BD), y "Enviar" mostró el error real de conexión cuando Evolution no respondía — sin romper la página.

## El ciclo completo de la carrera en el panel

Hasta este punto el panel solo llevaba una carrera hasta `ASIGNADA` — no había forma de avanzarla el resto del ciclo (§6: `ASIGNADA→EN_CAMINO→EN_SERVICIO→FINALIZADA`), lo que en la práctica hacía imposible cumplir la definición de hecho de Fase 1 ("una carrera real recorre RECIBIDA→FINALIZADA"), sin importar si el motor conversacional funcionaba o no.

- `modules/panel/api/avanzar_estado.php`: valida cada transición explícitamente (no permite saltarse pasos), actualiza el estado del vehículo en paralelo (`SOLICITADO` en camino, `EN_SERVICIO` con el cliente a bordo, `DISPONIBLE` al finalizar), registra cada paso en `tx_carrera_eventos` con `actor_tipo=RADIOOPERADOR`.
- El botón de cancelar desaparece una vez la carrera está `EN_SERVICIO` (regla del ciclo: de ahí solo se sale finalizando).
- **Probado en navegador real**: una carrera recorrió el ciclo completo `RECIBIDA→ASIGNADA→EN_CAMINO→EN_SERVICIO→FINALIZADA`, los 5 eventos quedaron en `tx_carrera_eventos` con el actor correcto, y el vehículo terminó `DISPONIBLE` otra vez.

## La conversación real — probada con IA de verdad, no simulada

El usuario proporcionó una API key real de Gemini. Configurada en `wa_config` (cifrada, nunca en un archivo del repo), se simuló una conversación completa contra `modules/webhook/mensajes.php`:

1. "Hola, necesito un taxi urgente" → el agente llama `identificar_cliente` y `consultar_tipos_servicio`, responde pidiendo la recogida.
2. "Me recoges en la Calle 10 con Carrera 5, voy para el Aeropuerto" → el agente llama `registrar_solicitud`, crea la carrera de verdad (`tx_carreras`, `actor_tipo=IA` en `tx_carrera_eventos`), y confirma al cliente con los datos correctos.
3. La carrera apareció en la cola del Centro de Transmisión; el radiooperador la asignó desde el panel real.

**Con esto, la definición de hecho de Fase 1 (§13) está cumplida**: una carrera real recorrió RECIBIDA→ASIGNADA creada por la IA, y (en una prueba separada) el ciclo completo hasta FINALIZADA funciona. Nota técnica: el modelo `gemini-2.5-flash` que se intentó primero ya no está disponible para keys nuevas — Google recomienda `gemini-3.6-flash`, que es el que quedó configurado. Un error transitorio de "alta demanda" del lado de Google también se manejó con gracia (handoff automático) sin intervención.

## Fase 2 — notificación al conductor y respuesta ACEPTO/RECHAZO (§10)

`core/Notificaciones.php`: mensajes salientes que NO pasan por el agente de IA (§10: "`EvolutionClient` directo, sin orquestador"), compartida entre el panel y el webhook.

- **Al asignar** (`modules/panel/api/asignar.php`): si el conductor del vehículo tiene WhatsApp registrado, se le pide confirmación (`tx_asignaciones.resultado='SIN_RESPUESTA'`, `medio='WHATSAPP'`) y el cliente **no** se notifica todavía. Si no tiene WhatsApp (o no tiene sentido pedirle nada porque el radiooperador ya habló con él por radio), se mantiene el comportamiento v1: `resultado='ACEPTADA'`, `medio='RADIO'`, cliente notificado de inmediato.
- **Al recibir la respuesta** (`modules/webhook/mensajes.php`): un mensaje entrante de un número que es `tx_conductores.whatsapp` activo de la empresa **nunca** llega al agente de IA — se resuelve en un camino aparte, antes del flujo de cliente. `ACEPTO` marca la asignación aceptada y dispara la notificación al cliente (pendiente hasta ese momento); `RECHAZO` libera el vehículo, **devuelve la carrera a `EN_DESPACHO` y limpia `vehiculo_id`/`conductor_id` sin ninguna intervención de código** — el radiooperador la vuelve a ver en la cola para reasignar. Idempotente a propósito (solo actúa si `resultado='SIN_RESPUESTA'`): un reintento del webhook o un "ACEPTO" repetido no hacen nada raro.
- **Bug real encontrado y corregido probando esto en vivo**: el número de WhatsApp del conductor se había guardado sin indicativo de país (`3011112222`), pero el webhook lo manda siempre con él (`573011112222`) — la comparación exacta nunca hacía match, y el mensaje del conductor caía en el flujo de cliente/IA por error. Se cambió a comparar por los últimos 10 dígitos (`RIGHT(whatsapp, 10)`) en las tres consultas que comparan un WhatsApp completo: `conductorDelTelefono()` en el webhook, `TaxiAdapter::resolverClienteId()`, y la búsqueda de cliente en `solicitud_nueva.php`. Un LID (`@lid`, sin número real) sigue comparándose exacto — no hay dígitos que recortar.
- **Probado de punta a punta con payloads simulados**: asignación en modo WhatsApp → confirmación pedida (falla con gracia contra Evolution falso, igual que las otras notificaciones) → conductor responde "Acepto" → `ACEPTADA` + cliente notificado; en una carrera aparte, conductor responde "No puedo, rechazo" → `RECHAZADA`, carrera vuelve a `EN_DESPACHO`, vehículo `DISPONIBLE`, visible de nuevo en la cola del panel (confirmado vía `cola.php`). **Esto cumple la definición de hecho de Fase 2** (§13: "un rechazo devuelve la carrera a despacho sin intervención de código").

## Fase 3 — segunda empresa de punta a punta, aislamiento real probado

Criterio de hecho de Fase 3 (§13): *"dos empresas operan aisladas con sus líneas y flotas"*. Requería un rol que hoy no existía: `ADMIN` está atado a una sola empresa, y crear una empresa *nueva* es una acción del dueño de la plataforma, no de un tenant.

- Migración `0020`: `tx_usuarios.empresa_id` pasa a nulable, `rol` gana `SUPERADMIN` (sin empresa — es quien administra la plataforma, no un negocio).
- `core/Auth.php`: `requerirSuperadmin()`, `requerirSesionDeEmpresa()` / `requerirSesionApiDeEmpresa()` (rechazan con 403 a un SUPERADMIN que intente entrar al panel de una empresa, ya que no tiene una).
- `modules/plataforma/empresas.php` (solo SUPERADMIN): lista empresas con sus contadores reales (líneas, usuarios, vehículos), formulario para crear una empresa nueva, y por cada una, alta de línea y del primer usuario `ADMIN` (credenciales generadas, mostradas una sola vez).
- `database/seed_superadmin.php`: crea el primer SUPERADMIN (no hay forma de crear el primero desde el panel — es el mismo problema del huevo y la gallina que ya resolvía `seed_dev.php` para el primer `ADMIN`).
- Login (`modules/panel/login.php`) enruta según el rol: un SUPERADMIN cae en `/modules/plataforma/empresas.php`, el resto en el Centro de Transmisión.
- **Probado en navegador real, de punta a punta**: login como SUPERADMIN → crear "Taxis Del Valle" (Cali) → agregarle una línea y un admin (`adminvalle1`) → logout → login como ese admin → **panel completamente vacío** (cola, flota y conversaciones en cero, sin ningún dato de Radio Tax) → confirmado también en `/modules/admin/vehiculos.php` con sus propios contadores. El aislamiento por `empresa_id` que se diseñó desde el primer commit de Fase 0 se sostuvo con un segundo tenant real, no solo en teoría.
- **Bug real encontrado con esta prueba, y corregido**: varias páginas tenían **"Radio Tax" hardcodeado** en `<title>`/`<h1>` (`modules/panel/index.php`, `modules/panel/login.php`, `modules/admin/_nav.php` y los cuatro `<title>` de `modules/admin/*.php`), y `TaxiTenant::nombre()` caía a `'Radio Tax'` como fallback — una violación directa de la regla §7 ("nombre... nunca constantes en código") que solo se hizo visible al operar de verdad como el segundo tenant y ver el nombre de otra empresa en su propio panel. Corregido: `Auth::intentarLogin()` ahora trae `empresa_nombre` con un `JOIN` a `tx_empresas` y lo deja en sesión (`Auth::usuarioActual()`); las páginas lo usan en vez del texto fijo. El fallback de `TaxiTenant::nombre()` pasó a `'esta empresa'`, genérico.

## Estadísticas y reportes (§12) — `modules/admin/reportes.php`

Confirmando la premisa del propio system prompt maestro ("si la trazabilidad está bien, los reportes son consultas, no infraestructura nueva"): todo el dashboard es SQL de agregación sobre `tx_carreras` + `tx_carrera_eventos` + `tx_asignaciones`, sin tablas nuevas.

- Filtro por rango de fechas (`desde`/`hasta`, GET, default últimos 30 días).
- Tarjetas: solicitudes totales, finalizadas, canceladas, no atendidas, en curso, asignaciones rechazadas.
- **% de automatización**: carreras creadas por la IA vs. por el radiooperador, a partir de `tx_carrera_eventos.actor_tipo` en el evento `CARRERA_RECIBIDA` — la métrica que el §12 marca como la que justifica el proyecto.
- Tiempo promedio solicitud→asignación (`TIMESTAMPDIFF` entre `creado_en` y `asignada_en`).
- Servicios por tipo, direcciones de recogida más frecuentes (zonas de demanda), vehículos y conductores con más servicios completados.
- **Probado en navegador real** con los datos acumulados de toda la sesión (incluida la carrera creada por la IA real): 5 solicitudes, 20% automatización (1 IA / 4 radiooperador) — coincide exactamente con lo esperado. Probado también el estado vacío (rango sin datos): sin división por cero, mensajes claros en vez de tablas rotas.

### No hecho / fuera de alcance de esta sesión

- **Una instancia real de Evolution API** conectada a un número de WhatsApp de verdad — todo lo anterior se probó con payloads simulados por curl; falta el canal real para hablar con clientes de verdad. El envío de respuestas ya falla con gracia contra una URL falsa (probado); con Evolution real, funciona sin tocar código.
- Voz e imagen (STT/TTS/Visión) — necesitan credenciales de proveedor aparte.
- Panel administrativo completo (empresas, líneas, agentes de IA) — reportes ya están, falta el resto de Fase 3.
- Decidir si la generalización de `ToolEngine` se porta de vuelta a `Control_BarMax`/`maytech`/`MisRifas`/`PAduanero` — pendiente, es una decisión aparte de TAXIS.
