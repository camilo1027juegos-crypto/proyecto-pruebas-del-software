<?php
/**
 * handlers/quote-handler.php
 * -----------------------------------------------------------------------
 * Procesa el formulario de cotización. Mismo estándar de seguridad que el
 * resto del ecosistema Rubicam: CSRF, honeypot, rate limiting,
 * sanitización estricta y envío de correo con cabeceras seguras.
 * -----------------------------------------------------------------------
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
security_send_headers();
security_start_session();

header('Content-Type: application/json; charset=UTF-8');

function respond(bool $ok, string $message, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, 'Método no permitido.', 405);
}
if (!csrf_validate($_POST['csrf_token'] ?? null)) {
    respond(false, 'Tu sesión expiró, recarga la página e inténtalo de nuevo.', 419);
}
if (!empty($_POST['website'])) {
    respond(true, 'Gracias, hemos recibido tu solicitud.'); // honeypot: éxito falso
}
if (!rate_limit_check('quote')) {
    respond(false, 'Has enviado demasiadas solicitudes. Intenta nuevamente en unos minutos.', 429);
}

$operation    = clean_string($_POST['operation'] ?? '');
$cifValueRaw  = $_POST['cif_value'] ?? '';
$cifValue     = $cifValueRaw !== '' ? clean_number($cifValueRaw) : null;
$origin       = clean_string($_POST['origin'] ?? '');
$destination  = clean_string($_POST['destination'] ?? '');
$name         = clean_string($_POST['name'] ?? '');
$email        = clean_email($_POST['email'] ?? '');
$phone        = clean_phone($_POST['phone'] ?? '');

// MR-003: sin tope, un origen de millones de caracteres terminaba en el cuerpo del correo.
const MAX_ROUTE_LENGTH = 120;

$validOps = ['importacion', 'exportacion', 'zona_franca', 'asesoria'];
$errors = [];
if (mb_strlen($origin) > MAX_ROUTE_LENGTH) $errors[] = 'El origen no puede superar ' . MAX_ROUTE_LENGTH . ' caracteres.';
if (mb_strlen($destination) > MAX_ROUTE_LENGTH) $errors[] = 'El destino no puede superar ' . MAX_ROUTE_LENGTH . ' caracteres.';
if (!in_array($operation, $validOps, true)) $errors[] = 'Selecciona un tipo de operación válido.';
if ($cifValueRaw !== '' && $cifValue === null) $errors[] = 'El valor CIF debe ser numérico.';
if ($name === '' || mb_strlen($name) > 200) $errors[] = 'El nombre es obligatorio.';
if ($email === null) $errors[] = 'El correo electrónico no es válido.';
if ($phone === null) $errors[] = 'El teléfono no es válido.';

if (!empty($errors)) {
    respond(false, implode(' ', $errors), 422);
}

$body = "Nueva solicitud de cotización — RUBICAM\n\n"
    . "Operación: {$operation}\n"
    . ($cifValue !== null ? "Valor CIF estimado: USD " . number_format($cifValue, 2) . "\n" : '')
    . ($origin !== '' ? "Origen: {$origin}\n" : '')
    . ($destination !== '' ? "Destino: {$destination}\n" : '')
    . "\nContacto:\nNombre: {$name}\nEmail: {$email}\nTeléfono: {$phone}\n"
    . "\n---\nEnviado " . date('Y-m-d H:i:s') . " desde IP " . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida') . "\n";

$headers = [
    'From: Cotizador Web Rubicam <no-responder@rubicam.co>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
];
$subject = '=?UTF-8?B?' . base64_encode('Nueva cotización - RUBICAM') . '?=';
$sent = @mail(EMAIL_CONTACT, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
    error_log('[quote-handler] Fallo al enviar cotización de ' . $email);
    respond(false, 'No pudimos procesar tu solicitud. Escríbenos directamente a ' . EMAIL_CONTACT . '.', 500);
}

respond(true, '¡Gracias! Un asesor especializado te contactará muy pronto con tu cotización.');
