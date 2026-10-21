<?php

/**
 * src/Panel/Autenticador.php
 * -----------------------------------------------------------------------
 * Verifica las credenciales del panel con tres defensas (MR-006):
 *
 *  1. Límite de intentos fallidos por usuario+origen y por origen: tras
 *     FALLOS_POR_USUARIO errores seguidos se bloquea (incluso la clave
 *     correcta) durante VENTANA_SEGUNDOS, y mientras dura el bloqueo ni
 *     siquiera se comprueba la clave.
 *  2. Un usuario inexistente también ejecuta password_verify (contra un
 *     hash de relleno) para que el tiempo de respuesta no delate qué
 *     usuarios existen.
 *  3. Mensaje único para «usuario» o «clave» incorrectos.
 *
 * La búsqueda del usuario y la verificación de la clave se inyectan para
 * poder probar todo sin base de datos.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Rubicam\Panel;

use Closure;
use Rubicam\Seguridad\LimitadorTasa;

final class Autenticador
{
    public const FALLOS_POR_USUARIO = 5;
    public const FALLOS_POR_IP = 20;
    public const VENTANA_SEGUNDOS = 900; // 15 minutos

    /** Hash bcrypt de una clave que nadie conoce: solo sirve para igualar el tiempo de respuesta. */
    private const HASH_DE_RELLENO = '$2y$10$1XzJu8kAfV1W3ntsJ18UMO/eBETk/SL13JPJ60yOuYIQmtJMq2dNi';

    private const CUBO_USUARIO = 'login_usuario';
    private const CUBO_IP = 'login_ip';

    private Closure $verificarClave;

    /**
     * @param Closure(string): ?array<string, mixed>   $buscarUsuario  fila del usuario activo o null
     * @param Closure(string, string): bool|null        $verificarClave por defecto password_verify
     */
    public function __construct(
        private readonly Closure $buscarUsuario,
        private readonly LimitadorTasa $porUsuario,
        private readonly LimitadorTasa $porIp,
        ?Closure $verificarClave = null
    ) {
        $this->verificarClave = $verificarClave
            ?? static fn (string $clave, string $hash): bool => password_verify($clave, $hash);
    }

    public function autenticar(string $usuario, string $clave, string $direccionIp): ResultadoLogin
    {
        $usuario = trim($usuario);
        if ($usuario === '' || $clave === '') {
            return new ResultadoLogin(ResultadoLogin::VACIO);
        }

        $claveDeUsuario = strtolower($usuario) . '|' . $direccionIp;
        $bloqueado = $this->porUsuario->estaBloqueado(self::CUBO_USUARIO, $claveDeUsuario)
            || $this->porIp->estaBloqueado(self::CUBO_IP, $direccionIp);
        if ($bloqueado) {
            return new ResultadoLogin(ResultadoLogin::BLOQUEADO);
        }

        $fila = ($this->buscarUsuario)($usuario);
        $hash = is_array($fila) ? (string) $fila['password_hash'] : self::HASH_DE_RELLENO;
        $correcta = ($this->verificarClave)($clave, $hash);

        if ($fila !== null && $correcta) {
            $this->porUsuario->reiniciar(self::CUBO_USUARIO, $claveDeUsuario);

            return new ResultadoLogin(ResultadoLogin::OK, $fila);
        }

        $this->porUsuario->registrar(self::CUBO_USUARIO, $claveDeUsuario);
        $this->porIp->registrar(self::CUBO_IP, $direccionIp);

        return new ResultadoLogin(ResultadoLogin::CREDENCIALES);
    }
}
