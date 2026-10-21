<?php

/**
 * src/Infraestructura/ClienteN8n.php
 * -----------------------------------------------------------------------
 * Envía las alertas de cambio de estado al webhook de n8n (cURL). Nunca lanza
 * excepciones: la base de datos es la fuente de verdad, así que un fallo de
 * n8n se informa al operador pero no deshace el cambio.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Infraestructura;

final class ClienteN8n
{
    private const TIEMPO_CONEXION_SEGUNDOS = 4;
    private const CURLE_SSL_CERT_PROBLEM = 60;
    private const CURLE_SSL_CACERT_BADFILE = 77;

    public function __construct(
        private readonly string $url,
        private readonly string $secreto,
        private readonly string $bundleCa,
        private readonly int $tiempoMaximoSegundos = 8
    ) {
    }

    /**
     * @param array<string, mixed> $carga
     * @return array{enviado: bool, http_code: ?int, error: ?string}
     */
    public function enviar(array $carga): array
    {
        if (!filter_var($this->url, FILTER_VALIDATE_URL)) {
            return self::fallo(null, 'N8N_WEBHOOK_URL no está configurada.');
        }
        if (!function_exists('curl_init')) {
            return self::fallo(null, 'La extensión cURL de PHP no está habilitada (php.ini → extension=curl).');
        }

        $curl = curl_init($this->url);
        curl_setopt_array($curl, $this->opciones($carga));

        $respuesta = curl_exec($curl);
        $errno     = curl_errno($curl);
        $detalle   = curl_error($curl);
        $http      = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        unset($curl);

        if ($respuesta === false || $errno !== 0) {
            error_log("[rubicam-panel] cURL n8n ({$errno}): {$detalle}");

            return self::fallo(null, self::mensajeDeCurl($errno, $detalle));
        }

        if ($http >= 200 && $http < 300) {
            return ['enviado' => true, 'http_code' => $http, 'error' => null];
        }

        error_log("[rubicam-panel] n8n respondió HTTP {$http}: " . substr((string) $respuesta, 0, 500));

        return self::fallo($http, self::mensajeDeHttp($http));
    }

    public static function mensajeDeCurl(int $errno, string $detalle): string
    {
        return match ($errno) {
            CURLE_OPERATION_TIMEDOUT => 'n8n no respondió a tiempo.',
            CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT => 'no hay conexión con el servidor de n8n.',
            self::CURLE_SSL_CERT_PROBLEM, self::CURLE_SSL_CACERT_BADFILE
                => 'falló la verificación SSL; configure curl.cainfo en php.ini o N8N_CA_BUNDLE.',
            default => 'error de conexión (' . $detalle . ').',
        };
    }

    public static function mensajeDeHttp(int $codigo): string
    {
        return match (true) {
            $codigo === 404 => 'n8n respondió 404 (¿el workflow está activo y la URL es /webhook/ '
                . 'y no /webhook-test/?).',
            $codigo === 401, $codigo === 403 => "n8n rechazó la autenticación (HTTP {$codigo}).",
            default => "n8n respondió con código HTTP {$codigo}.",
        };
    }

    /**
     * @param array<string, mixed> $carga
     * @return array<int, mixed>
     */
    private function opciones(array $carga): array
    {
        $cabeceras = ['Content-Type: application/json; charset=utf-8', 'Accept: application/json'];
        if ($this->secreto !== '') {
            $cabeceras[] = 'X-Rubicam-Token: ' . $this->secreto;
        }

        $opciones = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(
                $carga,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            ),
            CURLOPT_HTTPHEADER     => $cabeceras,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIEMPO_CONEXION_SEGUNDOS,
            CURLOPT_TIMEOUT        => $this->tiempoMaximoSegundos,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'Rubicam-Panel/2.0',
        ];
        if ($this->bundleCa !== '' && is_file($this->bundleCa)) {
            $opciones[CURLOPT_CAINFO] = $this->bundleCa;
        }

        return $opciones;
    }

    /**
     * @return array{enviado: bool, http_code: ?int, error: ?string}
     */
    private static function fallo(?int $http, string $mensaje): array
    {
        return ['enviado' => false, 'http_code' => $http, 'error' => $mensaje];
    }
}
