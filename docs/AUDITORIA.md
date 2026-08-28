# Auditoría — Fase 0

Fecha: 2026-08-28

## Estado del repositorio al iniciar

- Repo sin inicializar (`git init` ejecutado en esta sesión).
- Único archivo preexistente: `SYSTEM_PROMPT_MAESTRO_TAXIAPP.md` (system prompt maestro del proyecto).
- Sin `composer.json`, sin `vendor/`, sin base de datos, sin código previo.

## Paquete `elkinlinan/whatsapp-ai-engine`

**Actualización (28 ago 2026, misma sesión, más tarde):** el paquete sí existe — el hallazgo inicial de abajo estaba incompleto, no la respuesta del usuario en su momento. Ver `docs/ESTADO_Y_PENDIENTES.md` para el relato completo: vive como *path repository* de Composer, con una copia local en cada proyecto hermano (`Control_BarMax`, `maytech`, `MisRifas`, `PAduanero`), y las cuatro habían divergido. Se copió la de `Control_BarMax` (la más reciente) a `packages/whatsapp-engine/` dentro de TAXIS y se declaró en `composer.json`. **Fase 0 ya no está bloqueada por esto.**

### Hallazgo original (ya superado, se deja como registro)

- No estaba disponible en la búsqueda inicial dentro de la carpeta de TAXIS. No se buscó en el resto de `C:\laragon\www` hasta que el usuario preguntó "en la carpeta del proyecto ¿dónde más?", lo cual llevó a encontrarlo en los proyectos hermanos.
- Cuando se preguntó por su origen, el usuario respondió "aún no existe / se creará después" — respuesta que, a la luz de lo encontrado después, probablemente reflejaba que el paquete *reutilizable y consolidado* no existe (las 4 copias divergieron), no que no hubiera nada escrito.
- **Segundo hallazgo, más serio**: `ToolEngine.php` (dentro del motor) tenía el catálogo de herramientas y la persistencia de `crear_pedido` hardcodeados para negocios de "producto + cantidad" con reparto a domicilio — no había forma de agregar `registrar_solicitud` (recogida/destino) sin modificar el paquete. Se resolvió generalizando `ToolEngine` (`SinCatalogoDeProductos`, `SoportaHerramientasPersonalizadas`), con autorización explícita del usuario, sin romper la suite propia del motor (55/55 sigue en verde).

## Entorno detectado

- PHP 8.3.30 (cumple el requisito `^8.2`).
- Composer disponible en `C:\laragon\bin\composer\composer.phar` (no está en el PATH de bash/PowerShell de esta sesión).
- MySQL vía Laragon — credenciales/puerto no verificados en esta sesión, pendiente de configurar `.env`.

## Memoria de sesiones previas

Existe una observación de memoria (proyecto `TAXIS`, id 3075, 28 ago 2026) que registra una versión anterior del documento con otro nombre de archivo: `SYSTEM PROMPT MAESTRO PROMAX PREMIUM — RADIO TAX AI DISPATCH PLATFORM.md`. Ese archivo ya no existe en el repo; fue reemplazado por `SYSTEM_PROMPT_MAESTRO_TAXIAPP.md`, que es el que se ejecutó en esta sesión.
