<?php
/**
 * BLOQUE B12 · PRUEBAS Y QA
 * Qué hace: pruebas de integración del flujo de pedidos, pagos,
 *          caja, stock y cancelaciones sobre PostgreSQL.
 */
declare(strict_types=1);

final class PedidosTest extends CasoBase
{
    public function testFlujoCompletoPagoTarjetaYCancelacion(): void
    {
        global $conn;

        $sufijo = strtoupper(bin2hex(random_bytes(4)));
        $correo = 'qa.' . strtolower($sufijo) . '@example.com';
        $catId = 0;
        $prodId = 0;
        $pedidoId = 0;
        $usuarioId = (int) $conn->query(
            "SELECT id FROM usuario WHERE rol = 'ADMIN' AND activo = TRUE ORDER BY id LIMIT 1"
        )->fetchColumn();

        $this->assertGreaterThan(0, $usuarioId, 'No hay un administrador activo para ejecutar la prueba.');

        try {
            $stmt = $conn->prepare('INSERT INTO categoria (nombre, descripcion) VALUES (:nombre, :descripcion) RETURNING id');
            $stmt->execute([':nombre' => 'Categoría QA ' . $sufijo, ':descripcion' => 'Temporal de pruebas']);
            $catId = (int) $stmt->fetchColumn();

            $stmt = $conn->prepare(
                'INSERT INTO producto (nombre, descripcion, precio, stock_actual, stock_minimo, categoria_id, tiempo_preparacion_min)
                 VALUES (:nombre, :descripcion, 1234.50, 5, 1, :categoria_id, 4)
                 RETURNING id'
            );
            $stmt->execute([
                ':nombre'       => 'Producto QA ' . $sufijo,
                ':descripcion'  => 'Temporal de pruebas',
                ':categoria_id' => $catId,
            ]);
            $prodId = (int) $stmt->fetchColumn();

            // El carrito no permite superar el stock disponible.
            $_SESSION['carrito'] = [];
            $rechazoStock = false;
            try {
                carritoAgregar($conn, $prodId, 6);
            } catch (ReglaNegocioException $e) {
                $rechazoStock = true;
            }
            $this->assertTrue($rechazoStock, 'Se debió rechazar una cantidad mayor al stock.');

            $_SESSION['carrito'] = [$prodId => 2];
            $resultado = crearPedido($conn, [
                'nombre'         => 'Cliente QA ' . $sufijo,
                'correo'         => $correo,
                'telefono'       => '3000000000',
                'observaciones'  => 'Pedido de prueba automatizada',
                'consentimiento' => true,
                'acepta_cancelacion' => true,
            ]);
            $pedidoId = (int) $resultado['id'];

            $this->assertMatchesRegularExpression('/^CAF-[A-Z2-9]{6}$/', $resultado['codigo']);
            $this->assertEquals(2469.00, $resultado['total']);
            $this->assertGreaterThanOrEqual(5, $resultado['tiempo_estimado']);

            $stmt = $conn->prepare('SELECT estado, total, metodo_pago FROM pedido WHERE id = :id');
            $stmt->execute([':id' => $pedidoId]);
            $pedido = $stmt->fetch();
            $this->assertSame('PENDIENTE', $pedido['estado']);
            $this->assertEquals(2469.00, $pedido['total']);
            $this->assertNull($pedido['metodo_pago']);

            // El stock no se descuenta al crear el pedido.
            $this->assertSame(5, $this->stock($conn, $prodId));

            $publico = obtenerPedidoPublico($conn, $resultado['codigo'], $resultado['token']);
            $this->assertNotNull($publico);
            $this->assertSame('PENDIENTE', $publico['estado']);
            $this->assertNull(obtenerPedidoPublico($conn, $resultado['codigo'], str_repeat('0', 48)));

            $pago = confirmarPago($conn, $pedidoId, $usuarioId, 'TARJETA', null, 'TRX-' . $sufijo);
            $this->assertEquals(2469.00, $pago['total']);
            $this->assertSame(3, $this->stock($conn, $prodId));

            $stmt = $conn->prepare("SELECT estado, metodo_pago FROM pedido WHERE id = :id");
            $stmt->execute([':id' => $pedidoId]);
            $pedido = $stmt->fetch();
            $this->assertSame('PAGADO', $pedido['estado']);
            $this->assertSame('TARJETA', $pedido['metodo_pago']);

            $stmt = $conn->prepare("SELECT id, metodo, referencia, estado FROM pago WHERE pedido_id = :id");
            $stmt->execute([':id' => $pedidoId]);
            $pagoFila = $stmt->fetch();
            $this->assertSame('CONFIRMADO', $pagoFila['estado']);
            $this->assertSame('TARJETA', $pagoFila['metodo']);
            $this->assertSame('TRX-' . $sufijo, $pagoFila['referencia']);

            // El pago con tarjeta no genera movimiento de caja.
            $stmt = $conn->prepare("SELECT count(*) FROM movimiento_caja WHERE related_type = 'pago' AND related_id = :id");
            $stmt->execute([':id' => (int) $pagoFila['id']]);
            $this->assertSame(0, (int) $stmt->fetchColumn());

            // No se puede saltar de PAGADO a ENTREGADO.
            $transicionInvalida = false;
            try {
                avanzarEstadoPedido($conn, $pedidoId, 'ENTREGADO', $usuarioId);
            } catch (ReglaNegocioException $e) {
                $transicionInvalida = true;
            }
            $this->assertTrue($transicionInvalida, 'Se debió rechazar la transición PAGADO -> ENTREGADO.');

            cancelarPedido(
                $conn,
                $pedidoId,
                $usuarioId,
                'Cancelación de prueba automatizada',
                'TARJETA',
                'REF-' . $sufijo
            );
            $this->assertSame(5, $this->stock($conn, $prodId));

            $stmt = $conn->prepare('SELECT estado, motivo_cancelacion FROM pedido WHERE id = :id');
            $stmt->execute([':id' => $pedidoId]);
            $pedido = $stmt->fetch();
            $this->assertSame('CANCELADO', $pedido['estado']);
            $this->assertSame('Cancelación de prueba automatizada', $pedido['motivo_cancelacion']);

            $stmt = $conn->prepare('SELECT estado, referencia_reembolso FROM pago WHERE pedido_id = :id');
            $stmt->execute([':id' => $pedidoId]);
            $pagoFila = $stmt->fetch();
            $this->assertSame('REEMBOLSADO', $pagoFila['estado']);
            $this->assertSame('REF-' . $sufijo, $pagoFila['referencia_reembolso']);

            $stmt = $conn->prepare('SELECT count(*) FROM pedido_historial_estado WHERE pedido_id = :id');
            $stmt->execute([':id' => $pedidoId]);
            $this->assertGreaterThanOrEqual(3, (int) $stmt->fetchColumn());

            $stmt = $conn->prepare("SELECT count(*) FROM auditoria WHERE tabla = 'pedido' AND registro_id = :id");
            $stmt->execute([':id' => $pedidoId]);
            $this->assertGreaterThanOrEqual(3, (int) $stmt->fetchColumn());
        } finally {
            $this->limpiar($conn, $pedidoId, $correo, $prodId, $catId);
            $_SESSION['carrito'] = [];
        }
    }

