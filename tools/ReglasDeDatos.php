<?php

/**
 * tools/ReglasDeDatos.php
 * -----------------------------------------------------------------------
 * Reglas del escáner sobre el manejo de datos: SQL armado con variables
 * (SEG-004) y credenciales escritas en el código (SEG-005).
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Herramientas;

use PhpToken;

final class ReglasDeDatos
{
    private const METODOS_SQL    = ['query', 'exec', 'prepare'];
    private const NOMBRE_SECRETO = '/(pass(word|wd)?|secret|token|api[_-]?key|clave|credential)/i';

    /**
     * @param list<PhpToken> $tokens
     * @return list<array{string, string, string}>
     */
    public static function evaluar(array $tokens, int $posicion): array
    {
        return array_values(array_filter([
            self::sqlConVariables($tokens, $posicion),
            self::credencialEnElCodigo($tokens, $posicion),
        ]));
    }

    /**
     * @param list<PhpToken> $tokens
     * @return array{string, string, string}|null
     */
    private static function sqlConVariables(array $tokens, int $posicion): ?array
    {
        if (!self::esMetodoSql($tokens, $posicion)) {
            return null;
        }

        $argumentos = AyudantesTokens::argumentos($tokens, $posicion + 1);
        $concatena  = in_array('.', array_map(static fn (PhpToken $token): string => $token->text, $argumentos), true);
        $variables  = array_filter($argumentos, static fn (PhpToken $token): bool => $token->id === T_VARIABLE);

        return ($concatena || $variables !== [])
            ? ['SEG-004', 'medio', 'SQL armado con variables o concatenación: use consultas preparadas.']
            : null;
    }

    /** @param list<PhpToken> $tokens */
    private static function esMetodoSql(array $tokens, int $posicion): bool
    {
        $token = $tokens[$posicion];

        return $token->id === T_STRING
            && in_array(strtolower($token->text), self::METODOS_SQL, true)
            && in_array(AyudantesTokens::textoEn($tokens, $posicion - 1), ['->', '?->'], true)
            && AyudantesTokens::textoEn($tokens, $posicion + 1) === '(';
    }

    /**
     * @param list<PhpToken> $tokens
     * @return array{string, string, string}|null
     */
    private static function credencialEnElCodigo(array $tokens, int $posicion): ?array
    {
        $nombre = self::nombreAsignado($tokens, $posicion);
        if ($nombre === null || preg_match(self::NOMBRE_SECRETO, $nombre) !== 1) {
            return null;
        }

        return ['SEG-005', 'alto', 'Credencial escrita en el código («' . $nombre . '»): léala del entorno (.env).'];
    }

    /**
     * Si en la posición hay «$nombre = 'literal'», «'nombre' => 'literal'» o
     * define('NOMBRE', 'literal') con un literal no vacío, devuelve el nombre.
     *
     * @param list<PhpToken> $tokens
     */
    private static function nombreAsignado(array $tokens, int $posicion): ?string
    {
        $token    = $tokens[$posicion];
        $siguiente = AyudantesTokens::textoEn($tokens, $posicion + 1);

        [$nombre, $posicionValor] = match (true) {
            $token->id === T_VARIABLE && $siguiente === '='
                => [ltrim($token->text, '$'), $posicion + 2],
            $token->id === T_CONSTANT_ENCAPSED_STRING
                && AyudantesTokens::idEn($tokens, $posicion + 1) === T_DOUBLE_ARROW
                => [AyudantesTokens::sinComillas($token->text), $posicion + 2],
            AyudantesTokens::esLlamada($tokens, $posicion, ['define'])
                && AyudantesTokens::textoEn($tokens, $posicion + 3) === ','
                => [AyudantesTokens::sinComillas(AyudantesTokens::textoEn($tokens, $posicion + 2)), $posicion + 4],
            default => [null, 0],
        };

        $esSecretoLiteral = $nombre !== null && self::esLiteralNoVacio($tokens, $posicionValor);

        return $esSecretoLiteral ? $nombre : null;
    }

    /** @param list<PhpToken> $tokens */
    private static function esLiteralNoVacio(array $tokens, int $posicion): bool
    {
        return AyudantesTokens::idEn($tokens, $posicion) === T_CONSTANT_ENCAPSED_STRING
            && AyudantesTokens::sinComillas(AyudantesTokens::textoEn($tokens, $posicion)) !== '';
    }
}
