<?php
/**
 * handlers/upload_documento.php
 * -----------------------------------------------------------------------
 * Recibe un documento del legajo aduanero y lo asocia a un DO.
 *
 * Petición : POST multipart/form-data
 *            Cabecera X-CSRF-Token (la envía panel/dashboard.php)
 *            Campos: do_id, tipo_documento, documento (archivo)
 * Respuesta: SIEMPRE JSON
 *            { "status": "success" | "error", "message": "...", "data": {...} }
 *
 * Validaciones: sesión, CSRF, rol, extensión (PDF/JPG/PNG/DOCX), tipo MIME
 * real, firma interna del archivo y tamaño máximo (10 MB).
 * -----------------------------------------------------------------------
 */
declare(strict_types=1);

ini_set('display_errors', '0');
ob_start();

function responder(string $status, string $message, int $httpCode = 200, array $extra = []): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode(
        ['status' => $status, 'message' => $message] + $extra,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        error_log('[rubicam-panel] Fatal en upload_documento.php: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line']);
        responder('error', 'Error interno del servidor. Revise logs/php-error.log.', 500);
    }
});
set_exception_handler(static function (Throwable $e): void {
    error_log('[rubicam-panel] Excepción en upload_documento.php: ' . $e->getMessage());
    responder('error', 'Error interno del servidor. Intente de nuevo.', 500);
});

require_once dirname(__DIR__) . '/config/config_db.php';

/** Extensiones permitidas → tipos MIME aceptados (verificados con finfo). */
const MIMES_PERMITIDOS = [
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

/** Fragmento del nombre final según el tipo de documento. */
const SLUG_TIPO_DOCUMENTO = [
    'Declaración de Importación' => 'DECLARACION',
    'Factura Comercial'          => 'FACTURA',
    'Lista de Empaque'           => 'LISTA-EMPAQUE',
    'RUT'                        => 'RUT',
    'BL/Guía aérea'              => 'BL-GUIA',
    'Otro'                       => 'OTRO',
];

// ---------------------------------------------------------------------
// 1) Seguridad: sesión, método, límite del servidor, CSRF y permisos.
// ---------------------------------------------------------------------
iniciar_sesion_panel();

if (!usuario_autenticado()) {
    responder('error', 'Su sesión expiró. Inicie sesión nuevamente.', 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder('error', 'Método no permitido. Use POST.', 405);
}

// Si se supera post_max_size, PHP descarta $_POST y $_FILES sin avisar.
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST) && empty($_FILES)) {
    responder('error', 'El archivo supera el límite del servidor (post_max_size = ' . ini_get('post_max_size') . ').', 413);
}

if (!csrf_valido_panel($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null))) {
    responder('error', 'Token de seguridad inválido. Recargue la página e intente de nuevo.', 403);
}
if (!usuario_puede_editar()) {
    responder('error', 'Su rol no tiene permiso para adjuntar documentos.', 403);
}

// ---------------------------------------------------------------------
// 2) Validación de campos.
// ---------------------------------------------------------------------
$doId          = filter_var($_POST['do_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$tipoDocumento = trim((string) ($_POST['tipo_documento'] ?? ''));

if ($doId === false || $doId === null) {
    responder('error', 'Operación (do_id) inválida.', 422);
}
if (!in_array($tipoDocumento, TIPOS_DOCUMENTO, true)) {
    responder('error', 'Tipo de documento no válido.', 422);
}

$archivo = $_FILES['documento'] ?? null;
if (!is_array($archivo) || !isset($archivo['error']) || is_array($archivo['error'])) {
    responder('error', 'No se recibió ningún archivo (campo "documento").', 422);
}
if ($archivo['error'] !== UPLOAD_ERR_OK) {
    responder('error', mensaje_error_subida((int) $archivo['error']), 422);
}
if (!is_uploaded_file($archivo['tmp_name'])) {
    responder('error', 'El archivo recibido no es válido.', 400);
}

$tamano = (int) filesize($archivo['tmp_name']);
if ($tamano <= 0) {
    responder('error', 'El archivo está vacío.', 422);
}
if ($tamano > UPLOAD_MAX_BYTES) {
    responder('error', 'El archivo supera el tamaño máximo permitido (10 MB).', 413);
}

$nombreOriginal = limpiar_nombre_original((string) $archivo['name']);
$extension      = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

if (!array_key_exists($extension, MIMES_PERMITIDOS)) {
    responder('error', 'Formato no permitido. Solo se aceptan PDF, JPG, PNG o DOCX.', 415);
}

if (class_exists('finfo')) {
    $mimeReal = (string) (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    if (!in_array($mimeReal, MIMES_PERMITIDOS[$extension], true)) {
        responder('error', 'El contenido del archivo no corresponde a la extensión .' . $extension . '.', 415);
    }
}
if (!firma_archivo_valida($archivo['tmp_name'], $extension)) {
    responder('error', 'El archivo está dañado o no es un .' . $extension . ' válido.', 415);
}

// ---------------------------------------------------------------------
// 3) Verificar el DO.
// ---------------------------------------------------------------------
try {
    $pdo = get_db_connection();
} catch (RuntimeException $e) {
    responder('error', $e->getMessage(), 503);
}

$stmt = $pdo->prepare('SELECT id, do_codigo FROM procesos_do WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $doId]);
$do = $stmt->fetch();
if (!$do) {
    responder('error', 'La orden de operación indicada no existe.', 404);
}

// ---------------------------------------------------------------------
// 4) Guardar el archivo con nombre seguro: DO_TIPO_FECHA-HORA_ALEATORIO.ext
// ---------------------------------------------------------------------
preparar_directorio_cargas();

$extensionFinal = $extension === 'jpeg' ? 'jpg' : $extension;
$prefijoDo      = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $do['do_codigo'])) ?: 'DO';
$nombreSeguro   = sprintf(
    '%s_%s_%s_%s.%s',
    $prefijoDo,
    SLUG_TIPO_DOCUMENTO[$tipoDocumento],
    date('Ymd-His'),
    bin2hex(random_bytes(3)),
    $extensionFinal
);
$rutaDestino = UPLOAD_DIR . $nombreSeguro;

if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
    error_log('[rubicam-panel] move_uploaded_file falló hacia ' . $rutaDestino);
    responder('error', 'No se pudo guardar el archivo en el servidor (verifique permisos de /uploads).', 500);
}
@chmod($rutaDestino, 0644);

// ---------------------------------------------------------------------
// 5) Registrar en documentos_do.
// ---------------------------------------------------------------------
$rutaRelativa = 'uploads/' . $nombreSeguro;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO documentos_do (do_id, nombre_archivo, ruta_archivo, tipo_documento)
         VALUES (:do_id, :nombre_archivo, :ruta_archivo, :tipo_documento)'
    );
    $stmt->execute([
        'do_id'          => $doId,
        'nombre_archivo' => $nombreOriginal,
        'ruta_archivo'   => $rutaRelativa,
        'tipo_documento' => $tipoDocumento,
    ]);
    $documentoId = (int) $pdo->lastInsertId();

    // Referencia rápida al último adjunto del DO.
    $stmt = $pdo->prepare('UPDATE procesos_do SET archivo_adjunto = :ruta WHERE id = :id');
    $stmt->execute(['ruta' => $rutaRelativa, 'id' => $doId]);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    @unlink($rutaDestino);
    error_log('[rubicam-panel] Error registrando documento del DO ' . $doId . ': ' . $e->getMessage());

    // 1265 = "Data truncated": el ENUM tipo_documento no tiene el valor nuevo.
    if ((int) ($e->errorInfo[1] ?? 0) === 1265) {
        responder('error', 'La base de datos no reconoce el tipo "' . $tipoDocumento . '". Ejecute SQL/migracion_panel_v2.sql.', 500);
    }
    responder('error', 'Error al registrar el documento en la base de datos.', 500);
}

