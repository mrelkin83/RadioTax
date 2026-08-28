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

## Pendiente explícito

Ver `docs/ESTADO_Y_PENDIENTES.md`.
