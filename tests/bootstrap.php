<?php
/**
 * BLOQUE B12 · PRUEBAS Y QA
 * Qué hace: arranque de las pruebas, conexión a la base QA y mini framework
 *          de aserciones cuando no hay PHPUnit.
 * Seguridad: aborta si la base no termina en _qa o _test.
 */
declare(strict_types=1);

$raiz = dirname(__DIR__);

require_once $raiz . '/app/php/lib/bootstrap.php';
require_once $raiz . '/app/php/lib/pedidos.php';
require_once $raiz . '/app/php/lib/cuenta.php';
require_once $raiz . '/app/php/lib/password_reset.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $_SESSION = $_SESSION ?? [];
}

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';

/** Evita ejecutar pruebas contra una base que no sea de QA. */
$baseActual = (string) $conn->query('SELECT current_database()')->fetchColumn();
if (!preg_match('/(_qa|_test)$/i', $baseActual)) {
    fwrite(STDERR, "ABORTADO: las pruebas solo se ejecutan sobre una base *_qa o *_test (actual: {$baseActual}).\n");
    exit(1);
}

if (class_exists(\PHPUnit\Framework\TestCase::class)) {
    abstract class CasoBase extends \PHPUnit\Framework\TestCase
    {
    }
} else {
    class ErrorAsercion extends \RuntimeException
    {
    }

    /** Implementación mínima de las aserciones usadas por las pruebas. */
    abstract class CasoBase
    {
        public function assertSame(mixed $esperado, mixed $actual, string $mensaje = ''): void
        {
            if ($esperado !== $actual) {
                $this->fallar($mensaje, 'Se esperaba ' . $this->exportar($esperado) . ' y llegó ' . $this->exportar($actual));
            }
        }

        public function assertEquals(mixed $esperado, mixed $actual, string $mensaje = ''): void
        {
            if ($esperado != $actual) {
                $this->fallar($mensaje, 'Se esperaba ' . $this->exportar($esperado) . ' y llegó ' . $this->exportar($actual));
            }
        }

        public function assertTrue(mixed $actual, string $mensaje = ''): void
        {
            $this->assertSame(true, $actual, $mensaje);
        }

        public function assertFalse(mixed $actual, string $mensaje = ''): void
        {
            $this->assertSame(false, $actual, $mensaje);
        }

        public function assertNull(mixed $actual, string $mensaje = ''): void
        {
            $this->assertSame(null, $actual, $mensaje);
        }

        public function assertNotNull(mixed $actual, string $mensaje = ''): void
        {
            if ($actual === null) {
                $this->fallar($mensaje, 'Se recibió null');
            }
        }

        public function assertCount(int $esperado, array $actual, string $mensaje = ''): void
        {
            $this->assertSame($esperado, count($actual), $mensaje);
        }

        public function assertGreaterThan(float|int $limite, float|int $actual, string $mensaje = ''): void
        {
            if (!($actual > $limite)) {
                $this->fallar($mensaje, "Se esperaba un valor mayor que {$limite} y llegó {$actual}");
            }
        }

        public function assertGreaterThanOrEqual(float|int $limite, float|int $actual, string $mensaje = ''): void
        {
            if (!($actual >= $limite)) {
                $this->fallar($mensaje, "Se esperaba un valor mayor o igual que {$limite} y llegó {$actual}");
            }
        }

        public function assertLessThan(float|int $limite, float|int $actual, string $mensaje = ''): void
        {
            if (!($actual < $limite)) {
                $this->fallar($mensaje, "Se esperaba un valor menor que {$limite} y llegó {$actual}");
            }
        }

        public function assertStringContainsString(string $buscado, string $texto, string $mensaje = ''): void
        {
            if (!str_contains($texto, $buscado)) {
                $this->fallar($mensaje, "No se encontró \"{$buscado}\" en el texto");
            }
        }

        public function assertMatchesRegularExpression(string $patron, string $texto, string $mensaje = ''): void
        {
            if (preg_match($patron, $texto) !== 1) {
                $this->fallar($mensaje, "El texto no cumple el patrón {$patron}");
            }
        }

        public function fail(string $mensaje = ''): never
        {
            throw new ErrorAsercion($mensaje !== '' ? $mensaje : 'Fallo de aserción');
        }

        private function fallar(string $mensaje, string $detalle): never
        {
            throw new ErrorAsercion($mensaje !== '' ? $mensaje . ': ' . $detalle : $detalle);
        }

        private function exportar(mixed $valor): string
        {
            return is_scalar($valor) || $valor === null
                ? var_export($valor, true)
                : get_debug_type($valor);
        }
    }
}
