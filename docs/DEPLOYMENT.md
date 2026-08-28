# Deployment

Sin objetivo de despliegue todavía (Fase 0). Entorno de desarrollo: Laragon (Apache/Nginx + PHP 8.3 + MySQL 8) en Windows, `C:\laragon\www\TAXIS`.

Pendiente de definir en fases posteriores: contenedor Docker para Evolution API por línea, proceso de migración en despliegue (`php database/migrate.php` como paso de release, nunca `ALTER` manual), variables de entorno de producción.
