<?php

/**
 * CP-HDR — Cabeceras HTTP de seguridad del sitio público, del panel y de la pantalla de acceso.
 *
 * MR-009 (mantenimiento preventivo): el panel enviaba la misma política que el sitio público,
 * con script-src 'unsafe-inline'. Un solo fallo de XSS habría permitido ejecutar cualquier script.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Seguridad;

use PHPUnit\Framework\TestCase;
use Rubicam\Seguridad\CabecerasSeguras;

/**
 * @group seguridad
 */
final class CabecerasSegurasTest extends TestCase
{
    /**
     * Valor de una directiva CSP (p. ej. «script-src») dentro de la cabecera.
     */
    private function directiva(string $csp, string $nombre): ?string
    {
        foreach (explode(';', $csp) as $parte) {
            $parte = trim($parte);
            if (str_starts_with($parte, $nombre . ' ') || $parte === $nombre) {
                return trim(substr($parte, strlen($nombre)));
            }
        }

        return null;
    }

    /**
     * @group MR-009
     * @group regresion
     */
    public function testElPanelRestringeLosScriptsAlPropioOrigen(): void
    {
        $csp = CabecerasSeguras::panel()['Content-Security-Policy'] ?? '';

        $this->assertSame("'self'", $this->directiva($csp, 'script-src'));
    }

    /**
     * @group MR-009
     * @group regresion
     */
    public function testElPanelBloqueaObjetosBaseExternaYEnvioDeFormulariosAjenos(): void
    {
        $csp = CabecerasSeguras::panel()['Content-Security-Policy'] ?? '';

        $this->assertSame("'none'", $this->directiva($csp, 'object-src'));
        $this->assertSame("'self'", $this->directiva($csp, 'base-uri'));
        $this->assertSame("'self'", $this->directiva($csp, 'form-action'));
        $this->assertSame("'self'", $this->directiva($csp, 'frame-ancestors'));
        $this->assertSame("'self'", $this->directiva($csp, 'default-src'));
    }

    /**
     * @group MR-009
     * @group regresion
     */
    public function testElPanelNoSeConectaAOrigenesExternos(): void
    {
        $csp = CabecerasSeguras::panel()['Content-Security-Policy'] ?? '';

        $this->assertSame("'self'", $this->directiva($csp, 'connect-src'));
    }

    /**
     * @group MR-009
     * @group regresion
     */
    public function testLaPantallaDeAccesoNoPermiteNingunScript(): void
    {
        $csp = CabecerasSeguras::acceso()['Content-Security-Policy'] ?? '';

        $this->assertSame("'none'", $this->directiva($csp, 'script-src'));
        $this->assertSame("'none'", $this->directiva($csp, 'object-src'));
    }

    public function testElPanelYElAccesoNoSeGuardanEnCache(): void
    {
        foreach ([CabecerasSeguras::panel(), CabecerasSeguras::acceso()] as $cabeceras) {
            $this->assertStringContainsString('no-store', $cabeceras['Cache-Control']);
            $this->assertSame('nosniff', $cabeceras['X-Content-Type-Options']);
            $this->assertSame('SAMEORIGIN', $cabeceras['X-Frame-Options']);
        }
    }

    public function testElSitioPublicoPermiteSoloElOrigenDelAsistenteParaConectarse(): void
    {
        $cabeceras = CabecerasSeguras::publico('https://n8n.ejemplo.com/webhook/chat?x=1', false);

        $this->assertSame(
            "'self' https://n8n.ejemplo.com",
            $this->directiva($cabeceras['Content-Security-Policy'], 'connect-src')
        );
        $this->assertArrayNotHasKey('Strict-Transport-Security', $cabeceras);
    }

    public function testElSitioPublicoSinAsistenteSoloPermiteSuOrigen(): void
    {
        $cabeceras = CabecerasSeguras::publico('', false);

        $this->assertSame("'self'", $this->directiva($cabeceras['Content-Security-Policy'], 'connect-src'));
    }

    public function testConHttpsSeActivaHsts(): void
    {
        $cabeceras = CabecerasSeguras::publico('', true);

        $this->assertStringContainsString('max-age=31536000', $cabeceras['Strict-Transport-Security']);
    }
}
