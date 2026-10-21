<?php

/**
 * src/Infraestructura/Conexion.php — Conexión PDO a MySQL (una por petición).
 */

declare(strict_types=1);

namespace Rubicam\Infraestructura;

use PDO;
use PDOException;

final class Conexion
{
    private static ?PDO $pdo = null;

    /**
     * @throws ConexionFallida con un mensaje seguro para el usuario; el detalle técnico va al log.
     */
    public static function obtener(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Configuracion::valor('DB_HOST'),
            Configuracion::valor('DB_PORT'),
            Configuracion::valor('DB_NAME')
        );

        try {
            $pdo = new PDO($dsn, Configuracion::valor('DB_USER'), Configuracion::valor('DB_PASS'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Hora de Colombia y modo estricto: un ENUM inválido falla en vez de guardarse vacío.
            $pdo->exec(
                "SET time_zone = '-05:00', "
                . "sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'"
            );
        } catch (PDOException $e) {
            error_log('[rubicam-panel] Error de conexión a BD: ' . $e->getMessage());
            throw new ConexionFallida(
                'No fue posible conectar con la base de datos. Contacte al administrador.',
                0,
                $e
            );
        }

        return self::$pdo = $pdo;
    }
}
