<?php

/**
 * panel/logout.php
 * -----------------------------------------------------------------------
 * Cierre seguro de sesión: vacía $_SESSION, borra la cookie de sesión (con los
 * mismos parámetros con que se creó), destruye los datos en el servidor y
 * redirige al login sin dejar páginas en caché.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

use Rubicam\Http\Respuesta;
use Rubicam\Seguridad\CabecerasSeguras;
use Rubicam\Seguridad\SesionPanel;

require_once dirname(__DIR__) . '/config/arranque.php';

SesionPanel::iniciar();
SesionPanel::cerrar();

// Evita que el botón «Atrás» muestre el panel desde la caché.
CabecerasSeguras::enviar(CabecerasSeguras::panel() + ['Expires' => '0']);
Respuesta::redireccion('index.php', 303)->enviar();
