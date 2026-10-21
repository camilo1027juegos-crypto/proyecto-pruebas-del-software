<?php

/**
 * tools/escanear_seguridad.php — Línea de comandos del escáner de seguridad estático.
 *
 * Uso:   php tools/escanear_seguridad.php [--umbral=bajo|medio|alto] [--formato=texto|json] [rutas...]
 *        composer seguridad
 *
 * Sin rutas, revisa el código de la aplicación (src, config, handlers, panel e index.php).
 * Termina con código 1 si hay hallazgos con severidad igual o mayor al umbral (por defecto «medio»);
 * así puede usarse como compuerta en CI.
 */

declare(strict_types=1);

use Rubicam\Herramientas\EscanerSeguridad;

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once __DIR__ . '/AyudantesTokens.php';
require_once __DIR__ . '/Hallazgo.php';
require_once __DIR__ . '/ReglasDeDatos.php';
require_once __DIR__ . '/ReglasPeligrosas.php';
require_once __DIR__ . '/EscanerSeguridad.php';

$raiz    = dirname(__DIR__);
$umbral  = 'medio';
$formato = 'texto';
$rutas   = [];

foreach (array_slice($argv, 1) as $argumento) {
    if (str_starts_with($argumento, '--umbral=')) {
        $umbral = substr($argumento, 9);
    } elseif (str_starts_with($argumento, '--formato=')) {
        $formato = substr($argumento, 10);
    } else {
        $rutas[] = $argumento;
    }
}

if (!in_array($umbral, ['bajo', 'medio', 'alto'], true) || !in_array($formato, ['texto', 'json'], true)) {
    fwrite(
        STDERR,
        "Uso: php tools/escanear_seguridad.php [--umbral=bajo|medio|alto] [--formato=texto|json] [rutas...]\n"
    );
    exit(2);
}

if ($rutas === []) {
    $rutas = array_map(
        static fn (string $relativa): string => $raiz . '/' . $relativa,
        ['src', 'config', 'handlers', 'panel', 'index.php', 'tools']
    );
}

// La clase Solicitud es el único lugar autorizado para leer $_GET, $_POST, $_COOKIE y $_FILES.
$archivosConEntradas = ['src/Http/Solicitud.php'];

$hallazgos = [];
foreach ($rutas as $ruta) {
    $hallazgos = array_merge($hallazgos, EscanerSeguridad::escanearRuta($ruta, $archivosConEntradas));
}

if ($formato === 'json') {
    echo json_encode(
        array_map('get_object_vars', $hallazgos),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), "\n";
} else {
    $raizConBarra = $raiz . '/';
    $relativos    = array_map(
        static fn ($hallazgo) => new Rubicam\Herramientas\Hallazgo(
            $hallazgo->regla,
            $hallazgo->severidad,
            str_replace($raizConBarra, '', $hallazgo->archivo),
            $hallazgo->linea,
            $hallazgo->mensaje
        ),
        $hallazgos
    );
    echo EscanerSeguridad::formatearTexto($relativos);
}

exit(EscanerSeguridad::superaUmbral($hallazgos, $umbral) ? 1 : 0);
