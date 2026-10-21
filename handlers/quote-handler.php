<?php

/**
 * handlers/quote-handler.php
 * -----------------------------------------------------------------------
 * Procesa el formulario de cotización del sitio público (CSRF, honeypot,
 * límite de solicitudes, sanitización estricta y correo con cabeceras
 * seguras). La lógica vive en Rubicam\Publico\ControladorCotizacion.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

use Rubicam\Http\Ejecutor;
use Rubicam\Http\Solicitud;
use Rubicam\Publico\ControladorCotizacion;
use Rubicam\Seguridad\CabecerasSeguras;
use Rubicam\Seguridad\LimitadorTasa;
use Rubicam\Seguridad\SesionPanel;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/bootstrap.php';

CabecerasSeguras::enviar(CabecerasSeguras::publico(CHAT_WEBHOOK_URL, !empty($_SERVER['HTTPS'])));
SesionPanel::iniciar();

(new Ejecutor())->ejecutarJson(static function () {
    $controlador = new ControladorCotizacion(
        new LimitadorTasa(ROOT_PATH . '/logs/ratelimit', QUOTE_RATE_LIMIT_MAX, QUOTE_RATE_LIMIT_WINDOW),
        static fn (string $destino, string $asunto, string $cuerpo, array $cabeceras): bool => mail(
            $destino,
            $asunto,
            $cuerpo,
            implode("\r\n", $cabeceras)
        ),
        EMAIL_CONTACT
    );

    return $controlador->procesar(Solicitud::desdeGlobales());
});
