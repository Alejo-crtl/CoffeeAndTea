<?php
/**
 * Formulario de detalles de pedido.
 *
 * Variables: $modoEdicion, $idEdicion, $valores, $pedidosTodos,
 *            $productosTodos y $detallesPorPedido.
 *
 * Al crear se envían varias filas (detalles[n][id_producto] y
 * detalles[n][cantidad]); al editar se mantiene una sola fila con los nombres
 * id_producto y cantidad, porque se edita un detalle existente.
 */
$productosFormulario = opcionesActivas(
    $productosTodos,
    isset($valores['id_producto']) && $valores['id_producto'] !== '' ? (int) $valores['id_producto'] : null
);

if ($modoEdicion) {
    $lineasFormulario = [[
        'id_producto' => (string) ($valores['id_producto'] ?? ''),
        'cantidad'    => (string) ($valores['cantidad'] ?? '1'),
        'error'       => false,
    ]];
} else {
    $lineasFormulario = is_array($valores['lineas'] ?? null) ? $valores['lineas'] : [];
    if (empty($lineasFormulario)) {
        $lineasFormulario = [['id_producto' => '', 'cantidad' => '1', 'error' => false]];
    }
}

$pedidoSeleccionado = (string) ($valores['id_pedido'] ?? '');
$hayPedidos = !empty($pedidosTodos);
$hayProductos = !empty($productosFormulario);
$ocupadosPorPedido = is_array($detallesPorPedido ?? null) ? $detallesPorPedido : [];

