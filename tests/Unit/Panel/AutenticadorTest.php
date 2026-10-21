<?php

/**
 * MR-006 (mantenimiento correctivo/preventivo) — El inicio de sesión no limitaba los intentos fallidos
 * (fuerza bruta de contraseñas) y respondía más rápido con un usuario inexistente que con uno real
 * (enumeración de usuarios por tiempo de respuesta).
 */

declare(strict_types=1);

namespace Rubicam\Tests\Unit\Panel;

use PHPUnit\Framework\TestCase;
use Rubicam\Panel\Autenticador;
use Rubicam\Panel\ResultadoLogin;
use Rubicam\Seguridad\LimitadorTasa;

/**
 * @group MR-006
 * @group regresion
 */
final class AutenticadorTest extends TestCase
{
    private const CLAVE = 'Clave-Correcta-2026!';
    private const IP = '10.0.0.7';

    private string $directorio;
    private int $verificaciones = 0;
    private int $ahora = 2_000_000;

    protected function setUp(): void
    {
        $this->directorio = sys_get_temp_dir() . '/rubicam_login_' . bin2hex(random_bytes(4));
        $this->verificaciones = 0;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directorio . '/*') ?: [] as $archivo) {
            unlink($archivo);
        }
        if (is_dir($this->directorio)) {
            rmdir($this->directorio);
        }
    }

    private function autenticador(): Autenticador
    {
        $reloj = fn (): int => $this->ahora;
        $usuarios = [
            'ana' => [
                'id'              => 1,
                'usuario'         => 'ana',
                'password_hash'   => password_hash(self::CLAVE, PASSWORD_BCRYPT, ['cost' => 4]),
                'rol'             => 'operador',
                'nombre_completo' => 'Ana Gómez',
                'sucursal'        => 'Bogotá',
            ],
        ];

        return new Autenticador(
            static fn (string $usuario): ?array => $usuarios[strtolower($usuario)] ?? null,
            new LimitadorTasa(
                $this->directorio,
                Autenticador::FALLOS_POR_USUARIO,
                Autenticador::VENTANA_SEGUNDOS,
                $reloj
            ),
            new LimitadorTasa(
                $this->directorio,
                Autenticador::FALLOS_POR_IP,
                Autenticador::VENTANA_SEGUNDOS,
                $reloj
            ),
            function (string $clave, string $hash): bool {
                $this->verificaciones++;

                return password_verify($clave, $hash);
            }
        );
    }

    public function testCredencialesCorrectasAbrenSesion(): void
    {
        $resultado = $this->autenticador()->autenticar('ana', self::CLAVE, self::IP);

        $this->assertSame(ResultadoLogin::OK, $resultado->estado());
        $this->assertSame('ana', $resultado->usuario()['usuario'] ?? null);
    }

    public function testCamposVaciosNoCuentanComoIntentoFallido(): void
    {
        $autenticador = $this->autenticador();

        for ($i = 0; $i < 10; $i++) {
            $this->assertSame(ResultadoLogin::VACIO, $autenticador->autenticar('', '', self::IP)->estado());
        }
        $this->assertSame(ResultadoLogin::OK, $autenticador->autenticar('ana', self::CLAVE, self::IP)->estado());
    }

    public function testClaveIncorrectaEsRechazadaConMensajeGenerico(): void
    {
        $resultado = $this->autenticador()->autenticar('ana', 'otra', self::IP);

        $this->assertSame(ResultadoLogin::CREDENCIALES, $resultado->estado());
        $this->assertNull($resultado->usuario());
        $this->assertSame('Usuario o contraseña incorrectos.', $resultado->mensaje());
    }

    public function testUnUsuarioInexistenteRecibeElMismoMensajeQueUnaClaveIncorrecta(): void
    {
        $autenticador = $this->autenticador();

        $this->assertSame(
            $autenticador->autenticar('ana', 'mala', self::IP)->mensaje(),
            $autenticador->autenticar('fantasma', 'mala', self::IP)->mensaje()
        );
    }

    public function testConUsuarioInexistenteIgualSeVerificaUnHashParaIgualarLosTiempos(): void
    {
        $this->autenticador()->autenticar('fantasma', 'cualquiera', self::IP);

        $this->assertSame(1, $this->verificaciones, 'Debe ejecutar password_verify aunque el usuario no exista.');
    }

    public function testTrasLosFallosPermitidosSeBloqueaIncluidoLaClaveCorrecta(): void
    {
        $autenticador = $this->autenticador();
        for ($i = 0; $i < Autenticador::FALLOS_POR_USUARIO; $i++) {
            $fallo = $autenticador->autenticar('ana', 'mala', self::IP);
            $this->assertSame(ResultadoLogin::CREDENCIALES, $fallo->estado());
        }

        $resultado = $autenticador->autenticar('ana', self::CLAVE, self::IP);

        $this->assertSame(ResultadoLogin::BLOQUEADO, $resultado->estado());
        $this->assertStringContainsString('Demasiados intentos', $resultado->mensaje());
    }

    public function testElBloqueoNoVerificaLaClaveAsiNoPermiteAdivinarlaDuranteElBloqueo(): void
    {
        $autenticador = $this->autenticador();
        for ($i = 0; $i < Autenticador::FALLOS_POR_USUARIO; $i++) {
            $autenticador->autenticar('ana', 'mala', self::IP);
        }
        $antes = $this->verificaciones;

        $autenticador->autenticar('ana', self::CLAVE, self::IP);

        $this->assertSame($antes, $this->verificaciones);
    }

    public function testElBloqueoExpiraDespuesDeLaVentana(): void
    {
        $autenticador = $this->autenticador();
        for ($i = 0; $i < Autenticador::FALLOS_POR_USUARIO; $i++) {
            $autenticador->autenticar('ana', 'mala', self::IP);
        }

        $this->ahora += Autenticador::VENTANA_SEGUNDOS + 1;

        $this->assertSame(ResultadoLogin::OK, $autenticador->autenticar('ana', self::CLAVE, self::IP)->estado());
    }

    public function testUnInicioCorrectoReiniciaElContadorDelUsuario(): void
    {
        $autenticador = $this->autenticador();
        for ($i = 0; $i < Autenticador::FALLOS_POR_USUARIO - 1; $i++) {
            $autenticador->autenticar('ana', 'mala', self::IP);
        }
        $this->assertSame(ResultadoLogin::OK, $autenticador->autenticar('ana', self::CLAVE, self::IP)->estado());

        for ($i = 0; $i < Autenticador::FALLOS_POR_USUARIO - 1; $i++) {
            $fallo = $autenticador->autenticar('ana', 'mala', self::IP);
            $this->assertSame(ResultadoLogin::CREDENCIALES, $fallo->estado());
        }
    }

    public function testElContadorEsPorUsuarioYPorOrigenAsiUnAtacanteNoBloqueaAOtraPersona(): void
    {
        $autenticador = $this->autenticador();
        for ($i = 0; $i < Autenticador::FALLOS_POR_USUARIO; $i++) {
            $autenticador->autenticar('ana', 'mala', '203.0.113.9');
        }

        $this->assertSame(
            ResultadoLogin::OK,
            $autenticador->autenticar('ana', self::CLAVE, self::IP)->estado(),
            'Ana desde su propia IP no debe quedar bloqueada por los fallos de otra IP.'
        );
    }

    public function testUnaMismaIpProbandoMuchosUsuariosTambienSeBloquea(): void
    {
        $autenticador = $this->autenticador();
        for ($i = 0; $i < Autenticador::FALLOS_POR_IP; $i++) {
            $autenticador->autenticar('usuario' . $i, 'mala', '198.51.100.4');
        }

        $this->assertSame(
            ResultadoLogin::BLOQUEADO,
            $autenticador->autenticar('ana', self::CLAVE, '198.51.100.4')->estado()
        );
    }
}
