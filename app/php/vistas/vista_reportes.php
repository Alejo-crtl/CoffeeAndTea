<?php
/** Sección de reportes. */
?>
<section id="reportes" class="section section-reportes">
    <div class="section-head">
        <div>
            <p class="eyebrow">Consultas del sistema</p>
            <h2>Reportes</h2>
            <p>
                Resultados calculados por los procedimientos almacenados de PostgreSQL.
                Elige una pestaña para consultar cada reporte.
            </p>
        </div>
        <div class="section-actions">
            <span class="chip chip-suave">5 procedimientos</span>
        </div>
    </div>

    <nav class="tabs tabs-reportes" aria-label="Reportes disponibles">
        <?php foreach ($reportesDefiniciones as $clave => $definicion): ?>
            <a class="tab <?= $clave === $reporteActivo ? 'activo' : '' ?>"
               href="reportes.php?reporte=<?= h($clave) ?>#reportes"
               <?= $clave === $reporteActivo ? 'aria-current="page"' : '' ?>>
                <span><?= h($definicion['titulo']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <article class="card reporte-card">
        <div class="reporte-head">
            <div>
                <p class="eyebrow">Procedimiento almacenado</p>
                <h3><?= h($reporteInfo['titulo']) ?></h3>
                <p><?= h($reporteInfo['descripcion']) ?></p>
            </div>
            <code class="chip chip-codigo"><?= h($reporteInfo['procedimiento']) ?></code>
        </div>

        <?php if ($reporteActivo === 'stock'): ?>
            <form class="form-inline" method="get" action="reportes.php#reportes">
                <input type="hidden" name="reporte" value="stock">
                <input type="hidden" name="seccion" value="reportes">
                <label for="limite-stock">Mostrar productos con stock menor que:</label>
                <input type="number" name="limite" id="limite-stock" min="1" max="9999" step="1"
                       value="<?= (int) $limiteStock ?>" required>
                <button type="submit" class="btn btn-pequeno">Actualizar reporte</button>
            </form>
        <?php endif; ?>

        <?php if ($errorReporte !== null): ?>
            <div class="alerta-error" role="alert">
                <strong>No se pudo generar el reporte.</strong>
                <p><?= h($errorReporte) ?></p>
            </div>
        <?php elseif (empty($filasReporte)): ?>
            <div class="reporte-vacio">
                <?php if ($reporteActivo === 'ventas'): ?>
                    <h4>No hay ventas pagadas hoy</h4>
                    <p>
                        El reporte consulta pedidos con estado <strong>PAGADO</strong> creados el
                        <strong><?= h(date('d/m/Y')) ?></strong>. Los pedidos de ejemplo tienen fechas anteriores.
                    </p>
                    <a class="btn btn-pequeno" href="gestion.php?tabla=pedido#gestion">Crear un pedido</a>
                <?php elseif ($reporteActivo === 'stock'): ?>
                    <h4>No hay productos por debajo de <?= (int) $limiteStock ?></h4>
                    <p>Todos los productos activos tienen existencias suficientes para ese límite.</p>
                <?php else: ?>
                    <h4>Sin resultados</h4>
                    <p>No hay datos que cumplan las condiciones de este reporte en este momento.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="reporte-resumen">
                <span class="chip chip-suave"><?= count($filasReporte) ?> fila(s)</span>
                <?php if ($totalColumna !== null): ?>
                    <span class="chip chip-suave">Total: <strong><?= h(moneda($totalColumna)) ?></strong></span>
                <?php endif; ?>
                <?php if ($reporteActivo === 'stock'): ?>
                    <span class="chip chip-suave">Límite: <?= (int) $limiteStock ?></span>
                <?php endif; ?>
            </div>

            <div class="tabla-scroll">
                <table class="tabla-datos tabla-reporte">
                    <thead>
                        <tr>
                            <?php foreach ($reporteInfo['columnas'] as $columna): ?>
                                <th><?= h($columna['etiqueta']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filasReporte as $fila): ?>
                            <tr>
                                <?php foreach ($reporteInfo['columnas'] as $clave => $columna): ?>
                                    <?php
                                    $valor = $fila[$clave] ?? null;
                                    $tipo = $columna['tipo'];
                                    ?>
                                    <td class="<?= in_array($tipo, ['moneda', 'entero', 'pedido'], true) ? 'num' : '' ?>">
                                        <?php if ($valor === null || $valor === ''): ?>
                                            <span class="vacio">—</span>
                                        <?php elseif ($tipo === 'moneda'): ?>
                                            <?= h(moneda($valor)) ?>
                                        <?php elseif ($tipo === 'fecha'): ?>
                                            <?= h(fechaBonita((string) $valor)) ?>
                                        <?php elseif ($tipo === 'pedido'): ?>
                                            <strong>#<?= (int) $valor ?></strong>
                                        <?php elseif ($tipo === 'entero'): ?>
                                            <?= (int) $valor ?>
                                        <?php else: ?>
                                            <?= h((string) $valor) ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="reporte-pie">
            <span>
                Procedimiento:
                <code><?= h($reporteActivo === 'stock'
                    ? 'CALL sp_reporte_bajo_stock(' . $limiteStock . ", 'cursor')"
                    : 'CALL ' . $reporteInfo['procedimiento'] . "('cursor')") ?></code>
            </span>
            <span>Consultado: <?= h(date('d/m/Y H:i')) ?></span>
        </div>
    </article>
</section>
