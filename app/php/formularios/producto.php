<?php
/** Formulario de productos. Variables: $modoEdicion, $idEdicion, $valores, $categoriasTodas. */
$categoriasFormulario = opcionesActivas(
    $categoriasTodas,
    isset($valores['categoria_id']) && $valores['categoria_id'] !== '' ? (int) $valores['categoria_id'] : null
);
?>
<form class="form-card" method="post" action="gestion.php">
    <?= campoCsrf() ?>
    <input type="hidden" name="tabla" value="producto">
    <input type="hidden" name="accion" value="<?= $modoEdicion ? 'actualizar' : 'crear' ?>">
    <?php if ($modoEdicion): ?>
        <input type="hidden" name="id" value="<?= (int) $idEdicion ?>">
    <?php endif; ?>

    <div class="form-card-head">
        <div>
            <p class="eyebrow"><?= $modoEdicion ? 'Editando registro' : 'Nuevo registro' ?></p>
            <h3><?= $modoEdicion ? 'Editar producto #' . (int) $idEdicion : 'Nuevo producto' ?></h3>
        </div>
        <?php if ($modoEdicion): ?>
            <a class="btn btn-fantasma" href="gestion.php?tabla=producto#gestion">Cancelar edición</a>
        <?php endif; ?>
    </div>

    <?php if (empty($categoriasFormulario)): ?>
        <div class="alerta-info">
            No hay categorías activas disponibles. Primero crea o activa una categoría.
            <a href="gestion.php?tabla=categoria#gestion">Ir a Categorías</a>.
        </div>
    <?php endif; ?>

    <p class="form-help">Los campos marcados con <strong>*</strong> son obligatorios.</p>

    <div class="form-grid">
        <div class="campo">
            <label for="pro-nombre">Nombre del producto *</label>
            <input type="text" name="nombre" id="pro-nombre" maxlength="150"
                   placeholder="Ej. Capuchino Grande" value="<?= h((string) $valores['nombre']) ?>" required>
        </div>

        <div class="campo">
            <label for="pro-unidades">Unidad de medida *</label>
            <input type="text" name="unidades_medida" id="pro-unidades" list="lista-unidades"
                   maxlength="50" placeholder="Ej. ml, unidades, gramos"
                   value="<?= h((string) $valores['unidades_medida']) ?>" required>
            <datalist id="lista-unidades">
                <option value="ml"></option>
                <option value="unidades"></option>
                <option value="gramos"></option>
            </datalist>
        </div>

        <div class="campo">
            <label for="pro-precio">Precio *</label>
            <input type="number" name="precio" id="pro-precio" step="0.01" min="0.01"
                   max="9999999999.99" placeholder="Ej. 5.50"
                   value="<?= h((string) $valores['precio']) ?>" required>
        </div>

        <div class="campo">
            <label for="pro-stock">Stock actual *</label>
            <input type="number" name="stock_actual" id="pro-stock" step="1" min="0"
                   placeholder="Ej. 20" value="<?= h((string) $valores['stock_actual']) ?>" required>
        </div>

        <div class="campo">
            <label for="pro-categoria">Categoría *</label>
            <select name="categoria_id" id="pro-categoria" required <?= empty($categoriasFormulario) ? 'disabled' : '' ?>>
                <option value="">— Selecciona una categoría —</option>
                <?php foreach ($categoriasFormulario as $categoria): ?>
                    <option value="<?= (int) $categoria['id'] ?>"
                        <?= ((string) $categoria['id'] === (string) $valores['categoria_id']) ? 'selected' : '' ?>>
                        <?= h((string) $categoria['nombre']) ?><?= esVerdadero($categoria['activo']) ? '' : ' (inactiva)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label class="checkbox" for="pro-activo">
                <input type="checkbox" name="activo" id="pro-activo" value="1" <?= $valores['activo'] ? 'checked' : '' ?>>
                <span>Producto activo</span>
            </label>
            <p class="hint">Los productos inactivos no se pueden agregar a pedidos nuevos.</p>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn" <?= empty($categoriasFormulario) ? 'disabled' : '' ?>>
            <?= $modoEdicion ? 'Guardar cambios' : 'Crear producto' ?>
        </button>
        <button type="reset" class="btn btn-secundario">Restablecer</button>
    </div>
</form>
