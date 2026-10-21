<?php

/**
 * src/Panel/ControladorDo.php — Acciones AJAX del panel sobre las Órdenes de Operación: listar y crear.
 */

declare(strict_types=1);

namespace Rubicam\Panel;

use DateTimeImmutable;
use Rubicam\Dominio\Bitacora;
use Rubicam\Dominio\Catalogos;
use Rubicam\Dominio\ValidadorNuevoDo;
use Rubicam\Http\ErrorHttp;
use Rubicam\Http\Respuesta;
use Rubicam\Http\Solicitud;
use Rubicam\Seguridad\SesionPanel;

final class ControladorDo
{
    public function __construct(private readonly RepositorioDo $ordenes)
    {
    }

    public function listar(): Respuesta
    {
        $ahora = new DateTimeImmutable();

        return Respuesta::json('success', 'Operaciones cargadas.', 200, [
            'data' => [
                'dos'      => $this->ordenes->todos(),
                'hoy'      => $ahora->format('Y-m-d'),
                'generado' => $ahora->format('d/m/Y H:i'),
            ],
        ]);
    }

    /**
     * Descarga las DO en CSV o JSON (acción de solo lectura: la puede usar cualquier rol).
     * Admite los filtros del panel: sucursal, regimen y nivel.
     */
    public function exportar(Solicitud $solicitud): Respuesta
    {
        $formato = $solicitud->consulta('formato') ?? '';
        if (!in_array($formato, ['csv', 'json'], true)) {
            throw new ErrorHttp('Formato no válido. Use csv o json.', 422);
        }

        $ahora   = new DateTimeImmutable();
        $ordenes = ExportadorDo::filtrar($this->ordenes->todos(), [
            'sucursal' => $solicitud->consulta('sucursal') ?? '',
            'regimen'  => $solicitud->consulta('regimen') ?? '',
            'nivel'    => $solicitud->consulta('nivel') ?? '',
        ]);

        return $formato === 'csv'
            ? Respuesta::descarga(
                ExportadorDo::aCsv($ordenes),
                'text/csv; charset=utf-8',
                ExportadorDo::nombreArchivo('csv', $ahora)
            )
            : Respuesta::descarga(
                ExportadorDo::aJson($ordenes, $ahora),
                'application/json; charset=utf-8',
                ExportadorDo::nombreArchivo('json', $ahora)
            );
    }

    public function crear(Solicitud $solicitud): Respuesta
    {
        Guardia::escritura($solicitud, 'Su rol no tiene permiso para crear operaciones.');

        $validacion = ValidadorNuevoDo::validar($solicitud->json());
        if (!$validacion->esValido()) {
            throw new ErrorHttp('Revise los campos marcados en el formulario.', 422, [
                'errors' => $validacion->errores(),
            ]);
        }

        $datos = $validacion->datos();
        if ($this->ordenes->existeCodigo($datos['do_codigo'])) {
            throw RepositorioDo::codigoDuplicado($datos['do_codigo']);
        }

        $bitacora = Bitacora::agregar(
            null,
            $datos['observacion'] !== '' ? $datos['observacion'] : 'Apertura del DO registrada en el panel.',
            SesionPanel::nombreUsuario(),
            Catalogos::ESTADO_INICIAL
        );
        $id = $this->ordenes->crear($datos, Catalogos::ESTADO_INICIAL, $bitacora);

        return Respuesta::json('success', 'DO ' . $datos['do_codigo'] . ' creado correctamente.', 201, [
            'data' => ['do' => $this->ordenes->porId($id)],
        ]);
    }
}
