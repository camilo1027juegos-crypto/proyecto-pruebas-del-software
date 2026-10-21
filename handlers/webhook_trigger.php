<?php
/**
 * handlers/webhook_trigger.php
 * -----------------------------------------------------------------------
 * Actualiza el estado / bitácora de un DO y alerta a n8n vía cURL.
 *
 * Petición : POST  application/json
 *            Cabecera X-CSRF-Token (la envía panel/dashboard.php)
 *            { "do_id": 2, "do_codigo": "CTG26IMP0841", "nuevo_estado": "Aceptada",
 *              "observaciones": "Inspección previa", "usuario": "..." }
 * Respuesta: SIEMPRE JSON
 *            { "status": "success" | "error", "message": "...", ... }
 *
 * Notas:
 *  - El usuario se toma de la SESIÓN; el campo "usuario" del cliente no se usa
 *    para auditoría (no es confiable).
 *  - La base de datos es la fuente de verdad: si n8n falla, el estado YA quedó
 *    guardado y la respuesta es "success" con webhook.enviado = false.
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

// Cualquier error fatal o excepción no controlada también responde en JSON.
register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        error_log('[rubicam-panel] Fatal en webhook_trigger.php: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line']);
        responder('error', 'Error interno del servidor. Revise logs/php-error.log.', 500);
    }
});
set_exception_handler(static function (Throwable $e): void {
    error_log('[rubicam-panel] Excepción en webhook_trigger.php: ' . $e->getMessage());
    responder('error', 'Error interno del servidor. Intente de nuevo.', 500);
});

require_once dirname(__DIR__) . '/config/config_db.php';

// ---------------------------------------------------------------------
// 1) Seguridad: sesión, método, CSRF y permisos.
// ---------------------------------------------------------------------
iniciar_sesion_panel();

if (!usuario_autenticado()) {
    responder('error', 'Su sesión expiró. Inicie sesión nuevamente.', 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder('error', 'Método no permitido. Use POST.', 405);
}
if (!csrf_valido_panel($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    responder('error', 'Token de seguridad inválido. Recargue la página e intente de nuevo.', 403);
}
if (!usuario_puede_editar()) {
    responder('error', 'Su rol no tiene permiso para modificar operaciones.', 403);
}

// ---------------------------------------------------------------------
// 2) Lectura y validación del JSON.
// ---------------------------------------------------------------------
$raw = (string) file_get_contents('php://input');
if ($raw === '') {
    responder('error', 'La solicitud llegó vacía.', 400);
}
if (strlen($raw) > 65536) {
    responder('error', 'La solicitud es demasiado grande.', 413);
}

try {
    $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    responder('error', 'El cuerpo de la solicitud no es un JSON válido.', 400);
}
if (!is_array($input)) {
    responder('error', 'Formato de solicitud inválido.', 400);
}

$doId          = filter_var($input['do_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$doCodigo      = strtoupper(trim((string) ($input['do_codigo'] ?? '')));
$nuevoEstado   = trim((string) ($input['nuevo_estado'] ?? ''));
$observaciones = trim((string) preg_replace('/\s+/u', ' ', (string) ($input['observaciones'] ?? '')));

if ($doId === false || $doId === null) {
    responder('error', 'Identificador de operación (do_id) inválido.', 422);
}
if (!in_array($nuevoEstado, ESTADOS_DO, true)) {
    responder('error', 'Estado no permitido. Opciones: ' . implode(', ', ESTADOS_DO) . '.', 422);
}
if (mb_strlen($observaciones) > 1000) {
    responder('error', 'La observación no puede superar 1.000 caracteres.', 422);
}

// ---------------------------------------------------------------------
// 3) Actualización en MySQL (transacción + bloqueo de fila).
// ---------------------------------------------------------------------
try {
    $pdo = get_db_connection();
} catch (RuntimeException $e) {
    responder('error', $e->getMessage(), 503);
}

$usuarioNombre = usuario_nombre_sesion();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT id, do_codigo, cliente, nit, email, telefono, regimen, sucursal, estado,
                fecha_apertura, fecha_vencimiento, observaciones
           FROM procesos_do
          WHERE id = :id
          FOR UPDATE'
    );
    $stmt->execute(['id' => $doId]);
    $do = $stmt->fetch();

    if (!$do) {
        $pdo->rollBack();
        responder('error', 'La orden de operación indicada no existe.', 404);
    }
    if ($doCodigo !== '' && $doCodigo !== strtoupper($do['do_codigo'])) {
        $pdo->rollBack();
        responder('error', 'El código del DO no coincide con el registro. Recargue la página.', 409);
    }

    $estadoAnterior = (string) $do['estado'];
    $cambioEstado   = $estadoAnterior !== $nuevoEstado;

    if (!$cambioEstado && $observaciones === '') {
        $pdo->rollBack();
        responder('error', 'No hay cambios: seleccione un estado diferente o escriba una observación.', 422);
    }

    $textoBitacora = $observaciones !== ''
        ? $observaciones
        : sprintf('Cambio de estado: %s → %s.', $estadoAnterior, $nuevoEstado);

    $nuevaBitacora = bitacora_agregar($do['observaciones'], $textoBitacora, $usuarioNombre, $nuevoEstado);

    $stmt = $pdo->prepare('UPDATE procesos_do SET estado = :estado, observaciones = :obs WHERE id = :id');
    $stmt->execute([
        'estado' => $nuevoEstado,
        'obs'    => $nuevaBitacora,
        'id'     => $doId,
    ]);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[rubicam-panel] Error actualizando estado DO ' . $doId . ': ' . $e->getMessage());
    responder('error', 'No se pudo actualizar el estado en la base de datos.', 500);
}

// ---------------------------------------------------------------------
// 4) Alerta a n8n (después del commit).
// ---------------------------------------------------------------------
$semaforo = calcular_semaforo_do((string) $do['fecha_apertura'], $do['fecha_vencimiento'], $nuevoEstado);

$payload = [
    'evento'             => $cambioEstado ? 'cambio_estado_do' : 'observacion_do',
    'do_id'              => (int) $do['id'],
    'do_codigo'          => $do['do_codigo'],
    'cliente'            => $do['cliente'],
    'nit'                => $do['nit'],
    'email'              => $do['email'],
    'telefono'           => $do['telefono'],
    'regimen'            => $do['regimen'],
    'sucursal'           => $do['sucursal'],
    'estado_anterior'    => $estadoAnterior,
    'nuevo_estado'       => $nuevoEstado,
    'observaciones'      => $textoBitacora,
    'fecha_apertura'     => $do['fecha_apertura'],
    'fecha_vencimiento'  => $do['fecha_vencimiento'],
    'dias_transcurridos' => $semaforo['dias_transcurridos'],
    'dias_restantes'     => $semaforo['dias_restantes'],
    'nivel_alerta'       => $semaforo['nivel'],
    'usuario'            => $usuarioNombre,
    'usuario_login'      => (string) ($_SESSION['usuario'] ?? ''),
    'fecha_evento'       => date('c'),
];

$webhook = enviar_webhook_n8n($payload);

$datosRespuesta = [
    'webhook' => $webhook,
    'data'    => [
        'do_id'     => (int) $do['id'],
        'do_codigo' => $do['do_codigo'],
        'estado'    => $nuevoEstado,
        'semaforo'  => $semaforo,
    ],
];

if ($webhook['enviado']) {
    responder('success', sprintf('DO %s actualizado a "%s" y alerta enviada a n8n.', $do['do_codigo'], $nuevoEstado), 200, $datosRespuesta);
}

responder(
    'success',
    sprintf('DO %s actualizado a "%s", pero la alerta a n8n no se envió: %s', $do['do_codigo'], $nuevoEstado, $webhook['error']),
    200,
    $datosRespuesta
);

// ---------------------------------------------------------------------
// Funciones
// ---------------------------------------------------------------------

/**
 * Envía el payload al webhook de n8n.
 * @return array{enviado: bool, http_code: ?int, error: ?string}
 */
