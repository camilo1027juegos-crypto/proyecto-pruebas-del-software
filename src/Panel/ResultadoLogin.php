<?php

/**
 * src/Panel/ResultadoLogin.php — Resultado de un intento de inicio de sesión.
 */

declare(strict_types=1);

namespace Rubicam\Panel;

final class ResultadoLogin
{
    public const OK = 'ok';
    public const VACIO = 'vacio';
    public const CREDENCIALES = 'credenciales';
    public const BLOQUEADO = 'bloqueado';

    private const MENSAJES = [
        self::VACIO        => 'Por favor ingrese usuario y contraseña.',
        // Genérico a propósito: no revela si falló el usuario o la clave.
        self::CREDENCIALES => 'Usuario o contraseña incorrectos.',
        self::BLOQUEADO    => 'Demasiados intentos fallidos. Espere 15 minutos e intente de nuevo.',
    ];

    /**
     * @param array<string, mixed>|null $usuario fila del usuario solo cuando el estado es OK
     */
    public function __construct(private readonly string $estado, private readonly ?array $usuario = null)
    {
    }

    public function estado(): string
    {
        return $this->estado;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function usuario(): ?array
    {
        return $this->usuario;
    }

    /** Mensaje para mostrar al visitante (vacío cuando el inicio de sesión fue correcto). */
    public function mensaje(): string
    {
        return self::MENSAJES[$this->estado] ?? '';
    }
}
