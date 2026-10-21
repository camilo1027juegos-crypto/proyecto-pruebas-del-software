<?php

/**
 * src/Http/SolicitudJson.php — Lectura defensiva del cuerpo JSON de una petición.
 */

declare(strict_types=1);

namespace Rubicam\Http;

use JsonException;

final class SolicitudJson
{
    public const MAX_BYTES = 65536;
    public const PROFUNDIDAD_MAXIMA = 16;

    /**
     * @return array<mixed>
     * @throws ErrorHttp 400 si está vacío, mal formado o no es un objeto/lista; 413 si es demasiado grande.
     */
    public static function decodificar(string $crudo): array
    {
        if ($crudo === '') {
            throw new ErrorHttp('La solicitud llegó vacía.', 400);
        }
        if (strlen($crudo) > self::MAX_BYTES) {
            throw new ErrorHttp('La solicitud es demasiado grande.', 413);
        }

        try {
            $datos = json_decode($crudo, true, self::PROFUNDIDAD_MAXIMA, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ErrorHttp('El cuerpo de la solicitud no es un JSON válido.', 400);
        }

        if (!is_array($datos)) {
            throw new ErrorHttp('Formato de solicitud inválido.', 400);
        }

        return $datos;
    }

    /** Lee y decodifica el cuerpo de la petición actual (php://input). */
    public static function desdeEntrada(): array
    {
        return self::decodificar((string) file_get_contents('php://input'));
    }
}
