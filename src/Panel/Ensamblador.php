<?php

/**
 * src/Panel/Ensamblador.php
 * -----------------------------------------------------------------------
 * Arma los controladores con sus dependencias (conexión, repositorios,
 * cliente de n8n). Cada método abre la conexión a la base de datos solo
 * cuando se le pide, es decir, DESPUÉS de verificar la sesión.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Panel;

use Rubicam\Infraestructura\ClienteN8n;
use Rubicam\Infraestructura\Conexion;
use Rubicam\Infraestructura\Configuracion;

final class Ensamblador
{
    public function controladorDo(): ControladorDo
    {
        return new ControladorDo(new RepositorioDo(Conexion::obtener()));
    }

    public function controladorEstado(): ControladorEstado
    {
        $n8n = new ClienteN8n(
            Configuracion::valor('N8N_WEBHOOK_URL'),
            Configuracion::valor('N8N_WEBHOOK_SECRET'),
            Configuracion::valor('N8N_CA_BUNDLE')
        );

        return new ControladorEstado(new RepositorioDo(Conexion::obtener()), $n8n);
    }

    public function paginaDashboard(): PaginaDashboard
    {
        return new PaginaDashboard(static fn (): array => (new RepositorioDo(Conexion::obtener()))->todos());
    }

    public function controladorDocumentos(): ControladorDocumentos
    {
        $pdo = Conexion::obtener();

        return new ControladorDocumentos(
            new RepositorioDo($pdo),
            new RepositorioDocumentos($pdo),
            Configuracion::carpetaCargas()
        );
    }
}