function enviar_webhook_n8n(array $payload): array
{
    if (!filter_var(N8N_WEBHOOK_URL, FILTER_VALIDATE_URL)) {
        return ['enviado' => false, 'http_code' => null, 'error' => 'N8N_WEBHOOK_URL no está configurada.'];
    }
    if (!function_exists('curl_init')) {
        return ['enviado' => false, 'http_code' => null, 'error' => 'La extensión cURL de PHP no está habilitada (php.ini → extension=curl).'];
    }

    $headers = ['Content-Type: application/json; charset=utf-8', 'Accept: application/json'];
    if (N8N_WEBHOOK_SECRET !== '') {
        $headers[] = 'X-Rubicam-Token: ' . N8N_WEBHOOK_SECRET;
    }

    $opciones = [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => N8N_TIMEOUT_SEGUNDOS,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'Rubicam-Panel/2.0',
    ];
    if (N8N_CA_BUNDLE !== '' && is_file(N8N_CA_BUNDLE)) {
        $opciones[CURLOPT_CAINFO] = N8N_CA_BUNDLE;
    }

    $ch = curl_init(N8N_WEBHOOK_URL);
    curl_setopt_array($ch, $opciones);

    $respuesta = curl_exec($ch);
    $errno     = curl_errno($ch);
    $curlError = curl_error($ch);
    $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($respuesta === false || $errno !== 0) {
        error_log("[rubicam-panel] cURL n8n ($errno): $curlError");
        $detalle = match ($errno) {
            CURLE_OPERATION_TIMEDOUT => 'n8n no respondió a tiempo.',
            CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT => 'no hay conexión con el servidor de n8n.',
            60, 77 => 'falló la verificación SSL; configure curl.cainfo en php.ini o N8N_CA_BUNDLE.',
            default => 'error de conexión (' . $curlError . ').',
        };
        return ['enviado' => false, 'http_code' => null, 'error' => $detalle];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['enviado' => true, 'http_code' => $httpCode, 'error' => null];
    }

    error_log("[rubicam-panel] n8n respondió HTTP $httpCode: " . substr((string) $respuesta, 0, 500));
    $detalle = match (true) {
        $httpCode === 404 => 'n8n respondió 404 (¿el workflow está activo y la URL es /webhook/ y no /webhook-test/?).',
        $httpCode === 401, $httpCode === 403 => "n8n rechazó la autenticación (HTTP $httpCode).",
        default => "n8n respondió con código HTTP $httpCode.",
    };
    return ['enviado' => false, 'http_code' => $httpCode, 'error' => $detalle];
}
