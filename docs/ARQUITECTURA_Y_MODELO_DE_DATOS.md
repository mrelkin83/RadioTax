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

## Pendiente explícito

Ver `docs/ESTADO_Y_PENDIENTES.md`.
