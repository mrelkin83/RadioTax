# Seguridad — estado de implementación

- Secretos: `TaxiCifrado` usa `sodium_crypto_secretbox` con clave derivada de `APP_SECRET_KEY` (env). Ningún secreto en claro en código ni en migraciones.
- Aislamiento por empresa: toda consulta en `TaxiAdapter` filtra por `empresa_id` (clientes, vehículos, config) o por relación directa (carreras vía cliente/línea). Pendiente de auditoría cuando exista el panel (Fase 1) — ahí es donde se sanitiza entrada del cliente antes de mostrarla (XSS en la cola del radiooperador, regla §14.4 del system prompt maestro).
- SQL: 100% sentencias preparadas (`PDO::prepare` + bind), `PDO::ATTR_EMULATE_PREPARES => false`.
- Resolución de tenant: `TaxiTenant::resolverPorTokenWebhook()` — pendiente conectar a un 404 seco real cuando exista el endpoint de webhook (Fase 1, motor).
- Pendiente: definir dónde vive `APP_SECRET_KEY` en producción (no versionar `.env`; ver `.gitignore`).

## Panel del radiooperador (`modules/panel/`)

- Sesión PHP nativa reforzada en `core/Auth.php`: `cookie_httponly`, `cookie_samesite=Strict`, `use_strict_mode`, `cookie_secure` cuando la petición es HTTPS, `session_regenerate_id(true)` en cada login exitoso.
- Claves con `password_hash`/`password_verify` (`PASSWORD_DEFAULT`), nunca en claro.
- CSRF: token de sesión, verificado en todo endpoint POST (`Auth::verificarCsrf()`) vía header `X-CSRF-Token`. Probado: una petición sin token responde `419`.
- Toda la API exige sesión (`Auth::requerirSesionApi()` → `401` si no hay sesión) y filtra cada consulta por `empresa_id` de la sesión — un radiooperador nunca ve datos de otra empresa.
- XSS: `assets/panel.js` construye el DOM con `textContent`/`createElement`, nunca `innerHTML`, para todo dato que viene del cliente (dirección, observaciones, nombre) — regla §14.4 explícita sobre la cola del radiooperador.
- Sin auto-registro de usuarios del panel: se crean con `database/seed_dev.php` (script de servidor, no expuesto por HTTP).

## Webhook (`modules/webhook/mensajes.php`)

- Token de webhook de 64 hex (32 bytes aleatorios), guardado como SHA-256 en `wa_config.webhook_token_hash` — nunca en claro. Token inválido o que no casa con ninguna empresa → `404` seco, sin cuerpo, sin distinguir "no existe" de "está mal formado" (evita dar pistas a quien intente adivinarlo).
- Segunda verificación: la apikey que manda Evolution en la cabecera (`apikey`/`X-Api-Key`) se compara con `hash_equals()` contra la guardada — si no coincide, también `404`.
- Sin sesión ni CSRF (`session_abort()` al entrar): un webhook no trae cookies ni token CSRF, y no tiene sentido pedírselos.
- Secretos (`evolution_apikey`, `llm_api_key`, ...) cifrados con `TaxiCifrado` antes de guardarse (`WaConfig::guardar()` del motor); nunca se devuelven al frontend (`WaConfig::paraFrontend()` solo informa si hay uno guardado, no cuál es).
- Idempotencia real: `wa_mensajes.message_id_externo` es `UNIQUE` — un webhook reintentado por Evolution choca ahí y se descarta sin reprocesar. Probado con curl reenviando el mismo `message_id`.

## Panel administrativo (`modules/admin/`)

- Control de acceso por rol: `modules/admin/_bootstrap.php` exige sesión (como el resto del panel) **y** `rol=ADMIN`; un `RADIOOPERADOR` recibe `403` tanto por navegación como por URL directa (probado en navegador).
- Mismos formularios HTML con CSRF (`Auth::csrfValido()`) y salida escapada (`htmlspecialchars`) que el resto del panel.