/** Imprime las opciones de producto, marcando la elegida si la hay. */
$opcionesProducto = static function (string $seleccionado) use ($productosFormulario): void {
    foreach ($productosFormulario as $producto) {
        $valor = (string) $producto['id'];
        $texto = $producto['nombre'] . ' — ' . moneda($producto['precio'])
            . ' · stock ' . (int) $producto['stock_actual'];
        if (!esVerdadero($producto['activo'])) {
            $texto .= ' (inactivo)';
        }
        echo '<option value="' . h($valor) . '"'
            . ($valor === $seleccionado ? ' selected' : '')
            . '>' . h($texto) . '</option>';
    }
};
?>
<form class="form-card" method="post" action="gestion.php" id="detalle-form"
      data-modo="<?= $modoEdicion ? 'editar' : 'crear' ?>">
    <?= campoCsrf() ?>
    <input type="hidden" name="tabla" value="detalle">
    <input type="hidden" name="accion" value="<?= $modoEdicion ? 'actualizar' : 'crear' ?>">
    <?php if ($modoEdicion): ?>
        <input type="hidden" name="id" value="<?= (int) $idEdicion ?>">
    <?php endif; ?>

    <div class="form-card-head">
        <div>
            <p class="eyebrow"><?= $modoEdicion ? 'Editando registro' : 'Nuevo registro' ?></p>
            <h3><?= $modoEdicion ? 'Editar detalle #' . (int) $idEdicion : 'Nuevo detalle de pedido' ?></h3>
        </div>
        <?php if ($modoEdicion): ?>
            <a class="btn btn-fantasma" href="gestion.php?tabla=detalle#gestion">Cancelar edición</a>
        <?php endif; ?>
    </div>

    <?php if (!$hayPedidos || !$hayProductos): ?>
        <div class="alerta-info">
            <?php if (!$hayPedidos): ?>
                No hay pedidos registrados. <a href="gestion.php?tabla=pedido#gestion">Crea un pedido</a>.
            <?php endif; ?>
            <?php if (!$hayProductos): ?>
                No hay productos activos. <a href="gestion.php?tabla=producto#gestion">Crea o activa un producto</a>.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <p class="form-help">Los campos marcados con <strong>*</strong> son obligatorios.</p>

    <div class="campo">
        <label for="det-pedido">Pedido *</label>
        <select name="id_pedido" id="det-pedido" required <?= $hayPedidos ? '' : 'disabled' ?>>
            <option value="">— Selecciona un pedido —</option>
            <?php foreach ($pedidosTodos as $pedido): ?>
                <option value="<?= (int) $pedido['id'] ?>"
                    <?= ((string) $pedido['id'] === $pedidoSeleccionado) ? 'selected' : '' ?>>
                    <?= h('Pedido #' . $pedido['id'] . ' — ' . $pedido['cliente'] . ' (' . $pedido['estado'] . ') — ' . moneda($pedido['total'])) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($modoEdicion): ?>
        <div class="form-grid">
            <div class="campo">
                <label for="det-0-producto">Producto *</label>
                <select name="id_producto" id="det-0-producto" required <?= $hayProductos ? '' : 'disabled' ?>>
                    <option value="">— Selecciona un producto —</option>
                    <?php $opcionesProducto((string) $lineasFormulario[0]['id_producto']); ?>
                </select>
            </div>

            <div class="campo">
                <label for="det-0-cantidad">Cantidad *</label>
                <input type="number" name="cantidad" id="det-0-cantidad" step="1" min="1"
                       placeholder="Ej. 2" value="<?= h((string) $lineasFormulario[0]['cantidad']) ?>" required>
            </div>
        </div>
    <?php else: ?>
        <div class="detalle-lineas" id="detalle-lineas">
            <?php foreach ($lineasFormulario as $indice => $linea): ?>
                <?php $conError = !empty($linea['error']); ?>
                <div class="detalle-linea<?= $conError ? ' detalle-linea-error' : '' ?>">
                    <div class="campo campo-producto">
                        <label for="det-<?= (int) $indice ?>-producto">Producto *</label>
                        <select name="detalles[<?= (int) $indice ?>][id_producto]"
                                id="det-<?= (int) $indice ?>-producto" required
                                <?= $hayProductos ? '' : 'disabled' ?>
                                <?= $conError ? 'aria-invalid="true"' : '' ?>>
                            <option value="">— Selecciona un producto —</option>
                            <?php $opcionesProducto((string) $linea['id_producto']); ?>
                        </select>
                    </div>

                    <div class="campo campo-cantidad">
                        <label for="det-<?= (int) $indice ?>-cantidad">Cantidad *</label>
                        <input type="number" name="detalles[<?= (int) $indice ?>][cantidad]"
                               id="det-<?= (int) $indice ?>-cantidad" step="1" min="1"
                               value="<?= h((string) $linea['cantidad']) ?>" required
                               <?= $conError ? 'aria-invalid="true"' : '' ?>>
                    </div>

                    <button type="button" class="btn-quitar" aria-label="Quitar el producto <?= (int) $indice + 1 ?>"
                            <?= count($lineasFormulario) === 1 ? 'disabled' : '' ?>>×</button>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="detalle-lineas-acciones">
            <button type="button" class="btn btn-secundario" id="detalle-agregar"
                    <?= ($hayPedidos && $hayProductos) ? '' : 'disabled' ?>>
                + Agregar otro producto
            </button>
            <span class="hint">Cada producto puede aparecer una sola vez por pedido.</span>
        </div>

        <template id="detalle-linea-plantilla">
            <div class="detalle-linea">
                <div class="campo campo-producto">
                    <label for="det-__i__-producto">Producto *</label>
                    <select name="detalles[__i__][id_producto]" id="det-__i__-producto" required <?= $hayProductos ? '' : 'disabled' ?>>
                        <option value="">— Selecciona un producto —</option>
                        <?php $opcionesProducto(''); ?>
                    </select>
                </div>

                <div class="campo campo-cantidad">
                    <label for="det-__i__-cantidad">Cantidad *</label>
                    <input type="number" name="detalles[__i__][cantidad]" id="det-__i__-cantidad"
                           step="1" min="1" value="1" required>
                </div>

                <button type="button" class="btn-quitar" aria-label="Quitar producto">×</button>
            </div>
        </template>

        <script type="application/json" id="detalle-ocupados"><?= json_encode($ocupadosPorPedido, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php endif; ?>

    <p class="hint">
        El total del pedido se recalcula automáticamente al crear, editar o eliminar el detalle.
    </p>

    <div class="form-actions">
        <button type="submit" class="btn" <?= ($hayPedidos && $hayProductos) ? '' : 'disabled' ?>>
            <?= $modoEdicion ? 'Guardar cambios' : 'Agregar productos' ?>
        </button>
        <button type="reset" class="btn btn-secundario">Restablecer</button>
    </div>
</form>
