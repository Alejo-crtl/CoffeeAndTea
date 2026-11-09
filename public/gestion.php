<?php
/**
 * Panel de gestión: pedidos, cobros, estados, caja y stock.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/pedidos.php';

requerirRol(ROL_ENCARGADO, ROL_ADMIN);
exigirCambioPassword();
$usuario = usuarioActual();
if ($usuario === null) {
    redirigir(url('login.php'));
}

$tabPermitidas = ['pedidos', 'caja', 'stock'];
$tab = (string) ($_GET['tab'] ?? 'pedidos');
if (!in_array($tab, $tabPermitidas, true)) {
    $tab = 'pedidos';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $destino = url('gestion.php?tab=' . rawurlencode((string) ($_POST['tab'] ?? $tab)));

    try {
        switch ($accion) {
            case 'confirmar_pago':
                $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
                $metodo = (string) ($_POST['metodo'] ?? '');
                $recibido = trim((string) ($_POST['recibido'] ?? ''));
                $referencia = trim((string) ($_POST['referencia'] ?? ''));
                $resultado = confirmarPago(
                    $conn,
                    $pedidoId,
                    (int) $usuario['id'],
                    $metodo,
                    $recibido !== '' ? (float) str_replace(',', '.', $recibido) : null,
                    $referencia !== '' ? $referencia : null
                );
                flash('exito', 'Pago confirmado. Total ' . moneda($resultado['total'])
                    . ($resultado['cambio'] > 0 ? ' · Cambio ' . moneda($resultado['cambio']) : '') . '.');
                $destino = url('gestion.php?tab=pedidos&ver=' . $pedidoId);
                break;

            case 'avanzar':
                $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
                $nuevo = (string) ($_POST['nuevo_estado'] ?? '');
                avanzarEstadoPedido($conn, $pedidoId, $nuevo, (int) $usuario['id']);
                flash('exito', 'El pedido pasó a ' . etiquetaEstado($nuevo) . '.');
                $destino = url('gestion.php?tab=pedidos&ver=' . $pedidoId);
                break;

            case 'cancelar':
                $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
                $resultado = cancelarPedido(
                    $conn,
                    $pedidoId,
                    (int) $usuario['id'],
                    (string) ($_POST['motivo'] ?? ''),
                    (string) ($_POST['metodo_reembolso'] ?? 'NINGUNO'),
                    trim((string) ($_POST['referencia_reembolso'] ?? '')) ?: null
                );
                $mensajeCancelacion = 'Pedido cancelado.';
                if ($resultado['estaba_pagado']) {
                    $mensajeCancelacion .= ' Reembolso de '
                        . moneda((float) $resultado['monto_reembolsado'])
                        . ' (' . (int) $resultado['porcentaje_reembolso'] . '%) registrado.';
                }
                flash('exito', $mensajeCancelacion);
                $destino = url('gestion.php?tab=pedidos&ver=' . $pedidoId);
                break;

            case 'abrir_caja':
                $base = (float) str_replace(',', '.', (string) ($_POST['base_inicial'] ?? '0'));
                abrirCaja($conn, (int) $usuario['id'], $base);
                flash('exito', 'Caja abierta con base ' . moneda($base) . '.');
                $destino = url('gestion.php?tab=caja');
                break;

            case 'cerrar_caja':
                $sesionId = (int) ($_POST['sesion_id'] ?? 0);
                $contado = (float) str_replace(',', '.', (string) ($_POST['efectivo_contado'] ?? '0'));
                $cierre = cerrarCaja($conn, $sesionId, (int) $usuario['id'], $contado);
                $mensaje = 'Caja cerrada. Esperado ' . moneda($cierre['esperado']) . ', contado ' . moneda($cierre['contado']) . '.';
                if (abs($cierre['diferencia']) < 0.005) {
                    $mensaje .= ' Cuadra perfecto.';
                } elseif ($cierre['diferencia'] > 0) {
                    $mensaje .= ' Sobrante: ' . moneda($cierre['diferencia']) . '.';
                } else {
                    $mensaje .= ' Faltante: ' . moneda(abs($cierre['diferencia'])) . '.';
                }
                flash(abs($cierre['diferencia']) < 0.005 ? 'exito' : 'info', $mensaje);
                $destino = url('gestion.php?tab=caja');
                break;

            case 'movimiento_caja':
                $sesion = cajaAbierta($conn);
                if ($sesion === null) {
                    throw new ReglaNegocioException('No hay una caja abierta.');
                }
                registrarMovimientoCaja(
                    $conn,
                    (int) $sesion['id'],
                    (string) ($_POST['tipo'] ?? ''),
                    (float) str_replace(',', '.', (string) ($_POST['monto'] ?? '0')),
                    (string) ($_POST['concepto'] ?? ''),
                    (int) $usuario['id']
                );
                flash('exito', 'Movimiento de caja registrado.');
                $destino = url('gestion.php?tab=caja');
                break;

            case 'ajustar_stock':
                $productoId = (int) ($_POST['producto_id'] ?? 0);
                $nuevoStock = filter_var($_POST['nuevo_stock'] ?? null, FILTER_VALIDATE_INT);
                if ($productoId < 1 || $nuevoStock === false || $nuevoStock < 0) {
                    throw new ReglaNegocioException('Indica un stock válido (entero mayor o igual a cero).');
                }
                $conn->beginTransaction();
                try {
                    $stmt = $conn->prepare('SELECT nombre, stock_actual FROM producto WHERE id = :id FOR UPDATE');
                    $stmt->execute([':id' => $productoId]);
                    $producto = $stmt->fetch();
                    if ($producto === false) {
                        throw new ReglaNegocioException('El producto no existe.');
                    }
                    $anterior = (int) $producto['stock_actual'];
                    if ($nuevoStock === $anterior) {
                        throw new ReglaNegocioException('El stock no cambió.');
                    }
                    $stmt = $conn->prepare('UPDATE producto SET stock_actual = :stock WHERE id = :id');
                    $stmt->execute([':stock' => $nuevoStock, ':id' => $productoId]);
                    $stmt = $conn->prepare(
                        'INSERT INTO movimiento_stock (producto_id, tipo, cantidad, referencia, creado_por)
                         VALUES (:producto_id, \'AJUSTE\', :cantidad, :referencia, :usuario_id)'
                    );
                    $stmt->execute([
                        ':producto_id' => $productoId,
                        ':cantidad'    => abs($nuevoStock - $anterior),
                        ':referencia'  => 'Ajuste manual: ' . $anterior . ' -> ' . $nuevoStock,
                        ':usuario_id'  => (int) $usuario['id'],
                    ]);
                    registrarAuditoria($conn, (int) $usuario['id'], 'STOCK_AJUSTADO', 'producto', $productoId, [
                        'anterior' => $anterior,
                        'nuevo'    => $nuevoStock,
                    ]);
                    $conn->commit();
                } catch (Throwable $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    throw $e;
                }
                flash('exito', 'Stock de ' . $producto['nombre'] . ' actualizado a ' . $nuevoStock . '.');
                $destino = url('gestion.php?tab=stock');
                break;

            default:
                throw new ReglaNegocioException('Acción no reconocida.');
        }
    } catch (ReglaNegocioException $e) {
        flash('error', $e->getMessage());
    } catch (Throwable $e) {
        error_log('CoffeeAndtea - gestión: ' . $e->getMessage());
        flash('error', 'Ocurrió un error inesperado. Revisa el registro del servidor.');
    }

    redirigir($destino);
}

$verId = filter_input(INPUT_GET, 'ver', FILTER_VALIDATE_INT) ?: 0;
$pedidoVer = $verId > 0 ? obtenerPedidoCompleto($conn, $verId) : null;

$filtroEstado = (string) ($_GET['estado'] ?? 'TODOS');
$buscar = trim((string) ($_GET['buscar'] ?? ''));
$pedidos = listarPedidosGestion($conn, $filtroEstado, $buscar);

$caja = cajaAbierta($conn);
$resumenCajaActual = $caja !== null ? resumenCaja($conn, (int) $caja['id']) : null;
$movimientosCaja = [];
if ($caja !== null) {
    $stmt = $conn->prepare(
        'SELECT mc.*, u.nombre AS usuario
         FROM movimiento_caja mc
         INNER JOIN usuario u ON u.id = mc.creado_por
         WHERE mc.caja_sesion_id = :id
         ORDER BY mc.id DESC LIMIT 100'
    );
    $stmt->execute([':id' => (int) $caja['id']]);
    $movimientosCaja = $stmt->fetchAll();
}

$conteos = [
    'PENDIENTE'      => (int) $conn->query("SELECT COUNT(*) FROM pedido WHERE estado = 'PENDIENTE'")->fetchColumn(),
    'PAGADO'         => (int) $conn->query("SELECT COUNT(*) FROM pedido WHERE estado = 'PAGADO'")->fetchColumn(),
    'EN_PREPARACION' => (int) $conn->query("SELECT COUNT(*) FROM pedido WHERE estado = 'EN_PREPARACION'")->fetchColumn(),
    'LISTO'          => (int) $conn->query("SELECT COUNT(*) FROM pedido WHERE estado = 'LISTO'")->fetchColumn(),
];
$bajoStock = $conn->query(
    'SELECT p.*, c.nombre AS categoria FROM producto p
     INNER JOIN categoria c ON c.id = p.categoria_id
     WHERE p.activo = TRUE AND p.stock_actual <= p.stock_minimo
     ORDER BY p.stock_actual ASC, p.nombre'
)->fetchAll();
$productosStock = $conn->query(
    'SELECT p.*, c.nombre AS categoria FROM producto p
     INNER JOIN categoria c ON c.id = p.categoria_id
     ORDER BY p.activo DESC, c.nombre, p.nombre'
)->fetchAll();

cabeceraHtml('Gestión | CoffeeAndtea', 'gestion');
?>

<section class="pagina-cabecera panel-gestion-cabecera">
    <div>
        <p class="eyebrow">Operación diaria</p>
        <h1>Gestión</h1>
        <p><?= h($usuario['nombre']) ?> · <?= h($usuario['rol']) ?></p>
    </div>
    <div class="cabecera-acciones">
        <?php if ($caja !== null): ?>
            <span class="pastilla pastilla-verde">Caja abierta · base <?= h(moneda((float) $caja['base_inicial'])) ?></span>
        <?php else: ?>
            <span class="pastilla pastilla-roja">Caja cerrada</span>
        <?php endif; ?>
        <a class="btn btn-secundario" href="<?= h(url('reportes.php')) ?>">Reportes</a>
    </div>
</section>

<div class="stats-grid">
    <a class="stat" href="<?= h(url('gestion.php?tab=pedidos&estado=PENDIENTE')) ?>">
        <span>Por cobrar</span><strong><?= $conteos['PENDIENTE'] ?></strong>
    </a>
    <a class="stat" href="<?= h(url('gestion.php?tab=pedidos&estado=PAGADO')) ?>">
        <span>Pagados</span><strong><?= $conteos['PAGADO'] ?></strong>
    </a>
    <a class="stat" href="<?= h(url('gestion.php?tab=pedidos&estado=EN_PREPARACION')) ?>">
        <span>En preparación</span><strong><?= $conteos['EN_PREPARACION'] ?></strong>
    </a>
    <a class="stat" href="<?= h(url('gestion.php?tab=pedidos&estado=LISTO')) ?>">
        <span>Listos</span><strong><?= $conteos['LISTO'] ?></strong>
    </a>
    <a class="stat<?= $bajoStock !== [] ? ' stat-alerta' : '' ?>" href="<?= h(url('gestion.php?tab=stock')) ?>">
        <span>Bajo stock</span><strong><?= count($bajoStock) ?></strong>
    </a>
</div>

<nav class="tabs" aria-label="Secciones de gestión">
    <a class="tab<?= $tab === 'pedidos' ? ' activo' : '' ?>" href="<?= h(url('gestion.php?tab=pedidos')) ?>">Pedidos</a>
    <a class="tab<?= $tab === 'caja' ? ' activo' : '' ?>" href="<?= h(url('gestion.php?tab=caja')) ?>">Caja</a>
    <a class="tab<?= $tab === 'stock' ? ' activo' : '' ?>" href="<?= h(url('gestion.php?tab=stock')) ?>">Stock</a>
</nav>

<?php if ($tab === 'pedidos'): ?>
    <?php if ($pedidoVer !== null): ?>
        <?php require __DIR__ . '/../app/php/vistas/panel_pedido.php'; ?>
    <?php endif; ?>

    <section class="panel" aria-labelledby="lista-pedidos">
        <div class="panel-head">
            <h2 id="lista-pedidos">Pedidos</h2>
            <form class="filtros-inline" method="get" action="<?= h(url('gestion.php')) ?>">
                <input type="hidden" name="tab" value="pedidos">
                <label class="sr-only" for="estado">Estado</label>
                <select id="estado" name="estado">
                    <?php foreach (['TODOS', 'PENDIENTE', 'PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO', 'CANCELADO'] as $opcion): ?>
                        <option value="<?= h($opcion) ?>"<?= $filtroEstado === $opcion ? ' selected' : '' ?>>
                            <?= h($opcion === 'TODOS' ? 'Todos los estados' : etiquetaEstado($opcion)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label class="sr-only" for="buscar">Buscar</label>
                <input id="buscar" name="buscar" type="search" placeholder="Código, nombre o correo" value="<?= h($buscar) ?>">
                <button class="btn btn-pequeno btn-secundario" type="submit">Filtrar</button>
            </form>
        </div>

        <?php if ($pedidos === []): ?>
            <div class="estado-vacio compacto"><p>No hay pedidos con ese filtro.</p></div>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla tabla-pedidos">
                    <thead>
                        <tr>
                            <th>Código</th><th>Cliente</th><th>Fecha</th><th>Estado</th>
                            <th>Total</th><th>Pago</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $pedido): ?>
                            <tr class="<?= $verId === (int) $pedido['id'] ? 'fila-activa' : '' ?>">
                                <td><strong><?= h((string) $pedido['codigo']) ?></strong><span class="texto-suave">#<?= (int) $pedido['id'] ?> · <?= (int) $pedido['lineas'] ?> líneas</span></td>
                                <td><?= h((string) $pedido['cliente_nombre']) ?><span class="texto-suave"><?= h((string) $pedido['cliente_correo']) ?></span></td>
                                <td><?= h(fechaBonita($pedido['fecha_creacion'])) ?><span class="texto-suave">~<?= (int) $pedido['tiempo_estimado_min'] ?> min</span></td>
                                <td><?= badgeEstado((string) $pedido['estado']) ?></td>
                                <td><strong><?= h(moneda((float) $pedido['total'])) ?></strong></td>
                                <td><?= h((string) ($pedido['metodo_pago'] ?? '—')) ?></td>
                                <td>
                                    <a class="btn btn-pequeno btn-secundario"
                                       href="<?= h(url('gestion.php?tab=pedidos&ver=' . (int) $pedido['id'])) ?>">Abrir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($tab === 'caja'): ?>
    <div class="layout-caja">
        <section class="panel">
            <h2><?= $caja !== null ? 'Caja abierta' : 'Abrir caja' ?></h2>
            <?php if ($caja === null): ?>
                <p class="texto-suave">Registra el efectivo inicial del turno para poder cobrar en efectivo.</p>
                <form method="post" action="<?= h(url('gestion.php')) ?>" class="formulario">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="accion" value="abrir_caja">
                    <input type="hidden" name="tab" value="caja">
                    <div class="campo">
                        <label for="base_inicial">Base inicial (COP)</label>
                        <input id="base_inicial" name="base_inicial" type="number" min="0" step="100" value="50000" required>
                    </div>
                    <button class="btn btn-primario" type="submit">Abrir caja</button>
                </form>
            <?php else: ?>
                <dl class="detalle-lista">
                    <div><dt>Abierta por</dt><dd><?= h((string) $caja['abierto_por_nombre']) ?></dd></div>
                    <div><dt>Desde</dt><dd><?= h(fechaBonita($caja['fecha_apertura'])) ?></dd></div>
                    <div><dt>Base inicial</dt><dd><?= h(moneda((float) $resumenCajaActual['base'])) ?></dd></div>
                    <div><dt>Ingresos</dt><dd class="texto-verde"><?= h(moneda((float) $resumenCajaActual['ingresos'])) ?></dd></div>
                    <div><dt>Egresos</dt><dd class="texto-rojo"><?= h(moneda((float) $resumenCajaActual['egresos'])) ?></dd></div>
                    <div class="total"><dt>Efectivo esperado</dt><dd><?= h(moneda((float) $resumenCajaActual['esperado'])) ?></dd></div>
                </dl>

                <form method="post" action="<?= h(url('gestion.php')) ?>" class="formulario formulario-cierre">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="accion" value="cerrar_caja">
                    <input type="hidden" name="tab" value="caja">
                    <input type="hidden" name="sesion_id" value="<?= (int) $caja['id'] ?>">
                    <div class="campo">
                        <label for="efectivo_contado">Efectivo contado (COP)</label>
                        <input id="efectivo_contado" name="efectivo_contado" type="number" min="0" step="100"
                               value="<?= (int) $resumenCajaActual['esperado'] ?>" required>
                        <small>Cuenta el dinero físico antes de cerrar. La diferencia queda auditada.</small>
                    </div>
                    <button class="btn btn-peligro" type="submit"
                            onclick="return confirm('¿Cerrar la caja? No podrás registrar más movimientos en este turno.')">
                        Cerrar caja
                    </button>
                </form>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2>Movimiento manual</h2>
            <?php if ($caja === null): ?>
                <p class="texto-suave">Abre la caja para registrar ingresos o egresos.</p>
            <?php else: ?>
                <form method="post" action="<?= h(url('gestion.php')) ?>" class="formulario">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="accion" value="movimiento_caja">
                    <input type="hidden" name="tab" value="caja">
                    <div class="campo">
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo" required>
                            <option value="INGRESO">Ingreso</option>
                            <option value="EGRESO">Egreso</option>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="monto">Monto (COP)</label>
                        <input id="monto" name="monto" type="number" min="1" step="100" required>
                    </div>
                    <div class="campo">
                        <label for="concepto">Concepto</label>
                        <input id="concepto" name="concepto" type="text" maxlength="255" required
                               placeholder="Compra de insumos, propina, ajuste...">
                    </div>
                    <button class="btn btn-secundario" type="submit">Registrar movimiento</button>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <section class="panel">
        <h2>Movimientos del turno</h2>
        <?php if ($movimientosCaja === []): ?>
            <div class="estado-vacio compacto"><p>No hay movimientos registrados.</p></div>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Hora</th><th>Tipo</th><th>Concepto</th><th>Monto</th><th>Usuario</th></tr></thead>
                    <tbody>
                        <?php foreach ($movimientosCaja as $mov): ?>
                            <tr>
                                <td><?= h(fechaBonita($mov['creado_en'])) ?></td>
                                <td><span class="pastilla <?= $mov['tipo'] === 'INGRESO' ? 'pastilla-verde' : 'pastilla-roja' ?>"><?= h((string) $mov['tipo']) ?></span></td>
                                <td><?= h((string) $mov['concepto']) ?></td>
                                <td class="<?= $mov['tipo'] === 'INGRESO' ? 'texto-verde' : 'texto-rojo' ?>">
                                    <?= $mov['tipo'] === 'INGRESO' ? '+' : '−' ?><?= h(moneda((float) $mov['monto'])) ?>
                                </td>
                                <td><?= h((string) $mov['usuario']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($tab === 'stock'): ?>
    <?php if ($bajoStock !== []): ?>
        <div class="alerta-error" role="alert">
            <strong>Productos en o por debajo del mínimo:</strong>
            <?= h(implode(', ', array_map(static fn(array $p): string => $p['nombre'] . ' (' . (int) $p['stock_actual'] . ')', $bajoStock))) ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-head">
            <h2>Inventario</h2>
            <p class="texto-suave">El stock se descuenta al confirmar el pago y se devuelve al cancelar un pedido pagado.</p>
        </div>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Producto</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Mínimo</th><th>Ajustar</th></tr></thead>
                <tbody>
                    <?php foreach ($productosStock as $producto): ?>
                        <tr class="<?= (int) $producto['stock_actual'] <= (int) $producto['stock_minimo'] ? 'fila-alerta' : '' ?>">
                            <td><strong><?= h((string) $producto['nombre']) ?></strong><?= !esVerdadero($producto['activo']) ? ' <span class="pastilla pastilla-gris">Inactivo</span>' : '' ?></td>
                            <td><?= h((string) $producto['categoria']) ?></td>
                            <td><?= h(moneda((float) $producto['precio'])) ?></td>
                            <td><strong><?= (int) $producto['stock_actual'] ?></strong></td>
                            <td><?= (int) $producto['stock_minimo'] ?></td>
                            <td>
                                <form class="form-stock" method="post" action="<?= h(url('gestion.php')) ?>">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="accion" value="ajustar_stock">
                                    <input type="hidden" name="tab" value="stock">
                                    <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>">
                                    <label class="sr-only" for="stock-<?= (int) $producto['id'] ?>">Nuevo stock</label>
                                    <input id="stock-<?= (int) $producto['id'] ?>" type="number" name="nuevo_stock" min="0"
                                           value="<?= (int) $producto['stock_actual'] ?>" required>
                                    <button class="btn btn-pequeno btn-secundario" type="submit">Guardar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php pieHtml(); ?>
