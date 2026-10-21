<?php

/**
 * src/Publico/ControladorCotizacion.php
 * -----------------------------------------------------------------------
 * Procesa el formulario de cotización del sitio público. Controles, en este
 * orden: método POST → token CSRF → honeypot → límite de solicitudes →
 * sanitización y validación estricta → envío de correo con cabeceras seguras.
 *
 * El envío de correo se inyecta (Closure) para poder probar todo sin servidor
 * de correo. Responde {"ok": bool, "message": "..."} (contrato de assets/js/app.js).
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Publico;

use Closure;
use DateTimeImmutable;
use Rubicam\Http\Respuesta;
use Rubicam\Http\Solicitud;
use Rubicam\Seguridad\CsrfPublico;
use Rubicam\Seguridad\LimitadorTasa;
use Rubicam\Seguridad\Sanitizador;

final class ControladorCotizacion
{
    /** MR-003: sin tope, un origen de millones de caracteres terminaba en el cuerpo del correo. */
    public const MAX_RUTA = 120;
    public const MAX_NOMBRE = 200;
    public const OPERACIONES_VALIDAS = ['importacion', 'exportacion', 'zona_franca', 'asesoria'];

    /**
     * @param Closure(string, string, string, list<string>): bool $enviarCorreo destino, asunto, cuerpo, cabeceras
     */
    public function __construct(
        private readonly LimitadorTasa $limitador,
        private readonly Closure $enviarCorreo,
        private readonly string $correoDestino
    ) {
    }

    public function procesar(Solicitud $solicitud): Respuesta
    {
        if (!$solicitud->esPost()) {
            return self::respuesta(false, 'Método no permitido.', 405);
        }
        if (!CsrfPublico::valido($solicitud->formulario('csrf_token'))) {
            return self::respuesta(false, 'Tu sesión expiró, recarga la página e inténtalo de nuevo.', 419);
        }
        if (($solicitud->formulario('website') ?? '') !== '') {
            return self::respuesta(true, 'Gracias, hemos recibido tu solicitud.'); // honeypot: éxito falso
        }
        if (!$this->limitador->intentar('quote', $solicitud->direccionIp())) {
            return self::respuesta(false, 'Has enviado demasiadas solicitudes. Intenta nuevamente en unos minutos.', 429);
        }

        $datos   = $this->leerCampos($solicitud);
        $errores = $this->validar($datos);
        if ($errores !== []) {
            return self::respuesta(false, implode(' ', $errores), 422);
        }

        return $this->enviar($datos, $solicitud->direccionIp());
    }

    /**
     * @return array{operacion: string, importeCrudo: string, importe: ?float, origen: string,
     *               destino: string, nombre: string, correo: ?string, telefono: ?string}
     */
    private function leerCampos(Solicitud $solicitud): array
    {
        $importeCrudo = $solicitud->formulario('cif_value') ?? '';

        return [
            'operacion'    => Sanitizador::texto($solicitud->formulario('operation')),
            'importeCrudo' => $importeCrudo,
            'importe'      => $importeCrudo !== '' ? Sanitizador::importe($importeCrudo) : null,
            'origen'       => Sanitizador::texto($solicitud->formulario('origin')),
            'destino'      => Sanitizador::texto($solicitud->formulario('destination')),
            'nombre'       => Sanitizador::texto($solicitud->formulario('name')),
            'correo'       => Sanitizador::correo($solicitud->formulario('email')),
            'telefono'     => Sanitizador::telefono($solicitud->formulario('phone')),
        ];
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<string>
     */
    private function validar(array $datos): array
    {
        $errores = [];
        if (mb_strlen($datos['origen']) > self::MAX_RUTA) {
            $errores[] = 'El origen no puede superar ' . self::MAX_RUTA . ' caracteres.';
        }
        if (mb_strlen($datos['destino']) > self::MAX_RUTA) {
            $errores[] = 'El destino no puede superar ' . self::MAX_RUTA . ' caracteres.';
        }
        if (!in_array($datos['operacion'], self::OPERACIONES_VALIDAS, true)) {
            $errores[] = 'Selecciona un tipo de operación válido.';
        }
        if ($datos['importeCrudo'] !== '' && $datos['importe'] === null) {
            $errores[] = 'El valor CIF debe ser numérico.';
        }
        if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) > self::MAX_NOMBRE) {
            $errores[] = 'El nombre es obligatorio.';
        }
        if ($datos['correo'] === null) {
            $errores[] = 'El correo electrónico no es válido.';
        }
        if ($datos['telefono'] === null) {
            $errores[] = 'El teléfono no es válido.';
        }

        return $errores;
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function enviar(array $datos, string $ip): Respuesta
    {
        $cuerpo = "Nueva solicitud de cotización — RUBICAM\n\n"
            . "Operación: {$datos['operacion']}\n"
            . ($datos['importe'] !== null ? 'Valor CIF estimado: USD ' . number_format($datos['importe'], 2) . "\n" : '')
            . ($datos['origen'] !== '' ? "Origen: {$datos['origen']}\n" : '')
            . ($datos['destino'] !== '' ? "Destino: {$datos['destino']}\n" : '')
            . "\nContacto:\nNombre: {$datos['nombre']}\nEmail: {$datos['correo']}\nTeléfono: {$datos['telefono']}\n"
            . "\n---\nEnviado " . (new DateTimeImmutable())->format('Y-m-d H:i:s') . " desde IP {$ip}\n";

        $cabeceras = [
            'From: Cotizador Web Rubicam <no-responder@rubicam.co>',
            'Reply-To: ' . $datos['correo'],
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        $asunto = '=?UTF-8?B?' . base64_encode('Nueva cotización - RUBICAM') . '?=';

        if (!($this->enviarCorreo)($this->correoDestino, $asunto, $cuerpo, $cabeceras)) {
            error_log('[quote-handler] Fallo al enviar cotización de ' . $datos['correo']);

            return self::respuesta(
                false,
                'No pudimos procesar tu solicitud. Escríbenos directamente a ' . $this->correoDestino . '.',
                500
            );
        }

        return self::respuesta(true, '¡Gracias! Un asesor especializado te contactará muy pronto con tu cotización.');
    }

    private static function respuesta(bool $ok, string $mensaje, int $codigoHttp = 200): Respuesta
    {
        return Respuesta::datos(['ok' => $ok, 'message' => $mensaje], $codigoHttp);
    }
}
