<?php
/** Formulario de clientes. Variables: $modoEdicion, $idEdicion, $valores. */
?>
<form class="form-card" method="post" action="gestion.php">
    <?= campoCsrf() ?>
    <input type="hidden" name="tabla" value="cliente">
    <input type="hidden" name="accion" value="<?= $modoEdicion ? 'actualizar' : 'crear' ?>">
    <?php if ($modoEdicion): ?>
        <input type="hidden" name="id" value="<?= (int) $idEdicion ?>">
    <?php endif; ?>

    <div class="form-card-head">
        <div>
            <p class="eyebrow"><?= $modoEdicion ? 'Editando registro' : 'Nuevo registro' ?></p>
            <h3><?= $modoEdicion ? 'Editar cliente #' . (int) $idEdicion : 'Nuevo cliente' ?></h3>
        </div>
        <?php if ($modoEdicion): ?>
            <a class="btn btn-fantasma" href="gestion.php?tabla=cliente#gestion">Cancelar edición</a>
        <?php endif; ?>
    </div>

    <p class="form-help">Los campos marcados con <strong>*</strong> son obligatorios.</p>

    <div class="form-grid">
        <div class="campo">
            <label for="cli-nombre">Nombre *</label>
            <input type="text" name="nombre" id="cli-nombre" minlength="3" maxlength="150"
                   autocomplete="name" value="<?= h((string) $valores['nombre']) ?>" required>
        </div>

        <div class="campo">
            <label for="cli-correo">Correo electrónico *</label>
            <input type="email" name="correo" id="cli-correo" maxlength="150"
                   autocomplete="email" inputmode="email" value="<?= h((string) $valores['correo']) ?>" required>
        </div>

        <div class="campo">
            <label for="cli-telefono">Teléfono</label>
            <input type="tel" name="telefono" id="cli-telefono" maxlength="20"
                   autocomplete="tel" inputmode="tel" placeholder="Ej. 3001234567"
                   value="<?= h((string) $valores['telefono']) ?>">
        </div>

        <div class="campo">
            <label class="checkbox" for="cli-activo">
                <input type="checkbox" name="activo" id="cli-activo" value="1" <?= $valores['activo'] ? 'checked' : '' ?>>
                <span>Cliente activo</span>
            </label>
            <p class="hint">Los clientes inactivos no aparecen al crear pedidos nuevos.</p>
        </div>

        <div class="campo campo-completo">
            <label class="checkbox" for="cli-consentimiento">
                <input type="checkbox" name="consentimiento" id="cli-consentimiento" value="1"
                       <?= $valores['consentimiento'] ? 'checked' : '' ?>
                       <?= $modoEdicion ? '' : 'required' ?>>
                <span>Autorizo el tratamiento de datos personales conforme a la Ley 1581 de 2012. <?= $modoEdicion ? '' : '*' ?></span>
            </label>
            <p class="hint">
                <?= $modoEdicion
                    ? 'Puedes retirar el consentimiento desmarcando esta casilla.'
                    : 'El consentimiento es obligatorio para crear un cliente nuevo.' ?>
            </p>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn"><?= $modoEdicion ? 'Guardar cambios' : 'Crear cliente' ?></button>
        <button type="reset" class="btn btn-secundario">Restablecer</button>
    </div>
</form>
