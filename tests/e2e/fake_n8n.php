<?php

/**
 * tests/e2e/fake_n8n.php
 * -----------------------------------------------------------------------
 * Servidor "falso" de n8n para las pruebas E2E: acepta el POST del webhook,
 * guarda el JSON recibido (una línea por evento) en el archivo indicado por
 * la variable de entorno N8N_LOG_E2E y responde 200.
 *
 * Se inicia con:  php -S 127.0.0.1:8098 tests/e2e/fake_n8n.php
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

$rutaLog = getenv('N8N_LOG_E2E');
$cuerpo = (string) file_get_contents('php://input');

if (is_string($rutaLog) && $rutaLog !== '' && $cuerpo !== '') {
    file_put_contents($rutaLog, $cuerpo . "\n", FILE_APPEND | LOCK_EX);
}

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
