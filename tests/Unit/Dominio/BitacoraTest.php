<?php

/**
 * CP-BIT — Bitácora de observaciones del DO (campo procesos_do.observaciones).
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Dominio;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rubicam\Dominio\Bitacora;

/**
 * @group dominio
 */
final class BitacoraTest extends TestCase
{
    private function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-21 16:45:00');
    }

    public function testAgregarGeneraUnaLineaConFechaUsuarioYEstado(): void
    {
        $resultado = Bitacora::agregar(
            null,
            'Se presentó la declaración',
            'Ana Gómez',
            'Presentada DIAN',
            $this->ahora()
        );

        $this->assertSame(
            '[21/10/2026 16:45 · Ana Gómez · Presentada DIAN] Se presentó la declaración',
            $resultado
        );
    }

    public function testLaEntradaMasRecienteVaPrimero(): void
    {
        $primera  = Bitacora::agregar(null, 'Primera', 'Ana', 'En trámite', $this->ahora());
        $segunda  = Bitacora::agregar($primera, 'Segunda', 'Luis', 'Aceptada', $this->ahora());
        $entradas = Bitacora::entradas($segunda);

        $this->assertCount(2, $entradas);
        $this->assertSame('Segunda', $entradas[0]['texto']);
        $this->assertSame('Primera', $entradas[1]['texto']);
    }

    public function testElTextoSeNormalizaSinSaltosDeLineaNiEspaciosRepetidos(): void
    {
        $resultado = Bitacora::agregar(null, "uno\n\ndos    tres", 'Ana', 'En trámite', $this->ahora());

        $this->assertStringEndsWith('] uno dos tres', $resultado);
        $this->assertStringNotContainsString("\n", $resultado);
    }

    public function testUnUsuarioMaliciosoNoPuedeRomperElFormatoDeLaLinea(): void
    {
        $resultado = Bitacora::agregar(null, 'Texto', 'Ana] [Admin · root', 'En [trámite]', $this->ahora());
        $entradas  = Bitacora::entradas($resultado);

        $this->assertCount(1, $entradas);
        $this->assertSame('Ana Admin root', $entradas[0]['usuario']);
        $this->assertSame('En trámite', $entradas[0]['estado']);
        $this->assertSame('Texto', $entradas[0]['texto']);
    }

    public function testCuandoSeLlenaSeDescartanLasEntradasMasAntiguas(): void
    {
        $bitacora = null;
        for ($i = 1; $i <= 200; $i++) {
            $texto    = str_repeat('x', 400) . " #{$i}";
            $bitacora = Bitacora::agregar($bitacora, $texto, 'Ana', 'En trámite', $this->ahora());
        }

        $this->assertLessThanOrEqual(Bitacora::MAX_BYTES, strlen((string) $bitacora));
        $entradas = Bitacora::entradas($bitacora, 1);
        $this->assertStringEndsWith('#200', $entradas[0]['texto'], 'La entrada más reciente nunca se descarta.');
    }

    public function testEntradasRespetaElLimite(): void
    {
        $bitacora = null;
        for ($i = 1; $i <= 5; $i++) {
            $bitacora = Bitacora::agregar($bitacora, "n{$i}", 'Ana', 'En trámite', $this->ahora());
        }

        $this->assertCount(3, Bitacora::entradas($bitacora, 3));
    }

    public function testLasObservacionesAntiguasSinFormatoSeDevuelvenComoTextoPlano(): void
    {
        $entradas = Bitacora::entradas("Llegó la mercancía\n\n  Falta el BL  ");

        $this->assertCount(2, $entradas);
        $esperada = ['fecha' => '', 'usuario' => '', 'estado' => '', 'texto' => 'Llegó la mercancía'];
        $this->assertSame($esperada, $entradas[0]);
        $this->assertSame('Falta el BL', $entradas[1]['texto']);
    }

    /**
     * @dataProvider bitacorasVacias
     */
    public function testUnaBitacoraVaciaNoGeneraEntradas(?string $valor): void
    {
        $this->assertSame([], Bitacora::entradas($valor));
    }

    /**
     * @return array<string, array{?string}>
     */
    public function bitacorasVacias(): array
    {
        return ['null' => [null], 'cadena vacía' => [''], 'solo espacios' => ["  \n \n"]];
    }
}
