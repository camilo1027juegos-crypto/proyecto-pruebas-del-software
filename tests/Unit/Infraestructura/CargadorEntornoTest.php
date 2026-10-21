<?php

/**
 * MR-008 (mantenimiento adaptativo) — Lectura de un archivo .env para configurar la aplicación sin editar código.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Infraestructura;

use PHPUnit\Framework\TestCase;
use Rubicam\Infraestructura\CargadorEntorno;
use Rubicam\Infraestructura\Configuracion;

/**
 * @group MR-008
 * @group infraestructura
 */
final class CargadorEntornoTest extends TestCase
{
    private const VARIABLE = 'RUBICAM_PRUEBA_ENV';

    protected function tearDown(): void
    {
        putenv(self::VARIABLE);
        putenv('UPLOAD_DIR');
    }

    /**
     * @dataProvider contenidos
     * @param array<string, string> $esperado
     */
    public function testParsearInterpretaElFormatoClaveValor(string $contenido, array $esperado): void
    {
        $this->assertSame($esperado, CargadorEntorno::parsear($contenido));
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public function contenidos(): array
    {
        return [
            'clave simple'                  => ["DB_HOST=localhost\n", ['DB_HOST' => 'localhost']],
            'espacios alrededor del ='      => ["DB_HOST = localhost\n", ['DB_HOST' => 'localhost']],
            'comentarios y líneas vacías'   => ["# comentario\n\n  \nA=1\n", ['A' => '1']],
            'comillas dobles'               => ['MENSAJE="hola mundo"', ['MENSAJE' => 'hola mundo']],
            'comillas simples'              => ["MENSAJE='hola mundo'", ['MENSAJE' => 'hola mundo']],
            'comentario al final'           => ["A=valor # nota\n", ['A' => 'valor']],
            'almohadilla dentro de comillas' => ['A="no # comentario"', ['A' => 'no # comentario']],
            'prefijo export'                => ["export A=1\n", ['A' => '1']],
            'igual dentro del valor'        => ["CLAVE=a=b=c\n", ['CLAVE' => 'a=b=c']],
            'valor vacío'                   => ["A=\n", ['A' => '']],
            'fin de línea de Windows'       => ["A=1\r\nB=2\r\n", ['A' => '1', 'B' => '2']],
            'nombre inválido se ignora'     => ["1ABC=x\nA-B=y\nOK=z\n", ['OK' => 'z']],
            'línea sin igual se ignora'     => ["SOLO_TEXTO\nA=1\n", ['A' => '1']],
        ];
    }

    public function testCargarDefineLasVariablesDelArchivo(): void
    {
        $ruta = $this->archivoTemporal(self::VARIABLE . "=desde_archivo\n");

        $cargadas = CargadorEntorno::cargar($ruta);

        $this->assertSame(1, $cargadas);
        $this->assertSame('desde_archivo', getenv(self::VARIABLE));
        unlink($ruta);
    }

    public function testCargarNoSobrescribeVariablesYaDefinidasEnElServidor(): void
    {
        putenv(self::VARIABLE . '=del_servidor');
        $ruta = $this->archivoTemporal(self::VARIABLE . "=desde_archivo\n");

        $cargadas = CargadorEntorno::cargar($ruta);

        $this->assertSame(0, $cargadas);
        $this->assertSame('del_servidor', getenv(self::VARIABLE));
        unlink($ruta);
    }

    public function testCargarUnArchivoInexistenteNoHaceNada(): void
    {
        $this->assertSame(0, CargadorEntorno::cargar('/ruta/que/no/existe/.env'));
    }

    public function testLaCarpetaDeCargasSeConfiguraPorEntorno(): void
    {
        putenv('UPLOAD_DIR=/var/rubicam/cargas');

        $this->assertSame('/var/rubicam/cargas', Configuracion::carpetaCargas());
    }

    public function testLaCarpetaDeCargasRelativaSeResuelveDesdeLaRaiz(): void
    {
        putenv('UPLOAD_DIR=datos/cargas');

        $this->assertSame(Configuracion::raizProyecto() . '/datos/cargas', Configuracion::carpetaCargas());
    }

    public function testLaCarpetaDeCargasPorDefectoEsUploads(): void
    {
        $this->assertSame(Configuracion::raizProyecto() . '/uploads', Configuracion::carpetaCargas());
    }

    private function archivoTemporal(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($ruta, $contenido);

        return $ruta;
    }
}