    public function testCancelacionEnPreparacionReembolsaLaMitad(): void
    {
        global $conn;

        $sufijo = strtoupper(bin2hex(random_bytes(4)));
        $correo = 'qa.mitad.' . strtolower($sufijo) . '@example.com';
        $catId = 0;
        $prodId = 0;
        $pedidoId = 0;
        $usuarioId = (int) $conn->query(
            "SELECT id FROM usuario WHERE rol = 'ADMIN' AND activo = TRUE ORDER BY id LIMIT 1"
        )->fetchColumn();

        $this->assertGreaterThan(0, $usuarioId, 'No hay un administrador activo para ejecutar la prueba.');

        try {
            $stmt = $conn->prepare('INSERT INTO categoria (nombre, descripcion) VALUES (:nombre, :descripcion) RETURNING id');
            $stmt->execute([':nombre' => 'Categoría mitad ' . $sufijo, ':descripcion' => 'Temporal de pruebas']);
            $catId = (int) $stmt->fetchColumn();

            $stmt = $conn->prepare(
                'INSERT INTO producto (nombre, descripcion, precio, stock_actual, stock_minimo, categoria_id)
                 VALUES (:nombre, :descripcion, 1000.00, 5, 1, :categoria_id)
                 RETURNING id'
            );
            $stmt->execute([
                ':nombre'       => 'Producto mitad ' . $sufijo,
                ':descripcion'  => 'Temporal de pruebas',
                ':categoria_id' => $catId,
            ]);
            $prodId = (int) $stmt->fetchColumn();

            $_SESSION['carrito'] = [$prodId => 2];
            $resultado = crearPedido($conn, [
                'nombre'             => 'Cliente mitad ' . $sufijo,
                'correo'             => $correo,
                'telefono'           => '',
                'observaciones'      => '',
                'consentimiento'     => true,
                'acepta_cancelacion' => true,
            ]);
            $pedidoId = (int) $resultado['id'];

            confirmarPago($conn, $pedidoId, $usuarioId, 'TARJETA', null, 'TRX-MITAD-' . $sufijo);
            avanzarEstadoPedido($conn, $pedidoId, 'EN_PREPARACION', $usuarioId);
            $this->assertSame(3, $this->stock($conn, $prodId));

            $cancelacion = cancelarPedido(
                $conn,
                $pedidoId,
                $usuarioId,
                'Cancelación en preparación',
                'TARJETA',
                'REF-MITAD-' . $sufijo
            );

            $this->assertSame(50, $cancelacion['porcentaje_reembolso']);
            $this->assertEquals(1000.00, $cancelacion['monto_reembolsado']);
            $this->assertSame(5, $this->stock($conn, $prodId));

            $stmt = $conn->prepare('SELECT estado, monto, monto_reembolsado FROM pago WHERE pedido_id = :id');
            $stmt->execute([':id' => $pedidoId]);
            $pago = $stmt->fetch();
            $this->assertSame('REEMBOLSADO', $pago['estado']);
            $this->assertEquals(2000.00, $pago['monto']);
            $this->assertEquals(1000.00, $pago['monto_reembolsado']);
        } finally {
            $this->limpiar($conn, $pedidoId, $correo, $prodId, $catId);
            $_SESSION['carrito'] = [];
        }
    }

