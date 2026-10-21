<?php

/**
 * MR-004 (mantenimiento preventivo, codificación defensiva) — calcular_dv_nit() devolvía un
 * dígito «inventado» con entradas no numéricas ('abc12' → 9). Una función que calcula un
 * identificador legal debe rechazar lo que no es un NIT en lugar de producir un dato corrupto.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Regression;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @group MR-004
 * @group regresion
 */
final class Mr004NitDefensivoTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/config/config_db.php';
    }

    /**
     * @dataProvider entradasInvalidas
     */
    public function testEntradaNoNumericaLanzaExcepcion(string $entrada): void
    {
        $this->expectException(InvalidArgumentException::class);
        calcular_dv_nit($entrada);
    }

    /**
     * @return array<string, array{string}>
     */
    public function entradasInvalidas(): array
    {
        return [
            'vacío'                => [''],
            'letras'               => ['abc12'],
            'con espacio'          => ['12 345'],
            'con guion'            => ['900123456-8'],
            'más de 15 dígitos'    => ['1234567890123456'],
        ];
    }

    /**
     * @dataProvider nitReales
     */
    public function testDigitoDeNitsConocidos(string $base, int $esperado): void
    {
        $this->assertSame($esperado, calcular_dv_nit($base));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public function nitReales(): array
    {
        return [
            'Bancolombia'      => ['890903938', 8],
            'DIAN'             => ['800197268', 4],
            'Banco de Bogotá'  => ['860002964', 4],
            'caso de prueba'   => ['900123456', 8],
        ];
    }
}
