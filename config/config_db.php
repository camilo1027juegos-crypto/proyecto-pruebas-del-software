<?php

/**
 * config/config_db.php
 * -----------------------------------------------------------------------
 * Arranque común del panel y de los handlers: autocargador de clases (src/),
 * zona horaria y registro de errores. La configuración (base de datos, n8n,
 * carpeta de cargas) se lee del entorno en Rubicam\Infraestructura\Configuracion.
 *
 * Inclusión obligatoria (desde panel/ o handlers/):
 *     require_once dirname(__DIR__) . '/config/config_db.php';
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

date_default_timezone_set('America/Bogota');

// Los errores van al log; nunca a pantalla dentro de respuestas JSON.
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/logs/php-error.log');
