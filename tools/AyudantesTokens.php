<?php

/**
 * tools/AyudantesTokens.php — Consultas pequeñas sobre la lista de tokens de PHP.
 */

declare(strict_types=1);

namespace Rubicam\Herramientas;

use PhpToken;

final class AyudantesTokens
{
    /**
     * ¿Es la llamada a una función global (no un método, ni su declaración)?
     *
     * @param list<PhpToken> $tokens
     * @param list<string>   $nombres en minúsculas
     */
    public static function esLlamada(array $tokens, int $posicion, array $nombres): bool
    {
        $token = $tokens[$posicion];
        if ($token->id !== T_STRING || !in_array(strtolower($token->text), $nombres, true)) {
            return false;
        }

        $anterior = $tokens[max(0, $posicion - 1)];
        $esAcceso = in_array($anterior->text, ['->', '?->', '::'], true) || $anterior->id === T_FUNCTION;

        return !$esAcceso && self::textoEn($tokens, $posicion + 1) === '(';
    }

    /**
     * Tokens que hay entre el paréntesis que abre en $abre y su pareja.
     *
     * @param list<PhpToken> $tokens
     * @return list<PhpToken>
     */
    public static function argumentos(array $tokens, int $abre): array
    {
        $profundidad = 0;
        $dentro      = [];
        $total       = count($tokens);
        for ($indice = $abre; $indice < $total; $indice++) {
            $profundidad += self::cambioDeProfundidad($tokens[$indice]->text);
            if ($profundidad === 0) {
                break;
            }
            if ($indice > $abre) {
                $dentro[] = $tokens[$indice];
            }
        }

        return $dentro;
    }

    /** @param list<PhpToken> $tokens */
    public static function textoEn(array $tokens, int $posicion): string
    {
        return isset($tokens[$posicion]) ? $tokens[$posicion]->text : '';
    }

    /** @param list<PhpToken> $tokens */
    public static function idEn(array $tokens, int $posicion): int
    {
        return isset($tokens[$posicion]) ? $tokens[$posicion]->id : 0;
    }

    public static function sinComillas(string $literal): string
    {
        return strlen($literal) >= 2 ? substr($literal, 1, -1) : $literal;
    }

    private static function cambioDeProfundidad(string $texto): int
    {
        return match ($texto) {
            '('     => 1,
            ')'     => -1,
            default => 0,
        };
    }
}
