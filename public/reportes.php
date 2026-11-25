<?php
/**
 * BLOQUE B09 · REPORTES
 * Qué hace: reportes de ventas, productos, clientes, stock e ingresos;
 *          exportación CSV con rango de fechas.
 * Relacionados: pedidos.php, reportes.php (lib), plantilla.php.
 * Pruebas: CP-041.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/pedidos.php';
require_once __DIR__ . '/../app/php/lib/reportes.php';

requerirRol(ROL_ENCARGADO, ROL_ADMIN);
exigirCambioPassword();

// Rango de fechas: por defecto, hoy.
$hoy = date('Y-m-d');
$desde = (string) ($_GET['desde'] ?? $hoy);
$hasta = (string) ($_GET['hasta'] ?? $hoy);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $desde = $hoy;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $hasta = $hoy;
}
if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

$ventas = consultarProcedimiento($conn, 'sp_reporte_ventas_diarias', [$desde, $hasta]);

$totalVentas = 0.0;
$porMetodo = [];
foreach ($ventas as $venta) {
    $total = (float) $venta['total'];
    $totalVentas += $total;
    $metodo = (string) ($venta['metodo_pago'] ?? 'Sin método');
    if (!isset($porMetodo[$metodo])) {
        $porMetodo[$metodo] = ['metodo_pago' => $metodo, 'pedidos' => 0, 'total' => 0.0];
    }
    $porMetodo[$metodo]['pedidos']++;
    $porMetodo[$metodo]['total'] += $total;
}
usort($porMetodo, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);

$totales = [
    'pedidos' => count($ventas),
    'ventas'  => $totalVentas,
    'ticket'  => $ventas === [] ? 0.0 : $totalVentas / count($ventas),
];

$topProductos = consultarProcedimiento($conn, 'sp_reporte_top_productos', [$desde, $hasta, 10]);
$ingresosCategoria = consultarProcedimiento($conn, 'sp_reporte_ingresos_categoria', [$desde, $hasta]);
$clientes = consultarProcedimiento($conn, 'sp_reporte_clientes_vip', [$desde, $hasta, 10]);
$bajoStock = consultarProcedimiento($conn, 'sp_reporte_bajo_stock', [true]);

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

// Exportación CSV del rango seleccionado.
if (($_GET['export'] ?? '') === 'ventas') {
    $nombreArchivo = 'ventas-' . $desde . '_a_' . $hasta . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    $salida = fopen('php://output', 'w');
    if ($salida !== false) {
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, ['Código', 'Cliente', 'Total', 'Método', 'Fecha de pago'], ';');
        foreach ($ventas as $venta) {
            fputcsv($salida, [
                $venta['codigo'],
                $venta['cliente'],
                number_format((float) $venta['total'], 2, ',', ''),
                $venta['metodo_pago'] ?? '',
                (string) $venta['fecha'],
            ], ';');
        }
        fclose($salida);
    }
    exit;
}

$rangoTexto = $desde === $hasta
    ? date('d/m/Y', strtotime($desde))
    : date('d/m/Y', strtotime($desde)) . ' – ' . date('d/m/Y', strtotime($hasta));

cabeceraHtml('Reportes | CoffeeAndtea', 'reportes');
?>

<section class="pagina-cabecera">
    <div>
        <p class="eyebrow">Analítica del negocio</p>
        <h1>Reportes</h1>
        <p>Ventas, productos, clientes y caja. Los importes usan el precio histórico de cada pedido.</p>
    </div>
    <div class="cabecera-acciones">
        <a class="btn btn-secundario" href="<?= h(url('gestion.php')) ?>">Volver a gestión</a>
        <a class="btn btn-primario" href="<?= h(url('reportes.php?export=ventas&desde=' . urlencode($desde) . '&hasta=' . urlencode($hasta))) ?>">
            Exportar CSV (<?= h($rangoTexto) ?>)
        </a>
    </div>
</section>

<div class="barra-filtros">
    <form class="filtros-periodo" method="get" action="<?= h(url('reportes.php')) ?>">
        <div class="campo-filtro">
            <label for="desde">Desde</label>
            <input id="desde" name="desde" type="date" value="<?= h($desde) ?>" max="<?= h($hoy) ?>" required>
        </div>
        <div class="campo-filtro">
            <label for="hasta">Hasta</label>
            <input id="hasta" name="hasta" type="date" value="<?= h($hasta) ?>" max="<?= h($hoy) ?>" required>
        </div>
        <button class="btn btn-primario" type="submit">Aplicar</button>
        <a class="btn btn-fantasma" href="<?= h(url('reportes.php')) ?>">Hoy</a>
        <span class="periodo-texto">Periodo: <?= h($rangoTexto) ?></span>
    </form>
</div>

<div class="stats-grid">
    <div class="stat stat-destacado">
        <span>Ventas del periodo</span><strong><?= h(moneda($totales['ventas'])) ?></strong>
    </div>
    <div class="stat">
        <span>Pedidos pagados</span><strong><?= (int) $totales['pedidos'] ?></strong>
    </div>
    <div class="stat">
        <span>Ticket promedio</span><strong><?= h(moneda($totales['ticket'])) ?></strong>
    </div>
    <?php foreach ($porMetodo as $metodo): ?>
        <div class="stat">
            <span><?= h((string) $metodo['metodo_pago']) ?></span>
            <strong><?= h(moneda((float) $metodo['total'])) ?></strong>
            <small><?= (int) $metodo['pedidos'] ?> pedidos</small>
        </div>
    <?php endforeach; ?>
</div>

<div class="layout-reportes">
    <section class="panel">
        <div class="panel-head">
            <h2>Ventas del periodo</h2>
            <span class="texto-suave"><?= count($ventas) ?> registros</span>
        </div>
        <?php if ($ventas === []): ?>
            <div class="estado-vacio compacto"><p>No hay ventas registradas en este periodo.</p></div>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Código</th><th>Cliente</th><th>Método</th><th>Fecha</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($ventas as $venta): ?>
                            <tr>
                                <td><?= h((string) $venta['codigo']) ?></td>
                                <td><?= h((string) $venta['cliente']) ?></td>
                                <td><?= h((string) ($venta['metodo_pago'] ?? '—')) ?></td>
                                <td><?= h(fechaBonita($venta['fecha'] ?? null)) ?></td>
                                <td><?= h(moneda((float) $venta['total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Top productos</h2>
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
        <h2>Ingresos por categoría</h2>
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
        <h2>Clientes con mayor consumo</h2>
        <?php if ($clientes === []): ?>
            <p class="texto-suave">Sin clientes con pedidos en el periodo.</p>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Cliente</th><th>Correo</th><th>Pedidos</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><strong><?= h((string) $cliente['cliente']) ?></strong></td>
                                <td><?= h((string) $cliente['correo']) ?></td>
                                <td><?= (int) $cliente['pedidos'] ?></td>
                                <td><?= h(moneda((float) $cliente['total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
