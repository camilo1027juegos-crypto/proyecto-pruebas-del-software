<?php

/**
 * src/Dominio/Catalogos.php
 * -----------------------------------------------------------------------
 * Catálogos y reglas de negocio simples (sucursales, regímenes, estados,
 * tipos de documento y roles). Es la ÚNICA fuente de verdad: el panel, los
 * handlers y las pruebas validan contra la misma lista.
 *
 * Capa de dominio: no depende de la base de datos, de la sesión ni de HTTP.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Dominio;

final class Catalogos
{
    public const SUCURSALES = ['Bogotá', 'Cartagena', 'Cúcuta', 'Medellín'];
    public const PREFIJOS_SUCURSAL = [
        'Bogotá'    => 'BOG',
        'Cartagena' => 'CTG',
        'Cúcuta'    => 'CUC',
        'Medellín'  => 'MED',
    ];

    public const REGIMENES = ['Importación', 'Exportación', 'Tránsito'];
    public const PREFIJOS_REGIMEN = ['Importación' => 'IMP', 'Exportación' => 'EXP', 'Tránsito' => 'TRA'];

    public const ESTADO_INICIAL = 'En trámite';
    public const ESTADOS = [
        'En trámite',
        'Presentada DIAN',
        'Aceptada',
        'Nacionalizado',
        'Finalizada',
        'Cerrado sin facturar',
    ];

    /** Estados que cierran la operación: no generan alerta DIAN (badge neutro). */
    public const ESTADOS_FINALES = ['Finalizada', 'Nacionalizado', 'Cerrado sin facturar'];

    public const TIPOS_DOCUMENTO = [
        'Declaración de Importación',
        'Factura Comercial',
        'Lista de Empaque',
        'RUT',
        'BL/Guía aérea',
        'Otro',
    ];

    /** Roles con permiso de escritura (el rol «consulta» solo puede ver). */
    public const ROLES_EDICION = ['admin', 'operador'];

    public static function esEstadoFinal(string $estado): bool
    {
        return in_array($estado, self::ESTADOS_FINALES, true);
    }

    public static function rolPuedeEditar(string $rol): bool
    {
        return in_array($rol, self::ROLES_EDICION, true);
    }
}
