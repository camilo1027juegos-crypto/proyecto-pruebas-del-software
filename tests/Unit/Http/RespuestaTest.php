<?php

/**
 * CP-HTTP — Respuesta JSON uniforme y ejecutor que convierte excepciones en respuestas.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Rubicam\Http\ErrorHttp;
use Rubicam\Http\Ejecutor;
use Rubicam\Http\Respuesta;

/**
 * @group http
 */
final class RespuestaTest extends TestCase
{
    public function testJsonTieneEstadoMensajeYDatosExtra(): void
    {
        $respuesta = Respuesta::json('success', 'Listo', 201, ['data' => ['id' => 7]]);

        $this->assertSame(201, $respuesta->codigoHttp());
        $this->assertSame(
            ['status' => 'success', 'message' => 'Listo', 'data' => ['id' => 7]],
            json_decode($respuesta->cuerpo(), true)
        );
    }

    public function testLasCabecerasEvitanCacheYSniffing(): void
    {
        $cabeceras = Respuesta::json('success', 'ok')->cabeceras();

        $this->assertSame('application/json; charset=utf-8', $cabeceras['Content-Type']);
        $this->assertSame('no-store', $cabeceras['Cache-Control']);
        $this->assertSame('nosniff', $cabeceras['X-Content-Type-Options']);
    }

    public function testConservaTildesYBarrasSinEscapar(): void
    {
        $cuerpo = Respuesta::json('error', 'Sesión expirada / reintente')->cuerpo();

        $this->assertStringContainsString('Sesión expirada / reintente', $cuerpo);
    }

    public function testUtf8InvalidoNoRompeLaRespuesta(): void
    {
        $respuesta = Respuesta::json('error', "mal\xB1formado");

        $this->assertIsArray(json_decode($respuesta->cuerpo(), true));
    }

    public function testEjecutorConvierteErrorHttpEnRespuestaConSuCodigo(): void
    {
        $respuesta = (new Ejecutor())->resolver(static function (): Respuesta {
            throw new ErrorHttp('Estado no permitido.', 422, ['errors' => ['estado' => 'x']]);
        });

        $this->assertSame(422, $respuesta->codigoHttp());
        $cuerpo = json_decode($respuesta->cuerpo(), true);
        $this->assertSame('error', $cuerpo['status']);
        $this->assertSame('Estado no permitido.', $cuerpo['message']);
        $this->assertSame(['estado' => 'x'], $cuerpo['errors']);
    }

    public function testEjecutorOcultaLosDetallesInternosDeUnaExcepcionInesperada(): void
    {
        $respuesta = (new Ejecutor())->resolver(static function (): Respuesta {
            throw new RuntimeException('SQLSTATE[HY000] password=secreto');
        });

        $this->assertSame(500, $respuesta->codigoHttp());
        $this->assertStringNotContainsString('secreto', $respuesta->cuerpo());
        $this->assertStringNotContainsString('SQLSTATE', $respuesta->cuerpo());
    }

    public function testEjecutorDevuelveLaRespuestaDelControladorSiTodoSaleBien(): void
    {
        $esperada  = Respuesta::json('success', 'Hecho');
        $respuesta = (new Ejecutor())->resolver(static fn (): Respuesta => $esperada);

        $this->assertSame($esperada, $respuesta);
    }
}
