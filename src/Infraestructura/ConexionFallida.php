<?php

/**
 * src/Infraestructura/ConexionFallida.php — No se pudo conectar con la base de datos.
 * El mensaje es seguro para mostrar al usuario; el detalle técnico va solo al log.
 */

declare(strict_types=1);

namespace Rubicam\Infraestructura;

use RuntimeException;

final class ConexionFallida extends RuntimeException
{
}
