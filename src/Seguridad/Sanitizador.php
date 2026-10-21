<?php

/**
 * src/Seguridad/Sanitizador.php
 * -----------------------------------------------------------------------
 * Validación y limpieza de las entradas del usuario en la frontera del
 * sistema. Regla general: lo que no cumple el contrato se RECHAZA (null);
 * nunca se «repara» en silencio, porque reparar cambia el significado.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Seguridad;

final class Sanitizador
{
    /** Un teléfono real tiene al menos 7 dígitos (MR-002: antes se contaban caracteres, no dígitos). */
    public const TELEFONO_MIN_DIGITOS = 7;
    public const TELEFONO_MAX_CARACTERES = 20;

    /** Importe máximo aceptado (USD): 999.999.999.999,99. */
    public const IMPORTE_MAXIMO = 999999999999.99;

    /** Texto libre: sin espacios en los bordes, sin etiquetas HTML y sin caracteres de control. */
    public static function texto(?string $valor): string
    {
        $sinEtiquetas = strip_tags(trim($valor ?? ''));

        return preg_replace('/[\x00-\x1F\x7F]/u', '', $sinEtiquetas) ?? '';
    }

    /** Correo válido o null. Cualquier carácter de control (p. ej. CRLF de inyección de cabeceras) lo invalida. */
    public static function correo(?string $valor): ?string
    {
        $correo = trim($valor ?? '');
        if ($correo === '' || preg_match('/[\x00-\x1F\x7F]/', $correo)) {
            return null;
        }

        return filter_var($correo, FILTER_VALIDATE_EMAIL) !== false ? $correo : null;
    }

    /**
     * Teléfono válido o null: solo dígitos, «+», «-», espacios y paréntesis, con al menos
     * TELEFONO_MIN_DIGITOS dígitos reales ('-------' cumple el juego de caracteres pero no es un teléfono).
     */
    public static function telefono(?string $valor, int $maxCaracteres = self::TELEFONO_MAX_CARACTERES): ?string
    {
        $telefono = trim($valor ?? '');
        $patron   = '/^[0-9+\-\s()]{' . self::TELEFONO_MIN_DIGITOS . ',' . $maxCaracteres . '}$/D';

        if (!preg_match($patron, $telefono)) {
            return null;
        }

        return preg_match_all('/\d/', $telefono) >= self::TELEFONO_MIN_DIGITOS ? $telefono : null;
    }

    /**
     * Importe decimal estricto: mismo contrato que valida el navegador (/^\d+(\.\d{1,2})?$/).
     * Rechaza notación científica, hexadecimal, negativos e infinito (MR-001: is_numeric('1e999') daba INF).
     */
    public static function importe(?string $valor): ?float
    {
        $texto = trim($valor ?? '');
        if (!preg_match('/^\d+(\.\d{1,2})?$/D', $texto)) {
            return null;
        }

        $importe = (float) $texto;

        return $importe <= self::IMPORTE_MAXIMO ? $importe : null;
    }

    /** Escapa texto para insertarlo en HTML. */
    public static function html(?string $valor): string
    {
        return htmlspecialchars($valor ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
