<?php

/**
 * CP-SES — Estado de sesión del panel: autenticación, permisos por rol y token CSRF.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Seguridad;

use PHPUnit\Framework\TestCase;
use Rubicam\Seguridad\SesionPanel;

/**
 * @group seguridad
 */
final class SesionPanelTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testSinSesionNoEstaAutenticado(): void
    {
        $this->assertFalse(SesionPanel::autenticado());
    }

    public function testConIdentificadorDeUsuarioEstaAutenticado(): void
    {
        $_SESSION['user_id'] = 7;

        $this->assertTrue(SesionPanel::autenticado());
    }

    /**
     * @dataProvider roles
     */
    public function testPuedeEditarSegunElRol(?string $rol, bool $esperado): void
    {
        if ($rol !== null) {
            $_SESSION['rol'] = $rol;
        }

        $this->assertSame($esperado, SesionPanel::puedeEditar());
    }

    /**
     * @return array<string, array{?string, bool}>
     */
    public function roles(): array
    {
        return [
            'admin'     => ['admin', true],
            'operador'  => ['operador', true],
            'consulta'  => ['consulta', false],
            'sin rol'   => [null, false],
        ];
    }

    public function testElNombreSeTomaDeLaSesionConAlternativasSeguras(): void
    {
        $this->assertSame('Usuario', SesionPanel::nombreUsuario());

        $_SESSION['usuario'] = 'agomez';
        $this->assertSame('agomez', SesionPanel::nombreUsuario());

        $_SESSION['nombre_completo'] = 'Ana Gómez';
        $this->assertSame('Ana Gómez', SesionPanel::nombreUsuario());
    }

    public function testLosDatosDelUsuarioSalenDeLaSesion(): void
    {
        $_SESSION = [
            'usuario'         => 'aperez',
            'nombre_completo' => 'Ana Pérez',
            'rol'             => 'operador',
            'sucursal'        => 'Cúcuta',
        ];

        $this->assertSame('Ana Pérez', SesionPanel::nombreUsuario());
        $this->assertSame('aperez', SesionPanel::usuarioLogin());
        $this->assertSame('operador', SesionPanel::rol());
        $this->assertSame('Cúcuta', SesionPanel::sucursal());
    }

    public function testSinNombreCompletoSeUsaElUsuarioYSinNadaUnNombreGenerico(): void
    {
        $_SESSION = ['usuario' => 'aperez'];
        $this->assertSame('aperez', SesionPanel::nombreUsuario());

        $_SESSION = [];
        $this->assertSame('Usuario', SesionPanel::nombreUsuario());
        $this->assertSame('', SesionPanel::rol());
        $this->assertSame('', SesionPanel::usuarioLogin());
        $this->assertSame('', SesionPanel::sucursal());
    }
}
