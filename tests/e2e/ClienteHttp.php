<?php

/**
 * tests/e2e/ClienteHttp.php
 * -----------------------------------------------------------------------
 * Cliente HTTP mínimo (cURL + cookie jar) para las pruebas de extremo a
 * extremo. Mantiene la sesión entre peticiones y NO sigue redirecciones,
 * para poder comprobar los códigos 302/303 del panel.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Tests\E2e;

use CURLFile;
use RuntimeException;

final class ClienteHttp
{
    private string $archivoCookies;

    public function __construct(private readonly string $urlBase)
    {
        $archivo = tempnam(sys_get_temp_dir(), 'rubicam_e2e_');
        if ($archivo === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal de cookies.');
        }
        $this->archivoCookies = $archivo;
    }

    public function __destruct()
    {
        if (is_file($this->archivoCookies)) {
            unlink($this->archivoCookies);
        }
    }

    /** @return array{estado:int, cabeceras:array<string,string>, cuerpo:string, json:?array} */
    public function get(string $ruta, array $cabeceras = []): array
    {
        return $this->enviar('GET', $ruta, null, $cabeceras);
    }

    /** @return array{estado:int, cabeceras:array<string,string>, cuerpo:string, json:?array} */
    public function postFormulario(string $ruta, array $campos, array $cabeceras = []): array
    {
        return $this->enviar('POST', $ruta, http_build_query($campos), $cabeceras);
    }

    /** @return array{estado:int, cabeceras:array<string,string>, cuerpo:string, json:?array} */
    public function postJson(string $ruta, array $datos, array $cabeceras = []): array
    {
        $cabeceras[] = 'Content-Type: application/json';

        return $this->enviar('POST', $ruta, json_encode($datos, JSON_UNESCAPED_UNICODE), $cabeceras);
    }

    /**
     * @param array<string,string|CURLFile> $campos
     * @return array{estado:int, cabeceras:array<string,string>, cuerpo:string, json:?array}
     */
    public function postMultipart(string $ruta, array $campos, array $cabeceras = []): array
    {
        return $this->enviar('POST', $ruta, $campos, $cabeceras);
    }

    /**
     * @param string|array<string,mixed>|null $cuerpo
     * @param list<string> $cabeceras
     * @return array{estado:int, cabeceras:array<string,string>, cuerpo:string, json:?array}
     */
    private function enviar(string $metodo, string $ruta, string|array|null $cuerpo, array $cabeceras): array
    {
        $cabecerasRespuesta = [];
        $curl = curl_init($this->urlBase . $ruta);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_COOKIEFILE     => $this->archivoCookies,
            CURLOPT_COOKIEJAR      => $this->archivoCookies,
            CURLOPT_HTTPHEADER     => $cabeceras,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $linea) use (&$cabecerasRespuesta): int {
                $partes = explode(':', $linea, 2);
                if (count($partes) === 2) {
                    $cabecerasRespuesta[strtolower(trim($partes[0]))] = trim($partes[1]);
                }

                return strlen($linea);
            },
        ]);
        if ($cuerpo !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $cuerpo);
        }

        $texto = curl_exec($curl);
        if ($texto === false) {
            $error = curl_error($curl);
            throw new RuntimeException("Fallo de red en {$metodo} {$ruta}: {$error}");
        }
        $estado = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        $json = json_decode((string) $texto, true);

        return [
            'estado'    => $estado,
            'cabeceras' => $cabecerasRespuesta,
            'cuerpo'    => (string) $texto,
            'json'      => is_array($json) ? $json : null,
        ];
    }
}
