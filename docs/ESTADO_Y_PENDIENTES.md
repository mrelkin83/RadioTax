# Estado y pendientes

Última actualización: 2026-08-28 (sesión de arranque, Fase 0).

## Fase actual: FASE 0 — Cimientos (en progreso, no cerrada)

### Bloqueante principal

**El paquete `elkinlinan/whatsapp-ai-engine` no existe todavía.** Confirmado con el usuario. Esto impide:

- Declarar el `require` real en `composer.json`.
- Ejecutar `Engine::arrancar([...])`.
- Hacer que `TaxiAdapter` implemente formalmente `DomainAdapter`.
- Correr el patrón `tests/prueba.php` del paquete adaptado al taxi contra el contrato real (existe una versión propia en este repo, pero valida solo el adaptador + esquema, no la integración con el motor).

**Sin esto, la definición de hecho de Fase 0 no se puede cerrar del todo**, aunque todo lo demás de Fase 0 quedó construido y probado (ver `SPEC.md`).

### Hecho en esta sesión

- Estructura del proyecto, `git init`, `composer.json`, `.gitignore`, `.env.example`.
- `core/Database.php`, `core/BaseModel.php`.
- 13 migraciones idempotentes del esquema `tx_*` v1 + runner `database/migrate.php`.
- Puertos placeholder: `TaxiDb`, `TaxiCifrado`, `TaxiAlmacen`, `TaxiTenant`, `PesosColombianos`.
- Capacidades: `SoportaDireccionesFrecuentes`, `SoportaDespachoOperativo`.
- `TaxiAdapter` completo (9 métodos del contrato + 2 capacidades) con lógica real contra `tx_*`.
- Suite `tests/prueba.php` (E2E contra base temporal, se autodestruye).
- Documentación base en `docs/`.

### Próximos pasos (en orden)

1. **Decisión pendiente del usuario**: origen de `elkinlinan/whatsapp-ai-engine` (VCS privado / path local / aún por crear). Sin esto no se puede avanzar el resto de Fase 0.
2. Cuando el paquete exista: agregar `repositories` + `require` en `composer.json`, `composer install`, hacer que `TaxiAdapter implements DomainAdapter`, ajustar firmas si difieren del mapeo documentado en §5.2.
3. Configurar `.env` real (copiar de `.env.example`) y correr `php database/migrate.php` contra la base de desarrollo.
4. Correr `php tests/prueba.php` para confirmar que el adaptador sigue en verde.
5. Solo entonces: cerrar Fase 0 y arrancar Fase 1 (MVP conversacional + despacho híbrido/manual).

### Decisiones tomadas que vale la pena recordar

- Catálogo de tipos de servicio vive en `tx_empresas.config` (JSON), no en tabla propia — ver `ARQUITECTURA_Y_MODELO_DE_DATOS.md`.
- Namespace raíz de la plataforma: `TaxiApp\`.
