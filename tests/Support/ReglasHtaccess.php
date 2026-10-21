<?php

/**
 * Intérprete mínimo de un .htaccess: reconoce solo las dos formas que usa el proyecto para
 * negar acceso, de modo que una prueba unitaria pueda comprobar QUÉ rutas quedan bloqueadas
 * sin necesitar un servidor Apache.
 *
 *   RewriteRule ^carpeta/ - [F,NC,L]          → deniega las rutas que cumplan el patrón
 *   <FilesMatch "regex"> Require all denied   → deniega los archivos cuyo nombre cumpla la regex
 */

declare(strict_types=1);

namespace Rubicam\Tests\Support;

use RuntimeException;

final class ReglasHtaccess
{
    /**
     * @param list<string> $patronesRuta   expresiones PCRE completas (con delimitadores y banderas)
     * @param list<string> $patronesNombre expresiones PCRE completas para el nombre del archivo
     */
    private function __construct(
        private readonly array $patronesRuta,
        private readonly array $patronesNombre
    ) {
    }

    public static function desdeArchivo(string $ruta): self
    {
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            throw new RuntimeException("No se pudo leer {$ruta}");
        }

        $patronesRuta = [];
        $patronesNombre = [];
        $patronPendiente = null;

        foreach (preg_split('/\R/', $contenido) ?: [] as $linea) {
            $linea = trim($linea);

            if (preg_match('/^RewriteRule\s+(\S+)\s+-\s+\[([^\]]*)\]/i', $linea, $coincidencia) === 1) {
                $banderas = array_map('strtoupper', explode(',', $coincidencia[2]));
                if (in_array('F', $banderas, true)) {
                    $modificador = in_array('NC', $banderas, true) ? 'i' : '';
                    $patronesRuta[] = '~' . $coincidencia[1] . '~' . $modificador;
                }
                continue;
            }
            if (preg_match('/^<FilesMatch\s+"([^"]+)">/i', $linea, $coincidencia) === 1) {
                $patronPendiente = '~' . $coincidencia[1] . '~';
                continue;
            }
            if ($patronPendiente !== null && preg_match('/^Require\s+all\s+denied/i', $linea) === 1) {
                $patronesNombre[] = $patronPendiente;
            }
            if (stripos($linea, '</FilesMatch>') === 0) {
                $patronPendiente = null;
            }
        }

        return new self($patronesRuta, $patronesNombre);
    }

    /** ¿Apache respondería 403 para esta ruta relativa a la raíz (sin «/» inicial)? */
    public function deniega(string $rutaRelativa): bool
    {
        foreach ($this->patronesRuta as $patron) {
            if (preg_match($patron, $rutaRelativa) === 1) {
                return true;
            }
        }
        $nombre = basename($rutaRelativa);
        foreach ($this->patronesNombre as $patron) {
            if (preg_match($patron, $nombre) === 1) {
                return true;
            }
        }

        return false;
    }
}
