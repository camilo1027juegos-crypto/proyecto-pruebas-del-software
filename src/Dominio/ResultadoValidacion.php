<?php

/**
 * src/Dominio/ResultadoValidacion.php — Resultado de validar un formulario: datos normalizados + errores por campo.
 */

declare(strict_types=1);

namespace Rubicam\Dominio;

final class ResultadoValidacion
{
    /**
     * @param array<string, mixed>  $datos   valores ya limpios y normalizados
     * @param array<string, string> $errores mensaje de error por nombre de campo
     */
    public function __construct(private readonly array $datos, private readonly array $errores)
    {
    }

    public function esValido(): bool
    {
        return $this->errores === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return $this->datos;
    }

    /**
     * @return array<string, string>
     */
    public function errores(): array
    {
        return $this->errores;
    }
}
