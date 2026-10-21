<?php

/**
 * config/config.php
 * -----------------------------------------------------------------------
 * Configuración central del portal Rubicam (rediseño). Código propietario:
 * sin maquetadores, HTML5 semántico + Tailwind CSS + PHP 8.2+ nativo.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

date_default_timezone_set('America/Bogota');

define('APP_ENV', getenv('APP_ENV') ?: 'production');
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

define('BASE_URL', getenv('APP_BASE_URL') ?: 'https://rubicam.co');
define('ROOT_PATH', dirname(__DIR__));
define('ASSETS_URL', 'assets');

// --- Identidad corporativa ---
define('SITE_NAME', 'RUBICAM');
define('SITE_LEGAL_NAME', 'Agencia de Aduanas Rubicam S.A.S.');
define('SITE_NIT', '901.742.681-8');
define('SITE_DIAN_LEVEL', 'Agencia de Aduanas Nivel 2 autorizada por la DIAN');
define('SITE_YEARS', 30);

// --- Sedes ---
define('OFFICE_CUCUTA', "Zona Franca Cúcuta, Norte de Santander, Colombia");
define('OFFICE_ENVIGADO', "Envigado, Antioquia, Colombia");
define('PHONE_CUCUTA', '+57 (607) 000-0000');
define('PHONE_ENVIGADO', '+57 (604) 622-1815');
define('WHATSAPP_NUMBER', '573005959894');
define('EMAIL_CONTACT', 'contacto@rubicam.co');

// --- Enlaces del ecosistema interno ---
define('LOBBY_URL', '/lobby/'); // Panel privado (fuera de alcance de este entregable, ver README)
define('CHAT_WEBHOOK_URL', getenv('RUBI_WEBHOOK_URL') ?: ''); // n8n webhook — configurar en el servidor

// --- Seguridad del cotizador ---
define('CSRF_SECRET', getenv('CSRF_SECRET') ?: 'cambia_esta_clave_en_produccion');
define('QUOTE_RATE_LIMIT_MAX', 5);
define('QUOTE_RATE_LIMIT_WINDOW', 600);
