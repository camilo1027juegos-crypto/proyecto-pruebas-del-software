<?php

/**
 * src/Panel/Guardia.php
 * -----------------------------------------------------------------------
 * Controles de acceso que preceden a cualquier acción del panel, siempre en
 * el mismo orden: sesión (401) → método (405) → token CSRF (403) → rol (403).
 * Cada control lanza ErrorHttp; el Ejecutor lo convierte en respuesta.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Panel;

use Rubicam\Http\ErrorHttp;
use Rubicam\Http\Solicitud;
use Rubicam\Seguridad\SesionPanel;

final class Guardia
{
    public static function sesion(): void
    {
        if (!SesionPanel::autenticado()) {
            throw new ErrorHttp('Su sesión expiró. Inicie sesión nuevamente.', 401);
        }
    }

    public static function metodoPost(Solicitud $solicitud): void
    {
        if (!$solicitud->esPost()) {
            throw new ErrorHttp('Método no permitido. Use POST.', 405);
        }
    }

    /** El token CSRF debe llegar en la cabecera X-CSRF-Token (lo envía el JavaScript del panel). */
    public static function csrf(Solicitud $solicitud): void
    {
        self::validarToken($solicitud->cabecera('X-CSRF-Token'));
    }

    /** Igual que csrf(), pero admite el campo «csrf_token» de un formulario clásico. */
    public static function csrfEnCabeceraOFormulario(Solicitud $solicitud): void
    {
        self::validarToken($solicitud->cabecera('X-CSRF-Token') ?? $solicitud->formulario('csrf_token'));
    }

    public static function rolDeEdicion(string $mensaje): void
    {
        if (!SesionPanel::puedeEditar()) {
            throw new ErrorHttp($mensaje, 403);
        }
    }

    /** POST + CSRF + rol de edición, en ese orden. */
    public static function escritura(Solicitud $solicitud, string $mensajeSinPermiso): void
    {
        self::metodoPost($solicitud);
        self::csrf($solicitud);
        self::rolDeEdicion($mensajeSinPermiso);
    }

    private static function validarToken(?string $token): void
    {
        if (!SesionPanel::csrfValido($token)) {
            throw new ErrorHttp('Token de seguridad inválido. Recargue la página e intente de nuevo.', 403);
        }
    }
}
