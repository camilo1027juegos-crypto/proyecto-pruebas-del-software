<?php

/**
 * src/bootstrap.php
 * -----------------------------------------------------------------------
 * Autocargador PSR-4 mínimo para el espacio de nombres Rubicam\.
 *
 * Se usa un autocargador propio (en lugar de exigir vendor/autoload.php) para
 * que la aplicación funcione en un XAMPP sin Composer instalado. Composer solo
 * hace falta para las herramientas de desarrollo (PHPUnit, PHPCS, PHPMD).
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Rubicam\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }

    $ruta = __DIR__ . '/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($ruta)) {
        // seguridad:ignorar SEG-007 la ruta sale de un nombre de clase con prefijo Rubicam\ y se valida con is_file().
        require $ruta;
    }
});