    public function testCheckoutSinConsentimientoNoCreaPedido(): void
    {
        global $conn;

        $sufijo = strtoupper(bin2hex(random_bytes(3)));
        $correo = 'qa.sin.' . strtolower($sufijo) . '@example.com';
        $antes = (int) $conn->query('SELECT count(*) FROM pedido')->fetchColumn();

        $rechazado = false;
        try {
            crearPedido($conn, [
                'nombre'         => 'Cliente sin consentimiento',
                'correo'         => $correo,
                'telefono'       => '',
                'observaciones'  => '',
                'consentimiento' => false,
                'acepta_cancelacion' => true,
            ]);
        } catch (ReglaNegocioException $e) {
            $rechazado = true;
        }

        $despues = (int) $conn->query('SELECT count(*) FROM pedido')->fetchColumn();
        $this->assertTrue($rechazado);
        $this->assertSame($antes, $despues);
    }

    public function testConfiguracionSistemaDisponible(): void
    {
        global $conn;

        $this->assertSame('20', configSistema($conn, 'tiempo_estimado_pedidos_min'));
        $this->assertSame('2', configSistema($conn, 'capacidad_preparacion_simultanea'));
        $this->assertSame('15', configSistema($conn, 'buffer_preparacion_porcentaje'));
        $this->assertNotNull(configSistema($conn, 'nombre_tienda'));
        $this->assertNotNull(configSistema($conn, 'moneda'));
    }

    private function stock(PDO $conn, int $productoId): int
    {
        $stmt = $conn->prepare('SELECT stock_actual FROM producto WHERE id = :id');
        $stmt->execute([':id' => $productoId]);
        return (int) $stmt->fetchColumn();
    }

    private function limpiar(PDO $conn, int $pedidoId, string $correo, int $productoId, int $categoriaId): void
    {
        if ($pedidoId > 0) {
            $conn->prepare("DELETE FROM movimiento_caja WHERE related_type = 'pago' AND related_id IN (SELECT id FROM pago WHERE pedido_id = :id)")
                ->execute([':id' => $pedidoId]);
            $conn->prepare("DELETE FROM auditoria WHERE tabla = 'pedido' AND registro_id = :id")
                ->execute([':id' => $pedidoId]);
            $conn->prepare('DELETE FROM pago WHERE pedido_id = :id')->execute([':id' => $pedidoId]);
            $conn->prepare('DELETE FROM pedido_historial_estado WHERE pedido_id = :id')->execute([':id' => $pedidoId]);
            $conn->prepare('DELETE FROM detallepedido WHERE id_pedido = :id')->execute([':id' => $pedidoId]);
            $conn->prepare('DELETE FROM pedido WHERE id = :id')->execute([':id' => $pedidoId]);
        }

        $conn->prepare('DELETE FROM cliente WHERE correo = :correo')->execute([':correo' => $correo]);

        if ($productoId > 0) {
            $conn->prepare('DELETE FROM movimiento_stock WHERE producto_id = :id')->execute([':id' => $productoId]);
            $conn->prepare('DELETE FROM producto WHERE id = :id')->execute([':id' => $productoId]);
        }

        if ($categoriaId > 0) {
            $conn->prepare('DELETE FROM categoria WHERE id = :id')->execute([':id' => $categoriaId]);
        }
    }
}
