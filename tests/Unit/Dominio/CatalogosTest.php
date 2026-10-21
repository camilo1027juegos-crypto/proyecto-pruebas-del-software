<?php

/**
 * CP-DOM-01..04 — Catálogos de negocio: una sola fuente de verdad para el panel y los handlers.
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Dominio;

use PHPUnit\Framework\TestCase;
use Rubicam\Dominio\Catalogos;

/**
 * @group dominio
 */
final class CatalogosTest extends TestCase
{
    public function testElEstadoInicialPerteneceAlCatalogoDeEstados(): void
    {
        $this->assertContains(Catalogos::ESTADO_INICIAL, Catalogos::ESTADOS);
    }

    public function testLosEstadosFinalesSonUnSubconjuntoDeLosEstados(): void
    {
        $this->assertSame([], array_diff(Catalogos::ESTADOS_FINALES, Catalogos::ESTADOS));
    }

    public function testCadaSucursalYCadaRegimenTienenPrefijoDeTresLetras(): void
    {
        foreach (Catalogos::SUCURSALES as $sucursal) {
            $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', Catalogos::PREFIJOS_SUCURSAL[$sucursal]);
        }
        foreach (Catalogos::REGIMENES as $regimen) {
            $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', Catalogos::PREFIJOS_REGIMEN[$regimen]);
        }
    }

    /**
     * @dataProvider estadosYSuCondicionDeFinal
     */
    public function testEsEstadoFinal(string $estado, bool $esperado): void
    {
        $this->assertSame($esperado, Catalogos::esEstadoFinal($estado));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public function estadosYSuCondicionDeFinal(): array
    {
        return [
            'en trámite no es final'       => ['En trámite', false],
            'presentada no es final'       => ['Presentada DIAN', false],
            'finalizada es final'          => ['Finalizada', true],
            'nacionalizado es final'       => ['Nacionalizado', true],
            'cerrado sin facturar es final' => ['Cerrado sin facturar', true],
            'estado inventado no es final' => ['Archivado', false],
            'cadena vacía no es final'     => ['', false],
        ];
    }

    /**
     * @dataProvider rolesYSuPermisoDeEdicion
     */
    public function testPuedeEditar(string $rol, bool $esperado): void
    {
        $this->assertSame($esperado, Catalogos::rolPuedeEditar($rol));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public function rolesYSuPermisoDeEdicion(): array
    {
        return [
            'admin edita'            => ['admin', true],
            'operador edita'         => ['operador', true],
            'consulta solo lee'      => ['consulta', false],
            'rol desconocido'        => ['invitado', false],
            'mayúsculas no coinciden' => ['ADMIN', false],
            'vacío'                  => ['', false],
        ];
    }
}
