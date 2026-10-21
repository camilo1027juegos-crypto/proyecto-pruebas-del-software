<?php

/**
 * src/Panel/ControladorDocumentos.php
 * -----------------------------------------------------------------------
 * Legajo aduanero de cada DO: listar, descargar (con sesión) y subir.
 *
 * Subida: sesión, CSRF, rol, extensión (PDF/JPG/PNG/DOCX), tipo MIME real,
 * firma interna del archivo y tamaño máximo (10 MB). El nombre en disco lo
 * genera el servidor; nunca se usa el que eligió el usuario.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Panel;

use DateTimeImmutable;
use PDOException;
use Rubicam\Archivos\PoliticaArchivos;
use Rubicam\Dominio\Catalogos;
use Rubicam\Http\ErrorHttp;
use Rubicam\Http\Respuesta;
use Rubicam\Http\Solicitud;
use Rubicam\Seguridad\Sanitizador;

final class ControladorDocumentos
{
    private const MYSQL_DATOS_TRUNCADOS = 1265;

    public function __construct(
        private readonly RepositorioDo $ordenes,
        private readonly RepositorioDocumentos $documentos,
        private readonly string $carpetaCargas
    ) {
    }

    public function listar(Solicitud $solicitud): Respuesta
    {
        $doId = Sanitizador::enteroPositivo($solicitud->consulta('do_id'));
        if ($doId === null) {
            throw new ErrorHttp('Operación (do_id) inválida.', 422);
        }

        $orden = $this->ordenes->datosBasicos($doId);
        if ($orden === null) {
            throw new ErrorHttp('La orden de operación no existe.', 404);
        }

        return Respuesta::json('success', 'Legajo cargado.', 200, [
            'data' => [
                'do'         => ['id' => (int) $orden['id'], 'do_codigo' => (string) $orden['do_codigo']],
                'documentos' => array_map(
                    fn (array $fila): array => $this->describir($fila),
                    $this->documentos->delDo($doId)
                ),
            ],
        ]);
    }

    public function descargar(Solicitud $solicitud): Respuesta
    {
        $documentoId = Sanitizador::enteroPositivo($solicitud->consulta('doc_id'));
        if ($documentoId === null) {
            return self::paginaError(400, 'El identificador del documento no es válido.');
        }

        $documento = $this->documentos->porId($documentoId);
        if ($documento === null) {
            return self::paginaError(404, 'El documento no existe.');
        }

        $ruta = $this->rutaConfinada((string) $documento['ruta_archivo']);
        if ($ruta === null) {
            return self::paginaError(404, 'El archivo ya no se encuentra en el servidor.');
        }

        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $enLinea   = $solicitud->consulta('modo') === 'ver' && PoliticaArchivos::esVisualizable($extension);
        $nombre    = trim((string) $documento['nombre_archivo']) !== ''
            ? (string) $documento['nombre_archivo']
            : basename($ruta);
        $nombre    = str_replace(["\r", "\n", '"'], '', $nombre);

        return Respuesta::archivo($ruta, [
            'Content-Type'           => PoliticaArchivos::tipoMimeDescarga($extension),
            'Content-Disposition'    => sprintf(
                "%s; filename=\"%s\"; filename*=UTF-8''%s",
                $enLinea ? 'inline' : 'attachment',
                preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre),
                rawurlencode($nombre)
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store',
        ]);
    }

    public function subir(Solicitud $solicitud): Respuesta
    {
        Guardia::metodoPost($solicitud);
        if ($solicitud->cuerpoDescartadoPorElServidor()) {
            throw new ErrorHttp(
                'El archivo supera el límite del servidor (post_max_size = ' . ini_get('post_max_size') . ').',
                413
            );
        }
        Guardia::csrfEnCabeceraOFormulario($solicitud);
        Guardia::rolDeEdicion('Su rol no tiene permiso para adjuntar documentos.');

        $doId = Sanitizador::enteroPositivo($solicitud->formulario('do_id'));
        $tipo = trim($solicitud->formulario('tipo_documento') ?? '');
        if ($doId === null) {
            throw new ErrorHttp('Operación (do_id) inválida.', 422);
        }
        if (!in_array($tipo, Catalogos::TIPOS_DOCUMENTO, true)) {
            throw new ErrorHttp('Tipo de documento no válido.', 422);
        }

        $archivo = (new ValidadorCargaDocumento())->validar($solicitud->archivo('documento'));
        $orden   = $this->ordenes->datosBasicos($doId);
        if ($orden === null) {
            throw new ErrorHttp('La orden de operación indicada no existe.', 404);
        }

        $nombreSeguro = PoliticaArchivos::nombreSeguro(
            (string) $orden['do_codigo'],
            $tipo,
            $archivo['extension'],
            new DateTimeImmutable(),
            bin2hex(random_bytes(3))
        );
        $this->guardarEnDisco($archivo['temporal'], $nombreSeguro);

        $documentoId = $this->registrar($doId, $archivo['nombre'], $nombreSeguro, $tipo);

        return Respuesta::json(
            'success',
            sprintf('"%s" se adjuntó al DO %s.', $archivo['nombre'], $orden['do_codigo']),
            201,
            ['data' => ['documento' => [
                'id'             => $documentoId,
                'do_id'          => $doId,
                'do_codigo'      => $orden['do_codigo'],
                'tipo_documento' => $tipo,
                'nombre_archivo' => $archivo['nombre'],
                'archivo'        => $nombreSeguro,
                'tamano'         => $archivo['tamano'],
                'fecha_subida'   => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]]]
        );
    }

    private function guardarEnDisco(string $temporal, string $nombreSeguro): void
    {
        $this->prepararCarpeta();
        $destino = $this->carpetaCargas . '/' . $nombreSeguro;

        if (!move_uploaded_file($temporal, $destino)) {
            error_log('[rubicam-panel] move_uploaded_file falló hacia ' . $destino);
            throw new ErrorHttp('No se pudo guardar el archivo en el servidor (verifique permisos de /uploads).', 500);
        }
        chmod($destino, 0644);
    }

    private function registrar(int $doId, string $nombreOriginal, string $nombreSeguro, string $tipo): int
    {
        try {
            return $this->documentos->registrar($doId, $nombreOriginal, 'uploads/' . $nombreSeguro, $tipo);
        } catch (PDOException $e) {
            unlink($this->carpetaCargas . '/' . $nombreSeguro);
            error_log('[rubicam-panel] Error registrando documento del DO ' . $doId . ': ' . $e->getMessage());

            if ((int) ($e->errorInfo[1] ?? 0) === self::MYSQL_DATOS_TRUNCADOS) {
                throw new ErrorHttp(
                    'La base de datos no reconoce el tipo "' . $tipo . '". Ejecute SQL/migracion_panel_v2.sql.',
                    500
                );
            }
            throw new ErrorHttp('Error al registrar el documento en la base de datos.', 500);
        }
    }

    /** Crea /uploads si no existe y bloquea su acceso directo desde la web. */
    private function prepararCarpeta(): void
    {
        $carpeta = $this->carpetaCargas;
        if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true) && !is_dir($carpeta)) {
            throw new ErrorHttp('No se pudo crear la carpeta de documentos en el servidor.', 500);
        }
        if (!is_writable($carpeta)) {
            throw new ErrorHttp('La carpeta de documentos no tiene permisos de escritura.', 500);
        }

        $reglas = $carpeta . '/.htaccess';
        if (!is_file($reglas)) {
            file_put_contents(
                $reglas,
                "# Acceso directo bloqueado: los documentos se descargan desde el panel (con sesión).\n"
                . "Require all denied\nOptions -Indexes\n"
            );
        }
    }

    /** Ruta real del archivo SOLO si existe y está dentro de la carpeta de cargas. */
    private function rutaConfinada(string $rutaGuardada): ?string
    {
        $ruta = PoliticaArchivos::rutaFisica($this->carpetaCargas, $rutaGuardada);
        $real = $ruta !== null ? realpath($ruta) : false;
        $base = realpath($this->carpetaCargas);

        if ($real === false || $base === false || !is_file($real)) {
            return null;
        }

        return str_starts_with($real, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) ? $real : null;
    }

    /**
     * @param array<string, mixed> $fila
     * @return array<string, mixed>
     */
    private function describir(array $fila): array
    {
        $ruta      = $this->rutaConfinada((string) $fila['ruta_archivo']);
        $existe    = $ruta !== null;
        $extension = strtolower(pathinfo((string) $fila['ruta_archivo'], PATHINFO_EXTENSION));

        return [
            'id'             => (int) $fila['id'],
            'tipo_documento' => (string) $fila['tipo_documento'],
            'nombre_archivo' => (string) $fila['nombre_archivo'],
            'extension'      => $extension,
            'fecha_subida'   => (string) $fila['fecha_subida'],
            'existe'         => $existe,
            'tamano'         => $existe ? (int) filesize((string) $ruta) : null,
            'visualizable'   => $existe && PoliticaArchivos::esVisualizable($extension),
            'url_descarga'   => 'dashboard.php?action=descargar&doc_id=' . (int) $fila['id'],
            'url_ver'        => 'dashboard.php?action=descargar&modo=ver&doc_id=' . (int) $fila['id'],
        ];
    }

    private static function paginaError(int $codigoHttp, string $mensaje): Respuesta
    {
        $texto = Sanitizador::html($mensaje);

        return Respuesta::html(
            '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Documento no disponible</title>'
            . '</head><body style="font-family:system-ui,sans-serif;background:#f1f5f9;color:#0f172a;display:flex;'
            . 'min-height:100vh;align-items:center;justify-content:center;margin:0"><div style="background:#fff;'
            . 'padding:32px 40px;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);max-width:420px;'
            . 'text-align:center"><h1 style="font-size:18px;margin:0 0 8px">Documento no disponible</h1>'
            . '<p style="color:#475569;margin:0 0 20px">' . $texto . '</p>'
            . '<a href="dashboard.php" style="color:#065f46;font-weight:700">Volver al panel</a></div></body></html>',
            $codigoHttp
        );
    }
}
