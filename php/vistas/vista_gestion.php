<?php
/** Sección de gestión de datos (CRUD). */
$pestanasGestion = [
    'categoria' => ['titulo' => 'Categorías', 'conteo' => (int) ($conteos['categorias'] ?? 0)],
    'cliente'   => ['titulo' => 'Clientes', 'conteo' => (int) ($conteos['clientes'] ?? 0)],
    'producto'  => ['titulo' => 'Productos', 'conteo' => (int) ($conteos['productos'] ?? 0)],
    'pedido'    => ['titulo' => 'Pedidos', 'conteo' => (int) ($conteos['pedidos'] ?? 0)],
    'detalle'   => ['titulo' => 'Detalles', 'conteo' => (int) ($conteos['detalles'] ?? 0)],
];

$archivosTabla = [
    'categoria' => 'categorias',
    'cliente'   => 'clientes',
    'producto'  => 'productos',
    'pedido'    => 'pedidos',
    'detalle'   => 'detalles',
];

$archivoFormulario = [
    'categoria' => 'categoria',
    'cliente'   => 'cliente',
    'producto'  => 'producto',
    'pedido'    => 'pedido',
    'detalle'   => 'detalle',
];

$tituloActivo = $pestanasGestion[$tablaActiva]['titulo'] ?? 'Registros';
$conteoActivo = $pestanasGestion[$tablaActiva]['conteo'] ?? count($registros);
?>
<section id="gestion" class="section section-gestion">
    <div class="section-head">
        <div>
            <p class="eyebrow">CRUD completo</p>
            <h2>Gestión de datos</h2>
            <p>
                Crea, consulta, edita y elimina registros de las cinco tablas.
                Cada pestaña tiene su formulario y su listado.
            </p>
        </div>
        <div class="section-actions">
            <a class="btn btn-secundario" href="<?= h(urlGestion($tablaActiva)) ?>">Nuevo registro</a>
        </div>
    </div>

    <nav class="tabs tabs-gestion" aria-label="Tablas del CRUD">
        <?php foreach ($pestanasGestion as $clave => $pestana): ?>
            <a class="tab <?= $clave === $tablaActiva ? 'activo' : '' ?>"
               href="<?= h(urlGestion($clave)) ?>"
               <?= $clave === $tablaActiva ? 'aria-current="page"' : '' ?>>
                <span><?= h($pestana['titulo']) ?></span>
                <span class="tab-conteo"><?= (int) $pestana['conteo'] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="gestion-layout">
        <div class="gestion-formulario">
            <?php if (!empty($erroresGestion)): ?>
                <div class="alerta-error" role="alert">
                    <strong>Revisa la información:</strong>
                    <ul>
                        <?php foreach ($erroresGestion as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php require_once __DIR__ . '/../formularios/' . $archivoFormulario[$tablaActiva] . '.php'; ?>
        </div>

        <div class="gestion-listado">
            <div class="card listado-card">
                <div class="listado-head">
                    <div>
                        <p class="eyebrow">Consulta</p>
                        <h3>Listado de <?= h(mb_strtolower($tituloActivo)) ?></h3>
                    </div>
                    <span class="badge badge-neutro"><?= (int) $conteoActivo ?> registro(s)</span>
                </div>

                <p class="hint listado-ayuda">
                    <?php if ($tablaActiva === 'detalle'): ?>
                        Agrega varios productos distintos en un mismo envío; cada producto admite una sola fila por pedido. Al editar, el total se recalcula.
                    <?php elseif ($tablaActiva === 'pedido'): ?>
                        Eliminar un pedido borra también todos sus detalles, en una sola transacción.
                    <?php else: ?>
                        Si el registro tiene datos relacionados, se recomienda desactivarlo en lugar de eliminarlo.
                    <?php endif; ?>
                </p>

                <?php require_once __DIR__ . '/../tablas/' . $archivosTabla[$tablaActiva] . '.php'; ?>
            </div>
        </div>
    </div>
</section>
