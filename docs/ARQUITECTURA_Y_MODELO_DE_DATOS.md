# Arquitectura y modelo de datos — estado de implementación

Ver el diseño completo en `SYSTEM_PROMPT_MAESTRO_TAXIAPP.md` (secciones 3, 4 y 6). Este documento registra las decisiones concretas tomadas al implementar, no repite el diseño.

## Namespaces y autoload

- `TaxiApp\Core\` → `core/` (Database, BaseModel — patrón ControlBarMax/MayTech).
- `TaxiApp\Ports\` → `src/Ports/` (TaxiDb, TaxiCifrado, TaxiAlmacen, TaxiTenant, PesosColombianos).
- `TaxiApp\Capacidades\` → `src/Capacidades/` (interfaces `Soporta*`).
- `TaxiApp\Domain\` → `src/Domain/` (`TaxiAdapter`).

## Migraciones

- Numeradas `NNNN_create_tabla.sql`, idempotentes (`CREATE TABLE IF NOT EXISTS`), en `database/migrations/`.
- Runner `database/migrate.php`: crea `tx_migraciones` si no existe, aplica solo lo pendiente dentro de una transacción por archivo.
- Orden actual: 0001 tx_empresas → 0002 tx_lineas → 0003 tx_clientes → 0004 tx_direcciones → 0005 tx_vehiculos → 0006 tx_conductores → 0007 tx_vehiculo_conductor → 0008 tx_turnos → 0009 tx_carreras → 0010 tx_carrera_eventos → 0011 tx_asignaciones → 0012 tx_ubicaciones → 0013 tx_config_despacho.

## Decisión: catálogo de tipos de servicio

El §6 del system prompt maestro no define una tabla para "tipos de servicio" (referenciada en §5.2 como `buscarItems()`/`detalleItem()`), y dice explícitamente "catálogo pequeño y extensible por empresa". Se implementó leyendo `tx_empresas.config` (JSON) → clave `tipos_servicio`, con fallback a `[TAXI, CARGA]` si la empresa no lo configuró. No se creó una tabla `tx_tipos_servicio` para no inventar esquema fuera de lo documentado — **a revisar si en Fase 1 el catálogo necesita crecer más allá de JSON**.

## Idempotencia de `crearTransaccion()`

Hash SHA-256 de `conversacion_ref|cliente_id|tipo_servicio|recogida_texto|destino_texto`, columna única `idempotencia_hash` en `tx_carreras`. Si la carrera ya existe, `crearTransaccion()` la devuelve sin duplicar.

## Decisión: tabla `tx_usuarios` (login del panel)

El §6 tampoco define una tabla de usuarios/login, pero sin ella no hay forma de saber "QUIÉN" actuó en el panel (regla de trazabilidad §6 del system prompt maestro: cada evento debe registrar el actor concreto). Se agregó `tx_usuarios` (migración `0014`) con `usuario` **único globalmente** (no por empresa) para simplificar el login sin exigir selección de empresa en el formulario — el `empresa_id` del usuario define su tenant. Roles: `RADIOOPERADOR`, `ADMIN`. Sin auto-registro: el primer usuario de cada empresa se crea con `database/seed_dev.php`.

## Panel del radiooperador — convención de carpetas

Se revisaron los proyectos hermanos del mismo ecosistema (`Control_BarMax`, `maytech`, ambos en `C:\laragon\www\`) para no inventar una convención distinta: **ninguno usa carpeta `public/`** — el webroot es la raíz del proyecto, con `modules/<nombre>/` por feature. TAXIS sigue el mismo patrón: `modules/panel/` (páginas + `api/` con endpoints JSON + `assets/panel.js`).

- `core/Auth.php`: sesión PHP nativa reforzada (`httponly`, `samesite=Strict`, `use_strict_mode`, `secure` si HTTPS, regeneración de ID en login), login por `usuario`/`clave` (`password_hash`/`password_verify`), guardas `requerirSesion()` (páginas, redirige a login) y `requerirSesionApi()` (API, responde 401 JSON), y CSRF de sesión (`verificarCsrf()` / `tokenCsrf()`).
- Tailwind se carga vía CDN (`cdn.tailwindcss.com`) en vez de un pipeline de build — v1 sin Node configurado en el proyecto. Se puede reemplazar por un build real (como el `tailwind.config.js` que sí usa `maytech`) sin tocar el marcado, cuando haga falta purgar/optimizar CSS.
- Tiempo real: **polling cada 4 s** desde `assets/panel.js` (vanilla JS, sin dependencias), tal como exige §3 ("polling corto en v1; SSE como mejora, nunca requisito").
- Todo el DOM que incluye datos del cliente (`recogida_texto`, `destino_texto`, `observaciones`, nombres) se construye con `textContent`, nunca `innerHTML`, por la regla §14.4 sobre XSS en la cola del radiooperador.

## El motor: dónde vive y qué se le cambió

`packages/whatsapp-engine/` es una copia local del paquete `elkinlinan/whatsapp-ai-engine`, tomada de `C:\laragon\www\Control_BarMax\packages\whatsapp-engine` el 28 ago 2026 (la más reciente de cuatro copias divergentes — ver `AUDITORIA.md`). Se declara en `composer.json` como *path repository* (`"type": "path", "url": "packages/whatsapp-engine"`), igual que en los proyectos hermanos.

**Se modificó `src/Core/ToolEngine.php`** (con autorización explícita del usuario, no en automático — regla §4 del system prompt maestro: "el motor... NO se modifica" salvo que la interfaz esté mal, y aquí lo estaba). El motivo: el catálogo de herramientas mezclaba dos cosas que debían estar separadas — un núcleo neutral (autorización, capacidades, transferir a humano) y un **catálogo fijo de "producto + cantidad"** (consultar_menu, crear_pedido...) sin ningún punto de extensión, con `crear_pedido` escribiendo directo por SQL a `wa_pedidos` con columnas de reparto a domicilio. Un negocio de viajes no es eso.

Dos piezas nuevas, ambas opcionales y retrocompatibles (la suite propia del motor sigue en 55/55 sin cambios):

- **`Ports\SinCatalogoDeProductos`** (interfaz marcador, sin métodos): un `DomainAdapter` que la implemente apaga las nueve herramientas de "producto + cantidad" (antes no tenían `'capacidad'` que las filtrara — estaban siempre encendidas). Los adaptadores existentes (`ControlBarMaxAdapter`, `MayTechAdapter`, ...) no la implementan, así que no cambian de comportamiento.
- **`Ports\SoportaHerramientasPersonalizadas`**: un `DomainAdapter` que la implemente aporta su propio catálogo de herramientas (mismo formato que las internas) y su propio despachador. `ToolEngine::catalogo()` las mezcla con las suyas; `ToolEngine::despachar()` les cede el turno cuando el nombre no coincide con ningún `case` del `switch` fijo.

`TaxiAdapter` implementa las dos. Su catálogo propio (`herramientasPersonalizadas()`) es el mapeo real del §5.4 del system prompt maestro: `identificar_cliente`, `consultar_tipos_servicio`, `consultar_direcciones_frecuentes`, `registrar_solicitud`, `consultar_estado_carrera`, `cancelar_carrera`. `transferir_a_humano` ya viene del motor sin tocar nada (es `'siempre' => true`, no se puede deshabilitar).

**Pendiente de decidir con el usuario, aparte**: si esta generalización se debe portar de vuelta a `Control_BarMax` (y de ahí a `maytech`/`MisRifas`/`PAduanero`) para que las cinco copias converjan, o si se deja como una divergencia más — deliberada esta vez, documentada, y backward-compatible, a diferencia de las anteriores.

## DomainAdapter: el contrato real (no el de §5.2 del system prompt maestro)

La tabla del §5.2 del system prompt maestro describe la intención, pero las firmas reales del paquete (`buscarItems(?string, array, int)`, `crearTransaccion(array $conversacion, array $items, array $datos)`, `calcularTotal(array $items, float $extra)`, ids como `string` no `int`...) están pensadas para un carrito de productos, no para un viaje. `TaxiAdapter` sí implementa `DomainAdapter` formalmente, pero:

- Solo **`contextoCliente()`** y **`capacidades()`** son alcanzables en la práctica: el motor los llama siempre (vía `AiOrchestrator`).
- El resto (`buscarItems`, `detalleItem`, `disponibilidad`, `crearTransaccion`, `calcularTotal`, `transaccionesDe`) solo se alcanzaría desde las nueve herramientas que `SinCatalogoDeProductos` apaga — así que son delegados a métodos reales con otro nombre cuando la firma solo difiere en el tipo (ej. `estadoTransaccion(string $id)` → `estadoCarrera((int) $id)`), o un `throw` explícito cuando no hay traducción honesta (`crearTransaccion`/`calcularTotal`: un carrito de productos no es un viaje con recogida y destino).
- La lógica real de negocio vive en métodos con nombre propio — `crearCarrera()`, `estadoCarrera()`, `cancelarCarrera()`, `confirmarCarrera()`, `calcularTotalCarrera()` — llamados desde `modules/panel/api/*.php` directamente y desde las herramientas personalizadas (`ejecutarHerramientaPersonalizada()`).

## Multi-empresa: `TenantPort` (no solo `empresa_id` por parámetro)

`TaxiTenant implements TenantPort` se instancia **por request**, ya resuelta la empresa (hoy a mano; cuando exista el webhook de Capa 1, `TaxiTenant::resolverPorTokenWebhook()` lo hace). `scopeFila()` devuelve `['columna' => 'empresa_id', 'valor' => N]` — TAXIS es "una base, una columna", el mismo patrón que MayTech POS, no el de ControlBarMax (una base por negocio). Los métodos de `TaxiAdapter` que necesitan la empresa activa la piden con `Engine::negocio()->id()`, no por parámetro.

## Capa 1 + Capa 2: el webhook real (Fase 1)

### Las tablas `wa_*` no traen migración propia

El motor no incluye sus propias migraciones — cada proyecto consumidor escribe la suya (confirmado: ni `Control_BarMax` ni `maytech` tienen SQL dentro de `packages/whatsapp-engine/`, solo clases PHP que asumen el esquema existe). Las migraciones `0015`-`0019` (`wa_config`, `wa_agentes`, `wa_conversaciones`, `wa_mensajes`, `wa_eventos`) se adaptaron de la versión mono-tenant de `Control_BarMax`, agregándoles `empresa_id` donde el motor lo necesita — confirmado grep por grep contra `Scope::y()`/`Scope::paraInsert()` en todo `packages/whatsapp-engine/src/`, no supuesto: `wa_config`, `wa_agentes`, `wa_conversaciones` y `wa_eventos` lo usan; `wa_mensajes` no (siempre se filtra por `conversacion_id`, ya acotada).

**Nombres de columna**: el motor consulta estas tablas con SQL propio hardcodeado (`created_at`, no `creado_en`) — a diferencia de las tablas `tx_*` (propias de la plataforma, con su propia convención), las `wa_*` tienen que respetar exactamente los nombres que el motor espera. Primer intento de esta sesión los escribió con la convención `tx_*` por error; se corrigió tras `grep` contra el código real del motor.

No se migraron `wa_stock`, `wa_menu_dia`, `wa_pedidos`, `wa_pagos`, `wa_modelos` — son específicas de negocios de producto+cantidad (`wa_pedidos`/`wa_pagos`) o mejoras no esenciales para el MVP (`wa_modelos`, descubrimiento de modelos nuevos). TAXI no las necesita: sus herramientas van por `SoportaHerramientasPersonalizadas`, no por el `crear_pedido` que las usa.

### Multi-empresa en el webhook: el patrón de MayTech, no el del motor

`WaConfig::resolverPorToken()` (dentro del motor) asume "una base por negocio" — usa una tabla maestra `wa_instancias` y cambia de base de datos física. **No sirve para TAXIS**, que es "una base, una columna". Se investigó cómo lo resolvió `maytech` en producción (`modules/whatsapp/WhatsappWebhookController.php::empresaDelToken()`): consulta `wa_config` directo por `webhook_token_hash` en la base compartida, sin pasar por la función del motor. `modules/webhook/mensajes.php` sigue el mismo patrón exacto.

- `core/ConectorMotor.php`: equivalente a `waConectarMotor()` de MayTech — arranca `Engine::arrancar()` con la empresa ya resuelta, explícita (nunca "la actual": un webhook llega sin sesión).
- `modules/webhook/mensajes.php`: mismo orden que el controlador de MayTech — resolver empresa → conectar motor → deduplicar → responder 200 → procesar. **v1 solo procesa texto** (ni audio ni imagen): STT/TTS/Visión del motor necesitan credenciales de proveedor (ElevenLabs/Piper/Whisper) que este proyecto no tiene todavía.
- `modules/admin/whatsapp.php`: configura `wa_config` por empresa (proveedor LLM, Evolution, token del webhook) usando `WaConfig::guardar()`/`regenerarWebhookToken()` del motor tal cual — no se reimplementó nada.

### Probado de punta a punta, con una salvedad

Se simuló un payload de Evolution real contra `modules/webhook/mensajes.php` con una clave de Anthropic **inventada**: la llamada real a la API de Anthropic la rechazó (`invalid x-api-key`), confirmando que `LlmProviderManager`/`AnthropicAdapter` están bien conectados. El fallo se manejó con gracia (mensaje de error al cliente, `HumanHandoff` a una persona, todo en `wa_eventos`). Confirmado también: token inválido → 404 seco; motor apagado → 200 ignorado sin tocar nada; mismo `message_id` dos veces → deduplicado, cero filas nuevas.

**Lo único que no se pudo probar es la inteligencia real de la conversación** (que el agente entienda "necesito un taxi", pida recogida/destino, y llame a `registrar_solicitud`) — eso exige una clave de API válida (Anthropic/Gemini/OpenAI) y, para probarlo con WhatsApp de verdad, una instancia real de Evolution API conectada a un número. Ninguna de las dos existe todavía en este proyecto.

## Pendiente explícito

Ver `docs/ESTADO_Y_PENDIENTES.md`.
