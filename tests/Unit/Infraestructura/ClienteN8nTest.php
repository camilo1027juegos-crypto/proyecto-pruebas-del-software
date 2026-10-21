<?php

/**
 * CP-N8N — Traducción de fallos de red/HTTP del webhook de n8n a mensajes comprensibles para el operador.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Infraestructura;

use PHPUnit\Framework\TestCase;
use Rubicam\Infraestructura\ClienteN8n;

/**
 * @group infraestructura
 */
final class ClienteN8nTest extends TestCase
{
    /**
     * @dataProvider erroresDeCurl
     */
    public function testMensajeDeErrorDeCurl(int $codigo, string $fragmento): void
    {
        $this->assertStringContainsString($fragmento, ClienteN8n::mensajeDeCurl($codigo, 'detalle técnico'));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public function erroresDeCurl(): array
    {
        return [
            'tiempo agotado'      => [CURLE_OPERATION_TIMEDOUT, 'no respondió a tiempo'],
            'sin conexión'        => [CURLE_COULDNT_CONNECT, 'no hay conexión'],
            'DNS'                 => [CURLE_COULDNT_RESOLVE_HOST, 'no hay conexión'],
            'certificado (60)'    => [60, 'verificación SSL'],
            'certificado (77)'    => [77, 'verificación SSL'],
            'error desconocido'   => [999, 'detalle técnico'],
        ];
    }

    /**
     * @dataProvider erroresHttp
     */
    public function testMensajeDeErrorHttp(int $codigo, string $fragmento): void
    {
        $this->assertStringContainsString($fragmento, ClienteN8n::mensajeDeHttp($codigo));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public function erroresHttp(): array
    {
        return [
            '404 workflow inactivo' => [404, 'workflow está activo'],
            '401 autenticación'     => [401, 'rechazó la autenticación'],
            '403 autenticación'     => [403, 'rechazó la autenticación'],
            '500 genérico'          => [500, 'código HTTP 500'],
        ];
    }

    public function testSinUrlValidaNoIntentaConectar(): void
    {
        $resultado = (new ClienteN8n('no es una url', '', ''))->enviar(['evento' => 'x']);

        $this->assertFalse($resultado['enviado']);
        $this->assertNull($resultado['http_code']);
        $this->assertStringContainsString('N8N_WEBHOOK_URL', (string) $resultado['error']);
    }
}
