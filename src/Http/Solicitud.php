<?php

/**
 * src/Http/Solicitud.php
 * -----------------------------------------------------------------------
 * La petición HTTP actual como objeto de solo lectura. El código de negocio
 * NO toca $_GET/$_POST/$_SERVER directamente: recibe una Solicitud, lo que
 * permite probarlo con datos falsos y valida los tipos en un solo lugar
 * (p. ej. ?action[]=x nunca llega como texto).
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Http;

use Closure;

final class Solicitud
{
    /**
     * @param array<string, mixed> $servidor   normalmente $_SERVER
     * @param array<string, mixed> $consulta   normalmente $_GET
     * @param array<string, mixed> $formulario normalmente $_POST
     * @param array<string, mixed> $archivos   normalmente $_FILES
     * @param Closure():string     $lectorCuerpo devuelve el cuerpo crudo (php://input)
     */
    public function __construct(
        private readonly array $servidor,
        private readonly array $consulta,
        private readonly array $formulario,
        private readonly array $archivos,
        private readonly Closure $lectorCuerpo
    ) {
    }

    public static function desdeGlobales(): self
    {
        return new self(
            $_SERVER,
            $_GET,
            $_POST,
            $_FILES,
            static fn (): string => (string) file_get_contents('php://input')
        );
    }

    public function esPost(): bool
    {
        return strtoupper((string) ($this->servidor['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    /** Parámetro de la URL, solo si es texto. */
    public function consulta(string $clave): ?string
    {
        $valor = $this->consulta[$clave] ?? null;

        return is_string($valor) ? $valor : null;
    }

    /** Campo de un formulario, solo si es texto. */
    public function formulario(string $clave): ?string
    {
        $valor = $this->formulario[$clave] ?? null;

        return is_string($valor) ? $valor : null;
    }

    /**
     * true si el navegador envió un cuerpo pero PHP lo descartó: ocurre cuando se supera post_max_size
     * (PHP vacía $_POST y $_FILES sin avisar).
     */
    public function cuerpoDescartadoPorElServidor(): bool
    {
        return (int) ($this->servidor['CONTENT_LENGTH'] ?? 0) > 0 && $this->formulario === [] && $this->archivos === [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function archivo(string $clave): ?array
    {
        $archivo = $this->archivos[$clave] ?? null;

        return is_array($archivo) ? $archivo : null;
    }

    /** Cabecera HTTP (p. ej. «X-CSRF-Token») o null si no vino. */
    public function cabecera(string $nombre): ?string
    {
        $clave = 'HTTP_' . strtoupper(str_replace('-', '_', $nombre));
        $valor = $this->servidor[$clave] ?? null;

        return is_string($valor) ? $valor : null;
    }

    public function direccionIp(): string
    {
        $direccion = $this->servidor['REMOTE_ADDR'] ?? null;

        return is_string($direccion) && $direccion !== '' ? $direccion : 'desconocida';
    }

    /**
     * @return array<mixed>
     * @throws ErrorHttp si el cuerpo no es un JSON válido (ver SolicitudJson)
     */
    public function json(): array
    {
        return SolicitudJson::decodificar(($this->lectorCuerpo)());
    }
}
