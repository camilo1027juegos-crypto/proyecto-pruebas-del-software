<?php

/**
 * MR-005 (mantenimiento correctivo, seguridad) — El .htaccess solo bloqueaba config/ y logs/.
 * En Apache quedaban descargables SQL/rubicam_db.sql (hash de contraseña y datos de clientes),
 * README.md, composer.json/lock y el código fuente.
 *
 * La prueba interpreta las reglas de negación del .htaccess y comprueba rutas sensibles y
 * rutas públicas (CP-SEG-01..10). La verificación contra un Apache real está en
 * docs/05_DESPLIEGUE.md.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Regression;

use PHPUnit\Framework\TestCase;
use Rubicam\Tests\Support\ReglasHtaccess;

/**
 * @group MR-005
 * @group regresion
 */
final class Mr005ArchivosInternosTest extends TestCase
{
    private ReglasHtaccess $reglas;

    protected function setUp(): void
    {
        $this->reglas = ReglasHtaccess::desdeArchivo(dirname(__DIR__, 2) . '/.htaccess');
    }

    /**
     * @dataProvider rutasSensibles
     */
    public function testRutaSensibleEstaBloqueada(string $ruta): void
    {
        $this->assertTrue($this->reglas->deniega($ruta), "'{$ruta}' debería responder 403.");
    }

    /**
     * @return array<string, array{string}>
     */
    public function rutasSensibles(): array
    {
        return [
            'volcado SQL'                  => ['SQL/rubicam_db.sql'],
            'migración SQL'                => ['SQL/migracion_panel_v2.sql'],
            'SQL en minúsculas (Windows)'  => ['sql/rubicam_db.sql'],
            'README'                       => ['README.md'],
            'documentación'                => ['docs/INFORME_TECNICO.md'],
            'composer.json'                => ['composer.json'],
            'composer.lock'                => ['composer.lock'],
            'package-lock'                 => ['package-lock.json'],
            'código fuente'                => ['src/Dominio/Nit.php'],
            'pruebas'                      => ['tests/Unit/NitTest.php'],
            'herramientas'                 => ['tools/escanear_seguridad.php'],
            'dependencias'                 => ['vendor/autoload.php'],
            'repositorio git'              => ['.git/config'],
            'configuración'                => ['config/config.php'],
            'log de errores'               => ['logs/php-error.log'],
            'phpunit'                      => ['phpunit.xml.dist'],
            'phpcs'                        => ['phpcs.xml'],
            'phpmd'                        => ['phpmd.xml'],
            'flujo de CI'                  => ['.github/workflows/ci.yml'],
            'variables de entorno'         => ['.env'],
            'archivo oculto'               => ['.gitignore'],
            'configuración de Tailwind'    => ['tailwind.config.js'],
        ];
    }

    /**
     * @dataProvider rutasPublicas
     */
    public function testRutaPublicaNoSeBloquea(string $ruta): void
    {
        $this->assertFalse($this->reglas->deniega($ruta), "'{$ruta}' debe seguir siendo pública.");
    }

    /**
     * @return array<string, array{string}>
     */
    public function rutasPublicas(): array
    {
        return [
            'landing'                => ['index.php'],
            'login del panel'        => ['panel/index.php'],
            'dashboard'              => ['panel/dashboard.php'],
            'cotizador'              => ['handlers/quote-handler.php'],
            'webhook del panel'      => ['handlers/webhook_trigger.php'],
            'carga de documentos'    => ['handlers/upload_documento.php'],
            'CSS compilado'          => ['assets/css/tailwind.build.css'],
            'JS de la landing'       => ['assets/js/app.js'],
            'JS del panel'           => ['assets/js/panel.js'],
        ];
    }

    /**
     * Defensa en profundidad: si mod_rewrite estuviera desactivado, cada carpeta interna se
     * protege además con su propio .htaccess.
     *
     * @dataProvider carpetasInternas
     */
    public function testCarpetaInternaTienePropioBloqueoTotal(string $carpeta): void
    {
        $archivo = dirname(__DIR__, 2) . '/' . $carpeta . '/.htaccess';
        $this->assertFileExists($archivo, "Falta {$carpeta}/.htaccess");
        $this->assertMatchesRegularExpression('/^Require all denied/m', (string) file_get_contents($archivo));
    }

    /**
     * @return array<string, array{string}>
     */
    public function carpetasInternas(): array
    {
        return [
            'cargas de clientes' => ['uploads'],
            'scripts SQL'        => ['SQL'],
            'código fuente'      => ['src'],
            'pruebas'            => ['tests'],
            'herramientas'       => ['tools'],
            'documentación'      => ['docs'],
            'configuración'      => ['config'],
            'registros'          => ['logs'],
        ];
    }
}
