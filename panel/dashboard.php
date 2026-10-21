<?php

/**
 * panel/dashboard.php
 * -----------------------------------------------------------------------
 * Panel operativo de Órdenes de Operación (DO) — SIA RUBICAM.
 *
 * Este archivo es solo el PUNTO DE ENTRADA:
 *   - con ?action=...  → atiende la acción AJAX (Rubicam\Panel\Despachador):
 *        listar | crear (POST) | documentos&do_id=N | descargar&doc_id=N
 *   - sin acción       → muestra la página (panel/vistas/dashboard.php).
 *
 * La lógica está en src/Panel/, el HTML en panel/vistas/ y el JavaScript en
 * assets/js/panel.js. Estilos: Tailwind compilado (assets/css/tailwind.build.css);
 * si agrega clases nuevas ejecute: npm run build:css
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

use Rubicam\Http\Respuesta;
use Rubicam\Http\Solicitud;
use Rubicam\Panel\Despachador;
use Rubicam\Panel\Ensamblador;
use Rubicam\Seguridad\CabecerasSeguras;
use Rubicam\Seguridad\SesionPanel;

require_once dirname(__DIR__) . '/config/arranque.php';

$solicitud = Solicitud::desdeGlobales();
$accion    = $solicitud->consulta('action') ?? '';

if ($accion !== '') {
    (new Despachador())->atenderAccion($accion, $solicitud);

    return;
}

SesionPanel::iniciar();
if (!SesionPanel::autenticado()) {
    Respuesta::redireccion('index.php')->enviar();

    return;
}

CabecerasSeguras::enviar(CabecerasSeguras::panel());
$pagina = (new Ensamblador())->paginaDashboard()->datos();

require __DIR__ . '/vistas/dashboard.php';
