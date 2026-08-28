# Estado y pendientes

Última actualización: 2026-08-28 (sesión de arranque, Fase 0 — parte 4: panel admin mínimo, límite de lo avanzable sin el motor).

## Fase actual: FASE 0 — Cimientos (en progreso, no cerrada)

### Bloqueante principal

**El paquete `elkinlinan/whatsapp-ai-engine` no existe todavía.** Confirmado con el usuario. Esto impide:

- Declarar el `require` real en `composer.json`.
- Ejecutar `Engine::arrancar([...])`.
- Hacer que `TaxiAdapter` implemente formalmente `DomainAdapter`.
- Correr el patrón `tests/prueba.php` del paquete adaptado al taxi contra el contrato real (existe una versión propia en este repo, pero valida solo el adaptador + esquema, no la integración con el motor).

**Sin esto, la definición de hecho de Fase 0 no se puede cerrar del todo**, aunque todo lo demás de Fase 0 quedó construido y probado (ver `SPEC.md`).

### Hecho en esta sesión

- Estructura del proyecto, `git init`, `composer.json`, `.gitignore`, `.env.example`. Commit raíz `d435ecb`.
- `core/Database.php`, `core/BaseModel.php`, `core/Env.php` (loader mínimo de `.env`, sin dependencias).
- 13 migraciones idempotentes del esquema `tx_*` v1 + runner `database/migrate.php`.
- Puertos placeholder: `TaxiDb`, `TaxiCifrado`, `TaxiAlmacen`, `TaxiTenant`, `PesosColombianos`.
- Capacidades: `SoportaDireccionesFrecuentes`, `SoportaDespachoOperativo`.
- `TaxiAdapter` completo (9 métodos del contrato + 2 capacidades) con lógica real contra `tx_*`.
- Suite `tests/prueba.php` (E2E contra base temporal, se autodestruye) — verde.
- `.env` real creado y **base de desarrollo `taxiapp` migrada de punta a punta** (`php database/migrate.php`, 13 tablas + `tx_migraciones`), confirmado idempotente en una segunda corrida.
- Bug encontrado y corregido: `migrate.php` envolvía el DDL en una transacción PDO; MySQL hace commit implícito en `CREATE TABLE`, lo que rompía `rollBack()`. Ver `TROUBLESHOOTING.md`.
- Documentación base en `docs/`.

### Próximos pasos (en orden)

1. **Decisión pendiente del usuario**: origen de `elkinlinan/whatsapp-ai-engine` (VCS privado / path local / aún por crear). El usuario confirmó que aún no existe. Sin esto no se puede cerrar Fase 0 del todo.
2. Cuando el paquete exista: agregar `repositories` + `require` en `composer.json`, `composer install`, hacer que `TaxiAdapter implements DomainAdapter`, ajustar firmas si difieren del mapeo documentado en §5.2.
3. ~~Configurar `.env` real y correr `php database/migrate.php` contra la base de desarrollo.~~ **Hecho.**
4. ~~Correr `php tests/prueba.php` para confirmar que el adaptador sigue en verde.~~ **Hecho, en verde.**
5. Solo cuando el motor exista: cerrar Fase 0 y arrancar Fase 1 (MVP conversacional + despacho híbrido/manual).

### Qué se puede avanzar mientras el motor no exista

Todo lo que dependa exclusivamente del `TaxiAdapter`/esquema `tx_*` ya construidos, o del panel del radiooperador (TailwindCSS + JS vanilla, Capa 4 de §4), que no necesita el motor para su UI ni para las acciones manuales/CRUD de flota y turnos. Lo que sigue bloqueado: cualquier cosa de Capa 2 (agentes de IA, herramientas del §5.4, `AiOrchestrator`).

### Panel del radiooperador (Capa 4) — construido esta sesión

`modules/panel/` completo: login/logout con sesión + CSRF (`core/Auth.php`, tabla nueva `tx_usuarios`), dashboard con cola de solicitudes y tablero de flota (polling 4 s), y acciones manuales (asignar, cancelar con motivo, abrir/cerrar turno, cambiar estado de vehículo, solicitud manual). Probado de punta a punta por HTTP contra la base de desarrollo — ver `SPEC.md` y `TESTING.md` para el detalle y cómo volver a probarlo.

**Credenciales de desarrollo actuales** (base `taxiapp` local): usuario `operador1`, empresa "Radio Tax" (id 1), un vehículo (`084`/`ABC084`) y un conductor (`Carlos Perez`) de prueba ya insertados manualmente. La clave no se documenta aquí (no se vuelve a mostrar tras `seed_dev.php`); si se pierde, hay que crear otro usuario con el script o resetear la clave por SQL.

Pendiente de este pedazo: notificar al cliente por WhatsApp tras asignar (necesita el motor).

### Panel administrativo mínimo — construido esta sesión

`modules/admin/` (solo `rol=ADMIN`, `403` para radiooperador): alta y edición de vehículos y conductores, probado en navegador incluyendo el control de acceso. Cierra el gap real: antes de esto, la única forma de dar de alta un vehículo o conductor era `INSERT` directo por SQL. Empresas, líneas, agentes de IA, configuración de despacho y reportes siguen sin panel (no eran el gap urgente).

### Límite de lo que se puede "finalizar" sin el motor

Con esto, **todo lo de Capa 4 (Centro de Transmisión + administración mínima) que no depende de `elkinlinan/whatsapp-ai-engine` está construido y probado en navegador real**: login, cola de solicitudes, tablero de flota, asignar, cancelar, abrir/cerrar turno, cambiar estado de vehículo, solicitud manual, alta/edición de vehículos y conductores, control de acceso por rol.

Lo que queda pendiente **requiere el motor y no se puede simular de forma útil**:
- Capa 2 completa: el agente de IA que conversa por WhatsApp, identifica al cliente, recopila los datos y llama a las herramientas del §5.4.
- Notificar al cliente por WhatsApp tras una asignación (§7, modo híbrido).
- El propio `TaxiAdapter implements DomainAdapter` formal y las 49 pruebas del contrato del paquete (criterio de "hecho" de Fase 0, §13).
- Fase 4 completa (GPS, app del conductor, modo automático).

**Esta sesión llegó al límite de lo avanzable sin una decisión del usuario sobre el origen del motor.** Seguir "hasta finalizar" el proyecto completo requiere retomar el punto 1 de la lista de pendientes: de dónde sale `elkinlinan/whatsapp-ai-engine`.

### Decisiones tomadas que vale la pena recordar

- Catálogo de tipos de servicio vive en `tx_empresas.config` (JSON), no en tabla propia — ver `ARQUITECTURA_Y_MODELO_DE_DATOS.md`.
- Namespace raíz de la plataforma: `TaxiApp\`.
