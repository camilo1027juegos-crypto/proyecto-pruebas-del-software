<?php

/**
 * CP-REQ — Objeto Solicitud: acceso tipado y seguro a la petición actual.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Rubicam\Http\Solicitud;

/**
 * @group http
 */
final class SolicitudTest extends TestCase
{
    /**
     * @param array<string, mixed> $servidor
     * @param array<string, mixed> $consulta
     * @param array<string, mixed> $formulario
     */
    private function solicitud(
        array $servidor = [],
        array $consulta = [],
        array $formulario = [],
        string $cuerpo = ''
    ): Solicitud {
        return new Solicitud($servidor, $consulta, $formulario, [], static fn (): string => $cuerpo);
    }

    public function testEsPostIgnoraMayusculasYPorDefectoEsGet(): void
    {
        $this->assertTrue($this->solicitud(['REQUEST_METHOD' => 'post'])->esPost());
        $this->assertTrue($this->solicitud(['REQUEST_METHOD' => 'POST'])->esPost());
        $this->assertFalse($this->solicitud(['REQUEST_METHOD' => 'GET'])->esPost());
        $this->assertFalse($this->solicitud()->esPost());
    }

    public function testConsultaSoloDevuelveTexto(): void
    {
        $solicitud = $this->solicitud([], ['action' => 'listar', 'malo' => ['x'], 'num' => 5]);

        $this->assertSame('listar', $solicitud->consulta('action'));
        $this->assertNull($solicitud->consulta('malo'), 'Un arreglo en la URL (?malo[]=x) no debe llegar como texto.');
        $this->assertNull($solicitud->consulta('num'));
        $this->assertNull($solicitud->consulta('inexistente'));
    }

    public function testCabeceraSeLeeDeLasVariablesHttpDelServidor(): void
    {
        $solicitud = $this->solicitud(['HTTP_X_CSRF_TOKEN' => 'abc', 'REMOTE_ADDR' => '10.0.0.5']);

        $this->assertSame('abc', $solicitud->cabecera('X-CSRF-Token'));
        $this->assertNull($solicitud->cabecera('X-Otra'));
        $this->assertSame('10.0.0.5', $solicitud->direccionIp());
        $this->assertSame('desconocida', $this->solicitud()->direccionIp());
    }

    public function testDetectaElCuerpoDescartadoPorSuperarPostMaxSize(): void
    {
        $descartado = new Solicitud(['CONTENT_LENGTH' => '99999999'], [], [], [], static fn (): string => '');
        $normal     = new Solicitud(['CONTENT_LENGTH' => '20'], [], ['a' => 'b'], [], static fn (): string => '');

        $this->assertTrue($descartado->cuerpoDescartadoPorElServidor());
        $this->assertFalse($normal->cuerpoDescartadoPorElServidor());
        $this->assertFalse($this->solicitud()->cuerpoDescartadoPorElServidor());
    }

    public function testJsonDecodificaElCuerpoYRechazaElInvalido(): void
    {
        $this->assertSame(['a' => 1], $this->solicitud([], [], [], '{"a":1}')->json());

        $this->expectException(\Rubicam\Http\ErrorHttp::class);
        $this->solicitud([], [], [], '{roto')->json();
    }
}
