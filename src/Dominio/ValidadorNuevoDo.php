<?php

/**
 * src/Dominio/ValidadorNuevoDo.php
 * -----------------------------------------------------------------------
 * Valida y normaliza los datos del formulario «Nueva operación (DO)».
 *
 * Codificación defensiva: cualquier valor que no sea texto (arreglos, null,
 * números) se trata como vacío y produce un error de campo, nunca un aviso
 * de PHP ni una excepción. El reloj se inyecta ($hoy) para poder probar las
 * reglas de fechas.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Dominio;

use DateTimeImmutable;
use Rubicam\Seguridad\Sanitizador;

final class ValidadorNuevoDo
{
    private const CODIGO_PATRON = '/^[A-Z]{3}\d{2}[A-Z]{3}\d{3,12}$/';
    private const CODIGO_MAX_CARACTERES = 30;
    private const CLIENTE_MIN = 3;
    private const CLIENTE_MAX = 150;
    private const EMAIL_MAX = 150;
    private const TELEFONO_MAX = 30;
    private const OBSERVACION_MAX = 1000;

    /** @var array<string, mixed> */
    private array $datos = [];
    /** @var array<string, string> */
    private array $errores = [];

    /**
     * @param array<mixed> $entrada
     */
    private function __construct(private readonly array $entrada, private readonly DateTimeImmutable $hoy)
    {
    }

    /**
     * @param array<mixed> $entrada datos tal como llegan del navegador
     */
    public static function validar(array $entrada, ?DateTimeImmutable $hoy = null): ResultadoValidacion
    {
        return (new self($entrada, $hoy ?? new DateTimeImmutable('today')))->resultado();
    }

    /** Aplica todas las reglas y devuelve los datos normalizados con los errores por campo. */
    public function resultado(): ResultadoValidacion
    {
        $this->validarCodigo();
        $this->validarCliente();
        $this->validarNit();
        $this->validarCatalogos();
        $this->validarFechas();
        $this->validarContacto();
        $this->validarObservacion();

        return new ResultadoValidacion($this->datos, $this->errores);
    }

    /** Código DO: SUCURSAL(3) + AÑO(2) + RÉGIMEN(3) + CONSECUTIVO. Ej.: BOG26IMP0001 */
    private function validarCodigo(): void
    {
        $codigo = strtoupper((string) preg_replace('/\s+/', '', $this->cadena('do_codigo')));
        $this->datos['do_codigo'] = $codigo;

        if ($codigo === '') {
            $this->errores['do_codigo'] = 'El código del DO es obligatorio.';
        } elseif (strlen($codigo) > self::CODIGO_MAX_CARACTERES || !preg_match(self::CODIGO_PATRON, $codigo)) {
            $this->errores['do_codigo'] =
                'Formato esperado: sucursal + año + régimen + consecutivo (ej. BOG26IMP0001).';
        }
    }

    private function validarCliente(): void
    {
        $cliente = $this->texto('cliente');
        $this->datos['cliente'] = $cliente;

        $largo = mb_strlen($cliente);
        if ($largo < self::CLIENTE_MIN || $largo > self::CLIENTE_MAX) {
            $this->errores['cliente'] = 'Escriba la razón social del cliente (3 a 150 caracteres).';
        }
    }

    private function validarNit(): void
    {
        $nit = (string) preg_replace('/[\s.]/', '', $this->cadena('nit'));
        $this->datos['nit'] = $nit;

        try {
            $this->datos['nit'] = Nit::normalizar($nit);
        } catch (NitInvalido $e) {
            $this->errores['nit'] = $e->getMessage();
        }
    }

    private function validarCatalogos(): void
    {
        $this->datos['regimen']  = trim($this->cadena('regimen'));
        $this->datos['sucursal'] = trim($this->cadena('sucursal'));

        if (!in_array($this->datos['regimen'], Catalogos::REGIMENES, true)) {
            $this->errores['regimen'] = 'Seleccione un régimen válido.';
        }
        if (!in_array($this->datos['sucursal'], Catalogos::SUCURSALES, true)) {
            $this->errores['sucursal'] = 'Seleccione una sucursal válida.';
        }
    }

    private function validarFechas(): void
    {
        $this->datos['fecha_apertura']    = trim($this->cadena('fecha_apertura'));
        $this->datos['fecha_vencimiento'] = trim($this->cadena('fecha_vencimiento'));

        $apertura = self::fechaValida($this->datos['fecha_apertura']);
        if ($apertura === null) {
            $this->errores['fecha_apertura'] = 'Fecha de apertura inválida.';
        } elseif ($apertura > $this->hoy) {
            $this->errores['fecha_apertura'] = 'La fecha de apertura no puede ser futura.';
        }

        if ($this->datos['fecha_vencimiento'] === '' && $apertura !== null) {
            $plazo = '+' . SemaforoDo::PLAZO_DIAN_DIAS . ' days';
            $this->datos['fecha_vencimiento'] = $apertura->modify($plazo)->format('Y-m-d');
        }

        $vencimiento = self::fechaValida($this->datos['fecha_vencimiento']);
        if ($vencimiento === null) {
            $this->errores['fecha_vencimiento'] = 'Fecha de vencimiento inválida.';
        } elseif ($apertura !== null && $vencimiento < $apertura) {
            $this->errores['fecha_vencimiento'] = 'El vencimiento no puede ser anterior a la apertura.';
        }
    }

    private function validarContacto(): void
    {
        $email    = trim($this->cadena('email'));
        $telefono = $this->texto('telefono');
        $this->datos['email']    = $email;
        $this->datos['telefono'] = $telefono;

        if ($email !== '' && (Sanitizador::correo($email) === null || strlen($email) > self::EMAIL_MAX)) {
            $this->errores['email'] = 'Correo electrónico inválido.';
        }
        if ($telefono !== '' && Sanitizador::telefono($telefono, self::TELEFONO_MAX) === null) {
            $this->errores['telefono'] = 'Teléfono inválido (7 a 30 dígitos).';
        }
    }

    private function validarObservacion(): void
    {
        $this->datos['observacion'] = $this->texto('observacion');

        if (mb_strlen($this->datos['observacion']) > self::OBSERVACION_MAX) {
            $this->errores['observacion'] = 'La observación no puede superar 1.000 caracteres.';
        }
    }

    /** Valor del campo como texto; todo lo que no sea escalar cuenta como vacío. */
    private function cadena(string $campo): string
    {
        $valor = $this->entrada[$campo] ?? '';

        return is_scalar($valor) ? (string) $valor : '';
    }

    /** Texto libre: sin etiquetas HTML y con los espacios compactados. */
    private function texto(string $campo): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($this->cadena($campo))));
    }

    private static function fechaValida(string $valor): ?DateTimeImmutable
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        return ($fecha !== false && $fecha->format('Y-m-d') === $valor) ? $fecha : null;
    }
}
