<?php

/**
 * tools/EscanerSeguridad.php
 * -----------------------------------------------------------------------
 * Escáner de seguridad estático propio. Es el equivalente en PHP de «bandit»
 * (Python): recorre los tokens del código (PhpToken) buscando patrones
 * peligrosos. Como trabaja con tokens y no con texto, una palabra dentro de un
 * comentario o de una cadena NO genera falsos positivos.
 *
 * Reglas:
 *   SEG-001 alto   eval / create_function
 *   SEG-002 alto   ejecución de comandos del sistema (exec, system, `...`)
 *   SEG-003 medio  unserialize (deserialización de datos no confiables)
 *   SEG-004 medio  SQL armado con variables o concatenación (inyección SQL)
 *   SEG-005 alto   credenciales escritas en el código
 *   SEG-006 alto   verificación SSL de cURL desactivada
 *   SEG-007 medio  include/require con ruta variable
 *   SEG-008 bajo   funciones débiles (md5, sha1, rand, mt_rand)
 *   SEG-009 medio  lectura directa de $_GET/$_POST/... fuera de Solicitud
 *
 * Supresión con motivo (como «# nosec» de bandit), en la misma línea o en la
 * anterior:    // seguridad:ignorar SEG-004 consulta armada solo con constantes
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Herramientas;

use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class EscanerSeguridad
{
    private const ORDEN_SEVERIDAD   = ['bajo' => 1, 'medio' => 2, 'alto' => 3];
    private const ENTRADAS           = ['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_FILES'];
    private const MARCA_SUPRESION    = '/seguridad:ignorar\s+(SEG-\d{3})/';
    private const CARPETAS_IGNORADAS = ['/vendor/', '/node_modules/', '/.git/'];

    /**
     * @param list<string> $archivosConEntradas archivos autorizados a leer $_GET/$_POST (p. ej. Solicitud.php)
     * @return list<Hallazgo>
     */
    public static function escanearCodigo(string $codigo, string $archivo, array $archivosConEntradas = []): array
    {
        $todos           = PhpToken::tokenize($codigo);
        $significativos  = array_values(
            array_filter($todos, static fn (PhpToken $token): bool => !$token->isIgnorable())
        );
        $suprimidos      = self::lineasSuprimidas($todos);
        $permiteEntradas = self::terminaEnAlguno($archivo, $archivosConEntradas);

        $hallazgos = [];
        foreach ($significativos as $posicion => $token) {
            $encontrados = self::reglasDelToken($significativos, $posicion);
            if (!$permiteEntradas && self::esEntradaDelUsuario($token)) {
                $encontrados[] = ['SEG-009', 'medio', 'Lectura directa de ' . $token->text . ': use Solicitud.'];
            }
            foreach ($encontrados as [$regla, $severidad, $mensaje]) {
                if (!isset($suprimidos[$regla][$token->line])) {
                    $hallazgos[] = new Hallazgo($regla, $severidad, $archivo, $token->line, $mensaje);
                }
            }
        }

        usort(
            $hallazgos,
            static fn (Hallazgo $primero, Hallazgo $segundo): int => [$primero->linea, $primero->regla]
                <=> [$segundo->linea, $segundo->regla]
        );

        return $hallazgos;
    }

    /**
     * @param list<string> $archivosConEntradas
     * @return list<Hallazgo>
     */
    public static function escanearRuta(string $ruta, array $archivosConEntradas = []): array
    {
        $hallazgos = [];
        foreach (self::archivosPhp($ruta) as $archivo) {
            $codigo = file_get_contents($archivo);
            if ($codigo !== false) {
                $hallazgos = array_merge($hallazgos, self::escanearCodigo($codigo, $archivo, $archivosConEntradas));
            }
        }

        return $hallazgos;
    }

    /**
     * @param list<Hallazgo> $hallazgos
     */
    public static function superaUmbral(array $hallazgos, string $umbral): bool
    {
        $minimo = self::ORDEN_SEVERIDAD[$umbral] ?? self::ORDEN_SEVERIDAD['medio'];
        foreach ($hallazgos as $hallazgo) {
            if ((self::ORDEN_SEVERIDAD[$hallazgo->severidad] ?? 0) >= $minimo) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Hallazgo> $hallazgos
     */
    public static function formatearTexto(array $hallazgos): string
    {
        if ($hallazgos === []) {
            return "Sin hallazgos de seguridad.\n";
        }

        $texto  = '';
        $cuenta = ['alto' => 0, 'medio' => 0, 'bajo' => 0];
        foreach ($hallazgos as $hallazgo) {
            $cuenta[$hallazgo->severidad] = ($cuenta[$hallazgo->severidad] ?? 0) + 1;
            $texto .= sprintf(
                "%s:%d  [%s] %s  %s\n",
                $hallazgo->archivo,
                $hallazgo->linea,
                strtoupper($hallazgo->severidad),
                $hallazgo->regla,
                $hallazgo->mensaje
            );
        }

        return $texto . sprintf(
            "\nTotal: %d hallazgo(s) — alto: %d, medio: %d, bajo: %d\n",
            count($hallazgos),
            $cuenta['alto'],
            $cuenta['medio'],
            $cuenta['bajo']
        );
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /**
     * @param list<PhpToken> $tokens
     * @return list<array{string, string, string}>
     */
    private static function reglasDelToken(array $tokens, int $posicion): array
    {
        return array_merge(
            ReglasPeligrosas::evaluar($tokens, $posicion),
            ReglasDeDatos::evaluar($tokens, $posicion)
        );
    }

    private static function esEntradaDelUsuario(PhpToken $token): bool
    {
        return $token->id === T_VARIABLE && in_array($token->text, self::ENTRADAS, true);
    }

    /**
     * @param list<PhpToken> $todos
     * @return array<string, array<int, true>> regla → líneas donde está suprimida
     */
    private static function lineasSuprimidas(array $todos): array
    {
        $suprimidas = [];
        foreach ($todos as $token) {
            if (
                ($token->id !== T_COMMENT && $token->id !== T_DOC_COMMENT)
                || preg_match_all(self::MARCA_SUPRESION, $token->text, $coincidencias) === 0
            ) {
                continue;
            }
            $ultimaLinea = $token->line + substr_count(rtrim($token->text), "\n");
            foreach ($coincidencias[1] as $regla) {
                $suprimidas[$regla][$token->line]       = true;
                $suprimidas[$regla][$ultimaLinea + 1]   = true;
            }
        }

        return $suprimidas;
    }

    /**
     * @param list<string> $sufijos
     */
    private static function terminaEnAlguno(string $archivo, array $sufijos): bool
    {
        foreach ($sufijos as $sufijo) {
            if (str_ends_with($archivo, $sufijo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function archivosPhp(string $ruta): array
    {
        if (is_file($ruta)) {
            return [$ruta];
        }
        if (!is_dir($ruta)) {
            return [];
        }

        $archivos  = [];
        $recorrido = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($ruta, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($recorrido as $archivo) {
            $camino = $archivo->getPathname();
            if ($archivo->getExtension() === 'php' && !self::estaIgnorado($camino)) {
                $archivos[] = $camino;
            }
        }
        sort($archivos);

        return $archivos;
    }

    private static function estaIgnorado(string $camino): bool
    {
        foreach (self::CARPETAS_IGNORADAS as $carpeta) {
            if (str_contains($camino, $carpeta)) {
                return true;
            }
        }

        return false;
    }
}
