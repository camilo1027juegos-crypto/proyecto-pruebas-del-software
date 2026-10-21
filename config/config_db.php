<?php
/**
 * config/config_db.php
 * -----------------------------------------------------------------------
 * Conexión PDO, constantes de BD, webhook n8n, directorio de cargas y
 * catálogos/reglas de negocio COMPARTIDOS por el panel y los handlers.
 *
 * Inclusión obligatoria (desde panel/ o handlers/):
 *     require_once dirname(__DIR__) . '/config/config_db.php';
 *
 * Los catálogos (estados, sucursales, tipos de documento) viven aquí para
 * que dashboard.php y los handlers validen contra la MISMA lista.
 * -----------------------------------------------------------------------
 */
declare(strict_types=1);

date_default_timezone_set('America/Bogota');

// Errores al log (nunca en pantalla dentro de respuestas JSON).
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/logs/php-error.log');

// ---------------------------------------------------------------------
// Base de datos (valores por defecto para XAMPP local).
// En producción defina variables de entorno en lugar de editar aquí.
// ---------------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'rubicam_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------
// Webhook de n8n para alertas de cambio de estado.
//  - URL /webhook/...      → requiere el workflow ACTIVO en n8n.
//  - URL /webhook-test/... → solo funciona con "Listen for test event".
// ---------------------------------------------------------------------
define('N8N_WEBHOOK_URL', getenv('N8N_WEBHOOK_URL') ?: 'https://camilo-fesc1.app.n8n.cloud/webhook/rubicam-alertas');
// Opcional: token compartido que se envía en la cabecera X-Rubicam-Token
// (en n8n: Webhook → Authentication → Header Auth).
define('N8N_WEBHOOK_SECRET', getenv('N8N_WEBHOOK_SECRET') ?: '');
// Opcional: ruta absoluta a cacert.pem. Úselo si XAMPP en Windows muestra
// "SSL certificate problem: unable to get local issuer certificate".
define('N8N_CA_BUNDLE', getenv('N8N_CA_BUNDLE') ?: '');
define('N8N_TIMEOUT_SEGUNDOS', 8);

// ---------------------------------------------------------------------
// Archivos adjuntos (legajo aduanero).
// ---------------------------------------------------------------------
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('UPLOAD_MAX_BYTES', 10 * 1024 * 1024); // 10 MB

// ---------------------------------------------------------------------
// Catálogos de negocio.
// ---------------------------------------------------------------------
const SUCURSALES_DO = ['Bogotá', 'Cartagena', 'Cúcuta', 'Medellín'];
const PREFIJOS_SUCURSAL = ['Bogotá' => 'BOG', 'Cartagena' => 'CTG', 'Cúcuta' => 'CUC', 'Medellín' => 'MED'];

const REGIMENES_DO = ['Importación', 'Exportación', 'Tránsito'];
const PREFIJOS_REGIMEN = ['Importación' => 'IMP', 'Exportación' => 'EXP', 'Tránsito' => 'TRA'];

const ESTADO_INICIAL_DO = 'En trámite';
const ESTADOS_DO = [
    'En trámite',
    'Presentada DIAN',
    'Aceptada',
    'Nacionalizado',
    'Finalizada',
    'Cerrado sin facturar',
];
// Estados que cierran la operación: NO generan alerta DIAN (badge neutro).
const ESTADOS_FINALES = ['Finalizada', 'Nacionalizado', 'Cerrado sin facturar'];

const TIPOS_DOCUMENTO = [
    'Declaración de Importación',
    'Factura Comercial',
    'Lista de Empaque',
    'RUT',
    'BL/Guía aérea',
    'Otro',
];

// Roles con permiso de escritura (el rol "consulta" solo puede ver).
const ROLES_EDICION = ['admin', 'operador'];

// Semáforo (días transcurridos desde la apertura).
const SEMAFORO_DIAS_SEGUIMIENTO = 30; // desde 30 días → naranja
const SEMAFORO_DIAS_CRITICO     = 75; // más de 75 días → rojo
const PLAZO_DIAN_DIAS           = 90; // vencimiento sugerido al crear un DO

