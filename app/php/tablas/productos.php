<?php
/** Tabla de productos. Variables: $registros. */
?>
<?php if (empty($registros)): ?>
    <p class="tabla-vacia">Todavía no hay productos registrados.</p>
<?php else: ?>
    <div class="tabla-scroll">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Unidad</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th>Detalles</th>
                    <th>Estado</th>
                    <th class="col-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $producto): ?>
                    <?php $stockBajo = (int) $producto['stock_actual'] < 30; ?>
                    <tr>
                        <td class="num">#<?= (int) $producto['id'] ?></td>
                        <td><strong><?= h((string) $producto['nombre']) ?></strong></td>
                        <td><?= h((string) $producto['categoria']) ?></td>
                        <td><?= h((string) $producto['unidades_medida']) ?></td>
                        <td class="num"><?= h(moneda($producto['precio'])) ?></td>
                        <td>
                            <span class="badge <?= $stockBajo ? 'badge-alerta' : 'badge-neutro' ?>">
                                <?= (int) $producto['stock_actual'] ?>
                            </span>
                        </td>
                        <td><span class="badge badge-neutro"><?= (int) $producto['detalles_count'] ?></span></td>
                        <td>
                            <span class="badge <?= esVerdadero($producto['activo']) ? 'badge-activo' : 'badge-inactivo' ?>">
                                <?= esVerdadero($producto['activo']) ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td class="col-acciones">
                            <div class="acciones">
                                <a class="btn btn-pequeno btn-secundario"
                                   href="gestion.php?tabla=producto&amp;editar=<?= (int) $producto['id'] ?>#gestion">Editar</a>
                                <form method="post" action="gestion.php" class="accion-form"
                                      onsubmit="return confirm('¿Eliminar el producto <?= h(addslashes((string) $producto['nombre'])) ?>?');">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="tabla" value="producto">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int) $producto['id'] ?>">
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
