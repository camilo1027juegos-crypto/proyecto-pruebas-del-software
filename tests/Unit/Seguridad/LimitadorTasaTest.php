<?php

/**
 * CP-RATE — Limitador de tasa por cubo y clave (cotizador y, luego, inicio de sesión).
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Seguridad;

use PHPUnit\Framework\TestCase;
use Rubicam\Seguridad\LimitadorTasa;

/**
 * @group seguridad
 */
final class LimitadorTasaTest extends TestCase
{
    private string $directorio;
    private int $ahora = 1_000_000;

    protected function setUp(): void
    {
        $this->directorio = sys_get_temp_dir() . '/rubicam_rate_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directorio . '/*') ?: [] as $archivo) {
            unlink($archivo);
        }
        if (is_dir($this->directorio)) {
            rmdir($this->directorio);
        }
    }

    private function limitador(int $maximo = 3, int $ventana = 60): LimitadorTasa
    {
        return new LimitadorTasa($this->directorio, $maximo, $ventana, fn (): int => $this->ahora);
    }

    public function testPermiteHastaElMaximoYLuegoBloquea(): void
    {
        $limitador = $this->limitador(3);

        $this->assertTrue($limitador->intentar('quote', '1.1.1.1'));
        $this->assertTrue($limitador->intentar('quote', '1.1.1.1'));
        $this->assertTrue($limitador->intentar('quote', '1.1.1.1'));
        $this->assertFalse($limitador->intentar('quote', '1.1.1.1'));
    }

    public function testLaVentanaSeReiniciaCuandoExpira(): void
    {
        $limitador = $this->limitador(1, 60);
        $this->assertTrue($limitador->intentar('quote', 'a'));
        $this->assertFalse($limitador->intentar('quote', 'a'));

        $this->ahora += 61;

        $this->assertTrue($limitador->intentar('quote', 'a'));
    }

    public function testLasClavesYLosCubosSonIndependientes(): void
    {
        $limitador = $this->limitador(1);
        $this->assertTrue($limitador->intentar('quote', 'a'));

        $this->assertTrue($limitador->intentar('quote', 'b'), 'Otra clave (IP) tiene su propio contador.');
        $this->assertTrue($limitador->intentar('login', 'a'), 'Otro cubo tiene su propio contador.');
    }

    public function testEstaBloqueadoNoConsumeIntentos(): void
    {
        $limitador = $this->limitador(2);

        for ($i = 0; $i < 10; $i++) {
            $this->assertFalse($limitador->estaBloqueado('login', 'ana'));
        }
        $limitador->registrar('login', 'ana');
        $limitador->registrar('login', 'ana');

        $this->assertTrue($limitador->estaBloqueado('login', 'ana'));
    }

    public function testReiniciarLevantaElBloqueo(): void
    {
        $limitador = $this->limitador(1);
        $limitador->registrar('login', 'ana');
        $this->assertTrue($limitador->estaBloqueado('login', 'ana'));

        $limitador->reiniciar('login', 'ana');

        $this->assertFalse($limitador->estaBloqueado('login', 'ana'));
    }

    public function testUnArchivoCorruptoNoImpideElServicio(): void
    {
        $limitador = $this->limitador(1);
        $limitador->intentar('quote', 'a');
        foreach (glob($this->directorio . '/*.json') ?: [] as $archivo) {
            file_put_contents($archivo, '{no es json');
        }

        $this->assertTrue($limitador->intentar('quote', 'a'));
    }

    public function testElNombreDelCuboNoPuedeEscaparDelDirectorio(): void
    {
        $limitador = $this->limitador(1);

        $limitador->intentar('../../fuera', '../../etc/passwd');

        $this->assertDirectoryExists($this->directorio);
        $this->assertCount(1, glob($this->directorio . '/*.json') ?: []);
        $this->assertFileDoesNotExist(dirname($this->directorio, 2) . '/fuera_' . md5('../../etc/passwd') . '.json');
    }

    public function testSiNoPuedeEscribirDejaPasarLaSolicitud(): void
    {
        $archivoComoDirectorio = tempnam(sys_get_temp_dir(), 'rubicam_');
        $limitador = new LimitadorTasa((string) $archivoComoDirectorio . '/sub', 1, 60, fn (): int => $this->ahora);

        $permitido = @$limitador->intentar('quote', 'a');

        $this->assertTrue($permitido, 'Fallo abierto: no bloquear al usuario por un error nuestro.');

        unlink((string) $archivoComoDirectorio);
    }
}
