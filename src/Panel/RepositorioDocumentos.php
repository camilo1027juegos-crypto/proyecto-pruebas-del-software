<?php

/**
 * src/Panel/RepositorioDocumentos.php — Acceso a datos del legajo (tabla documentos_do).
 */

declare(strict_types=1);

namespace Rubicam\Panel;

use PDO;
use PDOException;

final class RepositorioDocumentos
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function delDo(int $doId): array
    {
        $sentencia = $this->pdo->prepare(
            'SELECT id, tipo_documento, nombre_archivo, ruta_archivo, fecha_subida
               FROM documentos_do
              WHERE do_id = :id
              ORDER BY fecha_subida DESC, id DESC'
        );
        $sentencia->execute(['id' => $doId]);

        return $sentencia->fetchAll();
    }

    /**
     * @return array{nombre_archivo: string, ruta_archivo: string}|null
     */
    public function porId(int $documentoId): ?array
    {
        $sentencia = $this->pdo->prepare(
            'SELECT nombre_archivo, ruta_archivo FROM documentos_do WHERE id = :id LIMIT 1'
        );
        $sentencia->execute(['id' => $documentoId]);

        return $sentencia->fetch() ?: null;
    }

    /**
     * Registra el documento y deja la referencia rápida al último adjunto del DO, en una transacción.
     *
     * @throws PDOException si falla la base de datos (la transacción se revierte)
     */
    public function registrar(int $doId, string $nombreOriginal, string $rutaRelativa, string $tipoDocumento): int
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare(
                'INSERT INTO documentos_do (do_id, nombre_archivo, ruta_archivo, tipo_documento)
                 VALUES (:do_id, :nombre_archivo, :ruta_archivo, :tipo_documento)'
            )->execute([
                'do_id'          => $doId,
                'nombre_archivo' => $nombreOriginal,
                'ruta_archivo'   => $rutaRelativa,
                'tipo_documento' => $tipoDocumento,
            ]);
            $documentoId = (int) $this->pdo->lastInsertId();

            $this->pdo->prepare('UPDATE procesos_do SET archivo_adjunto = :ruta WHERE id = :id')
                ->execute(['ruta' => $rutaRelativa, 'id' => $doId]);

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $documentoId;
    }
}
