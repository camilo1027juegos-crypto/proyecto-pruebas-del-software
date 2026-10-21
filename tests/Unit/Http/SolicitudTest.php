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
    private function solicitud(array $servidor = [], array $consulta = [], array $formulario = [], string $cuerpo = ''): Solicitud
    {
        return new Solicitud($servidor, $consulta, $formulario, [], static fn (): string => $cuerpo);
    }

    public function testMetodoSeNormalizaAMayusculasYTieneValorPorDefecto(): void
    {
        $this->assertSame('POST', $this->solicitud(['REQUEST_METHOD' => 'post'])->metodo());
        $this->assertSame('GET', $this->solicitud()->metodo());
        $this->assertTrue($this->solicitud(['REQUEST_METHOD' => 'POST'])->esPost());
    }

    public function testConsultaSoloDevuelveTexto(): void
    {
        $solicitud = $this->solicitud([], ['action' => 'listar', 'malo' => ['x'], 'num' => 5]);

        $this->assertSame('listar', $solicitud->consulta('action'));
        $this->assertNull($solicitud->consulta('malo'), 'Un arreglo en la URL (?malo[]=x) no debe llegar como texto.');
        $this->assertNull($solicitud->consulta('num'));
        $this->assertNull($solicitud->consulta('inexistente'));
    }

    /**
     * @dataProvider enteros
     */
    public function testConsultaEnteroPositivo(mixed $valor, ?int $esperado): void
    {
        $this->assertSame($esperado, $this->solicitud([], ['do_id' => $valor])->consultaEnteroPositivo('do_id'));
    }

    /**
     * @return array<string, array{mixed, ?int}>
     */
    public function enteros(): array
    {
        return [
            'entero válido'     => ['12', 12],
            'cero'              => ['0', null],
            'negativo'          => ['-3', null],
            'decimal'           => ['1.5', null],
            'texto'             => ['abc', null],
            'inyección SQL'     => ['1 OR 1=1', null],
            'arreglo'           => [['1'], null],
        ];
    }

    public function testCabeceraSeLeeDeLasVariablesHttpDelServidor(): void
    {
        $solicitud = $this->solicitud(['HTTP_X_CSRF_TOKEN' => 'abc', 'REMOTE_ADDR' => '10.0.0.5']);

        $this->assertSame('abc', $solicitud->cabecera('X-CSRF-Token'));
        $this->assertNull($solicitud->cabecera('X-Otra'));
        $this->assertSame('10.0.0.5', $solicitud->ip());
        $this->assertSame('desconocida', $this->solicitud()->ip());
    }

    public function testJsonDecodificaElCuerpoYRechazaElInvalido(): void
    {
        $this->assertSame(['a' => 1], $this->solicitud([], [], [], '{"a":1}')->json());

        $this->expectException(\Rubicam\Http\ErrorHttp::class);
        $this->solicitud([], [], [], '{roto')->json();
    }
}
