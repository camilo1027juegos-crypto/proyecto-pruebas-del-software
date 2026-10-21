<?php

/**
 * src/Panel/ControladorEstado.php
 * -----------------------------------------------------------------------
 * Actualiza el estado / bitácora de un DO y alerta a n8n.
 *
 *  - El usuario de la auditoría sale de la SESIÓN; el campo «usuario» que
 *    envía el navegador no es confiable y se ignora.
 *  - La base de datos es la fuente de verdad: si n8n falla, el estado YA
 *    quedó guardado y la respuesta es «success» con webhook.enviado = false.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Panel;

use DateTimeImmutable;
use Rubicam\Dominio\Catalogos;
use Rubicam\Dominio\SemaforoDo;
use Rubicam\Http\ErrorHttp;
use Rubicam\Http\Respuesta;
use Rubicam\Http\Solicitud;
use Rubicam\Infraestructura\ClienteN8n;
use Rubicam\Seguridad\Sanitizador;
use Rubicam\Seguridad\SesionPanel;

final class ControladorEstado
{
    private const OBSERVACION_MAX = 1000;

    public function __construct(private readonly RepositorioDo $ordenes, private readonly ClienteN8n $n8n)
    {
    }

    public function cambiar(Solicitud $solicitud): Respuesta
    {
        Guardia::escritura($solicitud, 'Su rol no tiene permiso para modificar operaciones.');

        $entrada       = $solicitud->json();
        $texto         = static fn (string $campo): string => is_scalar($entrada[$campo] ?? null)
            ? (string) $entrada[$campo]
            : '';
        $doId          = Sanitizador::enteroPositivo($texto('do_id'));
        $nuevoEstado   = trim($texto('nuevo_estado'));
        $observaciones = trim((string) preg_replace('/\s+/u', ' ', $texto('observaciones')));

        if ($doId === null) {
            throw new ErrorHttp('Identificador de operación (do_id) inválido.', 422);
        }
        if (!in_array($nuevoEstado, Catalogos::ESTADOS, true)) {
            throw new ErrorHttp('Estado no permitido. Opciones: ' . implode(', ', Catalogos::ESTADOS) . '.', 422);
        }
        if (mb_strlen($observaciones) > self::OBSERVACION_MAX) {
            throw new ErrorHttp('La observación no puede superar 1.000 caracteres.', 422);
        }

        $usuario  = SesionPanel::nombreUsuario();
        $cambio   = $this->ordenes->cambiarEstado(
            $doId,
            strtoupper(trim($texto('do_codigo'))),
            $nuevoEstado,
            $observaciones,
            $usuario
        );
        $orden    = $cambio['do'];
        $semaforo = SemaforoDo::calcular(
            (string) $orden['fecha_apertura'],
            $orden['fecha_vencimiento'],
            $nuevoEstado
        );
        $webhook  = $this->n8n->enviar($this->carga($cambio, $nuevoEstado, $semaforo, $usuario));

        $datos = [
            'webhook' => $webhook,
            'data'    => [
                'do_id'     => (int) $orden['id'],
                'do_codigo' => $orden['do_codigo'],
                'estado'    => $nuevoEstado,
                'semaforo'  => $semaforo,
            ],
        ];

        $mensaje = $webhook['enviado']
            ? sprintf('DO %s actualizado a "%s" y alerta enviada a n8n.', $orden['do_codigo'], $nuevoEstado)
            : sprintf(
                'DO %s actualizado a "%s", pero la alerta a n8n no se envió: %s',
                $orden['do_codigo'],
                $nuevoEstado,
                $webhook['error']
            );

        return Respuesta::json('success', $mensaje, 200, $datos);
    }

    /**
     * @param array{do: array<string, mixed>, estado_anterior: string,
     *              cambio_estado: bool, texto_bitacora: string} $cambio
     * @param array<string, mixed> $semaforo
     * @return array<string, mixed>
     */
    private function carga(array $cambio, string $nuevoEstado, array $semaforo, string $usuario): array
    {
        $orden = $cambio['do'];

        return [
            'evento'             => $cambio['cambio_estado'] ? 'cambio_estado_do' : 'observacion_do',
            'do_id'              => (int) $orden['id'],
            'do_codigo'          => $orden['do_codigo'],
            'cliente'            => $orden['cliente'],
            'nit'                => $orden['nit'],
            'email'              => $orden['email'],
            'telefono'           => $orden['telefono'],
            'regimen'            => $orden['regimen'],
            'sucursal'           => $orden['sucursal'],
            'estado_anterior'    => $cambio['estado_anterior'],
            'nuevo_estado'       => $nuevoEstado,
            'observaciones'      => $cambio['texto_bitacora'],
            'fecha_apertura'     => $orden['fecha_apertura'],
            'fecha_vencimiento'  => $orden['fecha_vencimiento'],
            'dias_transcurridos' => $semaforo['dias_transcurridos'],
            'dias_restantes'     => $semaforo['dias_restantes'],
            'nivel_alerta'       => $semaforo['nivel'],
            'usuario'            => $usuario,
            'usuario_login'      => SesionPanel::usuarioLogin(),
            'fecha_evento'       => (new DateTimeImmutable())->format('c'),
        ];
    }
}
