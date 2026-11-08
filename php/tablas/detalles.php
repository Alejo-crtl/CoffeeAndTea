<?php
/** Tabla de detalles de pedido. Variables: $registros. */
?>
<?php if (empty($registros)): ?>
    <p class="tabla-vacia">Todavía no hay detalles de pedido registrados.</p>
<?php else: ?>
    <div class="tabla-scroll">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Pedido</th>
                    <th>Cliente</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Subtotal</th>
                    <th class="col-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $detalle): ?>
                    <tr>
                        <td class="num">#<?= (int) $detalle['id'] ?></td>
                        <td>
                            <span class="pedido-ref">
                                <strong>#<?= (int) $detalle['pedido_numero'] ?></strong>
                                <span class="badge badge-neutro"><?= h((string) $detalle['estado']) ?></span>
                            </span>
                        </td>
                        <td><?= h((string) $detalle['cliente']) ?></td>
                        <td><?= h((string) $detalle['producto']) ?></td>
                        <td><?= (int) $detalle['cantidad'] ?></td>
                        <td class="num"><?= h(moneda($detalle['precio'])) ?></td>
                        <td class="num"><strong><?= h(moneda($detalle['subtotal'])) ?></strong></td>
                        <td class="col-acciones">
                            <div class="acciones">
                                <a class="btn btn-pequeno btn-secundario"
                                   href="gestion.php?tabla=detalle&amp;editar=<?= (int) $detalle['id'] ?>#gestion">Editar</a>
                                <form method="post" action="gestion.php" class="accion-form"
                                      onsubmit="return confirm('¿Eliminar el detalle #<?= (int) $detalle['id'] ?>? El total del pedido se recalculará.');">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="tabla" value="detalle">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int) $detalle['id'] ?>">
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
