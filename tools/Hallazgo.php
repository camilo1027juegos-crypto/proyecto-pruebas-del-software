<?php

/**
 * tools/Hallazgo.php — Un problema de seguridad encontrado por el escáner.
 */

declare(strict_types=1);

namespace Rubicam\Herramientas;

final class Hallazgo
{
    public function __construct(
        public readonly string $regla,
        public readonly string $severidad,
        public readonly string $archivo,
        public readonly int $linea,
        public readonly string $mensaje
    ) {
    }
}
