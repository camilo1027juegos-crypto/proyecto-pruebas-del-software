<?php

/**
 * handlers/upload_documento.php
 * -----------------------------------------------------------------------
 * Recibe un documento del legajo aduanero y lo asocia a un DO.
 *
 * Petición : POST multipart/form-data con la cabecera X-CSRF-Token
 *            Campos: do_id, tipo_documento, documento (archivo)
 * Respuesta: SIEMPRE JSON  { "status": "success" | "error", "message": "...", "data": {...} }
 *
 * Toda la lógica vive en Rubicam\Panel\ControladorDocumentos; este archivo solo es el punto de entrada.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

use Rubicam\Http\Solicitud;
use Rubicam\Panel\Despachador;

require_once dirname(__DIR__) . '/config/config_db.php';

(new Despachador())->subirDocumento(Solicitud::desdeGlobales());
