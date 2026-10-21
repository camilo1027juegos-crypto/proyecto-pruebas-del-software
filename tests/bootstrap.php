<?php

/**
 * tests/bootstrap.php — Carga el autocargador de la app y el de las pruebas/herramientas.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

spl_autoload_register(static function (string $clase): void {
    $mapa = [
        'Rubicam\\Tests\\'        => __DIR__ . '/',
        'Rubicam\\Herramientas\\' => dirname(__DIR__) . '/tools/',
    ];
    foreach ($mapa as $prefijo => $directorio) {
        if (str_starts_with($clase, $prefijo)) {
            $ruta = $directorio . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
            if (is_file($ruta)) {
                require $ruta;
            }
        }
    }
});
