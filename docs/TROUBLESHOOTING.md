# Troubleshooting

## `php` no funciona en PowerShell (pero sí en bash)

En este equipo, `php` en el PATH de PowerShell resuelve a `C:\Users\ELKIN\bin\php` — un shim sin extensión que PowerShell no puede ejecutar ("No se puede ejecutar un documento en medio de una canalización"). `php -v` en bash sí funciona porque bash interpreta ese shim.

**Solución en PowerShell**: usar la ruta completa al `php.exe` real de Laragon, no `php` a secas:

```powershell
$phpExe = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
& $phpExe -v
& $phpExe database\migrate.php
& $phpExe tests\prueba.php
```

(Ajusta la versión de la carpeta si Laragon cambia de PHP activo — `Get-ChildItem C:\laragon\bin\php -Directory` lista las instaladas.)

## Composer no se encuentra en `bash`/PowerShell

`composer.phar` vive en `C:\laragon\bin\composer\composer.phar` pero no está en el PATH. Invocar con el `php.exe` real: `& $phpExe C:\laragon\bin\composer\composer.phar <comando>`, ejecutado desde el directorio del proyecto.

## `tests/prueba.php` falla al crear la base temporal

Verifica que el usuario de `DB_USERNAME`/`DB_PASSWORD` en `.env` tenga privilegios `CREATE`/`DROP` sobre bases de datos (no solo sobre `taxiapp`). En Laragon con `root` sin clave suele bastar.
