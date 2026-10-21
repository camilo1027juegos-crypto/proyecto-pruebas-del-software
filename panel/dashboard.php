<?php
/**
 * panel/dashboard.php
 * -----------------------------------------------------------------------
 * Panel operativo de Órdenes de Operación (DO) — SIA RUBICAM.
 *
 *  - Tabla de DOs con semáforo DIAN, KPIs y filtros (texto, sucursal,
 *    régimen y nivel de alerta).
 *  - Modal "Nueva operación (DO)"           → dashboard.php?action=crear
 *  - Modal "Actualizar estado / Observar"   → ../handlers/webhook_trigger.php
 *  - Modal "Documentos / Legajo aduanero"   → ../handlers/upload_documento.php
 *
 * Endpoints AJAX internos (GET salvo "crear"):
 *    ?action=listar                 JSON con todos los DOs + semáforo
 *    ?action=crear       (POST)     JSON: crea un DO
 *    ?action=documentos&do_id=N     JSON: legajo del DO
 *    ?action=descargar&doc_id=N     Descarga segura del archivo (con sesión)
 *
 * Estilos: Tailwind compilado (assets/css/tailwind.build.css).
 * Si agrega clases nuevas, ejecute: npm run build:css
 * -----------------------------------------------------------------------
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config_db.php';

iniciar_sesion_panel();

/** Íconos SVG (trazos 24×24) compartidos por PHP y JavaScript. */
const ICONOS = [
    'plus'      => '<path d="M12 5v14M5 12h14"/>',
    'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    'x'         => '<path d="M6 6l12 12M18 6 6 18"/>',
    'refresh'   => '<path d="M20 11a8 8 0 0 0-14.9-3.9M4 4v4h4M4 13a8 8 0 0 0 14.9 3.9M20 20v-4h-4"/>',
    'paperclip' => '<path d="m21 11-8.5 8.5a5 5 0 0 1-7-7L14 3.9a3.5 3.5 0 0 1 5 5l-8.6 8.6a2 2 0 0 1-2.8-2.8L15.5 7"/>',
    'folder'    => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
    'check'     => '<path d="m5 13 4 4L19 7"/>',
    'alert'     => '<path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
    'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'archive'   => '<path d="M3 4h18v4H3zM5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8M10 12h4"/>',
    'download'  => '<path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/>',
    'eye'       => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    'upload'    => '<path d="M12 20V9m0 0-4 4m4-4 4 4M5 4h14"/>',
    'logout'    => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3"/>',
    'menu'      => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    'pin'       => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
    'grid'      => '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
    'flow'      => '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 6h7M6 8.5v5a4 4 0 0 0 4 4h5.5"/>',
    'export'    => '<path d="M7 17 17 7M9 7h8v8"/>',
    'import'    => '<path d="M17 7 7 17M15 17H7V9"/>',
    'chart'     => '<path d="M4 20V10M10 20V4M16 20v-7M2 20h20"/>',
    'file'      => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/>',
    'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
    'chevron'   => '<path d="m9 6 6 6-6 6"/>',
    'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
];

function icono(string $nombre, string $clases = 'h-4 w-4'): string
{
    return '<svg class="' . h($clases) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . (ICONOS[$nombre] ?? '') . '</svg>';
}

// =====================================================================
// Endpoints AJAX
// =====================================================================

function responder_json(string $status, string $message, int $httpCode = 200, array $extra = []): never
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

function atender_accion_ajax(string $accion): never
{
    ini_set('display_errors', '0');
    ob_start();

    if (!usuario_autenticado()) {
        if ($accion === 'descargar') {
            header('Location: index.php');
            exit;
        }
        responder_json('error', 'Su sesión expiró. Inicie sesión nuevamente.', 401);
    }

    try {
        $pdo = get_db_connection();
        match ($accion) {
            'listar'     => accion_listar($pdo),
            'crear'      => accion_crear($pdo),
            'documentos' => accion_documentos($pdo),
            'descargar'  => accion_descargar($pdo),
            default      => responder_json('error', 'Acción no reconocida.', 400),
        };
    } catch (PDOException $e) {
        error_log('[rubicam-panel] Error SQL en acción "' . $accion . '": ' . $e->getMessage());
        responder_json('error', 'Error de base de datos. Intente de nuevo.', 500);
    } catch (RuntimeException $e) {
        responder_json('error', $e->getMessage(), 503);
    } catch (Throwable $e) {
        error_log('[rubicam-panel] Error en acción "' . $accion . '": ' . $e->getMessage());
        responder_json('error', 'Error interno del servidor.', 500);
    }

    responder_json('error', 'La acción no produjo respuesta.', 500);
}

/** Lista de DOs (o uno solo) con semáforo y bitácora. */
function obtener_dos(PDO $pdo, ?int $id = null): array
{
    $sql = 'SELECT p.id, p.do_codigo, p.cliente, p.nit, p.email, p.telefono, p.regimen, p.sucursal,
                   p.estado, p.fecha_apertura, p.fecha_vencimiento, p.observaciones, p.actualizado_en,
                   (SELECT COUNT(*) FROM documentos_do d WHERE d.do_id = p.id) AS total_documentos
              FROM procesos_do p';

    if ($id !== null) {
        $stmt = $pdo->prepare($sql . ' WHERE p.id = :id');
        $stmt->execute(['id' => $id]);
    } else {
        $stmt = $pdo->query($sql . ' ORDER BY p.fecha_apertura DESC, p.id DESC');
    }

    $hoy = new DateTimeImmutable('today');
    return array_map(static fn (array $fila): array => formatear_do($fila, $hoy), $stmt->fetchAll());
}

function formatear_do(array $fila, DateTimeImmutable $hoy): array
{
    $bitacora = bitacora_parsear($fila['observaciones'] ?? null, 15);

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
        'fecha_vencimiento'  => $fila['fecha_vencimiento'] !== null ? (string) $fila['fecha_vencimiento'] : null,
        'total_documentos'   => (int) $fila['total_documentos'],
        'semaforo'           => calcular_semaforo_do((string) $fila['fecha_apertura'], $fila['fecha_vencimiento'], (string) $fila['estado'], $hoy),
        'bitacora'           => $bitacora,
        'ultima_observacion' => $bitacora[0]['texto'] ?? '',
        'actualizado_en'     => (string) ($fila['actualizado_en'] ?? ''),
    ];
}

function accion_listar(PDO $pdo): never
{
    responder_json('success', 'Operaciones cargadas.', 200, [
        'data' => [
            'dos'      => obtener_dos($pdo),
            'hoy'      => date('Y-m-d'),
            'generado' => date('d/m/Y H:i'),
        ],
    ]);
}

function leer_json_entrada(): array
{
    $raw = (string) file_get_contents('php://input');
    if ($raw === '' || strlen($raw) > 65536) {
        responder_json('error', 'Solicitud vacía o demasiado grande.', 400);
    }
    try {
        $datos = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        responder_json('error', 'El cuerpo de la solicitud no es un JSON válido.', 400);
    }
    if (!is_array($datos)) {
        responder_json('error', 'Formato de solicitud inválido.', 400);
    }
    return $datos;
}

function fecha_valida(string $valor): ?DateTimeImmutable
{
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return ($fecha !== false && $fecha->format('Y-m-d') === $valor) ? $fecha : null;
}

/**
 * Valida y normaliza los datos de un DO nuevo.
 * @return array{0: array<string, mixed>, 1: array<string, string>} [datos, errores]
 */
function validar_nuevo_do(array $in): array
{
    $texto = static fn (string $k): string => trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($in[$k] ?? ''))));

    $errores = [];
    $datos = [
        'do_codigo'         => strtoupper((string) preg_replace('/\s+/', '', (string) ($in['do_codigo'] ?? ''))),
        'cliente'           => $texto('cliente'),
        'nit'               => (string) preg_replace('/[\s.]/', '', (string) ($in['nit'] ?? '')),
        'regimen'           => trim((string) ($in['regimen'] ?? '')),
        'sucursal'          => trim((string) ($in['sucursal'] ?? '')),
        'fecha_apertura'    => trim((string) ($in['fecha_apertura'] ?? '')),
        'fecha_vencimiento' => trim((string) ($in['fecha_vencimiento'] ?? '')),
        'email'             => trim((string) ($in['email'] ?? '')),
        'telefono'          => $texto('telefono'),
        'observacion'       => $texto('observacion'),
    ];

    // Código DO: SUCURSAL(3) + AÑO(2) + RÉGIMEN(3) + CONSECUTIVO. Ej.: BOG26IMP0001
    if ($datos['do_codigo'] === '') {
        $errores['do_codigo'] = 'El código del DO es obligatorio.';
    } elseif (strlen($datos['do_codigo']) > 30 || !preg_match('/^[A-Z]{3}\d{2}[A-Z]{3}\d{3,12}$/', $datos['do_codigo'])) {
        $errores['do_codigo'] = 'Formato esperado: sucursal + año + régimen + consecutivo (ej. BOG26IMP0001).';
    }

    $largoCliente = mb_strlen($datos['cliente']);
    if ($largoCliente < 3 || $largoCliente > 150) {
        $errores['cliente'] = 'Escriba la razón social del cliente (3 a 150 caracteres).';
    }

    if (!preg_match('/^(\d{5,15})(?:-(\d))?$/', $datos['nit'], $m)) {
        $errores['nit'] = 'NIT inválido. Use solo números, con o sin dígito de verificación (ej. 900123456-8).';
    } else {
        $dvCalculado = calcular_dv_nit($m[1]);
        if (isset($m[2]) && $m[2] !== '' && (int) $m[2] !== $dvCalculado) {
            $errores['nit'] = "El dígito de verificación no corresponde: para {$m[1]} es {$dvCalculado}.";
        } else {
            $datos['nit'] = $m[1] . '-' . $dvCalculado;
        }
    }

    if (!in_array($datos['regimen'], REGIMENES_DO, true)) {
        $errores['regimen'] = 'Seleccione un régimen válido.';
    }
    if (!in_array($datos['sucursal'], SUCURSALES_DO, true)) {
        $errores['sucursal'] = 'Seleccione una sucursal válida.';
    }

    $apertura = fecha_valida($datos['fecha_apertura']);
    if ($apertura === null) {
        $errores['fecha_apertura'] = 'Fecha de apertura inválida.';
    } elseif ($apertura > new DateTimeImmutable('today')) {
        $errores['fecha_apertura'] = 'La fecha de apertura no puede ser futura.';
    }

    if ($datos['fecha_vencimiento'] === '' && $apertura !== null) {
        $datos['fecha_vencimiento'] = $apertura->modify('+' . PLAZO_DIAN_DIAS . ' days')->format('Y-m-d');
    }
    $vencimiento = fecha_valida($datos['fecha_vencimiento']);
    if ($vencimiento === null) {
        $errores['fecha_vencimiento'] = 'Fecha de vencimiento inválida.';
    } elseif ($apertura !== null && $vencimiento < $apertura) {
        $errores['fecha_vencimiento'] = 'El vencimiento no puede ser anterior a la apertura.';
    }

    if ($datos['email'] !== '' && (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL) || strlen($datos['email']) > 150)) {
        $errores['email'] = 'Correo electrónico inválido.';
    }
    if ($datos['telefono'] !== '' && !preg_match('/^[0-9+\-\s()]{7,30}$/', $datos['telefono'])) {
        $errores['telefono'] = 'Teléfono inválido (7 a 30 dígitos).';
    }
    if (mb_strlen($datos['observacion']) > 1000) {
        $errores['observacion'] = 'La observación no puede superar 1.000 caracteres.';
    }

    return [$datos, $errores];
}

