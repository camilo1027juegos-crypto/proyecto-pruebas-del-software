<?php

/**
 * CP-COT — Formulario de cotización del sitio público (pruebas de CARACTERIZACIÓN: el comportamiento ya
 * existía y estaba cubierto solo por E2E-24…28; se fija aquí sin servidor web ni correo real).
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Publico;

use PHPUnit\Framework\TestCase;
use Rubicam\Http\Solicitud;
use Rubicam\Publico\ControladorCotizacion;
use Rubicam\Seguridad\LimitadorTasa;

/**
 * @group publico
 */
final class ControladorCotizacionTest extends TestCase
{
    private const TOKEN = 'token-de-prueba';

    private string $directorioTasa;

    /** @var list<array{string, string, string, list<string>}> */
    private array $correosEnviados = [];

    private bool $correoFunciona = true;

    protected function setUp(): void
    {
        $this->directorioTasa = sys_get_temp_dir() . '/rubicam_cot_' . bin2hex(random_bytes(4));
        $this->correosEnviados = [];
        $this->correoFunciona  = true;
        $_SESSION = ['csrf_token' => self::TOKEN];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directorioTasa . '/*') ?: [] as $archivo) {
            unlink($archivo);
        }
        if (is_dir($this->directorioTasa)) {
            rmdir($this->directorioTasa);
        }
        $_SESSION = [];
    }

    public function testSoloAceptaPost(): void
    {
        $respuesta = $this->controlador()->procesar($this->solicitud([], 'GET'));

        $this->assertSame(405, $respuesta->codigoHttp());
        $this->assertFalse($this->cuerpoJson($respuesta)['ok']);
    }

    public function testSinTokenCsrfResponde419(): void
    {
        $formulario = $this->formularioValido(['csrf_token' => 'otro']);

        $respuesta = $this->controlador()->procesar($this->solicitud($formulario));

        $this->assertSame(419, $respuesta->codigoHttp());
        $this->assertSame([], $this->correosEnviados);
    }

    public function testElHoneypotSimulaExitoYNoEnviaNada(): void
    {
        $formulario = $this->formularioValido(['website' => 'http://spam']);

        $respuesta = $this->controlador()->procesar($this->solicitud($formulario));

        $this->assertSame(200, $respuesta->codigoHttp());
        $this->assertTrue($this->cuerpoJson($respuesta)['ok']);
        $this->assertSame([], $this->correosEnviados);
    }

    public function testElCuartoEnvioDentroDeLaVentanaResponde429(): void
    {
        $controlador = $this->controlador(3);
        for ($envio = 1; $envio <= 3; $envio++) {
            $this->assertSame(200, $controlador->procesar($this->solicitud($this->formularioValido()))->codigoHttp());
        }

        $this->assertSame(429, $controlador->procesar($this->solicitud($this->formularioValido()))->codigoHttp());
        $this->assertCount(3, $this->correosEnviados);
    }

    public function testUnaSolicitudValidaEnviaElCorreoConCabecerasSeguras(): void
    {
        $respuesta = $this->controlador()->procesar($this->solicitud($this->formularioValido()));

        $this->assertSame(200, $respuesta->codigoHttp());
        $this->assertTrue($this->cuerpoJson($respuesta)['ok']);
        $this->assertCount(1, $this->correosEnviados);

        [$destino, $asunto, $cuerpo, $cabeceras] = $this->correosEnviados[0];
        $this->assertSame('contacto@rubicam.co', $destino);
        $this->assertStringStartsWith('=?UTF-8?B?', $asunto);
        $this->assertStringContainsString('Nombre: Ana Pérez', $cuerpo);
        $this->assertStringContainsString('Valor CIF estimado: USD 1,500.50', $cuerpo);
        $this->assertContains('Reply-To: ana@example.com', $cabeceras);
        $this->assertContains('From: Cotizador Web Rubicam <no-responder@rubicam.co>', $cabeceras);
    }

    public function testElValorCifEsOpcional(): void
    {
        $respuesta = $this->controlador()->procesar($this->solicitud($this->formularioValido(['cif_value' => ''])));

        $this->assertSame(200, $respuesta->codigoHttp());
        $this->assertStringNotContainsString('Valor CIF', $this->correosEnviados[0][2]);
    }

    /**
     * @dataProvider entradasInvalidas
     * @param array<string, string> $cambio
     */
    public function testRechazaEntradasInvalidasConMensajeClaro(array $cambio, string $mensaje): void
    {
        $respuesta = $this->controlador()->procesar($this->solicitud($this->formularioValido($cambio)));

        $this->assertSame(422, $respuesta->codigoHttp());
        $this->assertStringContainsString($mensaje, $this->cuerpoJson($respuesta)['message']);
        $this->assertSame([], $this->correosEnviados, 'una solicitud inválida nunca envía correo');
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public function entradasInvalidas(): array
    {
        return [
            'operación fuera de la lista'        => [['operation' => 'contrabando'], 'tipo de operación'],
            'correo inválido'                    => [['email' => 'no-es-correo'], 'correo electrónico'],
            'inyección de cabeceras en el correo' => [['email' => "a@b.co\nBcc: x@y.z"], 'correo electrónico'],
            'teléfono sin dígitos (MR-002)'      => [['phone' => '-------'], 'teléfono'],
            'nombre vacío'                       => [['name' => ''], 'nombre es obligatorio'],
            'nombre de 201 caracteres'           => [['name' => str_repeat('a', 201)], 'nombre es obligatorio'],
            'valor CIF en notación científica (MR-001)' => [['cif_value' => '1e999'], 'valor CIF'],
            'origen de 121 caracteres (MR-003)'  => [['origin' => str_repeat('o', 121)], 'origen'],
            'destino de 121 caracteres (MR-003)' => [['destination' => str_repeat('d', 121)], 'destino'],
        ];
    }

    public function testOrigenYDestinoDeExactamente120CaracteresSonValidos(): void
    {
        $respuesta = $this->controlador()->procesar($this->solicitud($this->formularioValido([
            'origin'      => str_repeat('o', 120),
            'destination' => str_repeat('d', 120),
        ])));

        $this->assertSame(200, $respuesta->codigoHttp());
    }

    public function testUnFalloDelCorreoResponde500ConElCorreoDeContacto(): void
    {
        $this->correoFunciona = false;
        $registroAnterior     = ini_set('error_log', '/dev/null'); // el fallo se registra en el log

        $respuesta = $this->controlador()->procesar($this->solicitud($this->formularioValido()));
        ini_set('error_log', (string) $registroAnterior);

        $this->assertSame(500, $respuesta->codigoHttp());
        $this->assertStringContainsString('contacto@rubicam.co', $this->cuerpoJson($respuesta)['message']);
    }

    private function controlador(int $maximoEnvios = 5): ControladorCotizacion
    {
        return new ControladorCotizacion(
            new LimitadorTasa($this->directorioTasa, $maximoEnvios, 600),
            function (string $destino, string $asunto, string $cuerpo, array $cabeceras): bool {
                $this->correosEnviados[] = [$destino, $asunto, $cuerpo, $cabeceras];

                return $this->correoFunciona;
            },
            'contacto@rubicam.co'
        );
    }

    /**
     * @param array<string, string> $formulario
     */
    private function solicitud(array $formulario, string $metodo = 'POST'): Solicitud
    {
        return new Solicitud(
            ['REQUEST_METHOD' => $metodo, 'REMOTE_ADDR' => '10.0.0.7'],
            [],
            $formulario,
            [],
            static fn (): string => ''
        );
    }

    /**
     * @param array<string, string> $cambios
     * @return array<string, string>
     */
    private function formularioValido(array $cambios = []): array
    {
        return array_merge([
            'csrf_token'  => self::TOKEN,
            'operation'   => 'importacion',
            'cif_value'   => '1500.50',
            'origin'      => 'Shanghái',
            'destination' => 'Cúcuta',
            'name'        => 'Ana Pérez',
            'email'       => 'ana@example.com',
            'phone'       => '300 123 4567',
        ], $cambios);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function cuerpoJson(\Rubicam\Http\Respuesta $respuesta): array
    {
        return json_decode($respuesta->cuerpo(), true, 512, JSON_THROW_ON_ERROR);
    }
}
