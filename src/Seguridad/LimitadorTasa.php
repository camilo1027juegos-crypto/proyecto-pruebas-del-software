<?php

/**
 * src/Seguridad/LimitadorTasa.php
 * -----------------------------------------------------------------------
 * Limitador de tasa por «cubo» (p. ej. quote, login) y «clave» (IP o usuario)
 * con ventana fija. Guarda un pequeño JSON por cubo+clave y usa flock para
 * que dos peticiones simultáneas no pierdan un conteo.
 *
 * Decisión de diseño (documentada en docs/03_AUDITORIA_CODIGO.md): si el disco
 * falla, el limitador «falla abierto» (deja pasar): un error nuestro no debe
 * dejar sin servicio al cliente. Los demás controles (CSRF, honeypot,
 * validación) siguen activos.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Seguridad;

use Closure;

final class LimitadorTasa
{
    private Closure $reloj;

    public function __construct(
        private readonly string $directorio,
        private readonly int $maximo,
        private readonly int $ventanaSegundos,
        ?Closure $reloj = null
    ) {
        $this->reloj = $reloj ?? static fn (): int => time();
    }

    /** Consume un intento. Devuelve false si el cubo ya alcanzó el máximo dentro de la ventana. */
    public function intentar(string $cubo, string $clave): bool
    {
        $permitido = $this->actualizar($this->rutaDe($cubo, $clave), function (array $estado): array {
            if ($estado['count'] >= $this->maximo) {
                return [$estado, false];
            }
            $estado['count']++;

            return [$estado, true];
        });

        return $permitido ?? true;
    }

    /** Cuenta un intento (p. ej. un inicio de sesión fallido) sin decidir nada. */
    public function registrar(string $cubo, string $clave): void
    {
        $this->actualizar($this->rutaDe($cubo, $clave), static function (array $estado): array {
            $estado['count']++;

            return [$estado, true];
        });
    }

    /** Consulta si el cubo está bloqueado SIN consumir intentos. */
    public function estaBloqueado(string $cubo, string $clave): bool
    {
        $ruta = $this->rutaDe($cubo, $clave);
        if (!is_file($ruta)) {
            return false;
        }

        $estado = $this->decodificar((string) file_get_contents($ruta));

        return $estado['count'] >= $this->maximo;
    }

    /** Borra el contador (p. ej. tras un inicio de sesión correcto). */
    public function reiniciar(string $cubo, string $clave): void
    {
        $ruta = $this->rutaDe($cubo, $clave);
        if (is_file($ruta)) {
            unlink($ruta);
        }
    }

    private function rutaDe(string $cubo, string $clave): string
    {
        $cuboSeguro = preg_replace('/[^A-Za-z0-9_-]/', '', $cubo);

        // seguridad:ignorar SEG-008 md5 solo da un nombre de archivo estable; no protege ningún secreto.
        return $this->directorio . '/' . ($cuboSeguro !== '' ? $cuboSeguro : 'cubo') . '_' . md5($clave) . '.json';
    }

    /**
     * Ejecuta $operacion(estado) → [nuevoEstado, resultado] bajo bloqueo exclusivo y guarda el nuevo estado.
     * Devuelve null si no se pudo usar el disco (fallo abierto).
     *
     * @param Closure(array{count:int, start:int}): array{0: array{count:int, start:int}, 1: bool} $operacion
     */
    private function actualizar(string $ruta, Closure $operacion): ?bool
    {
        if (!is_dir($this->directorio) && !mkdir($this->directorio, 0750, true) && !is_dir($this->directorio)) {
            return null;
        }

        $manejador = fopen($ruta, 'c+');
        if ($manejador === false) {
            return null;
        }

        try {
            if (!flock($manejador, LOCK_EX)) {
                return null;
            }
            [$nuevoEstado, $resultado] = $operacion($this->decodificar((string) stream_get_contents($manejador)));

            ftruncate($manejador, 0);
            rewind($manejador);
            fwrite($manejador, (string) json_encode($nuevoEstado));

            return $resultado;
        } finally {
            fclose($manejador);
        }
    }

    /**
     * @return array{count:int, start:int}
     */
    private function decodificar(string $contenido): array
    {
        $ahora  = ($this->reloj)();
        $estado = json_decode($contenido, true);

        $valido = is_array($estado) && isset($estado['count'], $estado['start'])
            && is_int($estado['count']) && is_int($estado['start']);

        if (!$valido || ($ahora - $estado['start']) > $this->ventanaSegundos) {
            return ['count' => 0, 'start' => $ahora];
        }

        return ['count' => $estado['count'], 'start' => $estado['start']];
    }
}
