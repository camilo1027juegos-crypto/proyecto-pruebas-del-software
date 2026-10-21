<?php

/**
 * src/Dominio/Nit.php
 * -----------------------------------------------------------------------
 * Número de Identificación Tributaria (NIT) colombiano: cálculo del dígito de
 * verificación (módulo 11 de la DIAN) y normalización a «base-dv».
 *
 * Codificación defensiva (MR-004): toda entrada que no sea un NIT se rechaza
 * con una excepción en lugar de producir un dígito «inventado».
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Dominio;

use InvalidArgumentException;

final class Nit
{
    /** Pesos oficiales de la DIAN, del dígito menos significativo al más significativo. */
    private const PESOS = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

    private const MAX_DIGITOS_BASE = 15;
    private const MIN_DIGITOS_BASE_USUARIO = 5;

    /**
     * @throws InvalidArgumentException si la base no son entre 1 y 15 dígitos numéricos.
     */
    public static function digitoVerificacion(string $base): int
    {
        if (!preg_match('/^\d{1,' . self::MAX_DIGITOS_BASE . '}$/', $base)) {
            throw new InvalidArgumentException('El NIT base debe tener entre 1 y 15 dígitos numéricos.');
        }

        $suma = 0;
        foreach (str_split(strrev($base)) as $posicion => $digito) {
            $suma += (int) $digito * self::PESOS[$posicion];
        }

        $residuo = $suma % 11;

        return $residuo > 1 ? 11 - $residuo : $residuo;
    }

    /**
     * Acepta «900123456», «900.123.456-8» o «900123456 - 8» y devuelve «900123456-8».
     *
     * @throws NitInvalido si el formato no es válido o el dígito de verificación no corresponde.
     */
    public static function normalizar(string $entrada): string
    {
        $limpio = (string) preg_replace('/[\s.]/', '', $entrada);
        $patron = '/^(\d{' . self::MIN_DIGITOS_BASE_USUARIO . ',' . self::MAX_DIGITOS_BASE . '})(?:-(\d))?$/';

        if (!preg_match($patron, $limpio, $partes)) {
            throw new NitInvalido(
                'NIT inválido. Use solo números, con o sin dígito de verificación (ej. 900123456-8).'
            );
        }

        $base     = $partes[1];
        $esperado = self::digitoVerificacion($base);

        if (isset($partes[2]) && (int) $partes[2] !== $esperado) {
            throw new NitInvalido("El dígito de verificación no corresponde: para {$base} es {$esperado}.");
        }

        return $base . '-' . $esperado;
    }
}
