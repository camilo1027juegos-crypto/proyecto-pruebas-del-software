<?php

/**
 * tests/e2e/ejecutar.php
 * -----------------------------------------------------------------------
 * Prueba de humo / regresión de extremo a extremo del panel Rubicam.
 *
 *  1. Crea una base de datos DESECHABLE (rubicam_e2e) desde SQL/rubicam_db.sql
 *     y dos usuarios de prueba (admin y consulta).
 *  2. Levanta el servidor embebido de PHP (aplicación) y un "n8n falso".
 *  3. Ejecuta los casos E2E-xx contra la aplicación real (HTTP + MySQL).
 *
 * Uso:   php tests/e2e/ejecutar.php
 * Requiere: extensiones curl, pdo_mysql, zip y un MySQL/MariaDB accesible
 * (variables DB_HOST, DB_PORT, DB_USER, DB_PASS; por defecto XAMPP local).
 *
 * Limitación conocida: el servidor embebido ignora los archivos .htaccess,
 * así que el bloqueo de /uploads y /config se verifica a mano en Apache
 * (ver docs/02_CASOS_DE_PRUEBA.md, casos CP-SEG-xx).
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

use Rubicam\Tests\E2e\ClienteHttp;

require __DIR__ . '/ClienteHttp.php';

const PUERTO_APP = 8099;
const PUERTO_N8N = 8098;
const NOMBRE_BD_E2E = 'rubicam_e2e';
const CLAVE_E2E = 'E2e-Pass-2026!';

$raizProyecto = dirname(__DIR__, 2);
$hostBd = getenv('DB_HOST') ?: '127.0.0.1';
$puertoBd = getenv('DB_PORT') ?: '3306';
$usuarioBd = getenv('DB_USER') ?: 'root';
$claveBd = getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '';

// ---------------------------------------------------------------------
// 1) Base de datos desechable
// ---------------------------------------------------------------------
$pdo = new PDO(
    "mysql:host={$hostBd};port={$puertoBd};charset=utf8mb4",
    $usuarioBd,
    $claveBd,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec('DROP DATABASE IF EXISTS `' . NOMBRE_BD_E2E . '`');
$pdo->exec('CREATE DATABASE `' . NOMBRE_BD_E2E . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$pdo->exec('USE `' . NOMBRE_BD_E2E . '`');
$pdo->exec((string) file_get_contents($raizProyecto . '/SQL/rubicam_db.sql'));
$pdo->exec('USE `' . NOMBRE_BD_E2E . '`');

$insertarUsuario = $pdo->prepare(
    'INSERT INTO usuarios (usuario, password_hash, rol, nombre_completo, sucursal, activo)
     VALUES (:usuario, :hash, :rol, :nombre, :sucursal, 1)'
);
foreach (['e2e_admin' => 'admin', 'e2e_consulta' => 'consulta', 'e2e_bloqueo' => 'consulta'] as $usuario => $rol) {
    $insertarUsuario->execute([
        'usuario'  => $usuario,
        'hash'     => password_hash(CLAVE_E2E, PASSWORD_DEFAULT),
        'rol'      => $rol,
        'nombre'   => 'Usuario ' . $usuario,
        'sucursal' => 'Cúcuta',
    ]);
}

// ---------------------------------------------------------------------
// 2) Servidores (aplicación + n8n falso)
// ---------------------------------------------------------------------
$logN8n = sys_get_temp_dir() . '/rubicam_e2e_n8n.log';
if (is_file($logN8n)) {
    unlink($logN8n);
}
$limitadorTasa = $raizProyecto . '/logs/ratelimit';
foreach (glob($limitadorTasa . '/*') ?: [] as $archivoTasa) {
    unlink($archivoTasa);
}

$entorno = array_merge(getenv(), [
    'DB_HOST'          => $hostBd,
    'DB_PORT'          => $puertoBd,
    'DB_USER'          => $usuarioBd,
    'DB_PASS'          => $claveBd,
    'DB_NAME'          => NOMBRE_BD_E2E,
    'N8N_WEBHOOK_URL'  => 'http://127.0.0.1:' . PUERTO_N8N . '/webhook/e2e',
    'N8N_LOG_E2E'      => $logN8n,
]);

$iniciarServidor = static function (string $comando, array $entorno) use ($raizProyecto): mixed {
    $registroServidor = sys_get_temp_dir() . '/rubicam_e2e_server.log';
    $descriptores = [0 => ['pipe', 'r'], 1 => ['file', $registroServidor, 'a'], 2 => ['pipe', 'w']];
    $proceso = proc_open($comando, $descriptores, $tuberias, $raizProyecto, $entorno);
    if (!is_resource($proceso)) {
        throw new RuntimeException('No se pudo iniciar el servidor: ' . $comando);
    }

    return $proceso;
};

$php = escapeshellarg(PHP_BINARY);
$servidores = [
    $iniciarServidor(
        $php . ' -d upload_max_filesize=64M -d post_max_size=64M -d sendmail_path=/bin/true -S 127.0.0.1:'
            . PUERTO_APP . ' -t ' . escapeshellarg($raizProyecto),
        $entorno
    ),
    $iniciarServidor(
        $php . ' -S 127.0.0.1:' . PUERTO_N8N . ' ' . escapeshellarg(__DIR__ . '/fake_n8n.php'),
        $entorno
    ),
];
register_shutdown_function(static function () use (&$servidores): void {
    foreach ($servidores as $servidor) {
        if (is_resource($servidor)) {
            proc_terminate($servidor);
        }
    }
});

foreach ([PUERTO_APP, PUERTO_N8N] as $puerto) {
    $listo = false;
    for ($intento = 0; $intento < 50 && !$listo; $intento++) {
        $socket = @fsockopen('127.0.0.1', $puerto, $codigo, $mensaje, 0.2);
        if ($socket !== false) {
            fclose($socket);
            $listo = true;
        } else {
            usleep(100000);
        }
    }
    if (!$listo) {
        fwrite(STDERR, "El servidor del puerto {$puerto} no arrancó.\n");
        exit(2);
    }
}

// ---------------------------------------------------------------------
// 3) Casos de prueba
// ---------------------------------------------------------------------
$resultados = ['ok' => 0, 'fallos' => 0];
$verificar = static function (
    string $id,
    string $descripcion,
    bool $condicion,
    string $detalle = ''
) use (&$resultados): void {
    if ($condicion) {
        $resultados['ok']++;
        echo "[PASS] {$id} {$descripcion}\n";

        return;
    }
    $resultados['fallos']++;
    echo "[FAIL] {$id} {$descripcion}" . ($detalle !== '' ? " -> {$detalle}" : '') . "\n";
};

$extraerConfiguracion = static function (string $html): ?array {
    if (preg_match('#<script type="application/json" id="app-config">(.*?)</script>#s', $html, $coincidencia) !== 1) {
        return null;
    }
    $datos = json_decode($coincidencia[1], true);

    return is_array($datos) ? $datos : null;
};

$hoy = new DateTimeImmutable('today');
$fechaHaceDias = static fn (int $dias): string => $hoy->modify("-{$dias} days")->format('Y-m-d');
$base = 'http://127.0.0.1:' . PUERTO_APP;

$admin = new ClienteHttp($base);
$consulta = new ClienteHttp($base);
$anonimo = new ClienteHttp($base);

// --- Acceso y sesión --------------------------------------------------
$r = $anonimo->get('/panel/dashboard.php');
$verificar(
    'E2E-01',
    'Dashboard sin sesión redirige al login',
    $r['estado'] === 302 && str_contains($r['cabeceras']['location'] ?? '', 'index.php')
);

$r = $anonimo->get('/panel/dashboard.php?action=listar');
$verificar(
    'E2E-02',
    'Endpoint listar sin sesión responde 401 JSON',
    $r['estado'] === 401 && ($r['json']['status'] ?? '') === 'error'
);

$r = $admin->postFormulario('/panel/index.php', ['usuario' => 'e2e_admin', 'password' => 'incorrecta']);
$verificar(
    'E2E-03',
    'Login con clave incorrecta muestra mensaje genérico',
    $r['estado'] === 200 && str_contains($r['cuerpo'], 'Usuario o contraseña incorrectos')
);

$r = $admin->postFormulario('/panel/index.php', ['usuario' => '', 'password' => '']);
$verificar(
    'E2E-04',
    'Login vacío pide usuario y contraseña',
    $r['estado'] === 200 && str_contains($r['cuerpo'], 'ingrese usuario y contraseña')
);

$r = $admin->postFormulario('/panel/index.php', ['usuario' => 'e2e_admin', 'password' => CLAVE_E2E]);
$verificar(
    'E2E-05',
    'Login correcto redirige al dashboard',
    $r['estado'] === 302 && str_contains($r['cabeceras']['location'] ?? '', 'dashboard.php')
);

$r = $admin->get('/panel/dashboard.php');
$configuracion = $extraerConfiguracion($r['cuerpo']);
$csrf = (string) ($configuracion['csrf'] ?? '');
$verificar(
    'E2E-06',
    'Dashboard autenticado entrega configuración con CSRF y permiso de edición',
    $r['estado'] === 200 && $csrf !== '' && ($configuracion['puedeEditar'] ?? false) === true
);
$cabeceraCsrf = ['X-CSRF-Token: ' . $csrf];

// --- Listado -----------------------------------------------------------
$r = $admin->get('/panel/dashboard.php?action=listar');
$dosIniciales = $r['json']['data']['dos'] ?? [];
$verificar(
    'E2E-07',
    'Listar devuelve los 4 DOs de la semilla con su semáforo',
    $r['estado'] === 200 && count($dosIniciales) === 4 && isset($dosIniciales[0]['semaforo']['nivel'])
);

// --- Crear DO ----------------------------------------------------------
$datosDo = static fn (string $codigo, string $apertura, array $extra = []): array => array_merge([
    'do_codigo'      => $codigo,
    'cliente'        => 'Cliente de Prueba E2E S.A.S.',
    'nit'            => '900123456',
    'regimen'        => 'Importación',
    'sucursal'       => 'Cúcuta',
    'fecha_apertura' => $apertura,
    'email'          => 'e2e@example.com',
    'telefono'       => '3001234567',
    'observacion'    => 'Creado por prueba E2E',
], $extra);

$r = $admin->postJson('/panel/dashboard.php?action=crear', $datosDo('CUC26IMP9101', $fechaHaceDias(10)));
$verificar('E2E-08', 'Crear DO sin token CSRF responde 403', $r['estado'] === 403);

$r = $admin->postJson('/panel/dashboard.php?action=crear', [
    'do_codigo' => 'xx', 'cliente' => 'A', 'nit' => 'abc', 'regimen' => 'Otro', 'sucursal' => 'Marte',
    'fecha_apertura' => '2999-01-01', 'email' => 'no-es-correo', 'telefono' => 'abc',
], $cabeceraCsrf);
$claves = array_keys($r['json']['errors'] ?? []);
sort($claves);
$verificar(
    'E2E-09',
    'Crear DO con datos inválidos responde 422 con un error por campo',
    $r['estado'] === 422
        && $claves === ['cliente', 'do_codigo', 'email', 'fecha_apertura', 'nit', 'regimen', 'sucursal', 'telefono'],
    'errores: ' . implode(',', $claves)
);

$r = $admin->postJson('/panel/dashboard.php?action=crear', $datosDo('CUC26IMP9101', $fechaHaceDias(10)), $cabeceraCsrf);
$idAlDia = (int) ($r['json']['data']['do']['id'] ?? 0);
$verificar(
    'E2E-10',
    'Crear DO válido (10 días) responde 201, "En trámite", semáforo al_dia y NIT con dígito de verificación',
    $r['estado'] === 201
        && ($r['json']['data']['do']['estado'] ?? '') === 'En trámite'
        && ($r['json']['data']['do']['semaforo']['nivel'] ?? '') === 'al_dia'
        && preg_match('/^900123456-\d$/', (string) ($r['json']['data']['do']['nit'] ?? '')) === 1
);

$r = $admin->postJson('/panel/dashboard.php?action=crear', $datosDo('CUC26IMP9101', $fechaHaceDias(10)), $cabeceraCsrf);
$verificar('E2E-11', 'Crear DO con código duplicado responde 409', $r['estado'] === 409);

$r = $admin->postJson('/panel/dashboard.php?action=crear', $datosDo('CUC26IMP9102', $fechaHaceDias(40)), $cabeceraCsrf);
$idSeguimiento = (int) ($r['json']['data']['do']['id'] ?? 0);
$nivel40 = $r['json']['data']['do']['semaforo']['nivel'] ?? '';
$r = $admin->postJson('/panel/dashboard.php?action=crear', $datosDo('CUC26IMP9103', $fechaHaceDias(80)), $cabeceraCsrf);
$nivel80 = $r['json']['data']['do']['semaforo']['nivel'] ?? '';
$vencidoAyer = $datosDo('CUC26IMP9104', $fechaHaceDias(20), ['fecha_vencimiento' => $fechaHaceDias(1)]);
$r = $admin->postJson('/panel/dashboard.php?action=crear', $vencidoAyer, $cabeceraCsrf);
$etiquetaVencido = $r['json']['data']['do']['semaforo']['etiqueta'] ?? '';
$verificar(
    'E2E-12',
    'Semáforo: 40 días = seguimiento, 80 días = crítico, vencimiento pasado = "Vencido"',
    $nivel40 === 'seguimiento' && $nivel80 === 'critico' && $etiquetaVencido === 'Vencido',
    "40d={$nivel40} 80d={$nivel80} venc={$etiquetaVencido}"
);

// --- Actualizar estado (webhook n8n) ----------------------------------
$urlWebhook = '/handlers/webhook_trigger.php';
$r = $admin->postJson($urlWebhook, ['do_id' => $idAlDia, 'nuevo_estado' => 'Aceptada']);
$sinCsrf = $r['estado'];
$r = $admin->postJson($urlWebhook, ['do_id' => $idAlDia, 'nuevo_estado' => 'Inventado'], $cabeceraCsrf);
$estadoInvalido = $r['estado'];
$r = $admin->postJson($urlWebhook, ['do_id' => 999999, 'nuevo_estado' => 'Aceptada'], $cabeceraCsrf);
$noExiste = $r['estado'];
$codigoDistinto = ['do_id' => $idAlDia, 'do_codigo' => 'OTRO00IMP0001', 'nuevo_estado' => 'Aceptada'];
$r = $admin->postJson($urlWebhook, $codigoDistinto, $cabeceraCsrf);
$codigoNoCoincide = $r['estado'];
$r = $admin->postJson($urlWebhook, ['do_id' => $idAlDia, 'nuevo_estado' => 'En trámite'], $cabeceraCsrf);
$sinCambios = $r['estado'];
$verificar(
    'E2E-13',
    'Webhook: sin CSRF=403, estado inválido=422, DO inexistente=404, código distinto=409, sin cambios=422',
    [$sinCsrf, $estadoInvalido, $noExiste, $codigoNoCoincide, $sinCambios] === [403, 422, 404, 409, 422],
    implode(',', [$sinCsrf, $estadoInvalido, $noExiste, $codigoNoCoincide, $sinCambios])
);

$cambioValido = ['do_id' => $idAlDia, 'nuevo_estado' => 'Aceptada', 'observaciones' => 'Inspección previa OK'];
$r = $admin->postJson($urlWebhook, $cambioValido, $cabeceraCsrf);
$eventoN8n = null;
if (is_file($logN8n)) {
    $lineas = file($logN8n, FILE_IGNORE_NEW_LINES) ?: [];
    $eventoN8n = json_decode((string) end($lineas), true);
}
$verificar(
    'E2E-14',
    'Cambio de estado válido responde 200, guarda bitácora y notifica a n8n con el payload esperado',
    $r['estado'] === 200
        && ($r['json']['status'] ?? '') === 'success'
        && ($r['json']['webhook']['enviado'] ?? false) === true
        && is_array($eventoN8n)
        && ($eventoN8n['evento'] ?? '') === 'cambio_estado_do'
        && ($eventoN8n['estado_anterior'] ?? '') === 'En trámite'
        && ($eventoN8n['nuevo_estado'] ?? '') === 'Aceptada'
        && ($eventoN8n['nivel_alerta'] ?? '') === 'al_dia',
    json_encode($eventoN8n, JSON_UNESCAPED_UNICODE) ?: ''
);

$r = $admin->get('/panel/dashboard.php?action=listar');
$doActualizado = null;
foreach ($r['json']['data']['dos'] ?? [] as $fila) {
    if (($fila['id'] ?? 0) === $idAlDia) {
        $doActualizado = $fila;
    }
}
$verificar(
    'E2E-15',
    'La bitácora del DO registra la observación y el usuario de la sesión',
    is_array($doActualizado)
        && ($doActualizado['estado'] ?? '') === 'Aceptada'
        && ($doActualizado['bitacora'][0]['texto'] ?? '') === 'Inspección previa OK'
        && ($doActualizado['bitacora'][0]['usuario'] ?? '') === 'Usuario e2e_admin'
);

// --- Carga de documentos ----------------------------------------------
$directorioTemporal = sys_get_temp_dir() . '/rubicam_e2e_archivos';
if (!is_dir($directorioTemporal)) {
    mkdir($directorioTemporal, 0777, true);
}
$rutaPdf = $directorioTemporal . '/legajo.pdf';
file_put_contents($rutaPdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
$rutaPng = $directorioTemporal . '/foto.png';
file_put_contents($rutaPng, (string) base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
));
$rutaDocx = $directorioTemporal . '/informe.docx';
$zip = new ZipArchive();
$zip->open($rutaDocx, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types/>');
$zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document/>');
$zip->close();
$rutaFalsa = $directorioTemporal . '/falso.pdf';
file_put_contents($rutaFalsa, 'Esto es texto plano, no un PDF.');
$rutaExe = $directorioTemporal . '/virus.exe';
file_put_contents($rutaExe, 'MZ----');
$rutaGrande = $directorioTemporal . '/grande.pdf';
file_put_contents($rutaGrande, '%PDF-' . str_repeat('0', 10 * 1024 * 1024));

$subir = static function (ClienteHttp $cliente, array $cabeceras, int $doId, string $tipo, ?string $ruta) {
    $campos = ['do_id' => (string) $doId, 'tipo_documento' => $tipo];
    if ($ruta !== null) {
        $campos['documento'] = new CURLFile($ruta);
    }

    return $cliente->postMultipart('/handlers/upload_documento.php', $campos, $cabeceras);
};

$sinArchivo = $subir($admin, $cabeceraCsrf, $idAlDia, 'RUT', null)['estado'];
$conExe = $subir($admin, $cabeceraCsrf, $idAlDia, 'RUT', $rutaExe)['estado'];
$pdfFalso = $subir($admin, $cabeceraCsrf, $idAlDia, 'RUT', $rutaFalsa)['estado'];
$demasiadoGrande = $subir($admin, $cabeceraCsrf, $idAlDia, 'RUT', $rutaGrande)['estado'];
$tipoInvalido = $subir($admin, $cabeceraCsrf, $idAlDia, 'Cualquiera', $rutaPdf)['estado'];
$doInexistente = $subir($admin, $cabeceraCsrf, 999999, 'RUT', $rutaPdf)['estado'];
$sinToken = $subir($admin, [], $idAlDia, 'RUT', $rutaPdf)['estado'];
$verificar(
    'E2E-16',
    'Carga rechazada: sin archivo=422, .exe=415, PDF falso=415, >10 MB=413, '
        . 'tipo inválido=422, DO inexistente=404, sin CSRF=403',
    [$sinArchivo, $conExe, $pdfFalso, $demasiadoGrande, $tipoInvalido, $doInexistente, $sinToken]
        === [422, 415, 415, 413, 422, 404, 403],
    implode(',', [$sinArchivo, $conExe, $pdfFalso, $demasiadoGrande, $tipoInvalido, $doInexistente, $sinToken])
);

$r = $subir($admin, $cabeceraCsrf, $idAlDia, 'Factura Comercial', $rutaPdf);
$estadoPdf = $r['estado'];
$r2 = $subir($admin, $cabeceraCsrf, $idAlDia, 'Otro', $rutaPng);
$r3 = $subir($admin, $cabeceraCsrf, $idAlDia, 'Declaración de Importación', $rutaDocx);
$verificar(
    'E2E-17',
    'Carga válida de PDF, PNG y DOCX responde 201 y renombra el archivo de forma segura',
    $estadoPdf === 201 && $r2['estado'] === 201 && $r3['estado'] === 201
        && preg_match(
            '/^CUC26IMP9101_FACTURA_\d{8}-\d{6}_[0-9a-f]{6}\.pdf$/',
            (string) ($r['json']['data']['documento']['archivo'] ?? '')
        ) === 1,
    (string) ($r['json']['data']['documento']['archivo'] ?? $r['cuerpo'])
);

$r = $admin->get('/panel/dashboard.php?action=documentos&do_id=' . $idAlDia);
$documentos = $r['json']['data']['documentos'] ?? [];
$verificar('E2E-18', 'El legajo lista los 3 documentos subidos', $r['estado'] === 200 && count($documentos) === 3);

$idDocumentoPdf = 0;
foreach ($documentos as $documento) {
    if (($documento['extension'] ?? '') === 'pdf') {
        $idDocumentoPdf = (int) $documento['id'];
    }
}
$descarga = $admin->get('/panel/dashboard.php?action=descargar&doc_id=' . $idDocumentoPdf);
$vista = $admin->get('/panel/dashboard.php?action=descargar&modo=ver&doc_id=' . $idDocumentoPdf);
$faltante = $admin->get('/panel/dashboard.php?action=descargar&doc_id=999999');
$verificar(
    'E2E-19',
    'Descarga segura: attachment, vista inline y 404 para documento inexistente',
    $descarga['estado'] === 200
        && ($descarga['cabeceras']['content-type'] ?? '') === 'application/pdf'
        && str_starts_with($descarga['cabeceras']['content-disposition'] ?? '', 'attachment')
        && str_starts_with($vista['cabeceras']['content-disposition'] ?? '', 'inline')
        && $faltante['estado'] === 404
);

// --- Estados finales y semáforo neutro ---------------------------------
$r = $admin->postJson($urlWebhook, ['do_id' => $idSeguimiento, 'nuevo_estado' => 'Finalizada'], $cabeceraCsrf);
$verificar(
    'E2E-20',
    'Un DO Finalizada pasa a semáforo "cerrado" (sin alerta DIAN)',
    $r['estado'] === 200 && ($r['json']['data']['semaforo']['nivel'] ?? '') === 'cerrado'
);

// --- Rol de solo consulta ----------------------------------------------
$consulta->postFormulario('/panel/index.php', ['usuario' => 'e2e_consulta', 'password' => CLAVE_E2E]);
$configuracionConsulta = $extraerConfiguracion($consulta->get('/panel/dashboard.php')['cuerpo']);
$cabeceraConsulta = ['X-CSRF-Token: ' . (string) ($configuracionConsulta['csrf'] ?? '')];
$nuevoDo = $datosDo('CUC26IMP9105', $fechaHaceDias(1));
$crear = $consulta->postJson('/panel/dashboard.php?action=crear', $nuevoDo, $cabeceraConsulta)['estado'];
$intentoFinalizar = ['do_id' => $idAlDia, 'nuevo_estado' => 'Finalizada'];
$estado = $consulta->postJson($urlWebhook, $intentoFinalizar, $cabeceraConsulta)['estado'];
$subida = $subir($consulta, $cabeceraConsulta, $idAlDia, 'RUT', $rutaPdf)['estado'];
$lista = $consulta->get('/panel/dashboard.php?action=listar')['estado'];
$bajar = $consulta->get('/panel/dashboard.php?action=descargar&doc_id=' . $idDocumentoPdf)['estado'];
$verificar(
    'E2E-21',
    'Rol consulta: no puede crear/cambiar estado/subir (403) pero sí listar y descargar (200)',
    [$crear, $estado, $subida, $lista, $bajar] === [403, 403, 403, 200, 200],
    implode(',', [$crear, $estado, $subida, $lista, $bajar])
);

// --- Cierre de sesión ---------------------------------------------------
$r = $admin->get('/panel/logout.php');
$verificar(
    'E2E-22',
    'Logout redirige (303) al login',
    $r['estado'] === 303 && str_contains($r['cabeceras']['location'] ?? '', 'index.php')
);
$r = $admin->get('/panel/dashboard.php?action=listar');
$verificar('E2E-23', 'Tras el logout la sesión ya no es válida (401)', $r['estado'] === 401);

// --- Cotizador público -------------------------------------------------
$landing = $anonimo->get('/');
preg_match('/name="csrf_token" value="([^"]+)"/', $landing['cuerpo'], $coincidencia);
$csrfLanding = $coincidencia[1] ?? '';
$verificar('E2E-24', 'La landing responde 200 con cabeceras de seguridad y token CSRF', $landing['estado'] === 200
    && $csrfLanding !== ''
    && ($landing['cabeceras']['x-content-type-options'] ?? '') === 'nosniff'
    && isset($landing['cabeceras']['content-security-policy']));

$cotizacion = [
    'operation' => 'importacion', 'cif_value' => '1500.50', 'origin' => 'Shanghái', 'destination' => 'Cúcuta',
    'name' => 'Ana Pérez', 'email' => 'ana@example.com', 'phone' => '3001234567', 'website' => '',
];
$urlCotizador = '/handlers/quote-handler.php';
$sinToken = $anonimo->postFormulario($urlCotizador, $cotizacion)['estado'];
$trampa = $anonimo->postFormulario(
    $urlCotizador,
    array_merge($cotizacion, ['csrf_token' => $csrfLanding, 'website' => 'http://spam'])
);
$verificar(
    'E2E-25',
    'Cotizador: sin CSRF=419 y honeypot lleno = éxito falso (200) sin enviar nada',
    $sinToken === 419 && $trampa['estado'] === 200 && ($trampa['json']['ok'] ?? false) === true
);

$correoMalo = $anonimo->postFormulario(
    $urlCotizador,
    array_merge($cotizacion, ['csrf_token' => $csrfLanding, 'email' => 'no-es-correo'])
)['estado'];
$operacionMala = $anonimo->postFormulario(
    $urlCotizador,
    array_merge($cotizacion, ['csrf_token' => $csrfLanding, 'operation' => 'hackeo'])
)['estado'];
$verificar(
    'E2E-26',
    'Cotizador: correo inválido=422 y operación fuera de lista=422',
    [$correoMalo, $operacionMala] === [422, 422]
);

if (PHP_OS_FAMILY === 'Windows') {
    echo "[SKIP] E2E-27 Cotizador válido y límite de tasa (requiere sendmail_path=/bin/true)\n";
} else {
    $valida = $anonimo->postFormulario($urlCotizador, array_merge($cotizacion, ['csrf_token' => $csrfLanding]));
    $verificar(
        'E2E-27',
        'Cotizador válido responde 200 ok=true',
        $valida['estado'] === 200 && ($valida['json']['ok'] ?? false) === true,
        $valida['cuerpo']
    );
    // Ya se contaron 2 envíos inválidos (E2E-26) + 1 válido: el 4º y el 5º aún pasan; el 6º se bloquea.
    $anonimo->postFormulario($urlCotizador, array_merge($cotizacion, ['csrf_token' => $csrfLanding]));
    $envioValido = array_merge($cotizacion, ['csrf_token' => $csrfLanding]);
    $quinta = $anonimo->postFormulario($urlCotizador, $envioValido)['estado'];
    $sexta = $anonimo->postFormulario($urlCotizador, $envioValido)['estado'];
    $verificar(
        'E2E-28',
        'Límite de tasa: 5 envíos por ventana (5º=200) y el 6º responde 429',
        $quinta === 200 && $sexta === 429,
        "5º={$quinta} 6º={$sexta}"
    );
}

// --- MR-001 / MR-003: límites del cotizador ------------------------------
foreach (glob($limitadorTasa . '/*') ?: [] as $archivoTasa) {
    unlink($archivoTasa);
}
$origenLargo = $anonimo->postFormulario(
    $urlCotizador,
    array_merge($cotizacion, ['csrf_token' => $csrfLanding, 'origin' => str_repeat('a', 201)])
)['estado'];
$destinoLargo = $anonimo->postFormulario(
    $urlCotizador,
    array_merge($cotizacion, ['csrf_token' => $csrfLanding, 'destination' => str_repeat('b', 201)])
)['estado'];
$cifInfinito = $anonimo->postFormulario(
    $urlCotizador,
    array_merge($cotizacion, ['csrf_token' => $csrfLanding, 'cif_value' => '1e999'])
)['estado'];
$verificar(
    'E2E-34',
    'MR-001/003: origen > 120 caracteres, destino > 120 y valor CIF 1e999 responden 422',
    [$origenLargo, $destinoLargo, $cifInfinito] === [422, 422, 422],
    implode(',', [$origenLargo, $destinoLargo, $cifInfinito])
);

// --- MR-002: teléfono sin dígitos en el alta de DO ---------------------
$admin->postFormulario('/panel/index.php', ['usuario' => 'e2e_admin', 'password' => CLAVE_E2E]);
$configuracionNueva = $extraerConfiguracion($admin->get('/panel/dashboard.php')['cuerpo']);
$cabeceraNueva = ['X-CSRF-Token: ' . (string) ($configuracionNueva['csrf'] ?? '')];
$r = $admin->postJson(
    '/panel/dashboard.php?action=crear',
    $datosDo('CUC26IMP9106', $fechaHaceDias(5), ['telefono' => '-------']),
    $cabeceraNueva
);
$verificar(
    'E2E-35',
    'MR-002: alta de DO con teléfono sin dígitos responde 422 con error en "telefono"',
    $r['estado'] === 422 && isset($r['json']['errors']['telefono']),
    'HTTP ' . $r['estado']
);

// --- MR-006: bloqueo del inicio de sesión por intentos fallidos --------
foreach (glob($limitadorTasa . '/*') ?: [] as $archivoTasa) {
    unlink($archivoTasa);
}
$atacante = new ClienteHttp($base);
$respuestasLogin = [];
for ($intento = 1; $intento <= 5; $intento++) {
    $respuestasLogin[] = $atacante->postFormulario(
        '/panel/index.php',
        ['usuario' => 'e2e_bloqueo', 'password' => 'clave-equivocada-' . $intento]
    );
}
$conClaveCorrecta = $atacante->postFormulario(
    '/panel/index.php',
    ['usuario' => 'e2e_bloqueo', 'password' => CLAVE_E2E]
);
$verificar(
    'E2E-29',
    'MR-006: tras 5 fallos seguidos, ni la clave correcta entra (200 con aviso, sin redirigir)',
    $respuestasLogin[4]['estado'] === 200
        && $conClaveCorrecta['estado'] === 200
        && !isset($conClaveCorrecta['cabeceras']['location'])
        && str_contains($conClaveCorrecta['cuerpo'], 'Demasiados intentos'),
    'HTTP ' . $conClaveCorrecta['estado']
);

foreach (glob($limitadorTasa . '/*') ?: [] as $archivoTasa) {
    unlink($archivoTasa);
}
$legitimo = new ClienteHttp($base);
for ($intento = 1; $intento <= 4; $intento++) {
    $legitimo->postFormulario('/panel/index.php', ['usuario' => 'e2e_bloqueo', 'password' => 'mal-' . $intento]);
}
$exito = $legitimo->postFormulario('/panel/index.php', ['usuario' => 'e2e_bloqueo', 'password' => CLAVE_E2E]);
$legitimo->get('/panel/logout.php');
$otraVez = new ClienteHttp($base);
$ultimoFallo = $otraVez->postFormulario('/panel/index.php', ['usuario' => 'e2e_bloqueo', 'password' => 'mal-otra-vez']);
$verificar(
    'E2E-30',
    'MR-006: un inicio de sesión correcto reinicia el contador (4 fallos + éxito + 1 fallo no bloquea)',
    $exito['estado'] === 302
        && $ultimoFallo['estado'] === 200
        && str_contains($ultimoFallo['cuerpo'], 'Usuario o contraseña incorrectos'),
    'éxito=' . $exito['estado'] . ' último=' . $ultimoFallo['estado']
);
foreach (glob($limitadorTasa . '/*') ?: [] as $archivoTasa) {
    unlink($archivoTasa);
}

// --- MR-009: política de seguridad de contenido (CSP) estricta en el panel ---
$paginaPanel = $admin->get('/panel/dashboard.php');
$cspPanel = $paginaPanel['cabeceras']['content-security-policy'] ?? '';
$scriptsEnLinea = preg_match_all(
    '/<script(?![^>]*\bsrc=)(?![^>]*type="application\/json")[^>]*>/i',
    $paginaPanel['cuerpo']
);
$paginaAcceso = (new ClienteHttp($base))->get('/panel/index.php');
$verificar(
    'E2E-33',
    'MR-009: el panel envía CSP con script-src \'self\' y no contiene scripts en línea; el acceso no permite scripts',
    str_contains($cspPanel, "script-src 'self';")
        && $scriptsEnLinea === 0
        && str_contains($paginaAcceso['cabeceras']['content-security-policy'] ?? '', "script-src 'none'"),
    'CSP=' . $cspPanel . ' enLinea=' . $scriptsEnLinea
);

echo "\nResultado E2E: {$resultados['ok']} correctas, {$resultados['fallos']} con fallos.\n";
exit($resultados['fallos'] === 0 ? 0 : 1);
