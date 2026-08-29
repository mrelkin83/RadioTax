<?php

declare(strict_types=1);

namespace TaxiApp\Core;

final class Auth
{
    private static bool $iniciada = false;

    public static function iniciar(): void
    {
        if (self::$iniciada) {
            return;
        }

        self::$iniciada = true;

        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_samesite', 'Strict');
            if ((($_SERVER['HTTPS'] ?? '') !== '') || (($_SERVER['SERVER_PORT'] ?? '') === '443')) {
                ini_set('session.cookie_secure', '1');
            }

            session_start();
        }
    }

    public static function intentarLogin(string $usuario, string $clave): ?array
    {
        self::iniciar();

        $sentencia = Database::conexion()->prepare(
            'SELECT u.*, e.nombre AS empresa_nombre FROM tx_usuarios u
             LEFT JOIN tx_empresas e ON e.id = u.empresa_id
             WHERE u.usuario = :usuario AND u.activo = 1 LIMIT 1'
        );
        $sentencia->execute(['usuario' => $usuario]);
        $fila = $sentencia->fetch();

        if ($fila === false || !password_verify($clave, $fila['clave_hash'])) {
            return null;
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $fila['id'];
        // Un SUPERADMIN no pertenece a ninguna empresa (§7: marca blanca real).
        $_SESSION['empresa_id'] = $fila['empresa_id'] !== null ? (int) $fila['empresa_id'] : null;
        $_SESSION['empresa_nombre'] = $fila['empresa_nombre']; // null para un SUPERADMIN — nunca "Radio Tax" fijo (§7)
        $_SESSION['nombre'] = $fila['nombre'];
        $_SESSION['rol'] = $fila['rol'];
        $_SESSION['csrf'] = bin2hex(random_bytes(16));

        return $fila;
    }

    public static function usuarioActual(): ?array
    {
        self::iniciar();

        if (!isset($_SESSION['usuario_id'])) {
            return null;
        }

        return [
            'id' => (int) $_SESSION['usuario_id'],
            'empresa_id' => $_SESSION['empresa_id'] !== null ? (int) $_SESSION['empresa_id'] : null,
            'empresa_nombre' => $_SESSION['empresa_nombre'] ?? null,
            'nombre' => (string) $_SESSION['nombre'],
            'rol' => (string) $_SESSION['rol'],
        ];
    }

    public static function requerirSuperadmin(): array
    {
        $usuario = self::requerirSesion();
        if ($usuario['rol'] !== 'SUPERADMIN') {
            http_response_code(403);
            echo 'No autorizado. Esta sección es solo para el dueño de la plataforma.';
            exit;
        }

        return $usuario;
    }

    /** Como requerirSesion(), pero además exige pertenecer a una empresa (un SUPERADMIN no tiene). */
    public static function requerirSesionDeEmpresa(): array
    {
        $usuario = self::requerirSesion();
        if ($usuario['empresa_id'] === null) {
            http_response_code(403);
            echo 'No autorizado. Un usuario de plataforma no pertenece a ninguna empresa.';
            exit;
        }

        return $usuario;
    }

    /** Como requerirSesionApi(), pero además exige pertenecer a una empresa. */
    public static function requerirSesionApiDeEmpresa(): array
    {
        $usuario = self::requerirSesionApi();
        if ($usuario['empresa_id'] === null) {
            self::responderJson(['error' => 'Un usuario de plataforma no pertenece a ninguna empresa'], 403);
        }

        return $usuario;
    }

    public static function requerirSesion(): array
    {
        $usuario = self::usuarioActual();
        if ($usuario === null) {
            header('Location: /modules/panel/login.php');
            exit;
        }

        return $usuario;
    }

    public static function requerirSesionApi(): array
    {
        $usuario = self::usuarioActual();
        if ($usuario === null) {
            self::responderJson(['error' => 'No autenticado'], 401);
        }

        return $usuario;
    }

    public static function verificarCsrf(): void
    {
        self::iniciar();
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
        if (!isset($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], (string) $token)) {
            self::responderJson(['error' => 'Token CSRF inválido o ausente'], 419);
        }
    }

    /** Como verificarCsrf(), pero para páginas HTML: no responde JSON, solo informa si el token es válido. */
    public static function csrfValido(): bool
    {
        self::iniciar();
        $token = (string) ($_POST['csrf'] ?? '');

        return isset($_SESSION['csrf']) && hash_equals((string) $_SESSION['csrf'], $token);
    }

    public static function tokenCsrf(): string
    {
        self::iniciar();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(16));

        return $_SESSION['csrf'];
    }

    public static function cerrarSesion(): void
    {
        self::iniciar();
        $_SESSION = [];
        session_destroy();
    }

    private static function responderJson(array $datos, int $codigo): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