function accion_crear(PDO $pdo): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        responder_json('error', 'Método no permitido. Use POST.', 405);
    }
    if (!csrf_valido_panel($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        responder_json('error', 'Token de seguridad inválido. Recargue la página.', 403);
    }
    if (!usuario_puede_editar()) {
        responder_json('error', 'Su rol no tiene permiso para crear operaciones.', 403);
    }

    [$datos, $errores] = validar_nuevo_do(leer_json_entrada());
    if ($errores) {
        responder_json('error', 'Revise los campos marcados en el formulario.', 422, ['errors' => $errores]);
    }

    $stmt = $pdo->prepare('SELECT id FROM procesos_do WHERE do_codigo = :codigo LIMIT 1');
    $stmt->execute(['codigo' => $datos['do_codigo']]);
    if ($stmt->fetch()) {
        responder_json('error', 'Ya existe un DO con el código ' . $datos['do_codigo'] . '.', 409, [
            'errors' => ['do_codigo' => 'Este código ya está registrado.'],
        ]);
    }

    $bitacora = bitacora_agregar(
        null,
        $datos['observacion'] !== '' ? $datos['observacion'] : 'Apertura del DO registrada en el panel.',
        usuario_nombre_sesion(),
        ESTADO_INICIAL_DO
    );

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO procesos_do
                (do_codigo, cliente, nit, email, telefono, regimen, sucursal, estado,
                 fecha_apertura, fecha_vencimiento, observaciones)
             VALUES
                (:do_codigo, :cliente, :nit, :email, :telefono, :regimen, :sucursal, :estado,
                 :fecha_apertura, :fecha_vencimiento, :observaciones)'
        );
        $stmt->execute([
            'do_codigo'         => $datos['do_codigo'],
            'cliente'           => $datos['cliente'],
            'nit'               => $datos['nit'],
            'email'             => $datos['email'] !== '' ? $datos['email'] : null,
            'telefono'          => $datos['telefono'] !== '' ? $datos['telefono'] : null,
            'regimen'           => $datos['regimen'],
            'sucursal'          => $datos['sucursal'],
            'estado'            => ESTADO_INICIAL_DO,
            'fecha_apertura'    => $datos['fecha_apertura'],
            'fecha_vencimiento' => $datos['fecha_vencimiento'],
            'observaciones'     => $bitacora,
        ]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            responder_json('error', 'Ya existe un DO con el código ' . $datos['do_codigo'] . '.', 409, [
                'errors' => ['do_codigo' => 'Este código ya está registrado.'],
            ]);
        }
        throw $e;
    }

    $nuevo = obtener_dos($pdo, (int) $pdo->lastInsertId())[0] ?? null;

    responder_json('success', 'DO ' . $datos['do_codigo'] . ' creado correctamente.', 201, ['data' => ['do' => $nuevo]]);
}

/** Ruta física de un documento (siempre dentro de UPLOAD_DIR). */
function ruta_fisica_documento(string $rutaGuardada): ?string
{
    $archivo = basename(str_replace('\\', '/', $rutaGuardada));
    if ($archivo === '' || $archivo === '.' || $archivo === '..') {
        return null;
    }
    return UPLOAD_DIR . $archivo;
}

function accion_documentos(PDO $pdo): never
{
    $doId = filter_var($_GET['do_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($doId === false || $doId === null) {
        responder_json('error', 'Operación (do_id) inválida.', 422);
    }

    $stmt = $pdo->prepare('SELECT id, do_codigo FROM procesos_do WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $doId]);
    $do = $stmt->fetch();
    if (!$do) {
        responder_json('error', 'La orden de operación no existe.', 404);
    }

    $stmt = $pdo->prepare(
        'SELECT id, tipo_documento, nombre_archivo, ruta_archivo, fecha_subida
           FROM documentos_do
          WHERE do_id = :id
          ORDER BY fecha_subida DESC, id DESC'
    );
    $stmt->execute(['id' => $doId]);

    $documentos = [];
    foreach ($stmt->fetchAll() as $doc) {
        $ruta   = ruta_fisica_documento((string) $doc['ruta_archivo']);
        $existe = $ruta !== null && is_file($ruta);
        $ext    = strtolower(pathinfo((string) $doc['ruta_archivo'], PATHINFO_EXTENSION));

        $documentos[] = [
            'id'             => (int) $doc['id'],
            'tipo_documento' => (string) $doc['tipo_documento'],
            'nombre_archivo' => (string) $doc['nombre_archivo'],
            'extension'      => $ext,
            'fecha_subida'   => (string) $doc['fecha_subida'],
            'existe'         => $existe,
            'tamano'         => $existe ? (int) filesize($ruta) : null,
            'visualizable'   => $existe && in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true),
            'url_descarga'   => 'dashboard.php?action=descargar&doc_id=' . (int) $doc['id'],
            'url_ver'        => 'dashboard.php?action=descargar&modo=ver&doc_id=' . (int) $doc['id'],
        ];
    }

    responder_json('success', 'Legajo cargado.', 200, [
        'data' => [
            'do'         => ['id' => (int) $do['id'], 'do_codigo' => (string) $do['do_codigo']],
            'documentos' => $documentos,
        ],
    ]);
}

function error_descarga(int $httpCode, string $mensaje): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($httpCode);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Documento no disponible</title></head>'
        . '<body style="font-family:system-ui,sans-serif;background:#f1f5f9;color:#0f172a;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0">'
        . '<div style="background:#fff;padding:32px 40px;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);max-width:420px;text-align:center">'
        . '<h1 style="font-size:18px;margin:0 0 8px">Documento no disponible</h1><p style="color:#475569;margin:0 0 20px">' . h($mensaje) . '</p>'
        . '<a href="dashboard.php" style="color:#065f46;font-weight:700">Volver al panel</a></div></body></html>';
    exit;
}

function accion_descargar(PDO $pdo): never
{
    $docId = filter_var($_GET['doc_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($docId === false || $docId === null) {
        error_descarga(400, 'El identificador del documento no es válido.');
    }

    $stmt = $pdo->prepare('SELECT nombre_archivo, ruta_archivo FROM documentos_do WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $docId]);
    $doc = $stmt->fetch();
    if (!$doc) {
        error_descarga(404, 'El documento no existe.');
    }

    $ruta = ruta_fisica_documento((string) $doc['ruta_archivo']);
    $real = $ruta !== null ? realpath($ruta) : false;
    $base = realpath(UPLOAD_DIR);
    if ($real === false || $base === false || !str_starts_with($real, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) || !is_file($real)) {
        error_descarga(404, 'El archivo ya no se encuentra en el servidor.');
    }

    $mimes = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $ext    = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    $inline = ($_GET['modo'] ?? '') === 'ver' && in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true);

    $nombre = trim((string) $doc['nombre_archivo']) !== '' ? (string) $doc['nombre_archivo'] : basename($real);
    $nombre = str_replace(["\r", "\n", '"'], '', $nombre);
    $nombreAscii = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($real));
    header(sprintf(
        "Content-Disposition: %s; filename=\"%s\"; filename*=UTF-8''%s",
        $inline ? 'inline' : 'attachment',
        $nombreAscii,
        rawurlencode($nombre)
    ));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($real);
    exit;
}

// =====================================================================
// Controlador
// =====================================================================

$accion = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';
if ($accion !== '') {
    atender_accion_ajax($accion);
}

