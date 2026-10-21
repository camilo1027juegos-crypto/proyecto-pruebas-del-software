<?php

/**
 * Escáner de seguridad estático propio (equivalente en PHP a «bandit» de Python):
 * detecta patrones peligrosos en el código fuente usando el analizador léxico de PHP.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Herramientas;

use PHPUnit\Framework\TestCase;
use Rubicam\Herramientas\EscanerSeguridad;
use Rubicam\Herramientas\Hallazgo;

/**
 * @group herramientas
 */
final class EscanerSeguridadTest extends TestCase
{
    /**
     * @dataProvider codigoPeligroso
     */
    public function testDetectaElPatronPeligroso(string $codigo, string $regla, string $severidad): void
    {
        $hallazgos = EscanerSeguridad::escanearCodigo("<?php\n" . $codigo, 'ejemplo.php');

        $this->assertSame([$regla], $this->reglasDe($hallazgos), 'debe detectar exactamente ' . $regla);
        $this->assertSame($severidad, $hallazgos[0]->severidad);
        $this->assertSame(2, $hallazgos[0]->linea);
        $this->assertSame('ejemplo.php', $hallazgos[0]->archivo);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public function codigoPeligroso(): array
    {
        return [
            'eval'                       => ['eval($codigo);', 'SEG-001', 'alto'],
            'create_function'            => ['$f = create_function(\'$a\', $cuerpo);', 'SEG-001', 'alto'],
            'exec'                       => ['exec($comando);', 'SEG-002', 'alto'],
            'shell_exec'                 => ['shell_exec("ls " . $ruta);', 'SEG-002', 'alto'],
            'system'                     => ['system($comando);', 'SEG-002', 'alto'],
            'proc_open'                  => ['proc_open($comando, $d, $t);', 'SEG-002', 'alto'],
            'comillas invertidas'        => ['$salida = `ls`;', 'SEG-002', 'alto'],
            'unserialize'                => ['$datos = unserialize($entrada);', 'SEG-003', 'medio'],
            'SQL concatenado'            => ['$pdo->query("SELECT * FROM t WHERE id = " . $id);', 'SEG-004', 'medio'],
            'SQL interpolado'            => ['$pdo->query("SELECT * FROM t WHERE id = $id");', 'SEG-004', 'medio'],
            'SQL desde variable'         => ['$pdo->exec($sql);', 'SEG-004', 'medio'],
            'prepare concatenado'        => ['$pdo->prepare(\'SELECT \' . $columnas);', 'SEG-004', 'medio'],
            'clave en variable'          => ['$password = "secreta123";', 'SEG-005', 'alto'],
            'clave en arreglo'           => ['$c = [\'db_pass\' => \'secreta123\'];', 'SEG-005', 'alto'],
            'clave en define'            => ['define(\'API_TOKEN\', \'abc123\');', 'SEG-005', 'alto'],
            'SSL desactivado (peer)'     => ['$o = [CURLOPT_SSL_VERIFYPEER => false];', 'SEG-006', 'alto'],
            'SSL desactivado (host)'     => ['curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);', 'SEG-006', 'alto'],
            'include con variable'       => ['include $pagina;', 'SEG-007', 'medio'],
            'require con variable'       => ['require_once __DIR__ . \'/\' . $modulo . \'.php\';', 'SEG-007', 'medio'],
            'md5'                        => ['$h = md5($clave);', 'SEG-008', 'bajo'],
            'sha1'                       => ['$h = sha1($clave);', 'SEG-008', 'bajo'],
            'mt_rand'                    => ['$token = mt_rand();', 'SEG-008', 'bajo'],
            'superglobal de entrada'     => ['$nombre = $_POST[\'nombre\'];', 'SEG-009', 'medio'],
            'superglobal $_GET'          => ['$id = $_GET[\'id\'];', 'SEG-009', 'medio'],
        ];
    }

    /**
     * @dataProvider codigoSeguro
     */
    public function testNoMarcaCodigoSeguro(string $codigo): void
    {
        $this->assertSame([], EscanerSeguridad::escanearCodigo("<?php\n" . $codigo, 'ejemplo.php'));
    }

    /**
     * @return array<string, array{string}>
     */
    public function codigoSeguro(): array
    {
        return [
            'consulta preparada constante'  => ['$pdo->prepare(\'SELECT * FROM t WHERE id = :id\');'],
            'consulta con marcadores'       => ['$pdo->query(self::SQL_LISTADO);'],
            'clave vacía'                   => ['$config = [\'DB_PASS\' => \'\'];'],
            'clave leída del entorno'       => ['$password = getenv(\'DB_PASS\');'],
            'nombre que no es secreto'      => ['$nombre = "secreta";'],
            'SSL activado'                  => ['$o = [CURLOPT_SSL_VERIFYPEER => true];'],
            'include de ruta fija'          => ['require __DIR__ . \'/vistas/dashboard.php\';'],
            'método llamado exec'           => ['$this->exec($x);'],
            'método propio llamado system'  => ['$sistema->system($x);'],
            'sesión'                        => ['$usuario = $_SESSION[\'usuario\'];'],
            'servidor'                      => ['$ip = $_SERVER[\'REMOTE_ADDR\'];'],
            'hash de contraseña moderno'    => ['$h = password_hash($clave, PASSWORD_DEFAULT);'],
            'bytes aleatorios seguros'      => ['$t = bin2hex(random_bytes(16));'],
            'texto que menciona eval'       => ['$aviso = "no use eval ni exec";'],
            'comentario con eval'           => ['// eval($x) está prohibido'],
        ];
    }

    public function testUnaLineaPuedeSuprimirUnaReglaConMotivo(): void
    {
        $codigo = "<?php\n\$pdo->exec(\$sql); // seguridad:ignorar SEG-004 SQL armado con constantes\n";

        $this->assertSame([], EscanerSeguridad::escanearCodigo($codigo, 'ejemplo.php'));
    }

    public function testElComentarioEnLaLineaAnteriorTambienSuprime(): void
    {
        $codigo = "<?php\n// seguridad:ignorar SEG-004 consulta de solo constantes\n\$pdo->exec(\$sql);\n";

        $this->assertSame([], EscanerSeguridad::escanearCodigo($codigo, 'ejemplo.php'));
    }

    public function testSuprimirUnaReglaNoOcultaOtra(): void
    {
        $codigo = "<?php\neval(\$x); // seguridad:ignorar SEG-004 no es la regla de eval\n";

        $this->assertSame(['SEG-001'], $this->reglasDe(EscanerSeguridad::escanearCodigo($codigo, 'ejemplo.php')));
    }

    public function testLosSuperglobalesSePermitenEnLosArchivosAutorizados(): void
    {
        $codigo = "<?php\n\$_POST['a'];\n";

        $hallazgos = EscanerSeguridad::escanearCodigo($codigo, 'src/Http/Solicitud.php', ['src/Http/Solicitud.php']);

        $this->assertSame([], $hallazgos);
    }

    public function testVariosHallazgosSeOrdenanPorLinea(): void
    {
        $codigo = "<?php\nmd5(\$a);\neval(\$b);\nunserialize(\$c);\n";

        $lineas = array_map(
            static fn (Hallazgo $h): int => $h->linea,
            EscanerSeguridad::escanearCodigo($codigo, 'ejemplo.php')
        );

        $this->assertSame([2, 3, 4], $lineas);
    }

    public function testEscanearRutaRecorreSoloArchivosPhp(): void
    {
        $directorio = sys_get_temp_dir() . '/rubicam_escaner_' . bin2hex(random_bytes(4));
        mkdir($directorio . '/sub', 0775, true);
        file_put_contents($directorio . '/uno.php', "<?php\neval(\$x);\n");
        file_put_contents($directorio . '/sub/dos.php', "<?php\nmd5(\$x);\n");
        file_put_contents($directorio . '/nota.txt', 'eval($x);');

        $hallazgos = EscanerSeguridad::escanearRuta($directorio);

        $this->assertSame(['SEG-001', 'SEG-008'], $this->reglasDe($hallazgos));
        $this->assertStringEndsWith('/uno.php', $hallazgos[0]->archivo);

        unlink($directorio . '/uno.php');
        unlink($directorio . '/sub/dos.php');
        unlink($directorio . '/nota.txt');
        rmdir($directorio . '/sub');
        rmdir($directorio);
    }

    public function testEscanearUnArchivoInexistenteDevuelveVacio(): void
    {
        $this->assertSame([], EscanerSeguridad::escanearRuta('/ruta/que/no/existe'));
    }

    /**
     * @dataProvider umbrales
     */
    public function testElUmbralDecideSiElHallazgoFalla(string $severidad, string $umbral, bool $falla): void
    {
        $hallazgo = new Hallazgo('SEG-X', $severidad, 'a.php', 1, 'mensaje');

        $this->assertSame($falla, EscanerSeguridad::superaUmbral([$hallazgo], $umbral));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public function umbrales(): array
    {
        return [
            'alto con umbral medio'  => ['alto', 'medio', true],
            'medio con umbral medio' => ['medio', 'medio', true],
            'bajo con umbral medio'  => ['bajo', 'medio', false],
            'bajo con umbral bajo'   => ['bajo', 'bajo', true],
            'medio con umbral alto'  => ['medio', 'alto', false],
        ];
    }

    public function testSinHallazgosNuncaFalla(): void
    {
        $this->assertFalse(EscanerSeguridad::superaUmbral([], 'bajo'));
    }

    public function testElInformeDeTextoResumeLosHallazgos(): void
    {
        $informe = EscanerSeguridad::formatearTexto([
            new Hallazgo('SEG-001', 'alto', 'src/a.php', 12, 'Uso de eval'),
            new Hallazgo('SEG-008', 'bajo', 'src/b.php', 3, 'md5 es débil'),
        ]);

        $this->assertStringContainsString('src/a.php:12', $informe);
        $this->assertStringContainsString('[ALTO] SEG-001', $informe);
        $this->assertStringContainsString('Total: 2 hallazgo(s) — alto: 1, medio: 0, bajo: 1', $informe);
    }

    public function testElInformeSinHallazgosLoDice(): void
    {
        $this->assertStringContainsString('Sin hallazgos', EscanerSeguridad::formatearTexto([]));
    }

    /**
     * @param list<Hallazgo> $hallazgos
     * @return list<string>
     */
    private function reglasDe(array $hallazgos): array
    {
        return array_map(static fn (Hallazgo $h): string => $h->regla, $hallazgos);
    }
}
