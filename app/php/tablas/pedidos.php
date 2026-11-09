<?php
/** Tabla de pedidos. Variables: $registros. */
$clasesEstado = [
    'ABIERTO'  => 'badge-abierto',
    'PAGADO'   => 'badge-pagado',
    'CANCELADO' => 'badge-cancelado',
];
?>
<?php if (empty($registros)): ?>
    <p class="tabla-vacia">Todavía no hay pedidos registrados.</p>
<?php else: ?>
    <div class="tabla-scroll">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Estado</th>
                    <th>Total</th>
                    <th>Detalles</th>
                    <th>Fecha de creación</th>
                    <th>Fecha de cierre</th>
                    <th class="col-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $pedido): ?>
                    <?php $claseEstado = $clasesEstado[(string) $pedido['estado']] ?? 'badge-neutro'; ?>
                    <tr>
                        <td class="num">#<?= (int) $pedido['id'] ?></td>
                        <td><strong><?= h((string) $pedido['cliente']) ?></strong></td>
                        <td><span class="badge <?= h($claseEstado) ?>"><?= h((string) $pedido['estado']) ?></span></td>
                        <td class="num"><?= h(moneda($pedido['total'])) ?></td>
                        <td><span class="badge badge-neutro"><?= (int) $pedido['detalles_count'] ?></span></td>
                        <td class="fecha"><?= h(fechaBonita($pedido['fecha_creacion'] ?? null)) ?></td>
                        <td class="fecha"><?= h(fechaBonita($pedido['fecha_cierre'] ?? null)) ?></td>
                        <td class="col-acciones">
                            <div class="acciones">
                                <a class="btn btn-pequeno btn-secundario"
                                   href="gestion.php?tabla=pedido&amp;editar=<?= (int) $pedido['id'] ?>#gestion">Editar</a>
                                <form method="post" action="gestion.php" class="accion-form"
                                      onsubmit="return confirm('¿Eliminar el pedido #<?= (int) $pedido['id'] ?>? También se eliminarán sus <?= (int) $pedido['detalles_count'] ?> detalle(s).');">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="tabla" value="pedido">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int) $pedido['id'] ?>">
                                    <button type="submit" class="btn btn-peligro btn-pequeno">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
