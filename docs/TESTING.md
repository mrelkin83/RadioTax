# Testing

## Cómo correr las pruebas de Fase 0

1. Configura `.env` (copia `.env.example`) con credenciales de un MySQL local con permiso `CREATE DATABASE` / `DROP DATABASE`.
2. `composer install` (hoy no hay dependencias externas declaradas; cuando el motor esté disponible, este paso lo trae).
3. `php tests/prueba.php`

## Qué prueba `tests/prueba.php`

Crea una base `taxiapp_test_<random>` aislada, corre las 13 migraciones, ejercita `TaxiAdapter` de punta a punta (creación idempotente de carrera → confirmación → cancelación, con verificación de trazabilidad en `tx_carrera_eventos`), y **destruye la base al terminar** (bloque `finally`), pase o falle la prueba. Nunca toca una base real.

## Pendiente

- Integración contra el motor real (`Engine::arrancar()` con `TaxiAdapter`) en cuanto `elkinlinan/whatsapp-ai-engine` esté disponible — es el criterio de "hecho" formal de Fase 0.
- Medición de costo de tokens del prompt del agente (patrón `wa_medir_prompt.php`) — no aplica todavía porque no hay agente conectado.
