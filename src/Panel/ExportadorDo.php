<?php

/**
 * src/Panel/ExportadorDo.php
 * -----------------------------------------------------------------------
 * Exporta las Órdenes de Operación a CSV (para Excel) y a JSON.
 *
 *  - CSV: UTF-8 con BOM y «;» como separador, para que Excel en español
 *    respete tildes y columnas.
 *  - Defensa contra «CSV injection»: un texto que empieza por = + - @ (o
 *    tabulación/retorno) se antepone con «'» para que Excel lo trate como
 *    texto y no como una fórmula.
 *  - Solo se exporta una lista fija de columnas (no se vuelca la fila entera).
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Panel;

use DateTimeImmutable;

final class ExportadorDo
{
    /** Columnas del CSV, en orden. */
    public const COLUMNAS = [
        'do_codigo',
        'cliente',
        'nit',
        'regimen',
        'sucursal',
        'estado',
        'nivel_alerta',
        'dias_transcurridos',
        'fecha_apertura',
        'fecha_vencimiento',
        'ultima_observacion',
    ];

    /** Criterios por los que se puede filtrar: criterio → clave en la DO. */
    private const CRITERIOS = ['sucursal' => 'sucursal', 'regimen' => 'regimen', 'nivel' => 'semaforo.nivel'];

    private const INICIOS_DE_FORMULA = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param list<array<string, mixed>> $ordenes
     */
    public static function aCsv(array $ordenes): string
    {
        $flujo = fopen('php://temp', 'r+');
        fputcsv($flujo, self::COLUMNAS, ';', '"', '');
        foreach ($ordenes as $orden) {
            $fila = array_map(
                static fn (string $columna): string|int => self::neutralizar(self::valorDe($orden, $columna)),
                self::COLUMNAS
            );
            fputcsv($flujo, $fila, ';', '"', '');
        }
        rewind($flujo);
        $contenido = (string) stream_get_contents($flujo);
        fclose($flujo);

        return "\xEF\xBB\xBF" . $contenido;
    }

    /**
     * @param list<array<string, mixed>> $ordenes
     */
    public static function aJson(array $ordenes, DateTimeImmutable $generado): string
    {
        $operaciones = array_map(static function (array $orden): array {
            $fila = [];
            foreach (self::COLUMNAS as $columna) {
                if ($columna !== 'nivel_alerta' && $columna !== 'dias_transcurridos') {
                    $fila[$columna] = $orden[$columna] ?? null;
                }
            }
            $fila['semaforo'] = [
                'nivel'              => $orden['semaforo']['nivel'] ?? null,
                'dias_transcurridos' => $orden['semaforo']['dias_transcurridos'] ?? null,
                'dias_restantes'     => $orden['semaforo']['dias_restantes'] ?? null,
            ];

            return $fila;
        }, $ordenes);

        return (string) json_encode(
            ['generado' => $generado->format('c'), 'total' => count($operaciones), 'operaciones' => $operaciones],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /**
     * Conserva solo las DO que cumplen TODOS los criterios no vacíos; los criterios desconocidos se ignoran.
     *
     * @param list<array<string, mixed>> $ordenes
     * @param array<string, string>      $criterios p. ej. ['sucursal' => 'Bogotá', 'nivel' => 'critico']
     * @return list<array<string, mixed>>
     */
    public static function filtrar(array $ordenes, array $criterios): array
    {
        $activos = array_filter(
            array_intersect_key($criterios, self::CRITERIOS),
            static fn (string $valor): bool => $valor !== ''
        );

        return array_values(array_filter(
            $ordenes,
            static function (array $orden) use ($activos): bool {
                foreach ($activos as $criterio => $esperado) {
                    $real = $criterio === 'nivel' ? ($orden['semaforo']['nivel'] ?? null) : ($orden[$criterio] ?? null);
                    if ($real !== $esperado) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    public static function nombreArchivo(string $formato, DateTimeImmutable $ahora): string
    {
        return 'rubicam_dos_' . $ahora->format('Ymd-Hi') . '.' . $formato;
    }

    public static function posicionDe(string $columna): int
    {
        return (int) array_search($columna, self::COLUMNAS, true);
    }

    /**
     * @param array<string, mixed> $orden
     */
    private static function valorDe(array $orden, string $columna): string|int
    {
        $valor = match ($columna) {
            'nivel_alerta'       => $orden['semaforo']['nivel'] ?? '',
            'dias_transcurridos' => $orden['semaforo']['dias_transcurridos'] ?? '',
            default              => $orden[$columna] ?? '',
        };

        return is_int($valor) ? $valor : (string) $valor;
    }

    private static function neutralizar(string|int $valor): string|int
    {
        if (is_string($valor) && $valor !== '' && in_array($valor[0], self::INICIOS_DE_FORMULA, true)) {
            return "'" . $valor;
        }

        return $valor;
    }
}
