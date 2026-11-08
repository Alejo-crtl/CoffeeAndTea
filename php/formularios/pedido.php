<?php
/** Formulario de pedidos. Variables: $modoEdicion, $idEdicion, $valores, $clientesTodos. */
$clientesFormulario = opcionesActivas(
    $clientesTodos,
    isset($valores['cliente_id']) && $valores['cliente_id'] !== '' ? (int) $valores['cliente_id'] : null
);
$estadosPermitidos = ['ABIERTO', 'PAGADO', 'CANCELADO'];
?>
<form class="form-card" method="post" action="gestion.php">
    <?= campoCsrf() ?>
    <input type="hidden" name="tabla" value="pedido">
    <input type="hidden" name="accion" value="<?= $modoEdicion ? 'actualizar' : 'crear' ?>">
    <?php if ($modoEdicion): ?>
        <input type="hidden" name="id" value="<?= (int) $idEdicion ?>">
    <?php endif; ?>

    <div class="form-card-head">
        <div>
            <p class="eyebrow"><?= $modoEdicion ? 'Editando registro' : 'Nuevo registro' ?></p>
            <h3><?= $modoEdicion ? 'Editar pedido #' . (int) $idEdicion : 'Nuevo pedido' ?></h3>
        </div>
        <?php if ($modoEdicion): ?>
            <a class="btn btn-fantasma" href="gestion.php?tabla=pedido#gestion">Cancelar edición</a>
        <?php endif; ?>
    </div>

    <?php if (empty($clientesFormulario)): ?>
        <div class="alerta-info">
            No hay clientes activos disponibles. Primero crea o activa un cliente.
            <a href="gestion.php?tabla=cliente#gestion">Ir a Clientes</a>.
        </div>
    <?php endif; ?>

    <p class="form-help">Los campos marcados con <strong>*</strong> son obligatorios.</p>

    <div class="form-grid">
        <div class="campo">
            <label for="ped-cliente">Cliente *</label>
            <select name="cliente_id" id="ped-cliente" required <?= empty($clientesFormulario) ? 'disabled' : '' ?>>
                <option value="">— Selecciona un cliente —</option>
                <?php foreach ($clientesFormulario as $cliente): ?>
                    <option value="<?= (int) $cliente['id'] ?>"
                        <?= ((string) $cliente['id'] === (string) $valores['cliente_id']) ? 'selected' : '' ?>>
                        <?= h((string) $cliente['nombre']) ?><?= esVerdadero($cliente['activo']) ? '' : ' (inactivo)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="ped-estado">Estado *</label>
            <select name="estado" id="ped-estado" required>
                <?php foreach ($estadosPermitidos as $estado): ?>
                    <option value="<?= h($estado) ?>" <?= $estado === $valores['estado'] ? 'selected' : '' ?>>
                        <?= h($estado) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="ped-fecha">Fecha de cierre</label>
            <input type="datetime-local" name="fecha_cierre" id="ped-fecha"
                   value="<?= h((string) $valores['fecha_cierre']) ?>">
            <p class="hint">Opcional. Déjala vacía si el pedido sigue abierto.</p>
        </div>

        <div class="campo">
            <label for="ped-observaciones">Observaciones</label>
            <textarea name="observaciones" id="ped-observaciones" maxlength="1000"
                      placeholder="Ej. Sin azúcar, para llevar (opcional)"><?= h((string) $valores['observaciones']) ?></textarea>
        </div>
    </div>

    <p class="hint">
        El total se calcula automáticamente con los detalles del pedido; no se edita manualmente.
        <?php if ($modoEdicion): ?>
            El total actual aparece en la tabla.
        <?php endif; ?>
    </p>

    <div class="form-actions">
        <button type="submit" class="btn" <?= empty($clientesFormulario) ? 'disabled' : '' ?>>
            <?= $modoEdicion ? 'Guardar cambios' : 'Crear pedido' ?>
        </button>
        <button type="reset" class="btn btn-secundario">Restablecer</button>
    </div>
</form>
