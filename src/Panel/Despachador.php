<?php

/**
 * src/Panel/Despachador.php
 * -----------------------------------------------------------------------
 * Enruta las acciones AJAX de panel/dashboard.php (?action=...) y los dos
 * endpoints de handlers/ hacia su controlador, siempre dentro del Ejecutor
 * (que garantiza una respuesta bien formada) y siempre tras verificar la sesión.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Panel;

use Rubicam\Http\Ejecutor;
use Rubicam\Http\ErrorHttp;
use Rubicam\Http\Respuesta;
use Rubicam\Http\Solicitud;
use Rubicam\Seguridad\SesionPanel;

final class Despachador
{
    private Ensamblador $ensamblador;

    public function __construct(?Ensamblador $ensamblador = null)
    {
        $this->ensamblador = $ensamblador ?? new Ensamblador();
    }

    /** Acciones de panel/dashboard.php?action=... */
    public function atenderAccion(string $accion, Solicitud $solicitud): void
    {
        (new Ejecutor())->ejecutarJson(function () use ($accion, $solicitud): Respuesta {
            SesionPanel::iniciar();
            if ($accion === 'descargar' && !SesionPanel::autenticado()) {
                return Respuesta::redireccion('index.php');
            }
            Guardia::sesion();

            return match ($accion) {
                'listar'     => $this->ensamblador->controladorDo()->listar(),
                'exportar'   => $this->ensamblador->controladorDo()->exportar($solicitud),
                'crear'      => $this->ensamblador->controladorDo()->crear($solicitud),
                'documentos' => $this->ensamblador->controladorDocumentos()->listar($solicitud),
                'descargar'  => $this->ensamblador->controladorDocumentos()->descargar($solicitud),
                default      => throw new ErrorHttp('Acción no reconocida.', 400),
            };
        });
    }

    /** handlers/webhook_trigger.php */
    public function cambiarEstado(Solicitud $solicitud): void
    {
        (new Ejecutor())->ejecutarJson(function () use ($solicitud): Respuesta {
            SesionPanel::iniciar();
            Guardia::sesion();

            return $this->ensamblador->controladorEstado()->cambiar($solicitud);
        });
    }

    /** handlers/upload_documento.php */
    public function subirDocumento(Solicitud $solicitud): void
    {
        (new Ejecutor())->ejecutarJson(function () use ($solicitud): Respuesta {
            SesionPanel::iniciar();
            Guardia::sesion();

            return $this->ensamblador->controladorDocumentos()->subir($solicitud);
        });
    }
}
