<?php

/**
 * src/Http/Ejecutor.php
 * -----------------------------------------------------------------------
 * Ejecuta la acción de un punto de entrada y GARANTIZA una respuesta JSON
 * bien formada: convierte ErrorHttp en su código, la caída de la base de
 * datos en 503 y cualquier otra excepción (o error fatal) en un 500 genérico
 * que no revela detalles internos (rutas, SQL, contraseñas).
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Http;

use Closure;
use PDOException;
use Rubicam\Infraestructura\ConexionFallida;
use Throwable;

final class Ejecutor
{
    /** Resuelve la acción a una Respuesta sin enviarla (útil para pruebas). */
    public function resolver(Closure $accion): Respuesta
    {
        try {
            return $accion();
        } catch (ErrorHttp $e) {
            return Respuesta::json('error', $e->getMessage(), $e->codigoHttp(), $e->extra());
        } catch (ConexionFallida $e) {
            return Respuesta::json('error', $e->getMessage(), 503);
        } catch (PDOException $e) {
            error_log('[rubicam-panel] Error SQL: ' . $e->getMessage());

            return Respuesta::json('error', 'Error de base de datos. Intente de nuevo.', 500);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[rubicam-panel] %s: %s en %s:%d',
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            return Respuesta::json('error', 'Error interno del servidor. Intente de nuevo.', 500);
        }
    }

    /** Punto de entrada JSON: oculta los errores de PHP, resuelve y envía. */
    public function ejecutarJson(Closure $accion): void
    {
        ini_set('display_errors', '0');
        ob_start();
        register_shutdown_function(static function (): void {
            $error = error_get_last();
            $fatales = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if ($error !== null && in_array($error['type'], $fatales, true)) {
                error_log(sprintf(
                    '[rubicam-panel] Fatal: %s en %s:%d',
                    $error['message'],
                    $error['file'],
                    $error['line']
                ));
                Respuesta::json('error', 'Error interno del servidor. Revise logs/php-error.log.', 500)->enviar();
            }
        });

        $this->resolver($accion)->enviar();
    }
}
