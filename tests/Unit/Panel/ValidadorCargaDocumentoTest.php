<?php

/**
 * CP-CAR — Primeras barreras de la carga de documentos (el resto —extensión, MIME real y firma— está en
 * PoliticaArchivosTest y E2E-16/17: PHP solo reconoce como «subida real» lo que llega por HTTP).
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Panel;

use PHPUnit\Framework\TestCase;
use Rubicam\Http\ErrorHttp;
use Rubicam\Panel\ValidadorCargaDocumento;

/**
 * @group panel
 */
final class ValidadorCargaDocumentoTest extends TestCase
{
    /**
     * @dataProvider cargasRechazadas
     * @param array<string, mixed>|null $archivo
     */
    public function testRechazaCargasInvalidas(?array $archivo, int $codigo, string $fragmento): void
    {
        try {
            (new ValidadorCargaDocumento())->validar($archivo);
            $this->fail('Debió rechazar la carga');
        } catch (ErrorHttp $error) {
            $this->assertSame($codigo, $error->codigoHttp());
            $this->assertStringContainsString($fragmento, $error->getMessage());
        }
    }

    /**
     * @return array<string, array{array<string, mixed>|null, int, string}>
     */
    public function cargasRechazadas(): array
    {
        return [
            'sin archivo'                      => [null, 422, 'No se recibió'],
            'sin campo error'                  => [['name' => 'a.pdf'], 422, 'No se recibió'],
            'varios archivos (error es lista)' => [['error' => [0, 0], 'name' => ['a', 'b']], 422, 'No se recibió'],
            'excede upload_max_filesize'       => [['error' => UPLOAD_ERR_INI_SIZE, 'name' => 'a.pdf'], 422, ''],
            'ruta temporal inventada'          => [
                ['error' => UPLOAD_ERR_OK, 'name' => 'a.pdf', 'tmp_name' => '/etc/passwd'],
                400,
                'no es válido',
            ],
        ];
    }
}
