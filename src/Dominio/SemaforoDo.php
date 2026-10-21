<?php

/**
 * src/Dominio/SemaforoDo.php
 * -----------------------------------------------------------------------
 * Semáforo de seguimiento DIAN de una Orden de Operación (DO).
 *
 *   Verde  (al_dia)      : menos de 30 días transcurridos desde la apertura.
 *   Naranja (seguimiento): de 30 a 75 días.
 *   Rojo   (critico)     : más de 75 días, o fecha de vencimiento ya superada.
 *   Neutro (cerrado)     : estados finales (no aplica alerta).
 *
 * El reloj se inyecta ($hoy) para que las pruebas sean deterministas.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Dominio;

use DateTimeImmutable;

final class SemaforoDo
{
    public const DIAS_SEGUIMIENTO = 30; // desde 30 días → naranja
    public const DIAS_CRITICO = 75;     // más de 75 días → rojo
    public const PLAZO_DIAN_DIAS = 90;  // vencimiento sugerido al crear un DO

    private const CLASIFICACION_CERRADO = ['cerrado', 'Cerrado', 'Operación cerrada: no aplica alerta DIAN.'];

    /**
     * @return array{nivel:string, etiqueta:string, descripcion:string, dias_transcurridos:int,
     *               dias_restantes:?int, vencido:bool, progreso:int}
     */
    public static function calcular(
        string $fechaApertura,
        ?string $fechaVencimiento,
        string $estado,
        ?DateTimeImmutable $hoy = null
    ): array {
        $hoy ??= new DateTimeImmutable('today');

        $apertura = self::fecha($fechaApertura) ?? $hoy;
        $diasTranscurridos = self::diasEntre($apertura, $hoy);

        $vencimiento   = self::fecha((string) $fechaVencimiento);
        $diasRestantes = $vencimiento !== null ? self::diasEntre($hoy, $vencimiento) : null;
        $plazoTotal    = $vencimiento !== null
            ? max(1, self::diasEntre($apertura, $vencimiento))
            : self::PLAZO_DIAN_DIAS;

        $progreso = (int) max(0, min(100, round(max(0, $diasTranscurridos) / $plazoTotal * 100)));

        $cerrado = Catalogos::esEstadoFinal($estado);
        $vencido = !$cerrado && $diasRestantes !== null && $diasRestantes < 0;
        $nivel   = $cerrado ? self::CLASIFICACION_CERRADO : self::clasificar($diasTranscurridos, $vencido);

        return [
            'nivel'              => $nivel[0],
            'etiqueta'           => $nivel[1],
            'descripcion'        => $nivel[2],
            'dias_transcurridos' => $diasTranscurridos,
            'dias_restantes'     => $diasRestantes,
            'vencido'            => $vencido,
            'progreso'           => $progreso,
        ];
    }

    /**
     * @return array{0:string, 1:string, 2:string} nivel, etiqueta y descripción
     */
    private static function clasificar(int $diasTranscurridos, bool $vencido): array
    {
        if ($vencido) {
            return ['critico', 'Vencido', 'La fecha de vencimiento ya pasó.'];
        }
        if ($diasTranscurridos > self::DIAS_CRITICO) {
            return [
                'critico',
                'Crítico',
                'Más de ' . self::DIAS_CRITICO . ' días: cerca del límite de 3 meses DIAN.',
            ];
        }
        if ($diasTranscurridos >= self::DIAS_SEGUIMIENTO) {
            return [
                'seguimiento',
                'Seguimiento',
                'Entre ' . self::DIAS_SEGUIMIENTO . ' y ' . self::DIAS_CRITICO . ' días desde la apertura.',
            ];
        }

        return ['al_dia', 'Al día', 'Menos de ' . self::DIAS_SEGUIMIENTO . ' días desde la apertura.'];
    }

    /** Fecha «Y-m-d» (se ignora la hora si viene un DATETIME) o null si no es una fecha real. */
    private static function fecha(string $valor): ?DateTimeImmutable
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', substr($valor, 0, 10));

        return $fecha === false ? null : $fecha;
    }

    /** Días con signo entre dos fechas (negativo si $hasta es anterior a $desde). */
    private static function diasEntre(DateTimeImmutable $desde, DateTimeImmutable $hasta): int
    {
        return (int) $desde->diff($hasta)->format('%r%a');
    }
}
