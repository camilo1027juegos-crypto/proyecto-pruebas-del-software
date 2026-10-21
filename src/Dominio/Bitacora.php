<?php

/**
 * src/Dominio/Bitacora.php
 * -----------------------------------------------------------------------
 * Bitácora de observaciones de un DO (campo procesos_do.observaciones).
 *
 * Cada línea tiene el formato (la más reciente arriba):
 *   [07/10/2026 15:48 · Nombre Usuario · Presentada DIAN] Texto de la observación
 *
 * Los caracteres de control del formato ([ ] ·) se eliminan de usuario y
 * estado para que nadie pueda «falsificar» una entrada de otra persona.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Dominio;

use DateTimeImmutable;

final class Bitacora
{
    /** TEXT admite 65.535 bytes; se deja margen y se descartan las entradas más antiguas. */
    public const MAX_BYTES = 60000;

    public static function agregar(
        ?string $bitacoraActual,
        string $texto,
        string $usuario,
        string $estado,
        ?DateTimeImmutable $ahora = null
    ): string {
        $ahora ??= new DateTimeImmutable();

        $linea = sprintf(
            '[%s · %s · %s] %s',
            $ahora->format('d/m/Y H:i'),
            self::sinMarcasDeFormato($usuario),
            self::sinMarcasDeFormato($estado),
            self::compactarEspacios($texto)
        );

        $actual    = trim((string) $bitacoraActual);
        $resultado = $actual !== '' ? $linea . "\n" . $actual : $linea;

        while (strlen($resultado) > self::MAX_BYTES && str_contains($resultado, "\n")) {
            $resultado = substr($resultado, 0, (int) strrpos($resultado, "\n"));
        }

        return $resultado;
    }

    /**
     * Convierte la bitácora en una lista de entradas (la más reciente primero).
     * Las observaciones antiguas sin formato se devuelven como texto plano.
     *
     * @return list<array{fecha:string, usuario:string, estado:string, texto:string}>
     */
    public static function entradas(?string $bitacora, int $limite = 10): array
    {
        $entradas = [];

        foreach (preg_split('/\R/u', trim((string) $bitacora)) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea !== '') {
                $entradas[] = self::interpretarLinea($linea);
            }
            if (count($entradas) >= $limite) {
                break;
            }
        }

        return $entradas;
    }

    /**
     * @return array{fecha:string, usuario:string, estado:string, texto:string}
     */
    private static function interpretarLinea(string $linea): array
    {
        if (!preg_match('/^\[([^\]]*)\]\s*(.*)$/u', $linea, $partes)) {
            return ['fecha' => '', 'usuario' => '', 'estado' => '', 'texto' => $linea];
        }

        $meta = array_map('trim', explode('·', $partes[1]));

        return [
            'fecha'   => $meta[0],
            'usuario' => $meta[1] ?? '',
            'estado'  => $meta[2] ?? '',
            'texto'   => $partes[2],
        ];
    }

    private static function sinMarcasDeFormato(string $valor): string
    {
        return self::compactarEspacios(str_replace(['[', ']', '·'], ' ', $valor));
    }

    private static function compactarEspacios(string $valor): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $valor));
    }
}
