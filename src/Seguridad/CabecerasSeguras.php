<?php

/**
 * src/Seguridad/CabecerasSeguras.php
 * -----------------------------------------------------------------------
 * Cabeceras HTTP de seguridad para el sitio público y para el panel. Los
 * métodos que las construyen son puros (devuelven un arreglo) para poder
 * probarlos; solo enviar() las escribe en la respuesta.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Seguridad;

final class CabecerasSeguras
{
    private const BASE = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options'        => 'SAMEORIGIN',
    ];

    /**
     * Sitio público: permite el CDN de Tailwind y el webhook del asistente (connect-src).
     *
     * @return array<string, string>
     */
    public static function publico(string $urlWebhookChat, bool $https): array
    {
        $origenChat = '';
        if ($urlWebhookChat !== '') {
            $origenChat = sprintf(
                ' %s://%s',
                parse_url($urlWebhookChat, PHP_URL_SCHEME),
                parse_url($urlWebhookChat, PHP_URL_HOST)
            );
        }

        $cabeceras = self::BASE + [
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'Permissions-Policy'      => 'geolocation=(), microphone=(), camera=()',
            'Content-Security-Policy' => "default-src 'self'; "
                . "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; "
                . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
                . "font-src 'self' https://fonts.gstatic.com; "
                . "img-src 'self' data: https:; "
                . "connect-src 'self'" . $origenChat . ';',
        ];
        if ($https) {
            $cabeceras['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $cabeceras;
    }

    /**
     * Panel privado: nada de caché (el botón «Atrás» no debe mostrar datos tras cerrar sesión) y CSP
     * estricta (MR-009). El JavaScript es un archivo propio (assets/js/panel.js): sin 'unsafe-inline' en
     * script-src, un XSS no puede ejecutar código. Los estilos conservan 'unsafe-inline' porque el
     * JavaScript genera algunos atributos style.
     *
     * @return array<string, string>
     */
    public static function panel(): array
    {
        return self::sinCache() + [
            'Content-Security-Policy' => "default-src 'self'; "
                . "script-src 'self'; "
                . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
                . "font-src 'self' https://fonts.gstatic.com; "
                . "img-src 'self' data:; "
                . "connect-src 'self'; "
                . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'",
        ];
    }

    /**
     * Pantalla de inicio de sesión: no ejecuta ningún script; solo carga Bootstrap (CDN) y la tipografía.
     *
     * @return array<string, string>
     */
    public static function acceso(): array
    {
        return self::sinCache() + [
            'Content-Security-Policy' => "default-src 'self'; "
                . "script-src 'none'; "
                . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
                . "font-src 'self' https://fonts.gstatic.com; "
                . "img-src 'self' data:; "
                . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'",
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function sinCache(): array
    {
        return self::BASE + [
            'Cache-Control'   => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'          => 'no-cache',
            'Referrer-Policy' => 'same-origin',
        ];
    }

    /**
     * @param array<string, string> $cabeceras
     */
    public static function enviar(array $cabeceras): void
    {
        foreach ($cabeceras as $nombre => $valor) {
            header($nombre . ': ' . $valor);
        }
    }
}
