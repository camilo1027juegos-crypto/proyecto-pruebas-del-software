<?php

/**
 * src/Seguridad/CsrfPublico.php — Token CSRF del formulario de cotización del sitio público.
 */

declare(strict_types=1);

namespace Rubicam\Seguridad;

final class CsrfPublico
{
    private const CLAVE = 'csrf_token';

    public static function token(): string
    {
        SesionPanel::iniciar();
        if (empty($_SESSION[self::CLAVE])) {
            $_SESSION[self::CLAVE] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::CLAVE];
    }

    public static function valido(?string $token): bool
    {
        SesionPanel::iniciar();

        return !empty($_SESSION[self::CLAVE])
            && is_string($token)
            && $token !== ''
            && hash_equals((string) $_SESSION[self::CLAVE], $token);
    }
}