responder('success', sprintf('"%s" se adjuntó al DO %s.', $nombreOriginal, $do['do_codigo']), 201, [
    'data' => [
        'documento' => [
            'id'             => $documentoId,
            'do_id'          => $doId,
            'do_codigo'      => $do['do_codigo'],
            'tipo_documento' => $tipoDocumento,
            'nombre_archivo' => $nombreOriginal,
            'archivo'        => $nombreSeguro,
            'tamano'         => $tamano,
            'fecha_subida'   => date('Y-m-d H:i:s'),
        ],
    ],
]);

// ---------------------------------------------------------------------
// Funciones
// ---------------------------------------------------------------------

function mensaje_error_subida(int $codigo): string
{
    return match ($codigo) {
        UPLOAD_ERR_INI_SIZE   => 'El archivo supera upload_max_filesize del servidor (' . ini_get('upload_max_filesize') . ').',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el tamaño máximo del formulario.',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió de forma incompleta. Intente de nuevo.',
        UPLOAD_ERR_NO_FILE    => 'Seleccione un archivo para subir.',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor (upload_tmp_dir).',
        UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo en disco.',
        UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP bloqueó la subida.',
        default               => 'Error desconocido al subir el archivo.',
    };
}

/** Nombre original apto para mostrar (sin rutas ni caracteres de control). */
function limpiar_nombre_original(string $nombre): string
{
    $nombre = basename(str_replace('\\', '/', $nombre));
    $nombre = (string) preg_replace('/[\x00-\x1F\x7F"<>|:*?]/u', '', $nombre);
    $nombre = trim($nombre) !== '' ? trim($nombre) : 'documento';
    if (mb_strlen($nombre) > 200) {
        $ext    = pathinfo($nombre, PATHINFO_EXTENSION);
        $nombre = mb_substr(pathinfo($nombre, PATHINFO_FILENAME), 0, 190) . ($ext !== '' ? '.' . $ext : '');
    }
    return $nombre;
}

/** Comprueba la firma interna del archivo (no basta con la extensión). */
function firma_archivo_valida(string $ruta, string $extension): bool
{
    $cabecera = (string) file_get_contents($ruta, false, null, 0, 8);

    switch ($extension) {
        case 'pdf':
            return str_starts_with($cabecera, '%PDF-');
        case 'png':
            $info = @getimagesize($ruta);
            return $info !== false && $info[2] === IMAGETYPE_PNG;
        case 'jpg':
        case 'jpeg':
            $info = @getimagesize($ruta);
            return $info !== false && $info[2] === IMAGETYPE_JPEG;
        case 'docx':
            if (!str_starts_with($cabecera, "PK\x03\x04")) {
                return false;
            }
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($ruta) !== true) {
                    return false;
                }
                $esWord = $zip->locateName('word/document.xml') !== false;
                $zip->close();
                return $esWord;
            }
            return true;
        default:
            return false;
    }
}

/** Crea /uploads si no existe y bloquea su acceso directo desde la web. */
function preparar_directorio_cargas(): void
{
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        responder('error', 'No se pudo crear la carpeta de documentos en el servidor.', 500);
    }
    if (!is_writable(UPLOAD_DIR)) {
        responder('error', 'La carpeta de documentos no tiene permisos de escritura.', 500);
    }

    $htaccess = UPLOAD_DIR . '.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents(
            $htaccess,
            "# Acceso directo bloqueado: los documentos se descargan desde el panel (con sesión).\n"
            . "Require all denied\nOptions -Indexes\n"
        );
    }
}