if (!usuario_autenticado()) {
    header('Location: index.php');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

$errorCarga = null;
$dos = [];
try {
    $dos = obtener_dos(get_db_connection());
} catch (Throwable $e) {
    error_log('[rubicam-panel] Error cargando DOs: ' . $e->getMessage());
    $errorCarga = 'No fue posible cargar las órdenes de operación. Verifique la conexión con la base de datos.';
}

$puedeEditar     = usuario_puede_editar();
$sucursalUsuario = (string) ($_SESSION['sucursal'] ?? '');
$sucursalesFiltro = array_values(array_unique(array_merge(SUCURSALES_DO, array_column($dos, 'sucursal'))));
sort($sucursalesFiltro);

$configJs = [
    'csrf'             => csrf_token_panel(),
    'puedeEditar'      => $puedeEditar,
    'hoy'              => date('Y-m-d'),
    'generado'         => date('d/m/Y H:i'),
    'usuario'          => usuario_nombre_sesion(),
    'sucursalUsuario'  => $sucursalUsuario,
    'estados'          => ESTADOS_DO,
    'estadosFinales'   => ESTADOS_FINALES,
    'sucursales'       => SUCURSALES_DO,
    'regimenes'        => REGIMENES_DO,
    'tiposDocumento'   => TIPOS_DOCUMENTO,
    'prefijosSucursal' => PREFIJOS_SUCURSAL,
    'prefijosRegimen'  => PREFIJOS_REGIMEN,
    'plazoDias'        => PLAZO_DIAN_DIAS,
    'maxBytes'         => UPLOAD_MAX_BYTES,
    'iconos'           => ICONOS,
    'dos'              => $dos,
    'errorCarga'       => $errorCarga,
];

$claseInput = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 disabled:bg-slate-50';
$claseLabel = 'mb-1 block text-xs font-bold uppercase tracking-wide text-slate-600';
$claseNavActivo = 'bg-rubi-emerald text-white';
$claseNav = 'flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors';
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Órdenes de Operación · SIA RUBICAM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>svg{width:1.25em;height:1.25em;display:inline-block;flex-shrink:0}</style>
<link rel="stylesheet" href="../assets/css/tailwind.build.css">
<link rel="icon" href="data:,">
</head>
<body class="h-full bg-slate-100 font-sans text-slate-800 antialiased">

<noscript>
    <div class="bg-red-600 px-4 py-3 text-center text-sm font-semibold text-white">El panel necesita JavaScript habilitado para funcionar.</div>
</noscript>

<!-- ============================ SIDEBAR ============================ -->
<div id="sidebar-fondo" class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden"></div>
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-gradient-to-b from-rubi-navy to-[#061f2e] text-slate-300 transition-transform duration-200 lg:translate-x-0" aria-label="Menú lateral">
    <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-lg font-extrabold text-rubi-orange ring-1 ring-white/15">R</span>
        <div>
            <div class="text-base font-extrabold tracking-wide text-white">RUBICAM</div>
            <div class="text-[11px] font-bold uppercase tracking-widest text-rubi-orange">SIA · Nivel 2</div>
        </div>
    </div>

    <nav class="flex-1 space-y-1 px-3 py-4 text-sm font-medium" aria-label="Menú principal">
        <span class="<?= $claseNav ?> cursor-not-allowed text-slate-500" title="Próximamente" aria-disabled="true"><?= icono('grid') ?> Maestras</span>
        <a href="#" data-nav-regimen="" class="<?= $claseNav ?> <?= $claseNavActivo ?>"><?= icono('flow') ?> Operación</a>
        <a href="#" data-nav-regimen="Exportación" class="<?= $claseNav ?> text-slate-300 hover:bg-white/5 hover:text-white"><?= icono('export') ?> Exportaciones</a>
        <a href="#" data-nav-regimen="Importación" class="<?= $claseNav ?> text-slate-300 hover:bg-white/5 hover:text-white"><?= icono('import') ?> Importaciones</a>
        <span class="<?= $claseNav ?> cursor-not-allowed text-slate-500" title="Próximamente" aria-disabled="true"><?= icono('chart') ?> Reportes DO</span>
    </nav>

    <div class="border-t border-white/10 p-4">
        <div class="mb-3 flex items-center gap-3">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-slate-200"><?= icono('user', 'h-5 w-5') ?></span>
            <div class="min-w-0">
                <div class="truncate text-sm font-semibold text-white"><?= h(usuario_nombre_sesion()) ?></div>
                <div class="text-xs capitalize text-slate-400"><?= h((string) ($_SESSION['rol'] ?? '')) ?></div>
            </div>
        </div>
        <a href="logout.php" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-red-300 transition-colors hover:bg-red-500/10 hover:text-red-200"><?= icono('logout') ?> Cerrar sesión</a>
    </div>
</aside>

<!-- ============================ CONTENIDO ============================ -->
<div class="lg:pl-64">
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <button type="button" id="btn-menu" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 lg:hidden" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false"><?= icono('menu', 'h-5 w-5') ?></button>
                <nav aria-label="Ruta" class="flex items-center gap-1.5 text-sm text-slate-500">
                    Operación <?= icono('chevron', 'h-3.5 w-3.5 text-slate-400') ?> <strong class="font-semibold text-rubi-navy">Órdenes de Operación (DO)</strong>
                </nav>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($sucursalUsuario !== ''): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-rubi-emerald"><?= icono('pin', 'h-3.5 w-3.5') ?> <?= h($sucursalUsuario) ?></span>
                <?php endif; ?>
                <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-bold text-orange-600">Año operativo <?= date('Y') ?></span>
                <?php if ($puedeEditar): ?>
                <button type="button" id="btn-nuevo-do" class="inline-flex items-center gap-1.5 rounded-lg bg-rubi-orange px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-500/40">
                    <?= icono('plus') ?> Nueva operación (DO)
                </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="px-4 py-6 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-rubi-navy">Órdenes de Operación</h1>
                <p class="mt-1 text-sm text-slate-500">Seguimiento de DOs y semáforo DIAN: plazo de referencia de <?= PLAZO_DIAN_DIAS ?> días desde la apertura.</p>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-500">
                Actualizado: <span id="txt-actualizado" class="font-semibold text-slate-700"><?= h(date('d/m/Y H:i')) ?></span>
                <button type="button" id="btn-recargar" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-rubi-emerald" title="Recargar datos" aria-label="Recargar datos"><?= icono('refresh') ?></button>
            </div>
        </div>

        <div id="alerta-carga" class="mb-6 hidden items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
            <?= icono('alert', 'mt-0.5 h-5 w-5 shrink-0') ?><span id="alerta-carga-texto"></span>
        </div>

        <!-- KPIs (clic = filtrar) -->
        <section class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" aria-label="Indicadores">
            <?php
            $kpis = [
                ['activos', 'Activas', 'folder', 'bg-sky-50 text-sky-600'],
                ['al_dia', 'Al día', 'check', 'bg-emerald-50 text-emerald-600'],
                ['seguimiento', 'En seguimiento', 'clock', 'bg-orange-50 text-orange-600'],
                ['critico', 'Críticas', 'alert', 'bg-red-50 text-red-600'],
                ['cerrado', 'Finalizadas', 'archive', 'bg-slate-100 text-slate-500'],
            ];
            foreach ($kpis as [$clave, $etiqueta, $ic, $colores]): ?>
            <button type="button" data-kpi="<?= $clave ?>" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-slate-300 hover:shadow">
                <span>
                    <span data-kpi-num class="block text-2xl font-extrabold text-rubi-navy">0</span>
                    <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500"><?= h($etiqueta) ?></span>
                </span>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl <?= $colores ?>"><?= icono($ic, 'h-5 w-5') ?></span>
            </button>
            <?php endforeach; ?>
        </section>

        <!-- Panel principal -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="grid gap-3 border-b border-slate-100 p-4 md:grid-cols-12 md:items-end sm:p-5">
                <div class="md:col-span-4">
                    <label for="f-texto" class="<?= $claseLabel ?>">Buscar</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400"><?= icono('search') ?></span>
                        <input type="search" id="f-texto" class="<?= $claseInput ?> pl-9" placeholder="N.º de DO, cliente o NIT" autocomplete="off">
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label for="f-sucursal" class="<?= $claseLabel ?>">Sucursal</label>
                    <select id="f-sucursal" class="<?= $claseInput ?>">
                        <option value="">Todas</option>
                        <?php foreach ($sucursalesFiltro as $s): ?>
                        <option value="<?= h($s) ?>"><?= h($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="f-regimen" class="<?= $claseLabel ?>">Régimen</label>
                    <select id="f-regimen" class="<?= $claseInput ?>">
                        <option value="">Todos</option>
                        <?php foreach (REGIMENES_DO as $r): ?>
                        <option value="<?= h($r) ?>"><?= h($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="f-nivel" class="<?= $claseLabel ?>">Nivel de alerta</label>
                    <select id="f-nivel" class="<?= $claseInput ?>">
                        <option value="">Todos</option>
                        <option value="activos">Activos (sin finalizar)</option>
                        <option value="al_dia">Al día</option>
                        <option value="seguimiento">En seguimiento</option>
                        <option value="critico">Críticos</option>
                        <option value="cerrado">Finalizados</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <button type="button" id="f-limpiar" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-800">
                        <?= icono('x') ?> Limpiar filtros
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/60 px-4 py-2.5 text-xs text-slate-500 sm:px-5">
                <span id="txt-conteo" aria-live="polite">—</span>
                <span class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Al día &lt; <?= SEMAFORO_DIAS_SEGUIMIENTO ?> d</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-orange-500"></span>Seguimiento <?= SEMAFORO_DIAS_SEGUIMIENTO ?>–<?= SEMAFORO_DIAS_CRITICO ?> d</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-red-500"></span>Crítico &gt; <?= SEMAFORO_DIAS_CRITICO ?> d o vencido</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-slate-400"></span>Cerrado: sin alerta</span>
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="py-3 pl-5 pr-3">DO</th>
                            <th scope="col" class="px-3 py-3">Cliente</th>
                            <th scope="col" class="px-3 py-3">Estado</th>
                            <th scope="col" class="px-3 py-3">Fechas</th>
                            <th scope="col" class="px-3 py-3">Semáforo DIAN</th>
                            <th scope="col" class="sticky right-0 border-l border-slate-100 bg-slate-50 px-3 py-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-body" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>

            <div id="estado-vacio" class="hidden flex-col items-center justify-center gap-2 px-4 py-14 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><?= icono('folder', 'h-6 w-6') ?></span>
                <p id="estado-vacio-texto" class="text-sm font-medium text-slate-600"></p>
            </div>
        </section>
    </main>
</div>

<?php if ($puedeEditar): ?>
<!-- ===================== MODAL: NUEVA OPERACIÓN ===================== -->
<div id="modal-nuevo" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modal-nuevo-titulo">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6" data-fondo-modal>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <form id="form-nuevo" novalidate>
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
                    <div>
                        <h2 id="modal-nuevo-titulo" class="text-lg font-extrabold text-rubi-navy">Nueva operación (DO)</h2>
                        <p class="text-xs text-slate-500">Se crea en estado «<?= h(ESTADO_INICIAL_DO) ?>».</p>
                    </div>
                    <button type="button" data-cerrar-modal class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Cerrar"><?= icono('x', 'h-5 w-5') ?></button>
                </div>

                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <div>
                        <label for="n-sucursal" class="<?= $claseLabel ?>">Sucursal *</label>
                        <select id="n-sucursal" name="sucursal" class="<?= $claseInput ?>" required>
                            <?php foreach (SUCURSALES_DO as $s): ?>
                            <option value="<?= h($s) ?>"><?= h($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p data-error-para="sucursal" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="n-regimen" class="<?= $claseLabel ?>">Régimen *</label>
                        <select id="n-regimen" name="regimen" class="<?= $claseInput ?>" required>
                            <?php foreach (REGIMENES_DO as $r): ?>
                            <option value="<?= h($r) ?>"><?= h($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p data-error-para="regimen" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="n-codigo" class="<?= $claseLabel ?>">Código DO *</label>
                        <input id="n-codigo" name="do_codigo" type="text" maxlength="30" class="<?= $claseInput ?> font-semibold uppercase tracking-wide" placeholder="BOG26IMP0001" autocomplete="off" required data-autofoco>
                        <p id="hint-codigo" data-pista-para="do_codigo" class="mt-1 text-xs text-slate-500"></p>
                        <p data-error-para="do_codigo" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="n-cliente" class="<?= $claseLabel ?>">Cliente *</label>
                        <input id="n-cliente" name="cliente" type="text" maxlength="150" class="<?= $claseInput ?>" placeholder="Razón social" required>
                        <p data-error-para="cliente" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="n-nit" class="<?= $claseLabel ?>">NIT *</label>
                        <input id="n-nit" name="nit" type="text" maxlength="20" class="<?= $claseInput ?>" placeholder="900123456-8" inputmode="numeric" required>
                        <p id="hint-nit" data-pista-para="nit" class="mt-1 text-xs text-slate-500">El dígito de verificación se calcula automáticamente.</p>
                        <p data-error-para="nit" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="n-apertura" class="<?= $claseLabel ?>">Fecha de apertura *</label>
                        <input id="n-apertura" name="fecha_apertura" type="date" class="<?= $claseInput ?>" required>
                        <p data-error-para="fecha_apertura" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="n-vencimiento" class="<?= $claseLabel ?>">Fecha de vencimiento *</label>
                        <input id="n-vencimiento" name="fecha_vencimiento" type="date" class="<?= $claseInput ?>" required>
                        <p class="mt-1 text-xs text-slate-500">Sugerida: apertura + <?= PLAZO_DIAN_DIAS ?> días (DIAN).</p>
                        <p data-error-para="fecha_vencimiento" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="n-email" class="<?= $claseLabel ?>">Correo del cliente <span class="font-normal normal-case text-slate-400">(opcional)</span></label>
                        <input id="n-email" name="email" type="email" maxlength="150" class="<?= $claseInput ?>" placeholder="operaciones@cliente.com">
                        <p data-error-para="email" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="n-telefono" class="<?= $claseLabel ?>">Teléfono <span class="font-normal normal-case text-slate-400">(opcional)</span></label>
                        <input id="n-telefono" name="telefono" type="tel" maxlength="30" class="<?= $claseInput ?>" placeholder="300 000 0000">
                        <p data-error-para="telefono" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="n-observacion" class="<?= $claseLabel ?>">Observación inicial</label>
                        <textarea id="n-observacion" name="observacion" rows="3" maxlength="1000" class="<?= $claseInput ?>" placeholder="Ej.: Pendiente Formulario 500 y factura comercial original."></textarea>
                        <p data-error-para="observacion" class="mt-1 hidden text-xs text-red-600"></p>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/60 px-6 py-4 sm:flex-row sm:justify-end">
                    <button type="button" data-cerrar-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancelar</button>
                    <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-rubi-emerald px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-rubi-emerald2 disabled:cursor-not-allowed disabled:opacity-60"><?= icono('plus') ?> <span data-texto-boton>Crear DO</span></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================= MODAL: ACTUALIZAR ESTADO / OBSERVAR ================= -->
<div id="modal-estado" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modal-estado-titulo">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6" data-fondo-modal>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
                <div class="min-w-0">
                    <h2 id="modal-estado-titulo" class="text-lg font-extrabold text-rubi-navy"><?= $puedeEditar ? 'Actualizar estado / Observar' : 'Bitácora de seguimiento' ?> — <span id="estado-codigo"></span></h2>
                    <p id="estado-cliente" class="truncate text-xs text-slate-500"></p>
                </div>
                <button type="button" data-cerrar-modal class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Cerrar"><?= icono('x', 'h-5 w-5') ?></button>
            </div>

            <div id="estado-resumen" class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/60 px-6 py-3 text-xs"></div>

            <?php if ($puedeEditar): ?>
            <form id="form-estado" class="space-y-4 px-6 py-5" novalidate>
                <div>
                    <label for="e-estado" class="<?= $claseLabel ?>">Nuevo estado</label>
                    <select id="e-estado" name="nuevo_estado" class="<?= $claseInput ?>" data-autofoco>
                        <?php foreach (ESTADOS_DO as $e): ?>
                        <option value="<?= h($e) ?>"><?= h($e) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <div class="mb-1 flex items-end justify-between">
                        <label for="e-observaciones" class="block text-xs font-bold uppercase tracking-wide text-slate-600">Observación de seguimiento</label>
                        <span id="e-contador" class="text-[11px] text-slate-400">0 / 1000</span>
                    </div>
                    <textarea id="e-observaciones" name="observaciones" rows="3" maxlength="1000" class="<?= $claseInput ?>" placeholder="Ej.: Selectividad física antinarcóticos programada para mañana 8:00 a. m."></textarea>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <?php foreach (['Selectividad física antinarcóticos', 'Inspección previa', 'Visto bueno INVIMA', 'Levante autorizado', 'Pendiente documentos del cliente'] as $rapida): ?>
                        <button type="button" data-obs-rapida="<?= h($rapida) ?>" class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700">+ <?= h($rapida) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="flex items-start gap-2 rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-700"><?= icono('info', 'mt-0.5 h-4 w-4 shrink-0') ?> Al guardar se registra en la bitácora y se envía una alerta a n8n.</p>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" data-cerrar-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancelar</button>
                    <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-rubi-emerald px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-rubi-emerald2 disabled:cursor-not-allowed disabled:opacity-60"><?= icono('check') ?> <span data-texto-boton>Guardar y notificar</span></button>
                </div>
            </form>
            <?php endif; ?>

            <div class="border-t border-slate-100 px-6 py-5">
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Bitácora (más reciente primero)</h3>
                <ol id="estado-bitacora" class="max-h-64 space-y-3 overflow-y-auto pr-1"></ol>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL: DOCUMENTOS / LEGAJO ADUANERO ================= -->
<div id="modal-legajo" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modal-legajo-titulo">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6" data-fondo-modal>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
                <div class="min-w-0">
                    <h2 id="modal-legajo-titulo" class="text-lg font-extrabold text-rubi-navy">Legajo aduanero — <span id="legajo-codigo"></span></h2>
                    <p id="legajo-cliente" class="truncate text-xs text-slate-500"></p>
                </div>
                <button type="button" data-cerrar-modal class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Cerrar"><?= icono('x', 'h-5 w-5') ?></button>
            </div>

            <div id="legajo-checklist" class="flex flex-wrap gap-1.5 border-b border-slate-100 bg-slate-50/60 px-6 py-3"></div>

            <?php if ($puedeEditar): ?>
            <form id="form-legajo" class="space-y-3 border-b border-slate-100 px-6 py-5" novalidate>
                <div class="grid gap-3 sm:grid-cols-5">
                    <div class="sm:col-span-2">
                        <label for="l-tipo" class="<?= $claseLabel ?>">Tipo de documento</label>
                        <select id="l-tipo" name="tipo_documento" class="<?= $claseInput ?>" data-autofoco>
                            <?php foreach (TIPOS_DOCUMENTO as $t): ?>
                            <option value="<?= h($t) ?>"><?= h($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-3">
                        <span class="<?= $claseLabel ?>">Archivo</span>
                        <label id="l-zona" for="l-archivo" class="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-dashed border-slate-300 bg-white px-3 py-2 text-sm text-slate-500 transition hover:border-emerald-500 hover:bg-emerald-50/40">
                            <?= icono('upload', 'h-5 w-5 shrink-0 text-slate-400') ?>
                            <span id="l-archivo-nombre" class="min-w-0 truncate">Seleccione o arrastre un archivo</span>
                        </label>
                        <input id="l-archivo" name="documento" type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png,.docx,application/pdf,image/jpeg,image/png,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">PDF, JPG, PNG o DOCX · máximo 10 MB.</p>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-rubi-emerald px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-rubi-emerald2 disabled:cursor-not-allowed disabled:opacity-60"><?= icono('upload') ?> <span data-texto-boton>Subir documento</span></button>
                </div>
                <div id="l-progreso" class="hidden">
                    <div class="h-2 overflow-hidden rounded-full bg-slate-200"><div id="l-progreso-barra" class="h-full w-0 rounded-full bg-rubi-emerald transition-all"></div></div>
                    <p id="l-progreso-texto" class="mt-1 text-right text-[11px] text-slate-500">0 %</p>
                </div>
            </form>
            <?php endif; ?>

            <div class="px-6 py-5">
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Documentos adjuntos</h3>
                <ul id="legajo-lista" class="max-h-72 divide-y divide-slate-100 overflow-y-auto"></ul>
            </div>

            <div class="flex justify-end border-t border-slate-100 bg-slate-50/60 px-6 py-3">
                <button type="button" data-cerrar-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Notificaciones -->
<div id="toasts" class="pointer-events-none fixed right-4 top-4 z-[60] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2" aria-live="polite"></div>

<script type="application/json" id="app-config"><?= json_encode($configJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script>
(() => {
    'use strict';

    // ------------------------------------------------------------------
    // Configuración y rutas (relativas a /panel/)
    // ------------------------------------------------------------------
    const APP = JSON.parse(document.getElementById('app-config').textContent);
    const URL_PANEL   = 'dashboard.php';
    const URL_WEBHOOK = '../handlers/webhook_trigger.php';
    const URL_UPLOAD  = '../handlers/upload_documento.php';
    const EXT_PERMITIDAS = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];

    const estado = {
        dos: Array.isArray(APP.dos) ? APP.dos : [],
        filtros: { texto: '', sucursal: '', regimen: '', nivel: '' },
        doActual: null,
        resaltar: null,
    };

    const NIVEL_UI = {
        al_dia:      { badge: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', punto: 'bg-emerald-500', barra: 'bg-emerald-500' },
        seguimiento: { badge: 'bg-orange-50 text-orange-700 ring-orange-600/20',   punto: 'bg-orange-500',  barra: 'bg-orange-500' },
        critico:     { badge: 'bg-red-50 text-red-700 ring-red-600/20',             punto: 'bg-red-500',     barra: 'bg-red-500' },
        cerrado:     { badge: 'bg-slate-100 text-slate-600 ring-slate-500/20',      punto: 'bg-slate-400',   barra: 'bg-slate-300' },
    };
    const ESTADO_UI = {
        'En trámite':           'bg-sky-50 text-sky-700 ring-sky-600/20',
        'Presentada DIAN':      'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        'Aceptada':             'bg-teal-50 text-teal-700 ring-teal-600/20',
        'Nacionalizado':        'bg-slate-100 text-slate-700 ring-slate-500/20',
        'Finalizada':           'bg-slate-100 text-slate-700 ring-slate-500/20',
        'Cerrado sin facturar': 'bg-stone-100 text-stone-700 ring-stone-500/20',
    };
    const ORDEN_NIVEL = { critico: 0, seguimiento: 1, al_dia: 2, cerrado: 3 };
    const CLASES_OK  = ['border-slate-300', 'bg-white'];
    const CLASES_ERR = ['border-red-400', 'bg-red-50'];

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------
    const $  = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const normalizar = (v) => String(v ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    const plural = (n, uno, varios) => `${n} ${Math.abs(n) === 1 ? uno : varios}`;

    function icono(nombre, clases = 'h-4 w-4') {
        return `<svg class="${clases}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${APP.iconos[nombre] || ''}</svg>`;
    }
    function fechaCO(iso) {
        if (!iso) return '—';
        const [y, m, d] = String(iso).slice(0, 10).split('-');
        return (y && m && d) ? esc(`${d}/${m}/${y}`) : esc(iso);
    }
    function fechaHoraCO(valor) {
        if (!valor) return '—';
        const [fecha, hora = ''] = String(valor).split(' ');
        return `${fechaCO(fecha)} ${esc(hora.slice(0, 5))}`.trim();
    }
    function sumarDias(iso, dias) {
        const [y, m, d] = String(iso).split('-').map(Number);
        if (!y || !m || !d) return '';
        return new Date(Date.UTC(y, m - 1, d + dias)).toISOString().slice(0, 10);
    }
    function tamanoLegible(bytes) {
        if (bytes == null) return '—';
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1048576) return `${Math.round(bytes / 1024)} KB`;
        return `${(bytes / 1048576).toFixed(1)} MB`;
    }
    function dvNit(base) {
        const pesos = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
        const digitos = String(base).split('').reverse();
        const suma = digitos.reduce((acc, dig, i) => acc + Number(dig) * (pesos[i] || 0), 0);
        const r = suma % 11;
        return r > 1 ? 11 - r : r;
    }
    function botonOcupado(boton, ocupado, texto) {
        if (!boton) return;
        const etiqueta = boton.querySelector('[data-texto-boton]');
        if (ocupado) {
            boton.disabled = true;
            if (etiqueta) { boton.dataset.textoOriginal = etiqueta.textContent; etiqueta.textContent = texto || 'Procesando…'; }
        } else {
            boton.disabled = false;
            if (etiqueta && boton.dataset.textoOriginal) etiqueta.textContent = boton.dataset.textoOriginal;
        }
    }

    // ------------------------------------------------------------------
    // Notificaciones
    // ------------------------------------------------------------------
    function toast(mensaje, tipo = 'info', duracion = 5000) {
        const estilos = {
            success: { caja: 'border-emerald-200', color: 'text-emerald-600', ic: 'check' },
            error:   { caja: 'border-red-200',     color: 'text-red-600',     ic: 'alert' },
            warning: { caja: 'border-orange-200',  color: 'text-orange-500',  ic: 'alert' },
            info:    { caja: 'border-sky-200',     color: 'text-sky-600',     ic: 'info' },
        };
        const e = estilos[tipo] || estilos.info;
        const el = document.createElement('div');
        el.className = `pointer-events-auto flex items-start gap-3 rounded-xl border ${e.caja} bg-white p-4 shadow-lg transition duration-300 opacity-0 translate-y-2`;
        el.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
        el.innerHTML = `<span class="${e.color} mt-0.5">${icono(e.ic, 'h-5 w-5')}</span>
            <p class="flex-1 text-sm leading-snug text-slate-700"></p>
            <button type="button" class="text-slate-400 hover:text-slate-600" aria-label="Cerrar notificación">${icono('x')}</button>`;
        el.querySelector('p').textContent = mensaje;
        const contenedor = $('#toasts');
        while (contenedor.children.length >= 4) contenedor.firstElementChild.remove();
        contenedor.appendChild(el);
        requestAnimationFrame(() => el.classList.remove('opacity-0', 'translate-y-2'));
        const cerrar = () => { el.classList.add('opacity-0'); setTimeout(() => el.remove(), 300); };
        el.querySelector('button').addEventListener('click', cerrar);
        setTimeout(cerrar, duracion);
    }

    // ------------------------------------------------------------------
    // Peticiones: siempre esperan JSON { status, message, ... }
    // ------------------------------------------------------------------
    function sesionExpirada() {
        toast('Su sesión expiró. Redirigiendo al inicio de sesión…', 'warning');
        setTimeout(() => { window.location.href = 'index.php'; }, 1500);
    }

    function procesarRespuesta(httpStatus, texto) {
        let data = null;
        try { data = texto ? JSON.parse(texto) : null; } catch (_) { data = null; }

        if (!data || typeof data !== 'object') {
            console.error('Respuesta no JSON del servidor', httpStatus, String(texto).slice(0, 800));
            throw new Error(`El servidor devolvió una respuesta inesperada (HTTP ${httpStatus}). Revise logs/php-error.log.`);
        }
        if (httpStatus === 401) sesionExpirada();
        if (data.status !== 'success') {
            const error = new Error(data.message || `Error del servidor (HTTP ${httpStatus}).`);
            error.data = data;
            error.httpStatus = httpStatus;
            throw error;
        }
        return data;
    }

    async function fetchJSON(url, opciones = {}) {
        const config = {
            credentials: 'same-origin',
            ...opciones,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': APP.csrf,
                ...(opciones.headers || {}),
            },
        };
        let respuesta;
        try {
            respuesta = await fetch(url, config);
        } catch (_) {
            throw new Error('No hay conexión con el servidor. Verifique su red e intente de nuevo.');
        }
        return procesarRespuesta(respuesta.status, await respuesta.text());
    }

    function subirConProgreso(url, formData, alProgresar) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-Token', APP.csrf);
            xhr.timeout = 180000;
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) alProgresar(Math.round((e.loaded / e.total) * 100));
            });
            xhr.onload = () => {
                try { resolve(procesarRespuesta(xhr.status, xhr.responseText)); } catch (err) { reject(err); }
            };
            xhr.onerror = () => reject(new Error('No hay conexión con el servidor. Verifique su red e intente de nuevo.'));
            xhr.ontimeout = () => reject(new Error('La subida tardó demasiado. Intente de nuevo.'));
            xhr.send(formData);
        });
    }

    // ------------------------------------------------------------------
    // Tabla, filtros y KPIs
    // ------------------------------------------------------------------
    function filtrar(lista) {
        const f = estado.filtros;
        const texto = normalizar(f.texto);
        return lista.filter((d) => {
            const nivel = d.semaforo.nivel;
            if (texto && !normalizar(`${d.do_codigo} ${d.cliente} ${d.nit}`).includes(texto)) return false;
            if (f.sucursal && d.sucursal !== f.sucursal) return false;
            if (f.regimen && d.regimen !== f.regimen) return false;
            if (f.nivel === 'activos' && nivel === 'cerrado') return false;
            if (f.nivel && f.nivel !== 'activos' && nivel !== f.nivel) return false;
            return true;
        });
    }

    function ordenar(lista) {
        return [...lista].sort((a, b) => {
            const na = ORDEN_NIVEL[a.semaforo.nivel] ?? 9;
            const nb = ORDEN_NIVEL[b.semaforo.nivel] ?? 9;
            if (na !== nb) return na - nb;
            if (a.semaforo.nivel === 'cerrado') return String(b.fecha_apertura).localeCompare(String(a.fecha_apertura));
            const ra = a.semaforo.dias_restantes ?? Infinity;
            const rb = b.semaforo.dias_restantes ?? Infinity;
            if (ra !== rb) return ra - rb;
            return b.semaforo.dias_transcurridos - a.semaforo.dias_transcurridos;
        });
    }

    function textoRestante(s) {
        if (s.nivel === 'cerrado') return { texto: 'Sin alerta DIAN', clase: 'text-slate-400' };
        if (s.dias_restantes === null || s.dias_restantes === undefined) return { texto: 'Sin fecha de vencimiento', clase: 'text-slate-400' };
        if (s.dias_restantes < 0) return { texto: `Vencido hace ${plural(-s.dias_restantes, 'día', 'días')}`, clase: 'font-semibold text-red-600' };
        if (s.dias_restantes === 0) return { texto: 'Vence hoy', clase: 'font-semibold text-red-600' };
        const clase = s.dias_restantes <= 15 ? 'font-semibold text-red-600' : 'text-slate-500';
        return { texto: `Vence en ${plural(s.dias_restantes, 'día', 'días')}`, clase };
    }

    function badgeEstado(valor) {
        const clases = ESTADO_UI[valor] || 'bg-slate-100 text-slate-700 ring-slate-500/20';
        return `<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${clases}">${esc(valor)}</span>`;
    }

    function badgeSemaforo(s) {
        const ui = NIVEL_UI[s.nivel] || NIVEL_UI.al_dia;
        return `<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${ui.badge}" title="${esc(s.descripcion)}">
                    <span class="h-2 w-2 rounded-full ${ui.punto}"></span>${esc(s.etiqueta)}</span>`;
    }

    function filaHTML(d) {
        const s = d.semaforo;
        const ui = NIVEL_UI[s.nivel] || NIVEL_UI.al_dia;
        const transcurridos = s.dias_transcurridos < 0
            ? `Inicia en ${plural(-s.dias_transcurridos, 'día', 'días')}`
            : plural(s.dias_transcurridos, 'día', 'días');
        const restante = textoRestante(s);
        const progreso = Math.max(0, Math.min(100, Number(s.progreso) || 0));
        const resaltado = d.id === estado.resaltar;
        const fondoFila = resaltado ? 'bg-amber-50' : '';
        const fondoFijo = resaltado ? 'bg-amber-50' : 'bg-white group-hover:bg-slate-50';
        const obs = d.ultima_observacion
            ? `<div class="mt-1 max-w-[210px] truncate text-xs text-slate-500" title="${esc(d.ultima_observacion)}">${esc(d.ultima_observacion)}</div>`
            : '';

        return `<tr data-id="${d.id}" class="group align-top transition-colors hover:bg-slate-50 ${fondoFila}">
            <td class="whitespace-nowrap py-3 pl-5 pr-3">
                <div class="font-bold tracking-wide text-rubi-navy">${esc(d.do_codigo)}</div>
                <div class="mt-0.5 text-xs text-slate-500">${esc(d.regimen)} · ${esc(d.sucursal)}</div>
            </td>
            <td class="px-3 py-3">
                <div class="max-w-[200px] truncate font-medium text-slate-800" title="${esc(d.cliente)}">${esc(d.cliente)}</div>
                <div class="mt-0.5 text-xs text-slate-500">NIT ${esc(d.nit)}</div>
            </td>
            <td class="px-3 py-3">${badgeEstado(d.estado)}${obs}</td>
            <td class="whitespace-nowrap px-3 py-3 text-xs">
                <div><span class="inline-block w-14 text-slate-400">Apertura</span><span class="font-medium text-slate-700">${fechaCO(d.fecha_apertura)}</span></div>
                <div class="mt-1"><span class="inline-block w-14 text-slate-400">Vence</span><span class="font-medium text-slate-700">${fechaCO(d.fecha_vencimiento)}</span></div>
            </td>
            <td class="whitespace-nowrap px-3 py-3">
                <div class="flex items-center gap-2">${badgeSemaforo(s)}<span class="text-sm font-semibold text-slate-800">${transcurridos}</span></div>
                <div class="mt-2 h-1.5 w-36 overflow-hidden rounded-full bg-slate-200" title="${progreso}% del plazo consumido"><div class="h-full rounded-full ${ui.barra}" style="width:${progreso}%"></div></div>
                <div class="mt-1 text-xs ${restante.clase}">${restante.texto}</div>
            </td>
            <td class="sticky right-0 whitespace-nowrap border-l border-slate-100 px-3 py-3 ${fondoFijo}">
                <div class="flex w-28 flex-col gap-1.5">
                    <button type="button" data-accion="estado" data-id="${d.id}" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rubi-navy transition hover:border-rubi-navy/40 hover:bg-slate-50">
                        ${icono('refresh', 'h-3.5 w-3.5')} ${APP.puedeEditar ? 'Estado' : 'Bitácora'}
                    </button>
                    <button type="button" data-accion="legajo" data-id="${d.id}" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                        ${icono('paperclip', 'h-3.5 w-3.5')} Legajo
                        <span class="rounded-full bg-white px-1.5 text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-200">${Number(d.total_documentos) || 0}</span>
                    </button>
                </div>
            </td>
        </tr>`;
    }

    function renderKpis() {
        const conteo = { activos: 0, al_dia: 0, seguimiento: 0, critico: 0, cerrado: 0 };
        estado.dos.forEach((d) => {
            const n = d.semaforo.nivel;
            conteo[n] = (conteo[n] || 0) + 1;
            if (n !== 'cerrado') conteo.activos += 1;
        });
        $$('[data-kpi]').forEach((tarjeta) => {
            const clave = tarjeta.dataset.kpi;
            tarjeta.querySelector('[data-kpi-num]').textContent = conteo[clave] ?? 0;
            const activo = estado.filtros.nivel === clave;
            tarjeta.classList.toggle('ring-2', activo);
            tarjeta.classList.toggle('ring-rubi-emerald', activo);
            tarjeta.setAttribute('aria-pressed', activo ? 'true' : 'false');
        });
    }

    function renderNav() {
        $$('[data-nav-regimen]').forEach((enlace) => {
            const regimen = enlace.dataset.navRegimen;
            const activo = regimen === ''
                ? !['Exportación', 'Importación'].includes(estado.filtros.regimen)
                : estado.filtros.regimen === regimen;
            enlace.classList.toggle('bg-rubi-emerald', activo);
            enlace.classList.toggle('text-white', activo);
            enlace.classList.toggle('text-slate-300', !activo);
            enlace.classList.toggle('hover:bg-white/5', !activo);
            enlace.classList.toggle('hover:text-white', !activo);
            if (activo) enlace.setAttribute('aria-current', 'page'); else enlace.removeAttribute('aria-current');
        });
    }

    function renderTabla() {
        const visibles = ordenar(filtrar(estado.dos));
        $('#tbl-body').innerHTML = visibles.map(filaHTML).join('');
        $('#txt-conteo').textContent = `Mostrando ${visibles.length} de ${plural(estado.dos.length, 'operación', 'operaciones')}`;

        const vacio = $('#estado-vacio');
        const hayFilas = visibles.length > 0;
        vacio.classList.toggle('hidden', hayFilas);
        vacio.classList.toggle('flex', !hayFilas);
        $('#estado-vacio-texto').textContent = estado.dos.length === 0
            ? 'Aún no hay órdenes de operación registradas.'
            : 'Ninguna operación coincide con los filtros aplicados.';
    }

    function render() {
        renderKpis();
        renderNav();
        renderTabla();
    }

    function sincronizarControles() {
        $('#f-texto').value = estado.filtros.texto;
        $('#f-sucursal').value = estado.filtros.sucursal;
        $('#f-regimen').value = estado.filtros.regimen;
        $('#f-nivel').value = estado.filtros.nivel;
    }

    function limpiarFiltros() {
        estado.filtros = { texto: '', sucursal: '', regimen: '', nivel: '' };
        sincronizarControles();
        render();
    }

    function mostrarAlertaCarga(mensaje) {
        const caja = $('#alerta-carga');
        $('#alerta-carga-texto').textContent = mensaje || '';
        caja.classList.toggle('hidden', !mensaje);
        caja.classList.toggle('flex', Boolean(mensaje));
    }

    async function cargarDatos({ silencioso = false } = {}) {
        const boton = $('#btn-recargar');
        boton.classList.add('animate-spin');
        try {
            const r = await fetchJSON(`${URL_PANEL}?action=listar`);
            estado.dos = Array.isArray(r.data?.dos) ? r.data.dos : [];
            APP.hoy = r.data?.hoy || APP.hoy;
            $('#txt-actualizado').textContent = r.data?.generado || '';
            mostrarAlertaCarga('');
            render();
        } catch (err) {
            if (!silencioso) toast(err.message, 'error');
        } finally {
            boton.classList.remove('animate-spin');
        }
    }

    function resaltarFila(id) {
        estado.resaltar = id;
        render();
        const fila = $(`#tbl-body tr[data-id="${id}"]`);
        if (fila) fila.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => { if (estado.resaltar === id) { estado.resaltar = null; render(); } }, 4000);
    }

    // ------------------------------------------------------------------
    // Modales (accesibles: Esc, clic fuera, foco atrapado)
    // ------------------------------------------------------------------
    let modalAbierto = null;
    let focoPrevio = null;

    function abrirModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        if (modalAbierto && modalAbierto !== modal) cerrarModal(true);
        focoPrevio = document.activeElement;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        modalAbierto = modal;
        setTimeout(() => {
            const foco = modal.querySelector('[data-autofoco]') || modal.querySelector('button, input, select, textarea');
            if (foco) foco.focus();
        }, 40);
    }

    function cerrarModal(forzar = false) {
        if (!modalAbierto) return;
        if (!forzar && modalAbierto.dataset.ocupado === '1') {
            toast('Espere a que termine la operación en curso.', 'info', 2500);
            return;
        }
        modalAbierto.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        modalAbierto = null;
        estado.doActual = null;
        if (focoPrevio && typeof focoPrevio.focus === 'function') focoPrevio.focus();
    }

    function atraparFoco(e) {
        const enfocables = $$('a[href], button:not([disabled]), input:not([disabled]):not(.sr-only), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])', modalAbierto)
            .filter((el) => el.offsetParent !== null);
        if (enfocables.length === 0) return;
        const primero = enfocables[0];
        const ultimo = enfocables[enfocables.length - 1];
        if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
        else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
    }

    document.addEventListener('keydown', (e) => {
        if (!modalAbierto) return;
        if (e.key === 'Escape') cerrarModal();
        if (e.key === 'Tab') atraparFoco(e);
    });
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-cerrar-modal]')) cerrarModal();
        else if (e.target.hasAttribute && e.target.hasAttribute('data-fondo-modal')) cerrarModal();
    });

    function limpiarErrorCampo(form, nombre) {
        const campo = form.elements[nombre];
        const p = $(`[data-error-para="${nombre}"]`, form);
        const pista = $(`[data-pista-para="${nombre}"]`, form);
        if (campo && campo.classList) {
            campo.classList.remove(...CLASES_ERR);
            campo.classList.add(...CLASES_OK);
            campo.removeAttribute('aria-invalid');
        }
        if (p) { p.textContent = ''; p.classList.add('hidden'); }
        if (pista) pista.classList.remove('hidden');
    }

    function limpiarErrores(form) {
        $$('[data-error-para]', form).forEach((p) => limpiarErrorCampo(form, p.dataset.errorPara));
    }

    function mostrarErrores(form, errores) {
        let primero = null;
        Object.entries(errores || {}).forEach(([campo, mensaje]) => {
            const input = form.elements[campo];
            const p = $(`[data-error-para="${campo}"]`, form);
            if (input) {
                input.classList.remove(...CLASES_OK);
                input.classList.add(...CLASES_ERR);
                input.setAttribute('aria-invalid', 'true');
                primero = primero || input;
            }
            if (p) { p.textContent = mensaje; p.classList.remove('hidden'); }
            const pista = $(`[data-pista-para="${campo}"]`, form);
            if (pista) pista.classList.add('hidden');
        });
        if (primero) primero.focus();
    }

    // ------------------------------------------------------------------
    // Modal: Nueva operación (DO)
    // ------------------------------------------------------------------
    const formNuevo = $('#form-nuevo');
    let prefijoSugerido = '';
    let vencimientoManual = false;

    function calcularPrefijo() {
        const suc = APP.prefijosSucursal[formNuevo.sucursal.value] || '';
        const reg = APP.prefijosRegimen[formNuevo.regimen.value] || '';
        const anio = (formNuevo.fecha_apertura.value || APP.hoy).slice(2, 4);
        return suc && reg ? `${suc}${anio}${reg}` : '';
    }

    function actualizarPrefijo() {
        const nuevo = calcularPrefijo();
        const campo = formNuevo.do_codigo;
        const actual = campo.value.trim().toUpperCase();
        if (actual === '' || (prefijoSugerido && actual.startsWith(prefijoSugerido))) {
            const consecutivo = prefijoSugerido && actual.startsWith(prefijoSugerido) ? actual.slice(prefijoSugerido.length) : '';
            campo.value = nuevo + consecutivo;
        }
        prefijoSugerido = nuevo;
        $('#hint-codigo').textContent = `Formato: ${nuevo || 'BOG26IMP'} + consecutivo, ej. ${nuevo || 'BOG26IMP'}0001`;
    }

    function actualizarPistaNit() {
        const pista = $('#hint-nit');
        const valor = formNuevo.nit.value.replace(/[\s.]/g, '');
        const m = valor.match(/^(\d{5,15})(?:-(\d)?)?$/);
        pista.className = 'mt-1 text-xs text-slate-500';
        if (!valor) { pista.textContent = 'El dígito de verificación se calcula automáticamente.'; return; }
        if (!m) { pista.textContent = 'Use solo números y, si lo tiene, el guion del DV (ej. 900123456-8).'; return; }
        const dv = dvNit(m[1]);
        if (m[2] !== undefined && m[2] !== '' && Number(m[2]) !== dv) {
            pista.className = 'mt-1 text-xs font-semibold text-red-600';
            pista.textContent = `El DV no corresponde: para ${m[1]} es ${dv}.`;
        } else {
            pista.className = 'mt-1 text-xs text-emerald-700';
            pista.textContent = `Se guardará como ${m[1]}-${dv}.`;
        }
    }

    function abrirNuevo() {
        formNuevo.reset();
        limpiarErrores(formNuevo);
        formNuevo.sucursal.value = APP.sucursales.includes(APP.sucursalUsuario) ? APP.sucursalUsuario : APP.sucursales[0];
        formNuevo.regimen.value = APP.regimenes[0];
        formNuevo.fecha_apertura.value = APP.hoy;
        formNuevo.fecha_apertura.max = APP.hoy;
        formNuevo.fecha_vencimiento.value = sumarDias(APP.hoy, APP.plazoDias);
        formNuevo.fecha_vencimiento.min = APP.hoy;
        vencimientoManual = false;
        prefijoSugerido = '';
        actualizarPrefijo();
        actualizarPistaNit();
        abrirModal('modal-nuevo');
    }

    if (formNuevo) {
        $('#btn-nuevo-do').addEventListener('click', abrirNuevo);
        formNuevo.sucursal.addEventListener('change', actualizarPrefijo);
        formNuevo.regimen.addEventListener('change', actualizarPrefijo);
        formNuevo.do_codigo.addEventListener('input', () => {
            const pos = formNuevo.do_codigo.selectionStart;
            formNuevo.do_codigo.value = formNuevo.do_codigo.value.toUpperCase().replace(/\s+/g, '');
            formNuevo.do_codigo.setSelectionRange(pos, pos);
        });
        formNuevo.nit.addEventListener('input', actualizarPistaNit);
        formNuevo.fecha_apertura.addEventListener('change', () => {
            const apertura = formNuevo.fecha_apertura.value;
            if (apertura) {
                formNuevo.fecha_vencimiento.min = apertura;
                if (!vencimientoManual) formNuevo.fecha_vencimiento.value = sumarDias(apertura, APP.plazoDias);
            }
            actualizarPrefijo();
        });
        formNuevo.fecha_vencimiento.addEventListener('change', () => { vencimientoManual = true; });
        // Al corregir un campo, su mensaje de error desaparece.
        ['input', 'change'].forEach((ev) => formNuevo.addEventListener(ev, (e) => {
            if (e.target.name && e.target.getAttribute('aria-invalid') === 'true') limpiarErrorCampo(formNuevo, e.target.name);
        }));

        formNuevo.addEventListener('submit', async (e) => {
            e.preventDefault();
            limpiarErrores(formNuevo);
            const datos = Object.fromEntries(new FormData(formNuevo).entries());
            datos.do_codigo = String(datos.do_codigo || '').trim().toUpperCase();

            const faltantes = {};
            if (!datos.do_codigo) faltantes.do_codigo = 'El código del DO es obligatorio.';
            if (!String(datos.cliente || '').trim()) faltantes.cliente = 'El cliente es obligatorio.';
            if (!String(datos.nit || '').trim()) faltantes.nit = 'El NIT es obligatorio.';
            if (!datos.fecha_apertura) faltantes.fecha_apertura = 'Indique la fecha de apertura.';
            if (!datos.fecha_vencimiento) faltantes.fecha_vencimiento = 'Indique la fecha de vencimiento.';
            if (Object.keys(faltantes).length) { mostrarErrores(formNuevo, faltantes); return; }

            const boton = formNuevo.querySelector('button[type="submit"]');
            const modal = $('#modal-nuevo');
            botonOcupado(boton, true, 'Guardando…');
            modal.dataset.ocupado = '1';
            try {
                const r = await fetchJSON(`${URL_PANEL}?action=crear`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(datos),
                });
                modal.dataset.ocupado = '0';
                cerrarModal();
                toast(r.message, 'success');
                if (r.data?.do) {
                    estado.dos = [r.data.do, ...estado.dos.filter((d) => d.id !== r.data.do.id)];
                    limpiarFiltros();
                    resaltarFila(r.data.do.id);
                }
                cargarDatos({ silencioso: true });
            } catch (err) {
                if (err.data?.errors) mostrarErrores(formNuevo, err.data.errors);
                toast(err.message, 'error', 7000);
            } finally {
                modal.dataset.ocupado = '0';
                botonOcupado(boton, false);
            }
        });
    }

    // ------------------------------------------------------------------
    // Modal: Actualizar estado / Observar
    // ------------------------------------------------------------------
    const formEstado = $('#form-estado');

    function renderBitacora(entradas) {
        const lista = $('#estado-bitacora');
        if (!entradas || entradas.length === 0) {
            lista.innerHTML = '<li class="text-sm text-slate-400">Sin observaciones registradas.</li>';
            return;
        }
        lista.innerHTML = entradas.map((en) => `
            <li class="relative border-l-2 border-slate-200 pl-4">
                <span class="absolute -left-[5px] top-1.5 h-2 w-2 rounded-full bg-rubi-emerald"></span>
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-500">
                    ${en.fecha ? `<span class="font-semibold text-slate-600">${esc(en.fecha)}</span>` : '<span class="italic">Registro anterior</span>'}
                    ${en.usuario ? `<span>· ${esc(en.usuario)}</span>` : ''}
                    ${en.estado ? `<span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-600">${esc(en.estado)}</span>` : ''}
                </div>
                <p class="mt-0.5 text-sm text-slate-700">${esc(en.texto)}</p>
            </li>`).join('');
    }

    function actualizarContador() {
        if (!formEstado) return;
        $('#e-contador').textContent = `${formEstado.observaciones.value.length} / 1000`;
    }

    function abrirEstado(id) {
        const d = estado.dos.find((x) => x.id === id);
        if (!d) return;
        abrirModal('modal-estado');
        estado.doActual = d;
        $('#estado-codigo').textContent = d.do_codigo;
        $('#estado-cliente').textContent = `${d.cliente} · NIT ${d.nit} · ${d.regimen} · ${d.sucursal}`;
        const restante = textoRestante(d.semaforo);
        $('#estado-resumen').innerHTML = `
            <span class="text-slate-500">Estado actual:</span> ${badgeEstado(d.estado)}
            <span class="ml-2 text-slate-500">Alerta:</span> ${badgeSemaforo(d.semaforo)}
            <span class="ml-2 text-slate-500">${plural(d.semaforo.dias_transcurridos, 'día', 'días')} desde la apertura · <span class="${restante.clase}">${restante.texto}</span></span>`;
        if (formEstado) {
            formEstado.reset();
            formEstado.nuevo_estado.value = APP.estados.includes(d.estado) ? d.estado : APP.estados[0];
            actualizarContador();
        }
        renderBitacora(d.bitacora);
    }

    if (formEstado) {
        formEstado.observaciones.addEventListener('input', actualizarContador);
        $$('[data-obs-rapida]', formEstado).forEach((chip) => {
            chip.addEventListener('click', () => {
                const area = formEstado.observaciones;
                const actual = area.value.trim();
                area.value = actual ? `${actual.replace(/[.\s]+$/, '')}. ${chip.dataset.obsRapida}` : chip.dataset.obsRapida;
                area.focus();
                actualizarContador();
            });
        });

        formEstado.addEventListener('submit', async (e) => {
            e.preventDefault();
            const d = estado.doActual;
            if (!d) return;

            const payload = {
                do_id: Number(d.id),
                do_codigo: d.do_codigo,
                nuevo_estado: formEstado.nuevo_estado.value,
                observaciones: formEstado.observaciones.value.trim(),
                usuario: APP.usuario,
            };
            if (payload.nuevo_estado === d.estado && payload.observaciones === '') {
                toast('No hay cambios: elija otro estado o escriba una observación.', 'warning');
                formEstado.observaciones.focus();
                return;
            }

            const boton = formEstado.querySelector('button[type="submit"]');
            const modal = $('#modal-estado');
            botonOcupado(boton, true, 'Guardando y notificando…');
            modal.dataset.ocupado = '1';
            try {
                const r = await fetchJSON(URL_WEBHOOK, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                modal.dataset.ocupado = '0';
                cerrarModal();
                const fallaN8n = r.webhook && r.webhook.enviado === false;
                toast(r.message, fallaN8n ? 'warning' : 'success', fallaN8n ? 10000 : 5000);
                await cargarDatos({ silencioso: true });
                resaltarFila(d.id);
            } catch (err) {
                toast(err.message, 'error', 7000);
            } finally {
                modal.dataset.ocupado = '0';
                botonOcupado(boton, false);
            }
        });
    }

    // ------------------------------------------------------------------
    // Modal: Documentos / Legajo aduanero
    // ------------------------------------------------------------------
    const formLegajo = $('#form-legajo');
    const inputArchivo = $('#l-archivo');

    function claseExtension(ext) {
        if (ext === 'pdf') return 'bg-red-50 text-red-600';
        if (ext === 'docx') return 'bg-sky-50 text-sky-600';
        return 'bg-emerald-50 text-emerald-600';
    }

    function renderChecklist(documentos) {
        const presentes = new Set((documentos || []).map((doc) => doc.tipo_documento));
        $('#legajo-checklist').innerHTML = APP.tiposDocumento
            .filter((t) => t !== 'Otro')
            .map((t) => presentes.has(t)
                ? `<span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">${icono('check', 'h-3.5 w-3.5')} ${esc(t)}</span>`
                : `<span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-500 ring-1 ring-inset ring-slate-200">${esc(t)}</span>`)
            .join('');
    }

    function renderDocumentos(documentos) {
        renderChecklist(documentos);
        const lista = $('#legajo-lista');
        if (!documentos || documentos.length === 0) {
            lista.innerHTML = '<li class="py-6 text-center text-sm text-slate-400">Este DO aún no tiene documentos adjuntos.</li>';
            return;
        }
        lista.innerHTML = documentos.map((doc) => {
            const acciones = doc.existe
                ? `${doc.visualizable ? `<a href="${esc(doc.url_ver)}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">${icono('eye', 'h-3.5 w-3.5')} Ver</a>` : ''}
                   <a href="${esc(doc.url_descarga)}" class="inline-flex items-center gap-1 rounded-lg bg-rubi-navy px-2 py-1 text-xs font-semibold text-white hover:bg-rubi-navy2">${icono('download', 'h-3.5 w-3.5')} Descargar</a>`
                : '<span class="text-xs font-semibold text-red-600">Archivo no disponible</span>';
            return `<li class="flex items-center gap-3 py-3">
                <span class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg ${claseExtension(doc.extension)}">
                    ${icono('file', 'h-4 w-4')}<span class="text-[9px] font-bold uppercase">${esc(doc.extension)}</span>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-800" title="${esc(doc.nombre_archivo)}">${esc(doc.nombre_archivo)}</p>
                    <p class="text-xs text-slate-500"><span class="font-semibold text-slate-600">${esc(doc.tipo_documento)}</span> · ${tamanoLegible(doc.tamano)} · ${fechaHoraCO(doc.fecha_subida)}</p>
                </div>
                <div class="flex shrink-0 items-center gap-1.5">${acciones}</div>
            </li>`;
        }).join('');
    }

    async function cargarDocumentos(doId) {
        const lista = $('#legajo-lista');
        lista.innerHTML = '<li class="py-6 text-center text-sm text-slate-400">Cargando documentos…</li>';
        try {
            const r = await fetchJSON(`${URL_PANEL}?action=documentos&do_id=${encodeURIComponent(doId)}`);
            if (estado.doActual && estado.doActual.id === doId) renderDocumentos(r.data?.documentos || []);
        } catch (err) {
            lista.innerHTML = `<li class="py-6 text-center text-sm text-red-600">${esc(err.message)}</li>`;
        }
    }

    function reiniciarSubida() {
        if (!formLegajo) return;
        formLegajo.reset();
        $('#l-archivo-nombre').textContent = 'Seleccione o arrastre un archivo';
        $('#l-progreso').classList.add('hidden');
        $('#l-progreso-barra').style.width = '0%';
    }

    function abrirLegajo(id) {
        const d = estado.dos.find((x) => x.id === id);
        if (!d) return;
        abrirModal('modal-legajo');
        estado.doActual = d;
        $('#legajo-codigo').textContent = d.do_codigo;
        $('#legajo-cliente').textContent = `${d.cliente} · ${d.regimen} · ${d.sucursal}`;
        $('#legajo-checklist').innerHTML = '';
        reiniciarSubida();
        cargarDocumentos(d.id);
    }

    function validarArchivo(archivo) {
        if (!archivo) return 'Seleccione un archivo.';
        const ext = archivo.name.split('.').pop().toLowerCase();
        if (!EXT_PERMITIDAS.includes(ext)) return 'Formato no permitido. Solo PDF, JPG, PNG o DOCX.';
        if (archivo.size === 0) return 'El archivo está vacío.';
        if (archivo.size > APP.maxBytes) return `El archivo pesa ${tamanoLegible(archivo.size)}; el máximo es 10 MB.`;
        return null;
    }

    if (formLegajo) {
        const zona = $('#l-zona');
        const mostrarNombre = () => {
            const archivo = inputArchivo.files[0];
            $('#l-archivo-nombre').textContent = archivo ? `${archivo.name} (${tamanoLegible(archivo.size)})` : 'Seleccione o arrastre un archivo';
        };
        inputArchivo.addEventListener('change', mostrarNombre);
        ['dragenter', 'dragover'].forEach((ev) => zona.addEventListener(ev, (e) => {
            e.preventDefault();
            zona.classList.add('border-emerald-500', 'bg-emerald-50');
        }));
        ['dragleave', 'drop'].forEach((ev) => zona.addEventListener(ev, (e) => {
            e.preventDefault();
            zona.classList.remove('border-emerald-500', 'bg-emerald-50');
        }));
        zona.addEventListener('drop', (e) => {
            if (e.dataTransfer && e.dataTransfer.files.length) {
                inputArchivo.files = e.dataTransfer.files;
                mostrarNombre();
            }
        });

        formLegajo.addEventListener('submit', async (e) => {
            e.preventDefault();
            const d = estado.doActual;
            if (!d) return;
            const archivo = inputArchivo.files[0];
            const error = validarArchivo(archivo);
            if (error) { toast(error, 'error'); return; }

            const datos = new FormData();
            datos.append('do_id', String(d.id));
            datos.append('tipo_documento', formLegajo.tipo_documento.value);
            datos.append('documento', archivo);

            const boton = formLegajo.querySelector('button[type="submit"]');
            const modal = $('#modal-legajo');
            const barra = $('#l-progreso-barra');
            const textoProgreso = $('#l-progreso-texto');
            $('#l-progreso').classList.remove('hidden');
            botonOcupado(boton, true, 'Subiendo…');
            modal.dataset.ocupado = '1';
            try {
                const r = await subirConProgreso(URL_UPLOAD, datos, (p) => {
                    barra.style.width = `${p}%`;
                    textoProgreso.textContent = p < 100 ? `${p} %` : 'Validando archivo…';
                });
                toast(r.message, 'success');
                reiniciarSubida();
                await cargarDocumentos(d.id);
                cargarDatos({ silencioso: true });
            } catch (err) {
                toast(err.message, 'error', 7000);
                $('#l-progreso').classList.add('hidden');
            } finally {
                modal.dataset.ocupado = '0';
                botonOcupado(boton, false);
            }
        });
    }

    // ------------------------------------------------------------------
    // Eventos generales
    // ------------------------------------------------------------------
    $('#tbl-body').addEventListener('click', (e) => {
        const boton = e.target.closest('button[data-accion]');
        if (!boton) return;
        const id = Number(boton.dataset.id);
        if (boton.dataset.accion === 'estado') abrirEstado(id);
        if (boton.dataset.accion === 'legajo') abrirLegajo(id);
    });

    let temporizadorBusqueda;
    $('#f-texto').addEventListener('input', (e) => {
        clearTimeout(temporizadorBusqueda);
        temporizadorBusqueda = setTimeout(() => { estado.filtros.texto = e.target.value; render(); }, 150);
    });
    $('#f-sucursal').addEventListener('change', (e) => { estado.filtros.sucursal = e.target.value; render(); });
    $('#f-regimen').addEventListener('change', (e) => { estado.filtros.regimen = e.target.value; render(); });
    $('#f-nivel').addEventListener('change', (e) => { estado.filtros.nivel = e.target.value; render(); });
    $('#f-limpiar').addEventListener('click', limpiarFiltros);
    $('#btn-recargar').addEventListener('click', () => cargarDatos());

    $$('[data-kpi]').forEach((tarjeta) => tarjeta.addEventListener('click', () => {
        const clave = tarjeta.dataset.kpi;
        estado.filtros.nivel = estado.filtros.nivel === clave ? '' : clave;
        sincronizarControles();
        render();
    }));

    // Menú lateral
    const sidebar = $('#sidebar');
    const fondoSidebar = $('#sidebar-fondo');
    const alternarMenu = (abrir) => {
        sidebar.classList.toggle('-translate-x-full', !abrir);
        fondoSidebar.classList.toggle('hidden', !abrir);
        $('#btn-menu').setAttribute('aria-expanded', abrir ? 'true' : 'false');
    };
    $('#btn-menu').addEventListener('click', () => alternarMenu(sidebar.classList.contains('-translate-x-full')));
    fondoSidebar.addEventListener('click', () => alternarMenu(false));
    $$('[data-nav-regimen]').forEach((enlace) => enlace.addEventListener('click', (e) => {
        e.preventDefault();
        estado.filtros.regimen = enlace.dataset.navRegimen;
        sincronizarControles();
        render();
        alternarMenu(false);
    }));

    // Refresco automático cada 5 minutos (mantiene el semáforo al día).
    setInterval(() => {
        if (!document.hidden && !modalAbierto) cargarDatos({ silencioso: true });
    }, 5 * 60 * 1000);

    // ------------------------------------------------------------------
    // Inicio
    // ------------------------------------------------------------------
    if (APP.errorCarga) mostrarAlertaCarga(APP.errorCarga);
    $('#txt-actualizado').textContent = APP.generado || '';
    render();
})();
</script>
</body>
</html>
