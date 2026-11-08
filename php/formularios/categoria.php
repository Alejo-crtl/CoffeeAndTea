<?php
/** Formulario de categorías. Variables: $modoEdicion, $idEdicion, $valores. */
?>
<form class="form-card" method="post" action="gestion.php">
    <?= campoCsrf() ?>
    <input type="hidden" name="tabla" value="categoria">
    <input type="hidden" name="accion" value="<?= $modoEdicion ? 'actualizar' : 'crear' ?>">
    <?php if ($modoEdicion): ?>
        <input type="hidden" name="id" value="<?= (int) $idEdicion ?>">
    <?php endif; ?>

    <div class="form-card-head">
        <div>
            <p class="eyebrow"><?= $modoEdicion ? 'Editando registro' : 'Nuevo registro' ?></p>
            <h3><?= $modoEdicion ? 'Editar categoría #' . (int) $idEdicion : 'Nueva categoría' ?></h3>
        </div>
        <?php if ($modoEdicion): ?>
            <a class="btn btn-fantasma" href="gestion.php?tabla=categoria#gestion">Cancelar edición</a>
        <?php endif; ?>
    </div>

    <p class="form-help">Los campos marcados con <strong>*</strong> son obligatorios.</p>

    <div class="form-grid">
        <div class="campo campo-completo">
            <label for="cat-nombre">Nombre *</label>
            <input type="text" name="nombre" id="cat-nombre" maxlength="100"
                   placeholder="Ej. Bebidas Calientes" value="<?= h((string) $valores['nombre']) ?>" required>
        </div>

        <div class="campo campo-completo">
            <label for="cat-descripcion">Descripción</label>
            <textarea name="descripcion" id="cat-descripcion" maxlength="500"
                      placeholder="Descripción corta de la categoría (opcional)"><?= h((string) $valores['descripcion']) ?></textarea>
        </div>

        <div class="campo campo-completo">
            <label class="checkbox" for="cat-activo">
                <input type="checkbox" name="activo" id="cat-activo" value="1" <?= $valores['activo'] ? 'checked' : '' ?>>
                <span>Categoría activa</span>
            </label>
            <p class="hint">Las categorías inactivas no aparecen al crear productos nuevos, pero conservan su historial.</p>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn"><?= $modoEdicion ? 'Guardar cambios' : 'Crear categoría' ?></button>
        <button type="reset" class="btn btn-secundario">Restablecer</button>
    </div>
</form>
