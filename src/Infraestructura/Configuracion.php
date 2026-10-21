<?php

/**
 * src/Infraestructura/Configuracion.php
 * -----------------------------------------------------------------------
 * Configuración de la aplicación leída del entorno (variables de entorno),
 * con valores por defecto pensados para XAMPP local. En producción se definen
 * las variables en el servidor en lugar de editar código.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Infraestructura;

final class Configuracion
{
    private const DEFECTOS = [
        'DB_HOST'            => '127.0.0.1',
        'DB_PORT'            => '3306',
        'DB_NAME'            => 'rubicam_db',
        'DB_USER'            => 'root',
        'DB_PASS'            => '',
        'N8N_WEBHOOK_URL'    => 'https://camilo-fesc1.app.n8n.cloud/webhook/rubicam-alertas',
        'N8N_WEBHOOK_SECRET' => '',
        'N8N_CA_BUNDLE'      => '',
        'APP_ENV'            => 'production',
    ];

    public static function valor(string $clave): string
    {
        $valor = getenv($clave);

        return ($valor === false || $valor === '') ? (self::DEFECTOS[$clave] ?? '') : $valor;
    }

    public static function raizProyecto(): string
    {
        return dirname(__DIR__, 2);
    }

    /** Carpeta de documentos del legajo (fuera del alcance web gracias a su .htaccess). */
    public static function carpetaCargas(): string
    {
        return self::raizProyecto() . '/uploads';
    }

    public static function esDesarrollo(): bool
    {
        return self::valor('APP_ENV') === 'development';
    }
}
