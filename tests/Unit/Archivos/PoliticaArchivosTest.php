<?php

/**
 * CP-ARC — Política de archivos del legajo: extensión, firma interna, nombre seguro y ruta confinada.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Archivos;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rubicam\Archivos\PoliticaArchivos;
use ZipArchive;

/**
 * @group seguridad
 */
final class PoliticaArchivosTest extends TestCase
{
    /** @var list<string> */
    private array $temporales = [];

    protected function tearDown(): void
    {
        foreach ($this->temporales as $ruta) {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
    }

    private function archivo(string $contenido): string
    {
        $ruta = (string) tempnam(sys_get_temp_dir(), 'rubicam_arc_');
        file_put_contents($ruta, $contenido);
        $this->temporales[] = $ruta;

        return $ruta;
    }

    private const PNG_1X1 =
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYG'
        . 'AAAAAEAAGjChXjAAAAAElFTkSuQmCC';
    private const JPG_1X1 =
        '/9j/4AAQSkZJRgABAQEAYABgAAD//gA+Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLC'
        . 'BkZWZhdWx0IHF1YWxpdHkK/9sAQwAIBgYHBgUIBwcHCQkICgwUDQwLCwwZEhMPFB0aHx4dGhwcICQuJyAiLCMcHCg3'
        . 'KSwwMTQ0NB8nOT04MjwuMzQy/9sAQwEJCQkMCwwYDQ0YMiEcITIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMj'
        . 'IyMjIyMjIyMjIyMjIyMjIyMjIy/8AAEQgAAQABAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYH'
        . 'CAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGB'
        . 'kaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Sl'
        . 'pqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQ'
        . 'EAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkj'
        . 'M1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4'
        . 'iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/a'
        . 'AAwDAQACEQMRAD8A+f6KKKAP/9k=';

    /**
     * @dataProvider extensiones
     */
    public function testExtensionPermitida(string $extension, bool $esperado): void
    {
        $this->assertSame($esperado, PoliticaArchivos::extensionPermitida($extension));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public function extensiones(): array
    {
        return [
            'pdf'                => ['pdf', true],
            'jpg'                => ['jpg', true],
            'jpeg'               => ['jpeg', true],
            'png'                => ['png', true],
            'docx'               => ['docx', true],
            'php no se permite'  => ['php', false],
            'exe no se permite'  => ['exe', false],
            'html no se permite' => ['html', false],
            'vacía'              => ['', false],
        ];
    }

    public function testFirmaDePdfPngYJpg(): void
    {
        $this->assertTrue(PoliticaArchivos::firmaValida($this->archivo("%PDF-1.7\n..."), 'pdf'));
        $png = $this->archivo((string) base64_decode(self::PNG_1X1));
        $jpg = $this->archivo((string) base64_decode(self::JPG_1X1));

        $this->assertTrue(PoliticaArchivos::firmaValida($png, 'png'));
        $this->assertTrue(PoliticaArchivos::firmaValida($jpg, 'jpg'));
    }

    public function testUnArchivoConExtensionFalsaEsRechazado(): void
    {
        $php = $this->archivo('<?php system($_GET["c"]);');

        foreach (['pdf', 'png', 'jpg', 'jpeg', 'docx'] as $extension) {
            $this->assertFalse(PoliticaArchivos::firmaValida($php, $extension), "Se coló un PHP como .{$extension}");
        }
    }

    public function testUnPdfNoPuedePasarPorPng(): void
    {
        $this->assertFalse(PoliticaArchivos::firmaValida($this->archivo("%PDF-1.7\n"), 'png'));
    }

    public function testDocxDebeSerUnZipConDocumentXml(): void
    {
        $valido = $this->archivo('');
        $zip = new ZipArchive();
        $zip->open($valido, ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<w:document/>');
        $zip->close();

        $otroZip = $this->archivo('');
        $zip = new ZipArchive();
        $zip->open($otroZip, ZipArchive::OVERWRITE);
        $zip->addFromString('otro.txt', 'hola');
        $zip->close();

        $this->assertTrue(PoliticaArchivos::firmaValida($valido, 'docx'));
        $this->assertFalse(PoliticaArchivos::firmaValida($otroZip, 'docx'));
        $this->assertFalse(PoliticaArchivos::firmaValida($this->archivo('texto plano'), 'docx'));
    }

    public function testExtensionDesconocidaNuncaTieneFirmaValida(): void
    {
        $this->assertFalse(PoliticaArchivos::firmaValida($this->archivo('lo que sea'), 'exe'));
    }

    /**
     * @dataProvider nombresOriginales
     */
    public function testLimpiarNombreOriginal(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, PoliticaArchivos::limpiarNombreOriginal($entrada));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function nombresOriginales(): array
    {
        return [
            'nombre normal'                => ['Factura 123.pdf', 'Factura 123.pdf'],
            'ruta de Windows'              => ['C:\\Users\\ana\\factura.pdf', 'factura.pdf'],
            'recorrido de directorios'     => ['../../etc/passwd', 'passwd'],
            'caracteres peligrosos'        => ['a"b<c>d|e:f*g?.pdf', 'abcdefg.pdf'],
            'caracteres de control'        => ["fac\x00tura\n.pdf", 'factura.pdf'],
            'vacío cae a un nombre genérico' => ['', 'documento'],
            'solo caracteres peligrosos'   => ['<>:"', 'documento'],
        ];
    }

    public function testUnNombreMuyLargoSeRecortaConservandoLaExtension(): void
    {
        $nombre = PoliticaArchivos::limpiarNombreOriginal(str_repeat('a', 400) . '.pdf');

        $this->assertLessThanOrEqual(200, mb_strlen($nombre));
        $this->assertStringEndsWith('.pdf', $nombre);
    }

    public function testNombreSeguroNoDependeDelNombreOriginalDelUsuario(): void
    {
        $nombre = PoliticaArchivos::nombreSeguro(
            'ctg26-imp 0841',
            'Factura Comercial',
            'jpeg',
            new DateTimeImmutable('2026-10-21 16:50:07'),
            'a1b2c3'
        );

        $this->assertSame('CTG26IMP0841_FACTURA_20261021-165007_a1b2c3.jpg', $nombre);
    }

    public function testNombreSeguroUsaDoSiElCodigoQuedaVacio(): void
    {
        $ahora  = new DateTimeImmutable('2026-01-02 03:04:05');
        $nombre = PoliticaArchivos::nombreSeguro('!!!', 'Otro', 'pdf', $ahora, 'ffffff');

        $this->assertStringStartsWith('DO_OTRO_', $nombre);
    }

    public function testRutaFisicaSiempreQuedaDentroDeLaCarpetaBase(): void
    {
        $base = '/srv/uploads/';

        $this->assertSame('/srv/uploads/DO1_RUT.pdf', PoliticaArchivos::rutaFisica($base, 'uploads/DO1_RUT.pdf'));
        $this->assertSame('/srv/uploads/passwd', PoliticaArchivos::rutaFisica($base, '../../etc/passwd'));
        $this->assertSame('/srv/uploads/passwd', PoliticaArchivos::rutaFisica($base, '..\\..\\etc\\passwd'));
        $this->assertNull(PoliticaArchivos::rutaFisica($base, ''));
        $this->assertNull(PoliticaArchivos::rutaFisica($base, '..'));
        $this->assertNull(PoliticaArchivos::rutaFisica($base, 'uploads/'));
    }

    public function testMensajeDeErrorDeSubidaEsEspecificoParaCadaCodigo(): void
    {
        $mensajes = [];
        foreach ([UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_CANT_WRITE, 999] as $codigo) {
            $mensajes[] = PoliticaArchivos::mensajeErrorSubida($codigo);
        }

        $this->assertCount(5, array_unique($mensajes));
        $this->assertStringContainsString('Seleccione un archivo', $mensajes[2]);
    }
}
