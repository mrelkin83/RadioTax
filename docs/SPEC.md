# SPEC — lo construido

## Fase 0 — Cimientos (en progreso)

### Hecho

- Estructura de proyecto: `composer.json` (PSR-4, PHP `^8.2`, sin el motor todavía — ver pendientes).
- `core/Env.php`: loader mínimo de `.env` (sin dependencias) — parsea `KEY=VALUE`, ignora comentarios `#`, no pisa variables ya presentes en el entorno real. Se invoca perezosamente desde `Database::conectar()` y `TaxiCifrado`.
- `core/Database.php`: conexión PDO singleton vía variables de entorno (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, prepares emuladas desactivadas.
- `core/BaseModel.php`: helpers CRUD genéricos (`encontrar`, `todos`, `crear`, `actualizar`) sobre `static::$tabla`.
- 13 migraciones idempotentes para el esquema `tx_*` completo de la v1.
- `database/migrate.php`: runner con tabla de control `tx_migraciones`.
- Puertos (`src/Ports/`): `TaxiDb`, `TaxiCifrado` (libsodium secretbox), `TaxiAlmacen` (filesystem local), `TaxiTenant` (resolución de empresa por token de webhook), `PesosColombianos` (formato COP) — **placeholders pendientes de conformar al contrato real del motor**.
- Capacidades (`src/Capacidades/`): `SoportaDireccionesFrecuentes`, `SoportaDespachoOperativo`, tal como se definieron en §5.3 del system prompt maestro.
- `src/Domain/TaxiAdapter.php`: implementa los 9 métodos del mapeo §5.2 (`contextoCliente`, `buscarItems`, `detalleItem`, `disponibilidad`, `crearTransaccion`, `estadoTransaccion`, `cancelarTransaccion`, `confirmarTransaccion`, `calcularTotal`, `capacidades`) más las dos interfaces `Soporta*`, con lógica real contra `tx_*` (no simulada). `calcularTotal()` nunca devuelve monto. `capacidades()` nunca declara pagos.
- `tests/prueba.php`: suite E2E contra base de datos temporal (`CREATE DATABASE` / `DROP DATABASE` en cada corrida), sin tocar datos reales. Cubre: catálogo de servicios, creación idempotente, transición a despacho, cancelación con motivo, trazabilidad en `tx_carrera_eventos`, y que `capacidades()` no declare pagos.
- Base de datos de desarrollo real (`taxiapp`, local Laragon) creada y con las 13 migraciones aplicadas vía `.env` + `php database/migrate.php` — confirmado idempotente (segunda corrida no aplica nada) y la suite de pruebas también pasa leyendo solo `.env` (sin variables inyectadas a mano).

## Capa 4 — Centro de transmisión (panel del radiooperador)

No depende del motor, así que se construyó en paralelo mientras el bloqueo de `elkinlinan/whatsapp-ai-engine` sigue abierto.

### Hecho

- `tx_usuarios` (migración `0014`) + `core/Auth.php`: login por sesión, CSRF, guardas de página y de API.
- `database/seed_dev.php`: crea la empresa "Radio Tax", su primera línea y el primer usuario (sin auto-registro).
- `modules/panel/`: `login.php`, `logout.php`, `index.php` (dashboard), `assets/panel.js` (vanilla JS, polling 4 s).
- `modules/panel/api/`: `cola.php` y `flota.php` (GET), `conductores.php` (GET, para el selector de apertura de turno), `asignar.php`, `cancelar.php`, `turno_abrir.php`, `turno_cerrar.php`, `vehiculo_estado.php`, `solicitud_nueva.php` (POST, con CSRF).
- **Probado de punta a punta por HTTP real** (servidor embebido de PHP, curl con cookie jar): login → abrir turno → crear solicitud manual → asignar vehículo → carrera queda `ASIGNADA`, vehículo `SOLICITADO`, y `tx_carrera_eventos`/`tx_asignaciones` registran `actor_tipo=RADIOOPERADOR` con el `actor_id` real. Confirmado que sin sesión la API responde 401 y sin token CSRF responde 419.
- `TaxiAdapter::crearTransaccion()` ahora acepta un `$actorTipo` (default `IA`) para que las solicitudes creadas manualmente por el panel se atribuyan a `RADIOOPERADOR`, no a `IA`.

### No hecho / fuera de alcance de esta sesión

- Notificar al cliente por WhatsApp tras asignar ("el bot informa al cliente", §7) — requiere el motor conversacional.
- Panel administrativo (empresas, líneas, agentes de IA, alta de vehículos/conductores, reportes) — es "aparte" según §8, no forma parte del Centro de Transmisión.
- `Engine::arrancar()` real y `TaxiAdapter implements DomainAdapter` formal — el paquete `elkinlinan/whatsapp-ai-engine` no existe todavía.
- Capa 2 (conversación/agentes de IA), Capa 5 (API conductor) — pertenecen a fases posteriores y/o dependen del motor.
