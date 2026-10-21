<?php

/**
 * MR-002 (mantenimiento correctivo) — Teléfonos sin ningún dígito ('-------', '()()()()')
 * pasaban la validación porque se contaban caracteres y no dígitos.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Regression;

use PHPUnit\Framework\TestCase;
use Rubicam\Seguridad\Sanitizador;

/**
 * @group MR-002
 * @group regresion
 */
final class Mr002TelefonoConDigitosTest extends TestCase
{
    /**
     * @dataProvider telefonosInvalidos
     */
    public function testTelefonoInvalidoEsRechazado(string $entrada): void
    {
        $this->assertNull(Sanitizador::telefono($entrada), "El teléfono '{$entrada}' debía rechazarse.");
    }

    /**
     * @return array<string, array{string}>
     */
    public function telefonosInvalidos(): array
    {
        return [
            'solo guiones'          => ['-------'],
            'solo paréntesis'       => ['()()()()'],
            'solo signos más'       => ['++++++++'],
            'solo espacios'         => ['       '],
            'seis dígitos'          => ['123456'],
            'siete dígitos y letra' => ['123456a7'],
            'más de 20 caracteres'  => ['300 123 4567 300 123 4567'],
        ];
    }

    /**
     * @dataProvider telefonosValidos
     */
    public function testTelefonoValidoSeConserva(string $entrada): void
    {
        $this->assertSame($entrada, Sanitizador::telefono($entrada));
    }

    /**
     * @return array<string, array{string}>
     */
    public function telefonosValidos(): array
    {
        return [
            'celular'                   => ['3001234567'],
            'con indicativo y formato'  => ['+57 (607) 123-4567'],
            'exactamente siete dígitos' => ['1234567'],
        ];
    }
}
