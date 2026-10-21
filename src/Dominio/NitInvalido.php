<?php

/**
 * src/Dominio/NitInvalido.php — El texto recibido no es un NIT utilizable.
 */

declare(strict_types=1);

namespace Rubicam\Dominio;

use InvalidArgumentException;

final class NitInvalido extends InvalidArgumentException
{
}
