<?php
/** Tabla de clientes. Variables: $registros. */
?>
<?php if (empty($registros)): ?>
    <p class="tabla-vacia">Todavía no hay clientes registrados.</p>
<?php else: ?>
    <div class="tabla-scroll">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th>Consentimiento</th>
                    <th>Pedidos</th>
                    <th>Estado</th>
                    <th class="col-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $cliente): ?>
                    <tr>
                        <td class="num">#<?= (int) $cliente['id'] ?></td>
                        <td><strong><?= h((string) $cliente['nombre']) ?></strong></td>
                        <td><?= h((string) ($cliente['correo'] ?? '—')) ?></td>
                        <td><?= h((string) ($cliente['telefono'] ?? '—')) ?></td>
                        <td>
                            <span class="badge <?= esVerdadero($cliente['consentimiento']) ? 'badge-activo' : 'badge-inactivo' ?>">
                                <?= esVerdadero($cliente['consentimiento']) ? 'Sí' : 'No' ?>
                            </span>
                        </td>
                        <td><span class="badge badge-neutro"><?= (int) $cliente['pedidos_count'] ?></span></td>
                        <td>
                            <span class="badge <?= esVerdadero($cliente['activo']) ? 'badge-activo' : 'badge-inactivo' ?>">
                                <?= esVerdadero($cliente['activo']) ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td class="col-acciones">
                            <div class="acciones">
                                <a class="btn btn-pequeno btn-secundario"
                                   href="gestion.php?tabla=cliente&amp;editar=<?= (int) $cliente['id'] ?>#gestion">Editar</a>
                                <form method="post" action="gestion.php" class="accion-form"
                                      onsubmit="return confirm('¿Eliminar el cliente <?= h(addslashes((string) $cliente['nombre'])) ?>?');">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="tabla" value="cliente">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
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
