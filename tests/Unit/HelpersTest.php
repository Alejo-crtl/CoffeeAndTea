<?php
/**
 * Pruebas unitarias de funciones puras y validaciones.
 */
declare(strict_types=1);

final class HelpersTest extends CasoBase
{
    public function testMonedaFormateaPesos(): void
    {
        $this->assertSame('$12.500', moneda(12500));
        $this->assertSame('$0', moneda(null));
        $this->assertSame('$1.235', moneda(1234.5));
    }

    public function testEsVerdaderoAceptaValoresDePostgres(): void
    {
        $this->assertTrue(esVerdadero(true));
        $this->assertTrue(esVerdadero('t'));
        $this->assertTrue(esVerdadero(1));
        $this->assertTrue(esVerdadero('true'));
        $this->assertFalse(esVerdadero(false));
        $this->assertFalse(esVerdadero('f'));
        $this->assertFalse(esVerdadero('0'));
    }

    public function testSlugificarQuitaAcentosYEspacios(): void
    {
        $this->assertSame('cafe-con-leche', slugificar('Café con leche'));
        $this->assertSame('te-negro', slugificar('Té  negro'));
        $this->assertSame('postre-dia', slugificar('Postre / día'));
    }

    public function testRutaWebImagenProductoSoloAceptaLaCarpetaSegura(): void
    {
        $this->assertSame('media/productos/cafe-express.jpg', rutaWebImagenProducto('media/productos/cafe-express.jpg'));
        $this->assertSame('media/productos/cafe-express.jpg', rutaWebImagenProducto('/media/productos/cafe-express.jpg'));
        $this->assertSame('', rutaWebImagenProducto(''));
        $this->assertSame('', rutaWebImagenProducto('../.env'));
        $this->assertSame('', rutaWebImagenProducto('media/productos/../../.env'));
        $this->assertSame('', rutaWebImagenProducto('https://ejemplo.com/foto.jpg'));
        $this->assertSame('', rutaWebImagenProducto('media/productos/foto.php'));
    }

    public function testImagenProductoDisponibleVerificaElArchivo(): void
    {
        $this->assertTrue(imagenProductoDisponible('media/productos/cafe-express.jpg'));
        $this->assertFalse(imagenProductoDisponible('media/productos/no-existe.jpg'));
        $this->assertFalse(imagenProductoDisponible(''));
    }

    public function testValidarPasswordExigeComplejidad(): void
    {
        $this->assertCount(0, validarPassword('Admin123!'));
        $this->assertGreaterThanOrEqual(1, count(validarPassword('corta1!')));
        $this->assertCount(1, validarPassword('sinmayusculas1!'));
        $this->assertCount(1, validarPassword('SINMINUSCULAS1!'));
        $this->assertCount(1, validarPassword('SinNumeros!'));
    }

    public function testGenerarPasswordTemporalCumpleRequisitos(): void
    {
        $password = generarPasswordTemporal(14);
        $this->assertSame(14, mb_strlen($password));
        $this->assertCount(0, validarPassword($password));
        $this->assertMatchesRegularExpression('/[A-Z]/', $password);
        $this->assertMatchesRegularExpression('/[a-z]/', $password);
        $this->assertMatchesRegularExpression('/[0-9]/', $password);
    }

    public function testTiempoPreparacionConProductosCapacidadYCola(): void
    {
        $items = [
            ['cantidad' => 2, 'tiempo_preparacion' => 4],
            ['cantidad' => 1, 'tiempo_preparacion' => 3],
        ];

        // (2×4 + 1×3) = 11; 11/2 = 6; buffer 15% = 1; cola 6/2 = 3; total 10.
        $this->assertSame(10, calcularTiempoPreparacion($items, 6, 2, 15, 20));

        // Sin tiempos configurados usa el respaldo: 2 × 10 = 20; 20/2 = 10; buffer 2.
        $this->assertSame(12, calcularTiempoPreparacion(
            [['cantidad' => 2, 'tiempo_preparacion' => 0]],
            0,
            2,
            15,
            10
        ));

        // Con una sola estación: 11 + 2 de buffer + 6 de cola = 19.
        $this->assertSame(19, calcularTiempoPreparacion($items, 6, 1, 15, 20));

        // Nunca por debajo de 5 minutos.
        $this->assertSame(5, calcularTiempoPreparacion([], 0, 2, 15, 20));
    }

    public function testTransicionesPermitidas(): void
    {
        $this->assertSame(['EN_PREPARACION'], transicionesPermitidas('PAGADO'));
        $this->assertSame(['LISTO'], transicionesPermitidas('EN_PREPARACION'));
        $this->assertSame(['ENTREGADO'], transicionesPermitidas('LISTO'));
        $this->assertSame([], transicionesPermitidas('ENTREGADO'));
        $this->assertSame([], transicionesPermitidas('CANCELADO'));
        $this->assertSame([], transicionesPermitidas('PENDIENTE'));
    }

    public function testEtiquetaEstado(): void
    {
        $this->assertSame('Pendiente de pago', etiquetaEstado('PENDIENTE'));
        $this->assertSame('En preparación', etiquetaEstado('EN_PREPARACION'));
        $this->assertSame('Entregado', etiquetaEstado('ENTREGADO'));
        $this->assertSame('Cancelado', etiquetaEstado('CANCELADO'));
    }
}
