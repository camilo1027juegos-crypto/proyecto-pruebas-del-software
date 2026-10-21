<?php

/**
 * config/arranque.php
 * -----------------------------------------------------------------------
 * Arranque común de TODA entrada web (sitio, panel y handlers):
 *   1. autocargador de clases (src/);
 *   2. lectura del archivo .env (si existe) — lo definido en el servidor manda;
 *   3. zona horaria y política de errores según APP_ENV;
 *   4. registro de errores en logs/php-error.log (nunca a pantalla en producción).
 *
 * Las variables (base de datos, n8n, carpeta de cargas...) se consultan con
 * Rubicam\Infraestructura\Configuracion. Ver .env.example.
 *
 * Inclusión obligatoria (desde panel/, handlers/ o la raíz):
 *     require_once dirname(__DIR__) . '/config/arranque.php';
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

use Rubicam\Infraestructura\CargadorEntorno;
use Rubicam\Infraestructura\Configuracion;

require_once dirname(__DIR__) . '/src/bootstrap.php';

CargadorEntorno::cargar(Configuracion::raizProyecto() . '/.env');

date_default_timezone_set('America/Bogota');

if (Configuracion::esDesarrollo()) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

ini_set('log_errors', '1');
ini_set('error_log', Configuracion::raizProyecto() . '/logs/php-error.log');