// ---------------------------------------------------------------------
// Conexión PDO (singleton). Lanza RuntimeException si falla: cada
// script decide cómo responder (página de error o JSON).
// ---------------------------------------------------------------------
function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Hora de Colombia para los TIMESTAMP y modo estricto (los ENUM inválidos fallan en vez de guardarse vacíos).
        $pdo->exec("SET time_zone = '-05:00', sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    } catch (PDOException $e) {
        $pdo = null;
        error_log('[rubicam-panel] Error de conexión a BD: ' . $e->getMessage());
        throw new RuntimeException('No fue posible conectar con la base de datos. Contacte al administrador.', 0, $e);
    }

    return $pdo;
}

if (!function_exists('h')) {
    /** Escapa texto para HTML. */
    function h(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ---------------------------------------------------------------------
// Sesión y seguridad del panel.
// ---------------------------------------------------------------------
function iniciar_sesion_panel(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function usuario_autenticado(): bool
{
    return !empty($_SESSION['user_id']);
}

function usuario_puede_editar(): bool
{
    return in_array($_SESSION['rol'] ?? '', ROLES_EDICION, true);
}

function usuario_nombre_sesion(): string
{
    return (string) ($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'Usuario');
}

function csrf_token_panel(): string
{
    if (empty($_SESSION['csrf_panel'])) {
        $_SESSION['csrf_panel'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_panel'];
}

function csrf_valido_panel(?string $token): bool
{
    return !empty($_SESSION['csrf_panel'])
        && is_string($token)
        && $token !== ''
        && hash_equals($_SESSION['csrf_panel'], $token);
}

// ---------------------------------------------------------------------
// Reglas de negocio compartidas.
// ---------------------------------------------------------------------
function es_estado_final(string $estado): bool
{
    return in_array($estado, ESTADOS_FINALES, true);
}

/**
 * Semáforo de seguimiento DIAN.
 *   Verde  (al_dia)      : menos de 30 días transcurridos.
 *   Naranja (seguimiento): de 30 a 75 días.
 *   Rojo   (critico)     : más de 75 días, o fecha de vencimiento ya superada.
 *   Neutro (cerrado)     : estados Finalizada / Nacionalizado / Cerrado sin facturar.
 *
 * @return array{nivel:string, etiqueta:string, descripcion:string, dias_transcurridos:int,
 *               dias_restantes:?int, vencido:bool, progreso:int}
 */
function calcular_semaforo_do(string $fechaApertura, ?string $fechaVencimiento, string $estado, ?DateTimeImmutable $hoy = null): array
{
    $hoy ??= new DateTimeImmutable('today');

    $apertura = DateTimeImmutable::createFromFormat('!Y-m-d', substr($fechaApertura, 0, 10)) ?: $hoy;
    // %r%a = días con signo (negativo si la apertura es futura).
    $diasTranscurridos = (int) $apertura->diff($hoy)->format('%r%a');

    $diasRestantes = null;
    $plazoTotal    = PLAZO_DIAN_DIAS;
    if ($fechaVencimiento !== null && $fechaVencimiento !== '') {
        $vencimiento = DateTimeImmutable::createFromFormat('!Y-m-d', substr($fechaVencimiento, 0, 10));
        if ($vencimiento !== false) {
            $diasRestantes = (int) $hoy->diff($vencimiento)->format('%r%a');
            $plazoTotal    = max(1, (int) $apertura->diff($vencimiento)->format('%r%a'));
        }
    }

    $progreso = (int) max(0, min(100, round(max(0, $diasTranscurridos) / $plazoTotal * 100)));

    if (es_estado_final($estado)) {
        return [
            'nivel'              => 'cerrado',
            'etiqueta'           => 'Cerrado',
            'descripcion'        => 'Operación cerrada: no aplica alerta DIAN.',
            'dias_transcurridos' => $diasTranscurridos,
            'dias_restantes'     => $diasRestantes,
            'vencido'            => false,
            'progreso'           => $progreso,
        ];
    }

    $vencido = $diasRestantes !== null && $diasRestantes < 0;

    if ($vencido || $diasTranscurridos > SEMAFORO_DIAS_CRITICO) {
        $nivel = 'critico';
        $etiqueta = $vencido ? 'Vencido' : 'Crítico';
        $descripcion = $vencido
            ? 'La fecha de vencimiento ya pasó.'
            : 'Más de ' . SEMAFORO_DIAS_CRITICO . ' días: cerca del límite de 3 meses DIAN.';
    } elseif ($diasTranscurridos >= SEMAFORO_DIAS_SEGUIMIENTO) {
        $nivel = 'seguimiento';
        $etiqueta = 'Seguimiento';
        $descripcion = 'Entre ' . SEMAFORO_DIAS_SEGUIMIENTO . ' y ' . SEMAFORO_DIAS_CRITICO . ' días desde la apertura.';
    } else {
        $nivel = 'al_dia';
        $etiqueta = 'Al día';
        $descripcion = 'Menos de ' . SEMAFORO_DIAS_SEGUIMIENTO . ' días desde la apertura.';
    }

    return [
        'nivel'              => $nivel,
        'etiqueta'           => $etiqueta,
        'descripcion'        => $descripcion,
        'dias_transcurridos' => $diasTranscurridos,
        'dias_restantes'     => $diasRestantes,
        'vencido'            => $vencido,
        'progreso'           => $progreso,
    ];
}

/**
 * Agrega una entrada a la bitácora (campo procesos_do.observaciones).
 * Formato de cada línea (la más reciente arriba):
 *   [07/10/2026 15:48 · Nombre Usuario · Presentada DIAN] Texto de la observación
 */
function bitacora_agregar(?string $bitacoraActual, string $texto, string $usuario, string $estado): string
{
    $limpiar = static fn (string $v): string => trim((string) preg_replace('/\s+/u', ' ', str_replace(['[', ']', '·'], ' ', $v)));

    $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));
    $linea = sprintf('[%s · %s · %s] %s', date('d/m/Y H:i'), $limpiar($usuario), $limpiar($estado), $texto);

    $actual    = trim((string) $bitacoraActual);
    $resultado = $actual !== '' ? $linea . "\n" . $actual : $linea;

    // TEXT admite 65.535 bytes: si se llena, se descartan las entradas más antiguas.
    while (strlen($resultado) > 60000 && str_contains($resultado, "\n")) {
        $resultado = substr($resultado, 0, (int) strrpos($resultado, "\n"));
    }

    return $resultado;
}

/**
 * Convierte la bitácora en una lista de entradas (la más reciente primero).
 * Las observaciones antiguas sin formato se devuelven como texto plano.
 *
 * @return list<array{fecha:string, usuario:string, estado:string, texto:string}>
 */
function bitacora_parsear(?string $bitacora, int $limite = 10): array
{
    $lineas   = preg_split('/\R/u', trim((string) $bitacora)) ?: [];
    $entradas = [];

    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '') {
            continue;
        }
        if (preg_match('/^\[([^\]]*)\]\s*(.*)$/u', $linea, $m)) {
            $meta = array_map('trim', explode('·', $m[1]));
            $entradas[] = [
                'fecha'   => $meta[0] ?? '',
                'usuario' => $meta[1] ?? '',
                'estado'  => $meta[2] ?? '',
                'texto'   => $m[2],
            ];
        } else {
            $entradas[] = ['fecha' => '', 'usuario' => '', 'estado' => '', 'texto' => $linea];
        }
        if (count($entradas) >= $limite) {
            break;
        }
    }

    return $entradas;
}

/** Dígito de verificación del NIT según el algoritmo de la DIAN (módulo 11). */
function calcular_dv_nit(string $nitBase): int
{
    $pesos = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
    $digitos = strrev($nitBase);
    $suma = 0;
    for ($i = 0, $n = strlen($digitos); $i < $n && $i < count($pesos); $i++) {
        $suma += (int) $digitos[$i] * $pesos[$i];
    }
    $residuo = $suma % 11;
    return $residuo > 1 ? 11 - $residuo : $residuo;
}
