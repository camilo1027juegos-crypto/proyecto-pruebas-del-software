<?php

/**
 * MR-010 (mantenimiento ADITIVO / perfectivo, ISO/IEC/IEEE 14764) — Exportar las operaciones a CSV y JSON.
 *
 * Requisitos: abrir bien en Excel en español (BOM UTF-8 y «;»), no permitir inyección de fórmulas
 * (CSV injection), conservar tildes y respetar los filtros del panel.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Panel;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rubicam\Panel\ExportadorDo;

/**
 * @group MR-010
 */
final class ExportadorDoTest extends TestCase
{
    /**
     * @param array<string, mixed> $cambios
     * @return array<string, mixed>
     */
    private function orden(array $cambios = []): array
    {
        return array_replace_recursive([
            'id'                 => 1,
            'do_codigo'          => 'BOG26IMP0001',
            'cliente'            => 'Importadora Andina S.A.S.',
            'nit'                => '900123456-8',
            'regimen'            => 'Importación',
            'sucursal'           => 'Bogotá',
            'estado'             => 'En trámite',
            'fecha_apertura'     => '2026-10-01',
            'fecha_vencimiento'  => '2026-12-30',
            'ultima_observacion' => 'Se presentó la declaración',
            'semaforo'           => ['nivel' => 'al_dia', 'dias_transcurridos' => 20],
        ], $cambios);
    }

    /**
     * @return list<list<string>>
     */
    private function leerCsv(string $csv): array
    {
        $csv = (string) preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $filas = [];
        $flujo = fopen('php://memory', 'r+');
        fwrite($flujo, $csv);
        rewind($flujo);
        while (($fila = fgetcsv($flujo, 0, ';', '"', '')) !== false) {
            $filas[] = $fila;
        }
        fclose($flujo);

        return $filas;
    }

