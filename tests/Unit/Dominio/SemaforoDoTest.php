<?php

/**
 * CP-SEM — Semáforo de seguimiento DIAN (verde < 30 días, naranja 30–75, rojo > 75 o vencido).
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Dominio;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rubicam\Dominio\SemaforoDo;

/**
 * @group dominio
 */
final class SemaforoDoTest extends TestCase
{
    private const HOY = '2026-10-21';

    private function hoy(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::HOY);
    }

    private function aperturaHaceDias(int $dias): string
    {
        return $this->hoy()->modify("-{$dias} days")->format('Y-m-d');
    }

    /**
     * @dataProvider fronterasDelSemaforo
     */
    public function testNivelSegunDiasTranscurridos(int $dias, string $nivelEsperado): void
    {
        $resultado = SemaforoDo::calcular($this->aperturaHaceDias($dias), null, 'En trámite', $this->hoy());

        $this->assertSame($nivelEsperado, $resultado['nivel']);
        $this->assertSame($dias, $resultado['dias_transcurridos']);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public function fronterasDelSemaforo(): array
    {
        return [
            'día 0'                   => [0, 'al_dia'],
            'día 29 (último verde)'   => [29, 'al_dia'],
            'día 30 (primer naranja)' => [30, 'seguimiento'],
            'día 75 (último naranja)' => [75, 'seguimiento'],
            'día 76 (primer rojo)'    => [76, 'critico'],
            'día 200'                 => [200, 'critico'],
        ];
    }

    public function testUnDoConVencimientoPasadoEstaVencidoAunqueSeaReciente(): void
    {
        $resultado = SemaforoDo::calcular(
            $this->aperturaHaceDias(5),
            $this->hoy()->modify('-1 day')->format('Y-m-d'),
            'En trámite',
            $this->hoy()
        );

        $this->assertSame('critico', $resultado['nivel']);
        $this->assertSame('Vencido', $resultado['etiqueta']);
        $this->assertTrue($resultado['vencido']);
        $this->assertSame(-1, $resultado['dias_restantes']);
    }

    /**
     * @dataProvider estadosFinales
     */
    public function testLosEstadosFinalesNoGeneranAlerta(string $estado): void
    {
        $resultado = SemaforoDo::calcular($this->aperturaHaceDias(300), null, $estado, $this->hoy());

        $this->assertSame('cerrado', $resultado['nivel']);
        $this->assertFalse($resultado['vencido']);
    }

    /**
     * @return array<string, array{string}>
     */
    public function estadosFinales(): array
    {
        return [
            'Finalizada'           => ['Finalizada'],
            'Nacionalizado'        => ['Nacionalizado'],
            'Cerrado sin facturar' => ['Cerrado sin facturar'],
        ];
    }

    public function testElProgresoSeAcotaEntreCeroYCien(): void
    {
        $vencido = SemaforoDo::calcular($this->aperturaHaceDias(500), null, 'En trámite', $this->hoy());
        $futuro  = SemaforoDo::calcular($this->aperturaHaceDias(-10), null, 'En trámite', $this->hoy());

        $this->assertSame(100, $vencido['progreso']);
        $this->assertSame(0, $futuro['progreso']);
    }

    public function testElProgresoUsaElPlazoRealCuandoHayVencimiento(): void
    {
        $resultado = SemaforoDo::calcular(
            $this->aperturaHaceDias(10),
            $this->hoy()->modify('+10 days')->format('Y-m-d'),
            'En trámite',
            $this->hoy()
        );

        $this->assertSame(50, $resultado['progreso']);
        $this->assertSame(10, $resultado['dias_restantes']);
    }

    public function testUnaFechaDeAperturaInvalidaSeTrataComoHoy(): void
    {
        $resultado = SemaforoDo::calcular('no-es-fecha', null, 'En trámite', $this->hoy());

        $this->assertSame(0, $resultado['dias_transcurridos']);
        $this->assertSame('al_dia', $resultado['nivel']);
    }

    public function testElResultadoTieneTodasLasClavesQueConsumeElPanel(): void
    {
        $resultado = SemaforoDo::calcular($this->aperturaHaceDias(1), null, 'En trámite', $this->hoy());

        $this->assertSame(
            ['nivel', 'etiqueta', 'descripcion', 'dias_transcurridos', 'dias_restantes', 'vencido', 'progreso'],
            array_keys($resultado)
        );
    }
}
