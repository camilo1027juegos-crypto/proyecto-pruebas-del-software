<?php

/**
 * src/Vistas/ayudantes.php — Funciones auxiliares para las plantillas HTML (vistas).
 *
 * Se cargan con require_once desde cada vista. Solo contienen presentación:
 * ninguna consulta, ninguna regla de negocio.
 */

declare(strict_types=1);

use Rubicam\Seguridad\Sanitizador;
use Rubicam\Vistas\Iconos;

/** Escapa texto para HTML (equivalente a e() en otros frameworks). */
function h(?string $valor): string
{
    return Sanitizador::html($valor);
}

/** Ícono SVG del panel por nombre (ver Iconos::TRAZOS). */
function icono(string $nombre, string $clases = 'h-4 w-4'): string
{
    return Iconos::svg($nombre, $clases);
}