    public function testElCsvEmpiezaConBomUtf8ParaQueExcelRespeteLasTildes(): void
    {
        $csv = ExportadorDo::aCsv([$this->orden()]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Importación', $csv);
    }

    public function testElCsvTieneEncabezadoYUnaFilaPorOperacion(): void
    {
        $filas = $this->leerCsv(ExportadorDo::aCsv([
            $this->orden(),
            $this->orden(['id' => 2, 'do_codigo' => 'CTG26EXP0002']),
        ]));

        $this->assertCount(3, $filas);
        $this->assertSame(ExportadorDo::COLUMNAS, $filas[0]);
        $this->assertSame('BOG26IMP0001', $filas[1][0]);
        $this->assertSame('CTG26EXP0002', $filas[2][0]);
    }

    public function testLosValoresConSeparadorComillasOSaltosDeLineaSeEscapan(): void
    {
        $filas = $this->leerCsv(ExportadorDo::aCsv([
            $this->orden(['cliente' => 'Andina; "Ltda"', 'ultima_observacion' => "línea 1\nlínea 2"]),
        ]));

        $this->assertCount(2, $filas);
        $this->assertSame('Andina; "Ltda"', $filas[1][1]);
        $this->assertSame("línea 1\nlínea 2", $filas[1][ExportadorDo::posicionDe('ultima_observacion')]);
    }

    /**
     * @dataProvider valoresPeligrosos
     */
    public function testLasFormulasSeNeutralizan(string $peligroso): void
    {
        $filas = $this->leerCsv(ExportadorDo::aCsv([$this->orden(['cliente' => $peligroso])]));

        $this->assertSame("'" . $peligroso, $filas[1][1]);
    }

    /**
     * @return array<string, array{string}>
     */
    public function valoresPeligrosos(): array
    {
        return [
            'igual'      => ['=HYPERLINK("http://malo","clic")'],
            'más'        => ['+cmd|\' /C calc\'!A0'],
            'menos'      => ['-2+3'],
            'arroba'     => ['@SUM(1+1)'],
            'tabulación' => ["\t=1+1"],
            'retorno'    => ["\r=1+1"],
        ];
    }

    public function testUnTextoNormalNoSeAlteraNiSiContieneUnSignoALMedio(): void
    {
        $filas = $this->leerCsv(ExportadorDo::aCsv([$this->orden(['cliente' => 'A+B = C'])]));

        $this->assertSame('A+B = C', $filas[1][1]);
    }

    public function testElCsvIncluyeElSemaforo(): void
    {
        $filas = $this->leerCsv(ExportadorDo::aCsv([
            $this->orden(['semaforo' => ['nivel' => 'critico', 'dias_transcurridos' => 80]]),
        ]));

        $this->assertSame('critico', $filas[1][ExportadorDo::posicionDe('nivel_alerta')]);
        $this->assertSame('80', $filas[1][ExportadorDo::posicionDe('dias_transcurridos')]);
    }

    public function testSinOperacionesElCsvSoloTraeElEncabezado(): void
    {
        $this->assertCount(1, $this->leerCsv(ExportadorDo::aCsv([])));
    }

    public function testElJsonTieneTotalFechaYOperacionesConTildes(): void
    {
        $json = ExportadorDo::aJson([$this->orden()], new DateTimeImmutable('2026-10-21 17:10:00'));
        $datos = json_decode($json, true);

        $this->assertSame(1, $datos['total']);
        $this->assertSame('2026-10-21T17:10:00', substr($datos['generado'], 0, 19));
        $this->assertSame('Importación', $datos['operaciones'][0]['regimen']);
        $this->assertStringContainsString('Importación', $json, 'No debe escapar las tildes como \\u00f3.');
    }

    public function testElJsonNoExponeCamposInternosComoLaBitacoraCompleta(): void
    {
        $datos = json_decode(ExportadorDo::aJson(
            [$this->orden(['bitacora' => [['texto' => 'x']], 'email' => 'a@b.co'])],
            new DateTimeImmutable()
        ), true);

        $this->assertArrayNotHasKey('bitacora', $datos['operaciones'][0]);
        $this->assertSame(
            array_values(array_diff(ExportadorDo::COLUMNAS, ['nivel_alerta', 'dias_transcurridos'])),
            array_values(array_diff(array_keys($datos['operaciones'][0]), ['semaforo']))
        );
    }

    /**
     * @dataProvider filtros
     * @param array<string, string> $criterios
     * @param list<string> $esperados
     */
    public function testFiltrar(array $criterios, array $esperados): void
    {
        $ordenes = [
            $this->orden(['do_codigo' => 'A', 'sucursal' => 'Bogotá', 'regimen' => 'Importación']),
            $this->orden(['do_codigo' => 'B', 'sucursal' => 'Cartagena', 'regimen' => 'Exportación']),
            $this->orden([
                'do_codigo' => 'C',
                'sucursal'  => 'Bogotá',
                'regimen'   => 'Exportación',
                'semaforo'  => ['nivel' => 'critico'],
            ]),
        ];

        $codigos = array_column(ExportadorDo::filtrar($ordenes, $criterios), 'do_codigo');

        $this->assertSame($esperados, $codigos);
    }

    /**
     * @return array<string, array{array<string, string>, list<string>}>
     */
    public function filtros(): array
    {
        return [
            'sin criterios'           => [[], ['A', 'B', 'C']],
            'criterios vacíos'        => [['sucursal' => '', 'regimen' => ''], ['A', 'B', 'C']],
            'por sucursal'            => [['sucursal' => 'Bogotá'], ['A', 'C']],
            'por régimen'             => [['regimen' => 'Exportación'], ['B', 'C']],
            'por nivel'               => [['nivel' => 'critico'], ['C']],
            'combinados'              => [['sucursal' => 'Bogotá', 'regimen' => 'Exportación'], ['C']],
            'criterio desconocido'    => [['inventado' => 'x'], ['A', 'B', 'C']],
            'sin coincidencias'       => [['sucursal' => 'Cali'], []],
        ];
    }

    public function testNombreDeArchivoLlevaFechaYExtension(): void
    {
        $ahora = new DateTimeImmutable('2026-10-21 17:10:00');

        $this->assertSame('rubicam_dos_20261021-1710.csv', ExportadorDo::nombreArchivo('csv', $ahora));
        $this->assertSame('rubicam_dos_20261021-1710.json', ExportadorDo::nombreArchivo('json', $ahora));
    }
}
