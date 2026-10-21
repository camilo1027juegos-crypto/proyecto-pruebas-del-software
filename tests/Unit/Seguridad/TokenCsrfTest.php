<?php

/**
 * CP-CSRF — Token anti-CSRF del panel y del formulario público.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Seguridad;

use PHPUnit\Framework\TestCase;
use Rubicam\Seguridad\TokenCsrf;

/**
 * @group seguridad
 */
final class TokenCsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testElTokenEsEstableDentroDeLaSesionYTiene64Hex(): void
    {
        $primero = TokenCsrf::panel()->emitir();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $primero);
        $this->assertSame($primero, TokenCsrf::panel()->emitir());
    }

    public function testSoloElTokenEmitidoEsValido(): void
    {
        $token = TokenCsrf::panel()->emitir();

        $this->assertTrue(TokenCsrf::panel()->valido($token));
        $this->assertFalse(TokenCsrf::panel()->valido($token . 'x'));
        $this->assertFalse(TokenCsrf::panel()->valido(''));
        $this->assertFalse(TokenCsrf::panel()->valido(null));
    }

    public function testSinTokenEmitidoNingunoEsValido(): void
    {
        $this->assertFalse(TokenCsrf::panel()->valido('cualquiera'));
    }

    public function testElTokenDelPanelNoSirveEnElFormularioPublico(): void
    {
        $tokenPanel = TokenCsrf::panel()->emitir();
        TokenCsrf::publico()->emitir();

        $this->assertFalse(TokenCsrf::publico()->valido($tokenPanel));
        $this->assertNotSame($tokenPanel, TokenCsrf::publico()->emitir());
    }
}
