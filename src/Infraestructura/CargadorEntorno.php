<?php

/**
 * src/Infraestructura/CargadorEntorno.php
 * -----------------------------------------------------------------------
 * Lee un archivo «.env» (CLAVE=valor) y lo publica como variables de entorno,
 * para configurar la aplicación (base de datos, webhook, carpeta de cargas)
 * sin tocar el código y sin versionar secretos.
 *
 * Regla de precedencia: lo que ya define el servidor MANDA sobre el archivo.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Infraestructura;

final class CargadorEntorno
{
    private const NOMBRE_VALIDO = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * @return array<string, string>
     */
    public static function parsear(string $contenido): array
    {
        $variables = [];
        foreach (preg_split('/\R/', $contenido) ?: [] as $linea) {
            $par = self::interpretarLinea($linea);
            if ($par !== null) {
                $variables[$par[0]] = $par[1];
            }
        }

        return $variables;
    }

    /**
     * Define las variables del archivo que el servidor todavía no tiene.
     *
     * @return int cantidad de variables definidas (0 si el archivo no existe)
     */
    public static function cargar(string $ruta): int
    {
        if (!is_file($ruta) || !is_readable($ruta)) {
            return 0;
        }

        $definidas = 0;
        foreach (self::parsear((string) file_get_contents($ruta)) as $clave => $valor) {
            if (getenv($clave) === false) {
                putenv($clave . '=' . $valor);
                $definidas++;
            }
        }

        return $definidas;
    }

    /**
     * @return array{string, string}|null null si la línea no es una asignación válida
     */
    private static function interpretarLinea(string $linea): ?array
    {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#') {
            return null;
        }
        if (str_starts_with($linea, 'export ')) {
            $linea = ltrim(substr($linea, 7));
        }

        $posicion = strpos($linea, '=');
        if ($posicion === false) {
            return null;
        }

        $clave = trim(substr($linea, 0, $posicion));
        if (preg_match(self::NOMBRE_VALIDO, $clave) !== 1) {
            return null;
        }

        return [$clave, self::interpretarValor(trim(substr($linea, $posicion + 1)))];
    }

    private static function interpretarValor(string $valor): string
    {
        $comilla = $valor[0] ?? '';
        if ($comilla === '"' || $comilla === "'") {
            $cierre = strpos($valor, $comilla, 1);
            if ($cierre !== false) {
                return substr($valor, 1, $cierre - 1);
            }
        }

        return rtrim((string) preg_replace('/\s+#.*$/', '', $valor));
    }
}
