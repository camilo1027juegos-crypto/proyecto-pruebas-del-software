<?php

/**
 * tools/ReglasPeligrosas.php
 * -----------------------------------------------------------------------
 * Reglas del escáner sobre funciones y construcciones peligrosas
 * (SEG-001, 002, 003, 006, 007 y 008). Cada regla recibe la lista de tokens
 * y la posición actual y devuelve [regla, severidad, mensaje] o null.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Herramientas;

use PhpToken;

final class ReglasPeligrosas
{
    private const FUNCIONES_EVAL    = ['create_function'];
    private const FUNCIONES_COMANDO = ['exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open', 'pcntl_exec'];
    private const FUNCIONES_DEBILES = ['md5', 'sha1', 'rand', 'mt_rand'];
    private const OPCIONES_SSL      = ['CURLOPT_SSL_VERIFYPEER', 'CURLOPT_SSL_VERIFYHOST'];
    private const INCLUSIONES       = [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE];

    /**
     * @param list<PhpToken> $tokens
     * @return list<array{string, string, string}>
     */
    public static function evaluar(array $tokens, int $posicion): array
    {
        $resultados = [
            self::ejecucionDinamica($tokens, $posicion),
            self::comandosDelSistema($tokens, $posicion),
            self::comillasInvertidas($tokens, $posicion),
            self::deserializacion($tokens, $posicion),
            self::sslDesactivado($tokens, $posicion),
            self::inclusionVariable($tokens, $posicion),
            self::funcionesDebiles($tokens, $posicion),
        ];

        return array_values(array_filter($resultados));
    }

    /**
     * @param list<PhpToken> $tokens
     * @return array{string, string, string}|null
     */
    private static function ejecucionDinamica(array $tokens, int $posicion): ?array
    {
        $esEval = $tokens[$posicion]->id === T_EVAL
            || AyudantesTokens::esLlamada($tokens, $posicion, self::FUNCIONES_EVAL);

        return $esEval ? ['SEG-001', 'alto', 'Ejecución de código dinámico (eval / create_function).'] : null;
    }

    /**
     * @param list<PhpToken> $tokens
     * @return array{string, string, string}|null
     */
    private static function comandosDelSistema(array $tokens, int $posicion): ?array
    {
        return AyudantesTokens::esLlamada($tokens, $posicion, self::FUNCIONES_COMANDO)
            ? ['SEG-002', 'alto', 'Ejecución de comandos del sistema: ' . $tokens[$posicion]->text . '().']
            : null;
    }

    /**
     * @param list<PhpToken> $tokens
     * @return array{string, string, string}|null
     */
    private static function comillasInvertidas(array $tokens, int $posicion): ?array
    {
        if ($tokens[$posicion]->text !== '`') {
            return null;
        }

        $previas = array_filter(
            array_slice($tokens, 0, $posicion),
            static fn (PhpToken $token): bool => $token->text === '`'
        );

        return count($previas) % 2 === 0
            ? ['SEG-002', 'alto', 'Ejecución de comandos con comillas invertidas.']
            : null;
    }

    /**
     * @param list<PhpToken> $tokens
     * @return array{string, string, string}|null
     */
    private static function deserializacion(array $tokens, int $posicion): ?array
    {
        return AyudantesTokens::esLlamada($tokens, $posicion, ['unserialize'])
            ? ['SEG-003', 'medio', 'unserialize() con datos no confiables permite inyección de objetos; use JSON.']
            : null;
    }

    /**
     * @param list<PhpToken> $tokens
     * @return array{string, string, string}|null
     */
    private static function sslDesactivado(array $tokens, int $posicion): ?array
    {
        $token = $tokens[$posicion];
        if ($token->id !== T_STRING || !in_array($token->text, self::OPCIONES_SSL, true)) {
            return null;
        }

        $valor = strtolower(AyudantesTokens::textoEn($tokens, $posicion + 2));

        return in_array($valor, ['false', '0'], true)
            ? ['SEG-006', 'alto', 'Verificación SSL desactivada (' . $token->text . '): expone a intermediarios.']
            : null;
    }

    /**
     * @param list<PhpToken> $tokens
     * @return array{string, string, string}|null
     */
    private static function inclusionVariable(array $tokens, int $posicion): ?array
    {
        if (!in_array($tokens[$posicion]->id, self::INCLUSIONES, true)) {
            return null;
        }

        $total = count($tokens);
        for ($indice = $posicion + 1; $indice < $total && $tokens[$indice]->text !== ';'; $indice++) {
            if ($tokens[$indice]->id === T_VARIABLE) {
                return ['SEG-007', 'medio', 'include/require con ruta variable: riesgo de inclusión de archivos.'];
            }
        }

        return null;
    }

    /**
     * @param list<PhpToken> $tokens
     * @return array{string, string, string}|null
     */
    private static function funcionesDebiles(array $tokens, int $posicion): ?array
    {
        return AyudantesTokens::esLlamada($tokens, $posicion, self::FUNCIONES_DEBILES)
            ? ['SEG-008', 'bajo', $tokens[$posicion]->text . '() no sirve para claves ni tokens: use random_bytes.']
            : null;
    }
}
