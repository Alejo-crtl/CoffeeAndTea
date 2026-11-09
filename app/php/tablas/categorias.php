<?php
/** Tabla de categorías. Variables: $registros. */
?>
<?php if (empty($registros)): ?>
    <p class="tabla-vacia">Todavía no hay categorías registradas.</p>
<?php else: ?>
    <div class="tabla-scroll">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Productos</th>
                    <th>Estado</th>
                    <th>Creada</th>
                    <th class="col-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $categoria): ?>
                    <tr>
                        <td class="num">#<?= (int) $categoria['id'] ?></td>
                        <td><strong><?= h((string) $categoria['nombre']) ?></strong></td>
                        <td class="texto-largo"><?= $categoria['descripcion'] !== null && $categoria['descripcion'] !== '' ? h((string) $categoria['descripcion']) : '<span class="vacio">—</span>' ?></td>
                        <td><span class="badge badge-neutro"><?= (int) $categoria['productos_count'] ?></span></td>
                        <td>
                            <span class="badge <?= esVerdadero($categoria['activo']) ? 'badge-activo' : 'badge-inactivo' ?>">
                                <?= esVerdadero($categoria['activo']) ? 'Activa' : 'Inactiva' ?>
                            </span>
                        </td>
                        <td class="fecha"><?= h(fechaBonita($categoria['creado_en'] ?? null)) ?></td>
                        <td class="col-acciones">
                            <div class="acciones">
                                <a class="btn btn-pequeno btn-secundario"
                                   href="gestion.php?tabla=categoria&amp;editar=<?= (int) $categoria['id'] ?>#gestion">Editar</a>
                                <form method="post" action="gestion.php" class="accion-form"
                                      onsubmit="return confirm('¿Eliminar la categoría <?= h(addslashes((string) $categoria['nombre'])) ?>?');">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="tabla" value="categoria">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int) $categoria['id'] ?>">
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
