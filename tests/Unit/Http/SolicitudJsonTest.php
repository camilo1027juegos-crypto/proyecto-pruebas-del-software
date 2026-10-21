<?php

/**
 * CP-JSON — Lectura defensiva del cuerpo JSON de una petición.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Rubicam\Http\ErrorHttp;
use Rubicam\Http\SolicitudJson;

/**
 * @group http
 */
final class SolicitudJsonTest extends TestCase
{
    public function testDecodificaUnObjetoJson(): void
    {
        $this->assertSame(['a' => 1, 'b' => ['c' => 2]], SolicitudJson::decodificar('{"a":1,"b":{"c":2}}'));
    }

    /**
     * @dataProvider cuerposInvalidos
     */
    public function testRechazaCuerposInvalidos(string $crudo, int $codigoEsperado): void
    {
        try {
            SolicitudJson::decodificar($crudo);
            $this->fail('Debió lanzar ErrorHttp.');
        } catch (ErrorHttp $e) {
            $this->assertSame($codigoEsperado, $e->codigoHttp());
        }
    }

    /**
     * @return array<string, array{string, int}>
     */
    public function cuerposInvalidos(): array
    {
        return [
            'vacío'                  => ['', 400],
            'JSON mal formado'       => ['{"a":', 400],
            'escalar, no objeto'     => ['"hola"', 400],
            'número, no objeto'      => ['42', 400],
            'demasiado grande'       => ['{"a":"' . str_repeat('x', SolicitudJson::MAX_BYTES) . '"}', 413],
            'anidamiento excesivo'   => [str_repeat('[', 40) . str_repeat(']', 40), 400],
        ];
    }
}
