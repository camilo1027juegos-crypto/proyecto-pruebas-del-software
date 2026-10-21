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
    private const ORDEN_SEVERIDAD = ['bajo' => 1, 'medio' => 2, 'alto' => 3];

    private const FUNCIONES_EVAL      = ['create_function'];
    private const FUNCIONES_COMANDO   = ['exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open', 'pcntl_exec'];
    private const METODOS_SQL         = ['query', 'exec', 'prepare'];
    private const FUNCIONES_DEBILES   = ['md5', 'sha1', 'rand', 'mt_rand'];
    private const OPCIONES_SSL        = ['CURLOPT_SSL_VERIFYPEER', 'CURLOPT_SSL_VERIFYHOST'];
    private const ENTRADAS            = ['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_FILES'];
    private const NOMBRE_SECRETO      = '/(pass(word|wd)?|secret|token|api[_-]?key|clave|credential)/i';
    private const MARCA_SUPRESION     = '/seguridad:ignorar\s+(SEG-\d{3})/';
    private const CARPETAS_IGNORADAS  = ['/vendor/', '/node_modules/', '/.git/'];

    /** Reglas que se evalúan sobre cada token, en este orden. */
    private const REGLAS = [
        'reglaEval', 'reglaComandos', 'reglaComillasInvertidas', 'reglaUnserialize', 'reglaSql',
        'reglaCredenciales', 'reglaSsl', 'reglaInclusion', 'reglaFuncionesDebiles',
    ];

    /**
     * @param list<string> $archivosConEntradas archivos autorizados a leer $_GET/$_POST (p. ej. Solicitud.php)
     * @return list<Hallazgo>
     */
    public static function escanearCodigo(string $codigo, string $archivo, array $archivosConEntradas = []): array
    {
        $todos        = PhpToken::tokenize($codigo);
        $significados = array_values(array_filter($todos, static fn (PhpToken $t): bool => !$t->isIgnorable()));
        $suprimidos   = self::lineasSuprimidas($todos);
        $permiteEntradas = self::terminaEnAlguno($archivo, $archivosConEntradas);

        $hallazgos = [];
        foreach ($significados as $posicion => $token) {
            $encontrados = self::reglasDelToken($significados, $posicion);
            if (!$permiteEntradas && self::esEntradaDelUsuario($token)) {
                $encontrados[] = ['SEG-009', 'medio', 'Lectura directa de ' . $token->text . ': use Solicitud.'];
            }
            foreach ($encontrados as [$regla, $severidad, $mensaje]) {
                if (!isset($suprimidos[$regla][$token->line])) {
                    $hallazgos[] = new Hallazgo($regla, $severidad, $archivo, $token->line, $mensaje);
                }
            }
        }

        usort($hallazgos, static fn (Hallazgo $a, Hallazgo $b): int => [$a->linea, $a->regla] <=> [$b->linea, $b->regla]);

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
    // Reglas (cada una recibe los tokens y la posición actual)
    // ------------------------------------------------------------------

    /**
     * @param list<PhpToken> $t
     * @return list<array{string, string, string}>
     */
    private static function reglasDelToken(array $t, int $i): array
    {
        $encontrados = [];
        foreach (self::REGLAS as $regla) {
            $resultado = self::$regla($t, $i);
            if ($resultado !== null) {
                $encontrados[] = $resultado;
            }
        }

        return $encontrados;
    }

    /** @param list<PhpToken> $t */
    private static function reglaEval(array $t, int $i): ?array
    {
        if ($t[$i]->id === T_EVAL || self::esLlamada($t, $i, self::FUNCIONES_EVAL)) {
            return ['SEG-001', 'alto', 'Ejecución de código dinámico (eval / create_function).'];
        }

        return null;
    }

    /** @param list<PhpToken> $t */
    private static function reglaComandos(array $t, int $i): ?array
    {
        return self::esLlamada($t, $i, self::FUNCIONES_COMANDO)
            ? ['SEG-002', 'alto', 'Ejecución de comandos del sistema: ' . $t[$i]->text . '().']
            : null;
    }

    /** @param list<PhpToken> $t */
    private static function reglaComillasInvertidas(array $t, int $i): ?array
    {
        if ($t[$i]->text !== '`') {
            return null;
        }
        $previas = count(array_filter(array_slice($t, 0, $i), static fn (PhpToken $x): bool => $x->text === '`'));

        return $previas % 2 === 0 ? ['SEG-002', 'alto', 'Ejecución de comandos con comillas invertidas.'] : null;
    }

    /** @param list<PhpToken> $t */
    private static function reglaUnserialize(array $t, int $i): ?array
    {
        return self::esLlamada($t, $i, ['unserialize'])
            ? ['SEG-003', 'medio', 'unserialize() con datos no confiables permite inyección de objetos; use JSON.']
            : null;
    }

    /** @param list<PhpToken> $t */
    private static function reglaSql(array $t, int $i): ?array
    {
        $esMetodo = $t[$i]->id === T_STRING
            && in_array(strtolower($t[$i]->text), self::METODOS_SQL, true)
            && in_array($t[max(0, $i - 1)]->text, ['->', '?->'], true)
            && ($t[$i + 1]->text ?? '') === '(';
        if (!$esMetodo) {
            return null;
        }

        $argumentos = self::argumentos($t, $i + 1);
        $textos     = array_map(static fn (PhpToken $x): string => $x->text, $argumentos);
        $concatena  = in_array('.', $textos, true);
        $variables  = count(array_filter($argumentos, static fn (PhpToken $x): bool => $x->id === T_VARIABLE)) > 0;

        return ($concatena || $variables)
            ? ['SEG-004', 'medio', 'SQL armado con variables o concatenación: use consultas preparadas con parámetros.']
            : null;
    }

    /** @param list<PhpToken> $t */
    private static function reglaCredenciales(array $t, int $i): ?array
    {
        $nombre = self::nombreAsignado($t, $i);
        if ($nombre === null || preg_match(self::NOMBRE_SECRETO, $nombre) !== 1) {
            return null;
        }

        return ['SEG-005', 'alto', 'Credencial escrita en el código («' . $nombre . '»): léala del entorno (.env).'];
    }

    /** @param list<PhpToken> $t */
    private static function reglaSsl(array $t, int $i): ?array
    {
        if ($t[$i]->id !== T_STRING || !in_array($t[$i]->text, self::OPCIONES_SSL, true)) {
            return null;
        }
        $valor = strtolower($t[$i + 2]->text ?? '');

        return in_array($valor, ['false', '0'], true)
            ? ['SEG-006', 'alto', 'Verificación SSL desactivada (' . $t[$i]->text . '): expone a ataques de intermediario.']
            : null;
    }

    /** @param list<PhpToken> $t */
    private static function reglaInclusion(array $t, int $i): ?array
    {
        if (!in_array($t[$i]->id, [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)) {
            return null;
        }
        for ($j = $i + 1; $j < count($t) && $t[$j]->text !== ';'; $j++) {
            if ($t[$j]->id === T_VARIABLE) {
                return ['SEG-007', 'medio', 'include/require con ruta variable: riesgo de inclusión de archivos.'];
            }
        }

        return null;
    }

    /** @param list<PhpToken> $t */
    private static function reglaFuncionesDebiles(array $t, int $i): ?array
    {
        return self::esLlamada($t, $i, self::FUNCIONES_DEBILES)
            ? ['SEG-008', 'bajo', $t[$i]->text . '() no es adecuada para contraseñas ni tokens: use password_hash / random_bytes.']
            : null;
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    private static function esEntradaDelUsuario(PhpToken $token): bool
    {
        return $token->id === T_VARIABLE && in_array($token->text, self::ENTRADAS, true);
    }

    /**
     * ¿Es la llamada a una función global (no un método, ni su declaración)?
     *
     * @param list<PhpToken> $t
     * @param list<string>   $nombres
     */
    private static function esLlamada(array $t, int $i, array $nombres): bool
    {
        if ($t[$i]->id !== T_STRING || !in_array(strtolower($t[$i]->text), $nombres, true)) {
            return false;
        }
        $anterior = $t[max(0, $i - 1)];
        $esAcceso = in_array($anterior->text, ['->', '?->', '::'], true) || $anterior->id === T_FUNCTION;

        return !$esAcceso && ($t[$i + 1]->text ?? '') === '(';
    }

    /**
     * Tokens entre el paréntesis que abre en $abre y su pareja.
     *
     * @param list<PhpToken> $t
     * @return list<PhpToken>
     */
    private static function argumentos(array $t, int $abre): array
    {
        $profundidad = 0;
        $dentro      = [];
        for ($j = $abre; $j < count($t); $j++) {
            $profundidad += ($t[$j]->text === '(') ? 1 : (($t[$j]->text === ')') ? -1 : 0);
            if ($profundidad === 0) {
                break;
            }
            if ($j > $abre) {
                $dentro[] = $t[$j];
            }
        }

        return $dentro;
    }

    /**
     * Si en la posición hay «nombre = 'literal'», «'nombre' => 'literal'» o
     * define('NOMBRE', 'literal') con un literal no vacío, devuelve el nombre.
     *
     * @param list<PhpToken> $t
     */
    private static function nombreAsignado(array $t, int $i): ?string
    {
        $actual = $t[$i];
        $nombre = match (true) {
            $actual->id === T_VARIABLE && ($t[$i + 1]->text ?? '') === '='        => [ltrim($actual->text, '$'), $i + 2],
            $actual->id === T_CONSTANT_ENCAPSED_STRING && ($t[$i + 1]->id ?? 0) === T_DOUBLE_ARROW
                                                                                   => [self::sinComillas($actual->text), $i + 2],
            self::esLlamada($t, $i, ['define']) && ($t[$i + 3]->text ?? '') === ',' => [self::sinComillas($t[$i + 2]->text ?? ''), $i + 4],
            default                                                                => null,
        };
        if ($nombre === null) {
            return null;
        }

        $valor = $t[$nombre[1]] ?? null;

        return ($valor !== null && $valor->id === T_CONSTANT_ENCAPSED_STRING && self::sinComillas($valor->text) !== '')
            ? $nombre[0]
            : null;
    }

    private static function sinComillas(string $literal): string
    {
        return strlen($literal) >= 2 ? substr($literal, 1, -1) : $literal;
    }

    /**
     * @param list<PhpToken> $todos
     * @return array<string, array<int, true>> regla → líneas donde está suprimida
     */
    private static function lineasSuprimidas(array $todos): array
    {
        $suprimidas = [];
        foreach ($todos as $token) {
            if (($token->id !== T_COMMENT && $token->id !== T_DOC_COMMENT)
                || preg_match_all(self::MARCA_SUPRESION, $token->text, $coincidencias) === 0) {
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
        $recorrido = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta, RecursiveDirectoryIterator::SKIP_DOTS));
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
