<?php

/**
 * src/Archivos/PoliticaArchivos.php
 * -----------------------------------------------------------------------
 * Reglas para los documentos del legajo aduanero: qué extensiones se aceptan,
 * cómo se comprueba que el contenido corresponde (firma interna, no solo la
 * extensión), cómo se nombra el archivo en disco y cómo se evita salir de la
 * carpeta de cargas (path traversal).
 *
 * Son funciones puras o de solo lectura: no mueven ni borran archivos.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Archivos;

use DateTimeImmutable;
use finfo;
use ZipArchive;

final class PoliticaArchivos
{
    public const TAMANO_MAXIMO_BYTES = 10 * 1024 * 1024; // 10 MB
    private const NOMBRE_MAX_CARACTERES = 200;
    private const NOMBRE_BASE_MAX_CARACTERES = 190;
    private const FIRMA_PNG = "\x89PNG\r\n\x1a\n";
    private const FIRMA_JPEG = "\xFF\xD8\xFF";
    private const FIRMA_ZIP = "PK\x03\x04";

    /** Extensión permitida → tipos MIME aceptados (verificados con finfo). */
    private const MIMES_PERMITIDOS = [
        'pdf'  => ['application/pdf', 'application/x-pdf'],
        'jpg'  => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png'  => ['image/png'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ],
    ];

    /** Tipo MIME con el que se entrega cada extensión al descargar. */
    private const MIME_DESCARGA = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /** Extensiones que el navegador puede mostrar en línea (el resto se descarga). */
    private const VISUALIZABLES = ['pdf', 'jpg', 'jpeg', 'png'];

    /** Fragmento del nombre final según el tipo de documento. */
    private const SLUG_TIPO_DOCUMENTO = [
        'Declaración de Importación' => 'DECLARACION',
        'Factura Comercial'          => 'FACTURA',
        'Lista de Empaque'           => 'LISTA-EMPAQUE',
        'RUT'                        => 'RUT',
        'BL/Guía aérea'              => 'BL-GUIA',
        'Otro'                       => 'OTRO',
    ];

    public static function extensionPermitida(string $extension): bool
    {
        return array_key_exists($extension, self::MIMES_PERMITIDOS);
    }

    public static function esVisualizable(string $extension): bool
    {
        return in_array($extension, self::VISUALIZABLES, true);
    }

    public static function tipoMimeDescarga(string $extension): string
    {
        return self::MIME_DESCARGA[$extension] ?? 'application/octet-stream';
    }

    /** Compara el tipo MIME REAL (finfo) con el que corresponde a la extensión. Sin finfo no bloquea. */
    public static function mimeCoincide(string $ruta, string $extension): bool
    {
        if (!class_exists(finfo::class)) {
            return true;
        }

        $mimeReal = (string) (new finfo(FILEINFO_MIME_TYPE))->file($ruta);

        return in_array($mimeReal, self::MIMES_PERMITIDOS[$extension] ?? [], true);
    }

    /** Comprueba la firma interna del archivo: no basta con la extensión. */
    public static function firmaValida(string $ruta, string $extension): bool
    {
        $cabecera = (string) file_get_contents($ruta, false, null, 0, 8);

        return match ($extension) {
            'pdf'         => str_starts_with($cabecera, '%PDF-'),
            'png'         => str_starts_with($cabecera, self::FIRMA_PNG) && self::esImagen($ruta, IMAGETYPE_PNG),
            'jpg', 'jpeg' => str_starts_with($cabecera, self::FIRMA_JPEG) && self::esImagen($ruta, IMAGETYPE_JPEG),
            'docx'        => str_starts_with($cabecera, self::FIRMA_ZIP) && self::esDocumentoWord($ruta),
            default       => false,
        };
    }

    /** Nombre original apto para mostrar (sin rutas ni caracteres peligrosos o de control). */
    public static function limpiarNombreOriginal(string $nombre): string
    {
        $nombre = basename(str_replace('\\', '/', $nombre));
        $nombre = trim((string) preg_replace('/[\x00-\x1F\x7F"<>|:*?]/u', '', $nombre));
        if ($nombre === '') {
            return 'documento';
        }

        if (mb_strlen($nombre) > self::NOMBRE_MAX_CARACTERES) {
            $extension = pathinfo($nombre, PATHINFO_EXTENSION);
            $base      = mb_substr(pathinfo($nombre, PATHINFO_FILENAME), 0, self::NOMBRE_BASE_MAX_CARACTERES);
            $nombre    = $base . ($extension !== '' ? '.' . $extension : '');
        }

        return $nombre;
    }

    /**
     * Nombre con el que se guarda en disco: CODIGODO_TIPO_FECHA-HORA_ALEATORIO.ext
     * No incluye NADA del nombre original que eligió el usuario.
     */
    public static function nombreSeguro(
        string $codigoDo,
        string $tipoDocumento,
        string $extension,
        DateTimeImmutable $ahora,
        string $aleatorio
    ): string {
        $prefijo   = preg_replace('/[^A-Z0-9]/', '', strtoupper($codigoDo));
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        return sprintf(
            '%s_%s_%s_%s.%s',
            $prefijo !== '' ? $prefijo : 'DO',
            self::SLUG_TIPO_DOCUMENTO[$tipoDocumento] ?? 'OTRO',
            $ahora->format('Ymd-His'),
            $aleatorio,
            $extension
        );
    }

    /**
     * Ruta física de un documento: SIEMPRE dentro de $carpetaBase, aunque la ruta guardada
     * en la base de datos contenga «../» (se conserva solo el nombre del archivo).
     */
    public static function rutaFisica(string $carpetaBase, string $rutaGuardada): ?string
    {
        if ($rutaGuardada === '' || preg_match('#[/\\\\]$#', $rutaGuardada)) {
            return null;
        }

        $archivo = basename(str_replace('\\', '/', $rutaGuardada));
        if ($archivo === '' || $archivo === '.' || $archivo === '..') {
            return null;
        }

        return rtrim($carpetaBase, '/\\') . '/' . $archivo;
    }

    public static function mensajeErrorSubida(int $codigo): string
    {
        return match ($codigo) {
            UPLOAD_ERR_INI_SIZE   => 'El archivo supera upload_max_filesize del servidor ('
                . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el tamaño máximo del formulario.',
            UPLOAD_ERR_PARTIAL    => 'El archivo se subió de forma incompleta. Intente de nuevo.',
            UPLOAD_ERR_NO_FILE    => 'Seleccione un archivo para subir.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor (upload_tmp_dir).',
            UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo en disco.',
            UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP bloqueó la subida.',
            default               => 'Error desconocido al subir el archivo.',
        };
    }

    private static function esImagen(string $ruta, int $tipoEsperado): bool
    {
        $informacion = getimagesize($ruta);

        return $informacion !== false && $informacion[2] === $tipoEsperado;
    }

    /** Un .docx es un ZIP con word/document.xml. Sin la extensión zip solo se confía en la firma «PK». */
    private static function esDocumentoWord(string $ruta): bool
    {
        if (!class_exists(ZipArchive::class)) {
            return true;
        }

        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            return false;
        }
        $esWord = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        return $esWord;
    }
}
