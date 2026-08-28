# Seguridad — estado de implementación

- Secretos: `TaxiCifrado` usa `sodium_crypto_secretbox` con clave derivada de `APP_SECRET_KEY` (env). Ningún secreto en claro en código ni en migraciones.
- Aislamiento por empresa: toda consulta en `TaxiAdapter` filtra por `empresa_id` (clientes, vehículos, config) o por relación directa (carreras vía cliente/línea). Pendiente de auditoría cuando exista el panel (Fase 1) — ahí es donde se sanitiza entrada del cliente antes de mostrarla (XSS en la cola del radiooperador, regla §14.4 del system prompt maestro).
- SQL: 100% sentencias preparadas (`PDO::prepare` + bind), `PDO::ATTR_EMULATE_PREPARES => false`.
- Resolución de tenant: `TaxiTenant::resolverPorTokenWebhook()` — pendiente conectar a un 404 seco real cuando exista el endpoint de webhook (Fase 1, motor).
- Pendiente: definir dónde vive `APP_SECRET_KEY` en producción (no versionar `.env`; ver `.gitignore`).
