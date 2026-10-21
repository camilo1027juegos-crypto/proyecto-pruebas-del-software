<?php

/**
 * CP-GUA — Controles de acceso del panel, en orden: sesión (401) → método (405) → CSRF (403) → rol (403).
 * (Pruebas de CARACTERIZACIÓN: antes solo las cubría el E2E; ver E2E-02, 08, 13 y 21.)
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Panel;

use PHPUnit\Framework\TestCase;
use Rubicam\Http\ErrorHttp;
use Rubicam\Http\Solicitud;
use Rubicam\Panel\Guardia;
use Rubicam\Seguridad\TokenCsrf;

/**
 * @group panel
 */
final class GuardiaTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testSinSesionResponde401(): void
    {
        $this->assertCodigo(401, static fn () => Guardia::sesion());
    }

    public function testConSesionPermite(): void
    {
        $_SESSION['user_id'] = 3;

        Guardia::sesion();
        $this->addToAssertionCount(1);
    }

    public function testElMetodoDebeSerPost(): void
    {
        $this->assertCodigo(405, fn () => Guardia::metodoPost($this->solicitud('GET')));

        Guardia::metodoPost($this->solicitud('POST'));
        $this->addToAssertionCount(1);
    }

    public function testElTokenCsrfDebeLlegarEnLaCabecera(): void
    {
        $token = TokenCsrf::panel()->emitir();

        $this->assertCodigo(403, fn () => Guardia::csrf($this->solicitud('POST')));
        $this->assertCodigo(403, fn () => Guardia::csrf($this->solicitud('POST', ['HTTP_X_CSRF_TOKEN' => 'falso'])));
        $this->assertCodigo(403, fn () => Guardia::csrf($this->solicitud('POST', [], ['csrf_token' => $token])));

        Guardia::csrf($this->solicitud('POST', ['HTTP_X_CSRF_TOKEN' => $token]));
        $this->addToAssertionCount(1);
    }

    public function testUnFormularioClasicoPuedeLlevarElTokenEnElCampo(): void
    {
        $token = TokenCsrf::panel()->emitir();

        $this->assertCodigo(403, fn () => Guardia::csrfEnCabeceraOFormulario($this->solicitud('POST')));

        Guardia::csrfEnCabeceraOFormulario($this->solicitud('POST', [], ['csrf_token' => $token]));
        Guardia::csrfEnCabeceraOFormulario($this->solicitud('POST', ['HTTP_X_CSRF_TOKEN' => $token]));
        $this->addToAssertionCount(2);
    }

    /**
     * @dataProvider roles
     */
    public function testSoloAdminYOperadorPuedenEditar(string $rol, bool $puede): void
    {
        $_SESSION['rol'] = $rol;

        if ($puede) {
            Guardia::rolDeEdicion('Sin permiso');
            $this->addToAssertionCount(1);

            return;
        }
        $this->assertCodigo(403, static fn () => Guardia::rolDeEdicion('Sin permiso'));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public function roles(): array
    {
        return ['admin' => ['admin', true], 'operador' => ['operador', true], 'consulta' => ['consulta', false],
            'rol desconocido' => ['invitado', false], 'sin rol' => ['', false]];
    }

    public function testEscrituraAplicaLosTresControlesEnOrden(): void
    {
        $_SESSION['rol'] = 'consulta';
        $token           = TokenCsrf::panel()->emitir();
        $cabecera        = ['HTTP_X_CSRF_TOKEN' => $token];

        $this->assertCodigo(405, fn () => Guardia::escritura($this->solicitud('GET', $cabecera), 'x'));
        $this->assertCodigo(403, fn () => Guardia::escritura($this->solicitud('POST'), 'x'));
        $this->assertCodigo(403, fn () => Guardia::escritura($this->solicitud('POST', $cabecera), 'x'), 'rol consulta');

        $_SESSION['rol'] = 'operador';
        Guardia::escritura($this->solicitud('POST', $cabecera), 'x');
        $this->addToAssertionCount(1);
    }

    public function testElMensajeSinPermisoLlegaAlUsuario(): void
    {
        $_SESSION['rol'] = 'consulta';

        try {
            Guardia::rolDeEdicion('Su rol no permite crear órdenes.');
            $this->fail('Debió lanzar ErrorHttp');
        } catch (ErrorHttp $error) {
            $this->assertSame('Su rol no permite crear órdenes.', $error->getMessage());
        }
    }

    /**
     * @param array<string, string> $servidor
     * @param array<string, string> $formulario
     */
    private function solicitud(string $metodo, array $servidor = [], array $formulario = []): Solicitud
    {
        return new Solicitud(
            array_merge(['REQUEST_METHOD' => $metodo], $servidor),
            [],
            $formulario,
            [],
            static fn (): string => ''
        );
    }

    private function assertCodigo(int $esperado, callable $accion, string $mensaje = ''): void
    {
        try {
            $accion();
        } catch (ErrorHttp $error) {
            $this->assertSame($esperado, $error->codigoHttp(), $mensaje);

            return;
        }
        $this->fail('Debió lanzar ErrorHttp ' . $esperado . ' ' . $mensaje);
    }
}
