<?php

/**
 * src/Http/Respuesta.php
 * -----------------------------------------------------------------------
 * Respuesta HTTP como OBJETO (código, cabeceras, cuerpo). Construirla no
 * imprime nada: eso permite probarla sin servidor web. Solo enviar() escribe
 * en la salida.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Http;

final class Respuesta
{
    private const FLAGS_JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * @param array<string, string> $cabeceras
     */
    private function __construct(
        private readonly int $codigoHttp,
        private readonly array $cabeceras,
        private readonly string $cuerpo,
        private readonly ?string $rutaArchivo = null
    ) {
    }

    /**
     * Respuesta JSON uniforme: {"status": "success"|"error", "message": "...", ...extra}.
     *
     * @param array<string, mixed> $extra
     */
    public static function json(string $estado, string $mensaje, int $codigoHttp = 200, array $extra = []): self
    {
        return self::datos(['status' => $estado, 'message' => $mensaje] + $extra, $codigoHttp);
    }

    /**
     * Respuesta JSON con un cuerpo libre (el cotizador público responde {ok, message}).
     *
     * @param array<string, mixed> $cuerpo
     */
    public static function datos(array $cuerpo, int $codigoHttp = 200): self
    {
        return new self(
            $codigoHttp,
            [
                'Content-Type'           => 'application/json; charset=utf-8',
                'Cache-Control'          => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
            (string) json_encode($cuerpo, self::FLAGS_JSON)
        );
    }

    /**
     * Contenido generado al vuelo (CSV, JSON...) que el navegador descarga como adjunto.
     */
    public static function descarga(string $contenido, string $tipoMime, string $nombreArchivo): self
    {
        $nombre = str_replace(['"', "\r", "\n", '\\', '/'], '', $nombreArchivo);

        return new self(
            200,
            [
                'Content-Type'           => $tipoMime,
                'Content-Disposition'    => 'attachment; filename="' . $nombre . '"',
                'Cache-Control'          => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $contenido
        );
    }

    public static function redireccion(string $url, int $codigoHttp = 302): self
    {
        return new self($codigoHttp, ['Location' => $url], '');
    }

    /**
     * @param array<string, string> $cabeceras
     */
    public static function html(string $html, int $codigoHttp = 200, array $cabeceras = []): self
    {
        return new self($codigoHttp, ['Content-Type' => 'text/html; charset=utf-8'] + $cabeceras, $html);
    }

    /**
     * Descarga de un archivo ya validado por quien llama (se envía con readfile).
     *
     * @param array<string, string> $cabeceras
     */
    public static function archivo(string $ruta, array $cabeceras): self
    {
        return new self(200, $cabeceras + ['Content-Length' => (string) filesize($ruta)], '', $ruta);
    }

    public function codigoHttp(): int
    {
        return $this->codigoHttp;
    }

    /**
     * @return array<string, string>
     */
    public function cabeceras(): array
    {
        return $this->cabeceras;
    }

    public function cuerpo(): string
    {
        return $this->cuerpo;
    }

    /** Escribe la respuesta en la salida. Solo la llaman los puntos de entrada (nunca las clases de negocio). */
    public function enviar(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($this->codigoHttp);
            foreach ($this->cabeceras as $nombre => $valor) {
                header($nombre . ': ' . $valor);
            }
        }
        if ($this->rutaArchivo !== null) {
            readfile($this->rutaArchivo);

            return;
        }
        echo $this->cuerpo;
    }
}
