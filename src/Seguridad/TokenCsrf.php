<?php

/**
 * src/Seguridad/TokenCsrf.php
 * -----------------------------------------------------------------------
 * Token anti-CSRF (falsificación de peticiones) guardado en la sesión. Hay
 * uno para el panel privado y otro para el formulario público, con claves
 * de sesión distintas. La sesión debe estar iniciada (SesionPanel::iniciar).
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Seguridad;

final class TokenCsrf
{
    private function __construct(private readonly string $claveDeSesion)
    {
    }

    public static function panel(): self
    {
        return new self('csrf_panel');
    }

    public static function publico(): self
    {
        return new self('csrf_token');
    }

    /** Devuelve el token de la sesión (lo crea la primera vez: 64 caracteres hexadecimales aleatorios). */
    public function emitir(): string
    {
        if (empty($_SESSION[$this->claveDeSesion])) {
            $_SESSION[$this->claveDeSesion] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[$this->claveDeSesion];
    }

    /** Compara en tiempo constante; sin token emitido o con token vacío siempre es falso. */
    public function valido(?string $token): bool
    {
        return !empty($_SESSION[$this->claveDeSesion])
            && is_string($token)
            && $token !== ''
            && hash_equals((string) $_SESSION[$this->claveDeSesion], $token);
    }
}
