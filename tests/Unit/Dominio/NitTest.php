<?php

/**
 * CP-NIT — Dígito de verificación (módulo 11 de la DIAN) y normalización del NIT.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Dominio;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rubicam\Dominio\Nit;
use Rubicam\Dominio\NitInvalido;

/**
 * @group dominio
 */
final class NitTest extends TestCase
{
    /**
     * @dataProvider nitsConocidos
     */
    public function testDigitoDeVerificacionDeNitsReales(string $base, int $esperado): void
    {
        $this->assertSame($esperado, Nit::digitoVerificacion($base));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public function nitsConocidos(): array
    {
        return [
            'Ecopetrol'      => ['899999068', 1],
            'Banco de Bogotá' => ['860002964', 4],
            'Bancolombia'    => ['890903938', 8],
            'Rubicam'        => ['901742681', 8],
            'un solo dígito' => ['1', 8],
        ];
    }

    /**
     * @dataProvider basesInvalidas
     */
    public function testBaseNoNumericaLanzaExcepcion(string $entrada): void
    {
        $this->expectException(InvalidArgumentException::class);
        Nit::digitoVerificacion($entrada);
    }

    /**
     * @return array<string, array{string}>
     */
    public function basesInvalidas(): array
    {
        return [
            'vacío'             => [''],
            'letras'            => ['abc12'],
            'con espacio'       => ['12 345'],
            'con guion'         => ['900123456-8'],
            'más de 15 dígitos' => ['1234567890123456'],
        ];
    }

    /**
     * @dataProvider entradasNormalizables
     */
    public function testNormalizarAgregaOCompletaElDigito(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, Nit::normalizar($entrada));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function entradasNormalizables(): array
    {
        return [
            'sin dígito'                => ['900123456', '900123456-8'],
            'con dígito correcto'       => ['900123456-8', '900123456-8'],
            'con puntos y espacios'     => [' 900.123.456 - 8 ', '900123456-8'],
            'mínimo de 5 dígitos'       => ['12345', '12345-' . Nit::digitoVerificacion('12345')],
        ];
    }

    /**
     * @dataProvider entradasRechazadas
     */
    public function testNormalizarRechazaEntradasInvalidas(string $entrada, string $fragmentoDelMensaje): void
    {
        try {
            Nit::normalizar($entrada);
            $this->fail('Debió lanzar NitInvalido para "' . $entrada . '".');
        } catch (NitInvalido $e) {
            $this->assertStringContainsString($fragmentoDelMensaje, $e->getMessage());
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function entradasRechazadas(): array
    {
        return [
            'vacío'                      => ['', 'NIT inválido'],
            'letras'                     => ['ABC123456', 'NIT inválido'],
            'muy corto (4 dígitos)'      => ['1234', 'NIT inválido'],
            'muy largo (16 dígitos)'     => ['1234567890123456', 'NIT inválido'],
            'dos dígitos de verificación' => ['900123456-88', 'NIT inválido'],
            'dígito de verificación erróneo' => ['900123456-3', 'dígito de verificación no corresponde'],
        ];
    }

    public function testElMensajeDelDigitoErroneoIndicaElValorCorrecto(): void
    {
        try {
            Nit::normalizar('900123456-3');
            $this->fail('Debió lanzar NitInvalido.');
        } catch (NitInvalido $e) {
            $this->assertStringContainsString('para 900123456 es 8', $e->getMessage());
        }
    }
}
