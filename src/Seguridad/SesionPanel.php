<?php

/**
 * src/Seguridad/SesionPanel.php
 * -----------------------------------------------------------------------
 * Sesión del panel operativo: cookie endurecida, usuario autenticado,
 * permisos por rol y token CSRF. Es la única clase que escribe en $_SESSION.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Seguridad;

use Rubicam\Dominio\Catalogos;

final class SesionPanel
{
    private const CLAVE_CSRF = 'csrf_panel';

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    /**
     * Abre la sesión de un usuario ya verificado. Regenera el identificador para impedir la fijación de sesión.
     *
     * @param array{id:int|string, usuario:string, rol:string, nombre_completo:string, sucursal:?string} $usuario
     */
    public static function abrir(array $usuario): void
    {
        session_regenerate_id(true);

        $_SESSION['user_id']         = (int) $usuario['id'];
        $_SESSION['usuario']         = $usuario['usuario'];
        $_SESSION['rol']             = $usuario['rol'];
        $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
        $_SESSION['sucursal']        = $usuario['sucursal'];
    }

    /** Cierre completo: vacía los datos, borra la cookie y destruye la sesión en el servidor. */
    public static function cerrar(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $parametros['path'],
                'domain'   => $parametros['domain'],
                'secure'   => $parametros['secure'],
                'httponly' => $parametros['httponly'],
                'samesite' => $parametros['samesite'] ?: 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function autenticado(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function puedeEditar(): bool
    {
        return Catalogos::rolPuedeEditar((string) ($_SESSION['rol'] ?? ''));
    }

    public static function nombreUsuario(): string
    {
        return (string) ($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'Usuario');
    }

    public static function rol(): string
    {
        return (string) ($_SESSION['rol'] ?? '');
    }

    public static function usuarioLogin(): string
    {
        return (string) ($_SESSION['usuario'] ?? '');
    }

    public static function sucursal(): string
    {
        return (string) ($_SESSION['sucursal'] ?? '');
    }

    public static function tokenCsrf(): string
    {
        if (empty($_SESSION[self::CLAVE_CSRF])) {
            $_SESSION[self::CLAVE_CSRF] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::CLAVE_CSRF];
    }

    public static function csrfValido(?string $token): bool
    {
        return !empty($_SESSION[self::CLAVE_CSRF])
            && is_string($token)
            && $token !== ''
            && hash_equals((string) $_SESSION[self::CLAVE_CSRF], $token);
    }
}
