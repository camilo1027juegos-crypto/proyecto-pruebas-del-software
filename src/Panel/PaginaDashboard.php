<?php

/**
 * src/Panel/PaginaDashboard.php
 * -----------------------------------------------------------------------
 * Prepara los datos que necesita la plantilla del panel (panel/vistas/dashboard.php):
 * las DO iniciales, los catálogos y el token CSRF. No imprime HTML.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Panel;

use Closure;
use DateTimeImmutable;
use Rubicam\Dominio\Catalogos;
use Rubicam\Dominio\SemaforoDo;
use Rubicam\Archivos\PoliticaArchivos;
use Rubicam\Seguridad\SesionPanel;
use Rubicam\Seguridad\TokenCsrf;
use Rubicam\Vistas\Iconos;
use Throwable;

final class PaginaDashboard
{
    /**
     * @param Closure():list<array<string, mixed>> $cargarOrdenes
     */
    public function __construct(private readonly Closure $cargarOrdenes)
    {
    }

    /**
     * @return array{puedeEditar: bool, sucursalUsuario: string, sucursalesFiltro: list<string>,
     *               configJs: array<string, mixed>}
     */
    public function datos(?DateTimeImmutable $ahora = null): array
    {
        $ahora ??= new DateTimeImmutable();
        $errorCarga = null;
        $ordenes    = [];

        try {
            $ordenes = ($this->cargarOrdenes)();
        } catch (Throwable $e) {
            error_log('[rubicam-panel] Error cargando DOs: ' . $e->getMessage());
            $errorCarga = 'No fue posible cargar las órdenes de operación. '
                . 'Verifique la conexión con la base de datos.';
        }

        $conocidas  = array_merge(Catalogos::SUCURSALES, array_column($ordenes, 'sucursal'));
        $sucursales = array_values(array_unique($conocidas));
        sort($sucursales);

        $puedeEditar     = SesionPanel::puedeEditar();
        $sucursalUsuario = SesionPanel::sucursal();

        return [
            'puedeEditar'      => $puedeEditar,
            'sucursalUsuario'  => $sucursalUsuario,
            'sucursalesFiltro' => $sucursales,
            'configJs'         => [
                'csrf'             => TokenCsrf::panel()->emitir(),
                'puedeEditar'      => $puedeEditar,
                'hoy'              => $ahora->format('Y-m-d'),
                'generado'         => $ahora->format('d/m/Y H:i'),
                'usuario'          => SesionPanel::nombreUsuario(),
                'sucursalUsuario'  => $sucursalUsuario,
                'estados'          => Catalogos::ESTADOS,
                'estadosFinales'   => Catalogos::ESTADOS_FINALES,
                'sucursales'       => Catalogos::SUCURSALES,
                'regimenes'        => Catalogos::REGIMENES,
                'tiposDocumento'   => Catalogos::TIPOS_DOCUMENTO,
                'prefijosSucursal' => Catalogos::PREFIJOS_SUCURSAL,
                'prefijosRegimen'  => Catalogos::PREFIJOS_REGIMEN,
                'plazoDias'        => SemaforoDo::PLAZO_DIAN_DIAS,
                'maxBytes'         => PoliticaArchivos::TAMANO_MAXIMO_BYTES,
                'iconos'           => Iconos::TRAZOS,
                'dos'              => $ordenes,
                'errorCarga'       => $errorCarga,
            ],
        ];
    }
}
