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
use Rubicam\Seguridad\LimitadorTasa;

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

    /** El acceso a la base de datos es diferido: un intento con campos vacíos no abre conexión. */
    public function autenticador(): Autenticador
    {
        $directorio = Configuracion::raizProyecto() . '/logs/ratelimit';

        return new Autenticador(
            static function (string $usuario): ?array {
                $consulta = Conexion::obtener()->prepare(
                    'SELECT id, usuario, password_hash, rol, nombre_completo, sucursal
                       FROM usuarios WHERE usuario = :usuario AND activo = 1 LIMIT 1'
                );
                $consulta->execute(['usuario' => $usuario]);

                return $consulta->fetch() ?: null;
            },
            new LimitadorTasa($directorio, Autenticador::FALLOS_POR_USUARIO, Autenticador::VENTANA_SEGUNDOS),
            new LimitadorTasa($directorio, Autenticador::FALLOS_POR_IP, Autenticador::VENTANA_SEGUNDOS)
        );
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
