<?php

/**
 * src/Http/ErrorHttp.php
 * -----------------------------------------------------------------------
 * Error «esperado» de una petición (validación, permisos, no encontrado...).
 * Se lanza desde cualquier capa y el Ejecutor lo convierte en una respuesta
 * JSON con el código HTTP correspondiente. Reemplaza los `exit` repartidos
 * por el código heredado.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Http;

use RuntimeException;

final class ErrorHttp extends RuntimeException
{
    /**
     * @param array<string, mixed> $extra campos adicionales de la respuesta (p. ej. 'errors')
     */
    public function __construct(
        string $mensaje,
        private readonly int $codigoHttp = 400,
        private readonly array $extra = []
    ) {
        parent::__construct($mensaje, $codigoHttp);
    }

    public function codigoHttp(): int
    {
        return $this->codigoHttp;
    }

    /**
     * @return array<string, mixed>
     */
    public function extra(): array
    {
        return $this->extra;
    }
}
