<?php

/**
 * handlers/webhook_trigger.php
 * -----------------------------------------------------------------------
 * Actualiza el estado / bitácora de un DO y alerta a n8n.
 *
 * Petición : POST application/json con la cabecera X-CSRF-Token
 *            { "do_id": 2, "do_codigo": "CTG26IMP0841", "nuevo_estado": "Aceptada", "observaciones": "..." }
 * Respuesta: SIEMPRE JSON  { "status": "success" | "error", "message": "...", ... }
 *
 * Toda la lógica vive en Rubicam\Panel\ControladorEstado; este archivo solo es el punto de entrada.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

use Rubicam\Http\Solicitud;
use Rubicam\Panel\Despachador;

require_once dirname(__DIR__) . '/config/arranque.php';

(new Despachador())->cambiarEstado(Solicitud::desdeGlobales());
