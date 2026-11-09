<?php
/**
 * Reportes de ventas, productos, stock y caja.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/pedidos.php';

requerirRol(ROL_ENCARGADO, ROL_ADMIN);
exigirCambioPassword();

$ventasHoy = $conn->query(
    "SELECT p.id, p.codigo, c.nombre AS cliente, p.total, p.metodo_pago, p.fecha_pago
     FROM pedido p
     INNER JOIN cliente c ON c.id = p.cliente_id
     WHERE p.estado IN ('PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
       AND COALESCE(p.fecha_pago, p.fecha_creacion)::date = CURRENT_DATE
     ORDER BY COALESCE(p.fecha_pago, p.fecha_creacion) DESC"
)->fetchAll();

$totalesHoy = $conn->query(
    "SELECT COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS ventas,
            COALESCE(AVG(total), 0) AS ticket
     FROM pedido
     WHERE estado IN ('PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
       AND COALESCE(fecha_pago, fecha_creacion)::date = CURRENT_DATE"
)->fetch();

$porMetodo = $conn->query(
    "SELECT metodo_pago, COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS total
     FROM pedido
     WHERE estado IN ('PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
       AND COALESCE(fecha_pago, fecha_creacion)::date = CURRENT_DATE
     GROUP BY metodo_pago ORDER BY total DESC"
)->fetchAll();

$topProductos = $conn->query(
    "SELECT pr.nombre AS producto, c.nombre AS categoria,
            SUM(d.cantidad) AS unidades, SUM(d.cantidad * d.precio_unitario) AS ingresos
     FROM detallepedido d
     INNER JOIN producto pr ON pr.id = d.id_producto
     INNER JOIN categoria c ON c.id = pr.categoria_id
     INNER JOIN pedido p ON p.id = d.id_pedido
     WHERE p.estado IN ('PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
       AND COALESCE(p.fecha_pago, p.fecha_creacion) >= CURRENT_DATE - INTERVAL '30 days'
     GROUP BY pr.id, pr.nombre, c.nombre
     ORDER BY unidades DESC, ingresos DESC LIMIT 10"
)->fetchAll();

$ingresosCategoria = $conn->query(
    "SELECT c.nombre AS categoria, SUM(d.cantidad) AS unidades,
            SUM(d.cantidad * d.precio_unitario) AS ingresos
     FROM detallepedido d
     INNER JOIN producto pr ON pr.id = d.id_producto
     INNER JOIN categoria c ON c.id = pr.categoria_id
     INNER JOIN pedido p ON p.id = d.id_pedido
     WHERE p.estado IN ('PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
       AND COALESCE(p.fecha_pago, p.fecha_creacion) >= CURRENT_DATE - INTERVAL '30 days'
     GROUP BY c.id, c.nombre ORDER BY ingresos DESC"
)->fetchAll();

$bajoStock = $conn->query(
    "SELECT p.nombre, p.stock_actual, p.stock_minimo, c.nombre AS categoria
     FROM producto p INNER JOIN categoria c ON c.id = p.categoria_id
     WHERE p.activo = TRUE AND p.stock_actual <= p.stock_minimo
     ORDER BY p.stock_actual ASC, p.nombre"
)->fetchAll();

$ultimosDias = $conn->query(
    "SELECT COALESCE(fecha_pago, fecha_creacion)::date AS dia,
            COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS total
     FROM pedido
     WHERE estado IN ('PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
       AND COALESCE(fecha_pago, fecha_creacion) >= CURRENT_DATE - INTERVAL '6 days'
     GROUP BY dia ORDER BY dia DESC"
)->fetchAll();

$cierres = $conn->query(
    "SELECT cs.*, ua.nombre AS abierto_por_nombre, uc.nombre AS cerrado_por_nombre
     FROM caja_sesion cs
     INNER JOIN usuario ua ON ua.id = cs.abierto_por
     LEFT JOIN usuario uc ON uc.id = cs.cerrado_por
     ORDER BY cs.id DESC LIMIT 10"
)->fetchAll();

// Exportación CSV de las ventas de hoy.
if (($_GET['export'] ?? '') === 'ventas') {
    $nombreArchivo = 'ventas-hoy-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    $salida = fopen('php://output', 'w');
    if ($salida !== false) {
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, ['Código', 'Cliente', 'Total', 'Método', 'Fecha de pago'], ';');
        foreach ($ventasHoy as $venta) {
            fputcsv($salida, [
                $venta['codigo'],
                $venta['cliente'],
                number_format((float) $venta['total'], 2, ',', ''),
                $venta['metodo_pago'] ?? '',
                (string) $venta['fecha_pago'],
            ], ';');
        }
        fclose($salida);
    }
    exit;
}

cabeceraHtml('Reportes | CoffeeAndtea', 'reportes');
?>

<section class="pagina-cabecera">
    <div>
        <p class="eyebrow">Analítica del negocio</p>
        <h1>Reportes</h1>
        <p>Ventas, productos y caja. Los importes usan el precio histórico de cada pedido.</p>
    </div>
    <a class="btn btn-secundario" href="<?= h(url('reportes.php?export=ventas')) ?>">Exportar ventas de hoy (CSV)</a>
</section>

<div class="stats-grid">
    <div class="stat stat-destacado">
        <span>Ventas de hoy</span><strong><?= h(moneda((float) $totalesHoy['ventas'])) ?></strong>
    </div>
    <div class="stat">
        <span>Pedidos pagados hoy</span><strong><?= (int) $totalesHoy['pedidos'] ?></strong>
    </div>
    <div class="stat">
        <span>Ticket promedio</span><strong><?= h(moneda((float) $totalesHoy['ticket'])) ?></strong>
    </div>
    <?php foreach ($porMetodo as $metodo): ?>
        <div class="stat">
            <span><?= h((string) ($metodo['metodo_pago'] ?? 'Sin método')) ?></span>
            <strong><?= h(moneda((float) $metodo['total'])) ?></strong>
            <small><?= (int) $metodo['pedidos'] ?> pedidos</small>
        </div>
    <?php endforeach; ?>
</div>

<div class="layout-reportes">
    <section class="panel">
        <div class="panel-head">
            <h2>Ventas de hoy</h2>
            <span class="texto-suave"><?= count($ventasHoy) ?> registros</span>
        </div>
        <?php if ($ventasHoy === []): ?>
            <div class="estado-vacio compacto"><p>Aún no hay ventas registradas hoy.</p></div>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Código</th><th>Cliente</th><th>Método</th><th>Hora</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($ventasHoy as $venta): ?>
                            <tr>
                                <td><?= h((string) $venta['codigo']) ?></td>
                                <td><?= h((string) $venta['cliente']) ?></td>
                                <td><?= h((string) ($venta['metodo_pago'] ?? '—')) ?></td>
                                <td><?= h(fechaBonita($venta['fecha_pago'] ?? null)) ?></td>
                                <td><?= h(moneda((float) $venta['total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Top productos (30 días)</h2>
        <?php if ($topProductos === []): ?>
            <p class="texto-suave">Sin ventas en el periodo.</p>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Producto</th><th>Unidades</th><th>Ingresos</th></tr></thead>
                    <tbody>
                        <?php foreach ($topProductos as $producto): ?>
                            <tr>
                                <td><strong><?= h((string) $producto['producto']) ?></strong><span class="texto-suave"><?= h((string) $producto['categoria']) ?></span></td>
                                <td><?= (int) $producto['unidades'] ?></td>
                                <td><?= h(moneda((float) $producto['ingresos'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Ingresos por categoría (30 días)</h2>
        <?php if ($ingresosCategoria === []): ?>
            <p class="texto-suave">Sin ventas en el periodo.</p>
        <?php else: ?>
            <ul class="barras">
                <?php
                $maxIngreso = max(array_map(static fn(array $f): float => (float) $f['ingresos'], $ingresosCategoria)) ?: 1;
                foreach ($ingresosCategoria as $categoria):
                    $ancho = max(4, round(((float) $categoria['ingresos'] / $maxIngreso) * 100));
                ?>
                    <li>
                        <span><?= h((string) $categoria['categoria']) ?></span>
                        <div class="barra"><i style="width:<?= (int) $ancho ?>%"></i></div>
                        <strong><?= h(moneda((float) $categoria['ingresos'])) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Bajo stock</h2>
        <?php if ($bajoStock === []): ?>
            <p class="texto-suave">Todo el inventario está por encima del mínimo.</p>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Producto</th><th>Categoría</th><th>Stock</th><th>Mínimo</th></tr></thead>
                    <tbody>
                        <?php foreach ($bajoStock as $producto): ?>
                            <tr class="fila-alerta">
                                <td><?= h((string) $producto['nombre']) ?></td>
                                <td><?= h((string) $producto['categoria']) ?></td>
                                <td><strong><?= (int) $producto['stock_actual'] ?></strong></td>
                                <td><?= (int) $producto['stock_minimo'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Últimos 7 días</h2>
        <?php if ($ultimosDias === []): ?>
            <p class="texto-suave">Sin datos.</p>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Día</th><th>Pedidos</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($ultimosDias as $dia): ?>
                            <tr>
                                <td><?= h(date('d/m/Y', strtotime((string) $dia['dia']))) ?></td>
                                <td><?= (int) $dia['pedidos'] ?></td>
                                <td><?= h(moneda((float) $dia['total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Últimos cierres de caja</h2>
        <?php if ($cierres === []): ?>
            <p class="texto-suave">No hay cierres registrados.</p>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Apertura</th><th>Base</th><th>Contado</th><th>Diferencia</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php foreach ($cierres as $cierre): ?>
                            <tr>
                                <td><?= h(fechaBonita($cierre['fecha_apertura'])) ?><span class="texto-suave"><?= h((string) $cierre['abierto_por_nombre']) ?></span></td>
                                <td><?= h(moneda((float) $cierre['base_inicial'])) ?></td>
                                <td><?= $cierre['efectivo_contado'] !== null ? h(moneda((float) $cierre['efectivo_contado'])) : '—' ?></td>
                                <td class="<?= $cierre['diferencia'] !== null && abs((float) $cierre['diferencia']) > 0.005 ? ((float) $cierre['diferencia'] < 0 ? 'texto-rojo' : 'texto-verde') : '' ?>">
                                    <?= $cierre['diferencia'] !== null ? h(moneda((float) $cierre['diferencia'])) : '—' ?>
                                </td>
                                <td><span class="pastilla <?= $cierre['estado'] === 'ABIERTA' ? 'pastilla-verde' : 'pastilla-gris' ?>"><?= h((string) $cierre['estado']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php pieHtml(); ?>
