<?php

/**
 * CP-SAN — Sanitización y validación de entradas (seguridad en la frontera del sistema).
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Seguridad;

use PHPUnit\Framework\TestCase;
use Rubicam\Seguridad\Sanitizador;

/**
 * @group seguridad
 */
final class SanitizadorTest extends TestCase
{
    /**
     * @dataProvider textos
     */
    public function testTextoQuitaEtiquetasYCaracteresDeControl(?string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, Sanitizador::texto($entrada));
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public function textos(): array
    {
        return [
            'null'                  => [null, ''],
            'espacios en los bordes' => ['  hola  ', 'hola'],
            'etiquetas HTML'        => ['<b>Hola</b> <script>alert(1)</script>', 'Hola alert(1)'],
            'caracteres de control' => ["a\x00b\x07c\x1Fd", 'abcd'],
            'tildes se conservan'   => ['Ñandú está aquí', 'Ñandú está aquí'],
        ];
    }

    /**
     * @dataProvider correos
     */
    public function testCorreo(?string $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, Sanitizador::correo($entrada));
    }

    /**
     * @return array<string, array{?string, ?string}>
     */
    public function correos(): array
    {
        return [
            'válido'                    => ['ana@rubicam.co', 'ana@rubicam.co'],
            'con espacios en bordes'    => ['  ana@rubicam.co ', 'ana@rubicam.co'],
            'sin arroba'                => ['ana.rubicam.co', null],
            'vacío'                     => ['', null],
            'null'                      => [null, null],
            'inyección de cabecera CRLF' => ["ana@rubicam.co\r\nBcc: victima@x.com", null],
        ];
    }

    /**
     * @dataProvider telefonos
     */
    public function testTelefono(string $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, Sanitizador::telefono($entrada));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public function telefonos(): array
    {
        return [
            'celular'                   => ['300 595 9894', '300 595 9894'],
            'con indicativo'            => ['+57 (604) 622-1815', '+57 (604) 622-1815'],
            'solo guiones (MR-002)'     => ['-------', null],
            'solo paréntesis y espacios' => ['(   )   ', null],
            'menos de 7 dígitos'        => ['12345 6', null],
            'letras'                    => ['300abc5959894', null],
            'demasiado largo'           => [str_repeat('1', 21), null],
        ];
    }

    /**
     * @dataProvider importes
     */
    public function testImporte(?string $entrada, ?float $esperado): void
    {
        $this->assertSame($esperado, Sanitizador::importe($entrada));
    }

    /**
     * @return array<string, array{?string, ?float}>
     */
    public function importes(): array
    {
        return [
            'entero'                     => ['1500', 1500.0],
            'con dos decimales'          => ['1500.25', 1500.25],
            'notación científica (MR-001)' => ['1e999', null],
            'hexadecimal'                => ['0x1A', null],
            'negativo'                   => ['-5', null],
            'tres decimales'             => ['1.234', null],
            'coma decimal'               => ['1,5', null],
            'sobre el máximo'            => ['1000000000000', null],
            'vacío'                      => ['', null],
            'null'                       => [null, null],
        ];
    }

    public function testHtmlEscapaComillasYEtiquetas(): void
    {
        $escapado = Sanitizador::html('<a href="x">O\'Neil</a>');

        $this->assertSame('&lt;a href=&quot;x&quot;&gt;O&#039;Neil&lt;/a&gt;', $escapado);
        $this->assertSame('', Sanitizador::html(null));
    }
}
