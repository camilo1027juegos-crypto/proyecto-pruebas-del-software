<?php
/**
 * panel/logout.php
 * -----------------------------------------------------------------------
 * Cierre seguro de sesión: vacía $_SESSION, borra la cookie de sesión
 * (con los mismos parámetros con que se creó), destruye los datos en el
 * servidor y redirige al login (index.php) sin dejar páginas en caché.
 * -----------------------------------------------------------------------
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config_db.php';

iniciar_sesion_panel();

// 1) Vaciar todas las variables de sesión.
$_SESSION = [];

// 2) Eliminar la cookie de sesión del navegador.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?: 'Lax',
    ]);
}

// 3) Destruir los datos de sesión en el servidor.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// 4) Evitar que el botón "Atrás" muestre el panel desde la caché.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

header('Location: index.php', true, 303);
exit;
