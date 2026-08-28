# Testing

## Cómo correr las pruebas de Fase 0

1. Configura `.env` (copia `.env.example`) con credenciales de un MySQL local con permiso `CREATE DATABASE` / `DROP DATABASE`.
2. `composer install` (hoy no hay dependencias externas declaradas; cuando el motor esté disponible, este paso lo trae).
3. `php tests/prueba.php`

## Qué prueba `tests/prueba.php`

Crea una base `taxiapp_test_<random>` aislada, corre las 13 migraciones, ejercita `TaxiAdapter` de punta a punta (creación idempotente de carrera → confirmación → cancelación, con verificación de trazabilidad en `tx_carrera_eventos`), y **destruye la base al terminar** (bloque `finally`), pase o falle la prueba. Nunca toca una base real.

## Cómo probar el panel del radiooperador en local

1. Migra y siembra: `php database/migrate.php` y luego `php database/seed_dev.php <usuario> <clave> "<nombre>"` (los tres argumentos son opcionales; sin ellos genera un usuario `operador1` con clave aleatoria — se imprime una sola vez).
2. Sirve el proyecto: con Laragon (Apache) apuntando a la raíz del repo, o para pruebas rápidas sin vhost: `php -S 127.0.0.1:8090 -t .` desde la raíz del proyecto.
3. Entra a `http://<host>/modules/panel/login.php` con el usuario sembrado.
4. Sin flota sembrada la cola y el tablero salen vacíos — inserta un vehículo y un conductor de prueba (`tx_vehiculos`, `tx_conductores`) para probar "Abrir turno" y "Asignar".

Esto se verificó de punta a punta en esta sesión con curl + cookie jar contra el servidor embebido de PHP (login → abrir turno → solicitud manual → asignar), incluyendo que la API rechaza peticiones sin sesión (`401`) y sin token CSRF (`419`). No hay todavía un test automatizado del panel (solo manual) — pendiente si se justifica el esfuerzo antes de Fase 2.

## Pendiente

- Integración contra el motor real (`Engine::arrancar()` con `TaxiAdapter`) en cuanto `elkinlinan/whatsapp-ai-engine` esté disponible — es el criterio de "hecho" formal de Fase 0.
- Medición de costo de tokens del prompt del agente (patrón `wa_medir_prompt.php`) — no aplica todavía porque no hay agente conectado.
- Test automatizado del panel (hoy solo probado manualmente por HTTP).
