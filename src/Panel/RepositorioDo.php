<?php

/**
 * src/Panel/RepositorioDo.php
 * -----------------------------------------------------------------------
 * Acceso a datos de las Órdenes de Operación (tabla procesos_do). Todas las
 * consultas usan sentencias preparadas (sin concatenar entradas del usuario).
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Panel;

use DateTimeImmutable;
use PDO;
use PDOException;
use Rubicam\Dominio\Bitacora;
use Rubicam\Dominio\SemaforoDo;
use Rubicam\Http\ErrorHttp;

final class RepositorioDo
{
    private const CODIGO_MYSQL_DUPLICADO = 1062;
    private const ENTRADAS_BITACORA_EN_LISTA = 15;

    private const SELECT_LISTA = 'SELECT p.id, p.do_codigo, p.cliente, p.nit, p.email, p.telefono, p.regimen,
                   p.sucursal, p.estado, p.fecha_apertura, p.fecha_vencimiento, p.observaciones,
                   p.actualizado_en,
                   (SELECT COUNT(*) FROM documentos_do d WHERE d.do_id = p.id) AS total_documentos
              FROM procesos_do p';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Todas las DO con semáforo y bitácora, de la más reciente a la más antigua.
     *
     * @return list<array<string, mixed>>
     */
    public function todos(?DateTimeImmutable $hoy = null): array
    {
        $filas = $this->pdo->query(self::SELECT_LISTA . ' ORDER BY p.fecha_apertura DESC, p.id DESC');
        $hoy ??= new DateTimeImmutable('today');

        return array_map(fn (array $fila): array => $this->formatear($fila, $hoy), $filas->fetchAll());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function porId(int $id): ?array
    {
        $sentencia = $this->pdo->prepare(self::SELECT_LISTA . ' WHERE p.id = :id');
        $sentencia->execute(['id' => $id]);
        $fila = $sentencia->fetch();

        return $fila ? $this->formatear($fila, new DateTimeImmutable('today')) : null;
    }

    /**
     * @return array{id: int|string, do_codigo: string}|null
     */
    public function datosBasicos(int $id): ?array
    {
        $sentencia = $this->pdo->prepare('SELECT id, do_codigo FROM procesos_do WHERE id = :id LIMIT 1');
        $sentencia->execute(['id' => $id]);

        return $sentencia->fetch() ?: null;
    }

    public function existeCodigo(string $codigo): bool
    {
        $sentencia = $this->pdo->prepare('SELECT id FROM procesos_do WHERE do_codigo = :codigo LIMIT 1');
        $sentencia->execute(['codigo' => $codigo]);

        return $sentencia->fetch() !== false;
    }

    /**
     * Inserta una DO nueva en estado inicial y devuelve su id.
     *
     * @param array<string, mixed> $datos datos ya validados (ValidadorNuevoDo)
     * @throws ErrorHttp 409 si el código ya existe (también ante una carrera entre dos peticiones)
     */
    public function crear(array $datos, string $estado, string $bitacora): int
    {
        try {
            $this->pdo->prepare(
                'INSERT INTO procesos_do
                    (do_codigo, cliente, nit, email, telefono, regimen, sucursal, estado,
                     fecha_apertura, fecha_vencimiento, observaciones)
                 VALUES
                    (:do_codigo, :cliente, :nit, :email, :telefono, :regimen, :sucursal, :estado,
                     :fecha_apertura, :fecha_vencimiento, :observaciones)'
            )->execute([
                'do_codigo'         => $datos['do_codigo'],
                'cliente'           => $datos['cliente'],
                'nit'               => $datos['nit'],
                'email'             => $datos['email'] !== '' ? $datos['email'] : null,
                'telefono'          => $datos['telefono'] !== '' ? $datos['telefono'] : null,
                'regimen'           => $datos['regimen'],
                'sucursal'          => $datos['sucursal'],
                'estado'            => $estado,
                'fecha_apertura'    => $datos['fecha_apertura'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'],
                'observaciones'     => $bitacora,
            ]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === self::CODIGO_MYSQL_DUPLICADO) {
                throw self::codigoDuplicado((string) $datos['do_codigo']);
            }
            throw $e;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public static function codigoDuplicado(string $codigo): ErrorHttp
    {
        return new ErrorHttp('Ya existe un DO con el código ' . $codigo . '.', 409, [
            'errors' => ['do_codigo' => 'Este código ya está registrado.'],
        ]);
    }

    /**
     * Cambia el estado y/o agrega una observación en una transacción con bloqueo de fila.
     *
     * @return array{do: array<string, mixed>, estado_anterior: string, cambio_estado: bool, texto_bitacora: string}
     * @throws ErrorHttp 404 si no existe, 409 si el código no coincide, 422 si no hay nada que cambiar
     */
    public function cambiarEstado(
        int $id,
        string $codigoEsperado,
        string $nuevoEstado,
        string $observaciones,
        string $usuario
    ): array {
        $this->pdo->beginTransaction();

        try {
            $sentencia = $this->pdo->prepare(
                'SELECT id, do_codigo, cliente, nit, email, telefono, regimen, sucursal, estado,
                        fecha_apertura, fecha_vencimiento, observaciones
                   FROM procesos_do
                  WHERE id = :id
                  FOR UPDATE'
            );
            $sentencia->execute(['id' => $id]);
            $orden = $sentencia->fetch();

            $cambio = self::verificarCambio($orden ?: null, $codigoEsperado, $nuevoEstado, $observaciones);
            $texto  = $observaciones !== ''
                ? $observaciones
                : sprintf('Cambio de estado: %s → %s.', $orden['estado'], $nuevoEstado);

            $this->pdo->prepare('UPDATE procesos_do SET estado = :estado, observaciones = :obs WHERE id = :id')
                ->execute([
                    'estado' => $nuevoEstado,
                    'obs'    => Bitacora::agregar($orden['observaciones'], $texto, $usuario, $nuevoEstado),
                    'id'     => $id,
                ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'do'              => $orden,
            'estado_anterior' => (string) $orden['estado'],
            'cambio_estado'   => $cambio,
            'texto_bitacora'  => $texto,
        ];
    }

    /**
     * @param array<string, mixed>|null $orden
     * @return bool true si cambia el estado
     */
    private static function verificarCambio(
        ?array $orden,
        string $codigoEsperado,
        string $nuevoEstado,
        string $obs
    ): bool {
        if ($orden === null) {
            throw new ErrorHttp('La orden de operación indicada no existe.', 404);
        }
        if ($codigoEsperado !== '' && $codigoEsperado !== strtoupper((string) $orden['do_codigo'])) {
            throw new ErrorHttp('El código del DO no coincide con el registro. Recargue la página.', 409);
        }

        $cambio = (string) $orden['estado'] !== $nuevoEstado;
        if (!$cambio && $obs === '') {
            throw new ErrorHttp('No hay cambios: seleccione un estado diferente o escriba una observación.', 422);
        }

        return $cambio;
    }

    /**
     * @param array<string, mixed> $fila
     * @return array<string, mixed>
     */
    private function formatear(array $fila, DateTimeImmutable $hoy): array
    {
        $bitacora = Bitacora::entradas($fila['observaciones'] ?? null, self::ENTRADAS_BITACORA_EN_LISTA);
        $vence    = $fila['fecha_vencimiento'] !== null ? (string) $fila['fecha_vencimiento'] : null;

        return [
            'id'                 => (int) $fila['id'],
            'do_codigo'          => (string) $fila['do_codigo'],
            'cliente'            => (string) $fila['cliente'],
            'nit'                => (string) $fila['nit'],
            'email'              => (string) ($fila['email'] ?? ''),
            'telefono'           => (string) ($fila['telefono'] ?? ''),
            'regimen'            => (string) $fila['regimen'],
            'sucursal'           => (string) $fila['sucursal'],
            'estado'             => (string) $fila['estado'],
            'fecha_apertura'     => (string) $fila['fecha_apertura'],
            'fecha_vencimiento'  => $vence,
            'total_documentos'   => (int) $fila['total_documentos'],
            'semaforo'           => SemaforoDo::calcular(
                (string) $fila['fecha_apertura'],
                $vence,
                (string) $fila['estado'],
                $hoy
            ),
            'bitacora'           => $bitacora,
            'ultima_observacion' => $bitacora[0]['texto'] ?? '',
            'actualizado_en'     => (string) ($fila['actualizado_en'] ?? ''),
        ];
    }
}
