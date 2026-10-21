<?php
/**
 * config/security.php — CSRF, sanitización, rate limiting, cabeceras seguras.
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function security_send_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "img-src 'self' data: https:; " .
        "connect-src 'self' " . (CHAT_WEBHOOK_URL !== '' ? parse_url(CHAT_WEBHOOK_URL, PHP_URL_SCHEME) . '://' . parse_url(CHAT_WEBHOOK_URL, PHP_URL_HOST) : '') . ";");
    if (!empty($_SERVER['HTTPS'])) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function security_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    security_start_session();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function csrf_validate(?string $token): bool
{
    security_start_session();
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function clean_string(?string $value): string
{
    $value = trim($value ?? '');
    $value = strip_tags($value);
    return preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
}

function clean_email(?string $value): ?string
{
    $value = filter_var(trim($value ?? ''), FILTER_SANITIZE_EMAIL);
    if (!filter_var($value, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $value)) return null;
    return $value;
}

function clean_phone(?string $value): ?string
{
    $value = trim($value ?? '');
    return preg_match('/^[0-9+\-\s()]{7,20}$/', $value) ? $value : null;
}

function clean_number(?string $value): ?float
{
    $value = trim($value ?? '');
    return is_numeric($value) && (float) $value >= 0 ? (float) $value : null;
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rate_limit_check(string $bucket): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $dir = ROOT_PATH . '/logs/ratelimit';
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    $file = $dir . '/' . $bucket . '_' . md5($ip) . '.json';
    $now = time();
    $data = ['count' => 0, 'start' => $now];
    if (is_file($file)) {
        $raw = json_decode((string) file_get_contents($file), true);
        if (is_array($raw)) $data = $raw;
    }
    if (($now - (int) $data['start']) > QUOTE_RATE_LIMIT_WINDOW) $data = ['count' => 0, 'start' => $now];
    if ((int) $data['count'] >= QUOTE_RATE_LIMIT_MAX) return false;
    $data['count'] = (int) $data['count'] + 1;
    file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}
