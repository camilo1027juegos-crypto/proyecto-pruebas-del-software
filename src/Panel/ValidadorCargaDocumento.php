<?php

/**
 * src/Panel/ValidadorCargaDocumento.php
 * -----------------------------------------------------------------------
 * Valida el archivo recibido en $_FILES['documento'] antes de guardarlo:
 * subida real, no vacío, tamaño máximo, extensión permitida, tipo MIME real
 * y firma interna. No mueve ni escribe nada: solo acepta o rechaza.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Panel;

use Rubicam\Archivos\PoliticaArchivos;
use Rubicam\Http\ErrorHttp;

final class ValidadorCargaDocumento
{
    /**
     * @param array<string, mixed>|null $archivo entrada de $_FILES['documento']
     * @return array{temporal: string, nombre: string, extension: string, tamano: int}
     */
    public function validar(?array $archivo): array
    {
        $temporal = $this->validarCarga($archivo);
        $tamano   = (int) filesize($temporal);
        if ($tamano <= 0) {
            throw new ErrorHttp('El archivo está vacío.', 422);
        }
        if ($tamano > PoliticaArchivos::TAMANO_MAXIMO_BYTES) {
            throw new ErrorHttp('El archivo supera el tamaño máximo permitido (10 MB).', 413);
        }

        $nombre    = PoliticaArchivos::limpiarNombreOriginal((string) ($archivo['name'] ?? ''));
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $this->validarContenido($temporal, $extension);

        return ['temporal' => $temporal, 'nombre' => $nombre, 'extension' => $extension, 'tamano' => $tamano];
    }

    /**
     * Comprueba que PHP recibió el archivo sin errores y que es una subida real (no una ruta inventada).
     *
     * @param array<string, mixed>|null $archivo
     * @return string ruta del archivo temporal
     */
    private function validarCarga(?array $archivo): string
    {
        if ($archivo === null || !isset($archivo['error']) || is_array($archivo['error'])) {
            throw new ErrorHttp('No se recibió ningún archivo (campo "documento").', 422);
        }
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new ErrorHttp(PoliticaArchivos::mensajeErrorSubida((int) $archivo['error']), 422);
        }

        $temporal = (string) ($archivo['tmp_name'] ?? '');
        if (!is_uploaded_file($temporal)) {
            throw new ErrorHttp('El archivo recibido no es válido.', 400);
        }

        return $temporal;
    }

    /** Extensión permitida, tipo MIME real y firma interna: las tres deben coincidir. */
    private function validarContenido(string $temporal, string $extension): void
    {
        if (!PoliticaArchivos::extensionPermitida($extension)) {
            throw new ErrorHttp('Formato no permitido. Solo se aceptan PDF, JPG, PNG o DOCX.', 415);
        }
        if (!PoliticaArchivos::mimeCoincide($temporal, $extension)) {
            throw new ErrorHttp('El contenido del archivo no corresponde a la extensión .' . $extension . '.', 415);
        }
        if (!PoliticaArchivos::firmaValida($temporal, $extension)) {
            throw new ErrorHttp('El archivo está dañado o no es un .' . $extension . ' válido.', 415);
        }
    }
}
