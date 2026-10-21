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

    public function testElTokenCsrfEsEstableDentroDeLaSesionYTiene64Hex(): void
    {
        $primero = SesionPanel::tokenCsrf();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $primero);
        $this->assertSame($primero, SesionPanel::tokenCsrf());
    }

    public function testSoloElTokenEmitidoEsValido(): void
    {
        $token = SesionPanel::tokenCsrf();

        $this->assertTrue(SesionPanel::csrfValido($token));
        $this->assertFalse(SesionPanel::csrfValido($token . 'x'));
        $this->assertFalse(SesionPanel::csrfValido(''));
        $this->assertFalse(SesionPanel::csrfValido(null));
    }

    public function testSinTokenEmitidoNingunoEsValido(): void
    {
        $this->assertFalse(SesionPanel::csrfValido('cualquiera'));
    }
}
