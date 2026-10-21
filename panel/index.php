<?php
/**
 * panel/index.php
 * -----------------------------------------------------------------------
 * Pantalla de acceso al panel operativo RUBICAM.
 * -----------------------------------------------------------------------
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config_db.php';
iniciar_sesion_panel();

// Si ya hay sesión activa, saltar directo al dashboard.
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario  = trim((string)($_POST['usuario'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($usuario === '' || $password === '') {
        $error = 'Por favor ingrese usuario y contraseña.';
    } else {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare(
                'SELECT id, usuario, password_hash, rol, nombre_completo, sucursal
                 FROM usuarios WHERE usuario = :usuario AND activo = 1 LIMIT 1'
            );
            $stmt->execute(['usuario' => $usuario]);
            $row = $stmt->fetch();
        } catch (RuntimeException | PDOException $e) {
            error_log('[rubicam-panel] Error en login: ' . $e->getMessage());
            $row = false;
            $error = 'No fue posible conectar con la base de datos. Intente más tarde.';
        }

        if ($row && password_verify($password, $row['password_hash'])) {
            // Previene fijación de sesión.
            session_regenerate_id(true);

            $_SESSION['user_id']         = (int)$row['id'];
            $_SESSION['usuario']         = $row['usuario'];
            $_SESSION['rol']             = $row['rol'];
            $_SESSION['nombre_completo'] = $row['nombre_completo'];
            $_SESSION['sucursal']        = $row['sucursal'];

            header('Location: dashboard.php');
            exit;
        }

        // Mensaje genérico: no revelar si falló el usuario o la clave.
        $error ??= 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acceso · SIA RUBICAM</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --rb-blue-deep: #082d42;
        --rb-blue: #0b3954;
        --rb-green: #065f46;
        --rb-green-light: #65a30d;
        --rb-orange: #f97316;
    }

    * { font-family: 'Inter', system-ui, sans-serif; }

    body {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            radial-gradient(circle at 15% 20%, rgba(101,163,10,0.25), transparent 45%),
            radial-gradient(circle at 85% 80%, rgba(249,115,22,0.15), transparent 40%),
            linear-gradient(135deg, var(--rb-blue-deep) 0%, var(--rb-blue) 55%, var(--rb-green) 130%);
        padding: 24px;
    }

    .login-card {
        width: 100%;
        max-width: 880px;
        background: #ffffff;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 30px 60px rgba(3, 15, 26, 0.45);
        display: grid;
        grid-template-columns: 1.1fr 0.9fr;
        min-height: 480px;
    }

    @media (max-width: 767px) {
        .login-card { grid-template-columns: 1fr; }
        .login-brand { order: -1; min-height: 200px; }
    }

    .login-form-side {
        padding: 56px 48px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .eyebrow {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.14em;
        color: var(--rb-green);
        text-transform: uppercase;
        margin-bottom: 6px;
    }

    .login-title {
        font-size: 26px;
        font-weight: 800;
        color: var(--rb-blue-deep);
        margin-bottom: 4px;
    }

    .login-sub {
        font-size: 13.5px;
        color: #64748b;
        margin-bottom: 28px;
    }

    .form-label {
        font-size: 12.5px;
        font-weight: 700;
        color: var(--rb-blue-deep);
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    .form-control {
        border-radius: 10px;
        padding: 11px 14px;
        border: 1.5px solid #e2e8f0;
        font-size: 14.5px;
    }

    .form-control:focus {
        border-color: var(--rb-green-light);
        box-shadow: 0 0 0 3px rgba(101, 163, 10, 0.15);
    }

    .btn-access {
        margin-top: 8px;
        padding: 12px;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        font-size: 14.5px;
        letter-spacing: 0.02em;
        color: #ffffff;
        background: linear-gradient(90deg, var(--rb-blue-deep), var(--rb-green));
        transition: filter 0.15s ease, transform 0.15s ease;
    }

    .btn-access:hover { filter: brightness(1.08); transform: translateY(-1px); color: #fff; }

    .login-brand {
        background:
            radial-gradient(circle at 30% 20%, rgba(255,255,255,0.10), transparent 55%),
            linear-gradient(160deg, var(--rb-blue-deep) 0%, var(--rb-green) 100%);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 32px;
        text-align: center;
        color: #fff;
        position: relative;
    }

    .brand-mark {
        width: 84px;
        height: 84px;
        margin-bottom: 18px;
        filter: drop-shadow(0 8px 18px rgba(0,0,0,0.35));
    }

    .brand-sia {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.18em;
        opacity: 0.75;
        text-transform: uppercase;
        margin-bottom: 6px;
    }

    .brand-name {
        font-size: 22px;
        font-weight: 800;
        line-height: 1.25;
        margin-bottom: 10px;
    }

    .brand-tag {
        font-size: 12.5px;
        opacity: 0.8;
        max-width: 220px;
        line-height: 1.5;
    }

    .alert-login {
        border-radius: 10px;
        font-size: 13.5px;
        padding: 10px 14px;
    }
</style>
</head>
<body>

<div class="login-card">
    <div class="login-form-side">
        <div class="eyebrow">Panel operativo</div>
        <div class="login-title">Ingreso al sistema</div>
        <div class="login-sub">Acceda con sus credenciales corporativas para gestionar órdenes de operación.</div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-login" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" novalidate>
            <div class="mb-3">
                <label class="form-label" for="usuario">Usuario</label>
                <input type="text" class="form-control" id="usuario" name="usuario" required autofocus
                       value="<?= h(is_string($_POST['usuario'] ?? null) ? $_POST['usuario'] : '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-access w-100">Acceder</button>
        </form>
    </div>

    <div class="login-brand">
        <svg class="brand-mark" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="50" r="48" fill="rgba(255,255,255,0.08)" stroke="rgba(255,255,255,0.35)" stroke-width="1.5"/>
            <circle cx="50" cy="50" r="34" fill="none" stroke="#f97316" stroke-width="2.5"/>
            <path d="M34 56L46 44L58 52L68 38" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="68" cy="38" r="3.5" fill="#f97316"/>
        </svg>
        <div class="brand-sia">SIA · Nivel 2</div>
        <div class="brand-name">Agencia de Aduanas<br>RUBICAM S.A.S.</div>
        <div class="brand-tag">Gestión de órdenes de operación, trámites y comercio exterior.</div>
    </div>
</div>

</body>
</html>
