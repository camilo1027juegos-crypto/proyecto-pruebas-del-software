<?php

/**
 * config/config.php
 * -----------------------------------------------------------------------
 * Constantes del sitio público Rubicam (identidad, sedes, enlaces, límites).
 * Solo declara constantes: no ejecuta nada. Requiere haber cargado antes
 * config/arranque.php, que prepara el entorno y el autocargador.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

use Rubicam\Infraestructura\Configuracion;

define('BASE_URL', Configuracion::valor('APP_BASE_URL'));
define('ROOT_PATH', Configuracion::raizProyecto());
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
define('CHAT_WEBHOOK_URL', Configuracion::valor('RUBI_WEBHOOK_URL')); // webhook de n8n: se define en el servidor

// --- Límite de solicitudes del cotizador (por IP): 5 envíos cada 10 minutos ---
define('QUOTE_RATE_LIMIT_MAX', 5);
define('QUOTE_RATE_LIMIT_WINDOW', 600);
