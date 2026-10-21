<?php

/**
 * CP-VAL — Validación y normalización del alta de un DO (codificación defensiva).
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Dominio;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rubicam\Dominio\ValidadorNuevoDo;

/**
 * @group dominio
 */
final class ValidadorNuevoDoTest extends TestCase
{
    private function hoy(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-21');
    }

    /**
     * @param array<string, mixed> $cambios
     * @return array<string, mixed>
     */
    private function entrada(array $cambios = []): array
    {
        return array_merge([
            'do_codigo'         => 'BOG26IMP0001',
            'cliente'           => 'Importadora Andina S.A.S.',
            'nit'               => '900123456',
            'regimen'           => 'Importación',
            'sucursal'          => 'Bogotá',
            'fecha_apertura'    => '2026-10-01',
            'fecha_vencimiento' => '',
            'email'             => 'compras@andina.co',
            'telefono'          => '300 595 9894',
            'observacion'       => 'Primer embarque',
        ], $cambios);
    }

    public function testUnaEntradaCorrectaEsValidaYSeNormaliza(): void
    {
        $resultado = ValidadorNuevoDo::validar(
            $this->entrada(['do_codigo' => ' bog26 imp0001 ', 'nit' => '900.123.456']),
            $this->hoy()
        );

        $this->assertTrue($resultado->esValido(), json_encode($resultado->errores()) ?: '');
        $this->assertSame('BOG26IMP0001', $resultado->datos()['do_codigo']);
        $this->assertSame('900123456-8', $resultado->datos()['nit']);
    }

    public function testSinVencimientoSeSugiereAperturaMasElPlazoDian(): void
    {
        $resultado = ValidadorNuevoDo::validar($this->entrada(), $this->hoy());

        $this->assertSame('2026-12-30', $resultado->datos()['fecha_vencimiento']);
    }

    /**
     * @dataProvider camposInvalidos
     * @param array<string, mixed> $cambios
     */
    public function testCadaCampoInvalidoProduceSuPropioError(array $cambios, string $campo): void
    {
        $resultado = ValidadorNuevoDo::validar($this->entrada($cambios), $this->hoy());

        $this->assertFalse($resultado->esValido());
        $this->assertArrayHasKey($campo, $resultado->errores());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public function camposInvalidos(): array
    {
        return [
            'código vacío'                  => [['do_codigo' => ''], 'do_codigo'],
            'código con formato incorrecto' => [['do_codigo' => 'ABC-1'], 'do_codigo'],
            'cliente muy corto'             => [['cliente' => 'ab'], 'cliente'],
            'cliente muy largo'             => [['cliente' => str_repeat('a', 151)], 'cliente'],
            'cliente solo etiquetas'        => [['cliente' => '<b></b>'], 'cliente'],
            'NIT con letras'                => [['nit' => '90012A456'], 'nit'],
            'NIT con dígito erróneo'        => [['nit' => '900123456-3'], 'nit'],
            'régimen fuera del catálogo'    => [['regimen' => 'Contrabando'], 'regimen'],
            'sucursal fuera del catálogo'   => [['sucursal' => 'Cali'], 'sucursal'],
            'apertura inválida'             => [['fecha_apertura' => '2026-02-30'], 'fecha_apertura'],
            'apertura futura'               => [['fecha_apertura' => '2026-10-22'], 'fecha_apertura'],
            'vencimiento inválido'          => [['fecha_vencimiento' => 'mañana'], 'fecha_vencimiento'],
            'vencimiento antes de apertura' => [['fecha_vencimiento' => '2026-09-30'], 'fecha_vencimiento'],
            'correo inválido'               => [['email' => 'sin-arroba'], 'email'],
            'teléfono solo guiones (MR-002)' => [['telefono' => '-------'], 'telefono'],
            'teléfono con letras'           => [['telefono' => 'llamar ya'], 'telefono'],
            'observación de 1001 caracteres' => [['observacion' => str_repeat('x', 1001)], 'observacion'],
        ];
    }

    public function testLosCamposOpcionalesPuedenVenirVacios(): void
    {
        $resultado = ValidadorNuevoDo::validar(
            $this->entrada(['email' => '', 'telefono' => '', 'observacion' => '']),
            $this->hoy()
        );

        $this->assertTrue($resultado->esValido(), json_encode($resultado->errores()) ?: '');
    }

    public function testElTextoLibreSeLimpiaDeEtiquetasHtml(): void
    {
        $resultado = ValidadorNuevoDo::validar(
            $this->entrada(['cliente' => '<script>x()</script>Andina   Ltda', 'observacion' => '<i>urgente</i>']),
            $this->hoy()
        );

        $this->assertSame('x()Andina Ltda', $resultado->datos()['cliente']);
        $this->assertSame('urgente', $resultado->datos()['observacion']);
    }

    public function testLosValoresQueNoSonTextoNoGeneranAvisosNiRompenLaValidacion(): void
    {
        $resultado = ValidadorNuevoDo::validar(
            $this->entrada(['cliente' => ['x'], 'nit' => ['1'], 'email' => 5, 'regimen' => null]),
            $this->hoy()
        );

        $this->assertFalse($resultado->esValido());
        $this->assertArrayHasKey('cliente', $resultado->errores());
        $this->assertArrayHasKey('nit', $resultado->errores());
        $this->assertArrayHasKey('regimen', $resultado->errores());
    }

    public function testUnaEntradaVaciaReportaTodosLosCamposObligatorios(): void
    {
        $resultado = ValidadorNuevoDo::validar([], $this->hoy());

        $obligatorios = ['do_codigo', 'cliente', 'nit', 'regimen', 'sucursal', 'fecha_apertura', 'fecha_vencimiento'];
        foreach ($obligatorios as $campo) {
            $this->assertArrayHasKey($campo, $resultado->errores(), "Falta el error de {$campo}.");
        }
    }
}
