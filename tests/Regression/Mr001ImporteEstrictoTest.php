<?php

/**
 * MR-001 (mantenimiento correctivo) — El valor CIF del cotizador aceptaba notación
 * científica e infinito ('1e999' → INF). El servidor debe aplicar el MISMO contrato
 * que el navegador: decimal estricto con hasta 2 decimales.
 *
 * Prueba de regresión: reproduce el defecto (rojo) y garantiza que no reaparezca.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Regression;

use PHPUnit\Framework\TestCase;

/**
 * @group MR-001
 * @group regresion
 */
final class Mr001ImporteEstrictoTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/config/security.php';
    }

    /**
     * @dataProvider importesInvalidos
     */
    public function testImporteInvalidoEsRechazado(string $entrada): void
    {
        $this->assertNull(clean_number($entrada), "El importe '{$entrada}' debía rechazarse.");
    }

    /**
     * @return array<string, array{string}>
     */
    public function importesInvalidos(): array
    {
        return [
            'notación científica'        => ['1e3'],
            'infinito por desbordamiento' => ['1e999'],
            'hexadecimal'                => ['0x1A'],
            'sin parte entera'           => ['.5'],
            'tres decimales'             => ['12.345'],
            'coma decimal'               => ['1,5'],
            'negativo'                   => ['-5'],
            'no es número'               => ['NaN'],
            'vacío'                      => [''],
            'sobre el máximo'            => ['1000000000000'],
        ];
    }

    /**
     * @dataProvider importesValidos
     */
    public function testImporteValidoSeConvierteAFloat(string $entrada, float $esperado): void
    {
        $this->assertSame($esperado, clean_number($entrada));
    }

    /**
     * @return array<string, array{string, float}>
     */
    public function importesValidos(): array
    {
        return [
            'con centavos'      => ['1500.50', 1500.5],
            'cero'              => ['0', 0.0],
            'con espacios'      => [' 12 ', 12.0],
            'en el máximo'      => ['999999999999.99', 999999999999.99],
            'entero grande'     => ['500000', 500000.0],
        ];
    }
}
