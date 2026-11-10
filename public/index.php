<?php
/**
 * Tienda pública: catálogo, tiempo estimado y acceso al carrito.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/pedidos.php';

$categorias = $conn->query(
    'SELECT c.id, c.nombre, COUNT(p.id) AS productos
     FROM categoria c
     LEFT JOIN producto p ON p.categoria_id = c.id AND p.activo = TRUE
     WHERE c.activo = TRUE
     GROUP BY c.id, c.nombre
     HAVING COUNT(p.id) > 0
     ORDER BY c.nombre'
)->fetchAll();

$categoriaId = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT);
if ($categoriaId === false || $categoriaId === null) {
    $categoriaId = 0;
}

$sql = 'SELECT p.*, c.nombre AS categoria
        FROM producto p
        INNER JOIN categoria c ON c.id = p.categoria_id
        WHERE p.activo = TRUE AND c.activo = TRUE';
$params = [];
if ($categoriaId > 0) {
    $sql .= ' AND p.categoria_id = :categoria';
    $params[':categoria'] = $categoriaId;
}
$sql .= ' ORDER BY p.destacado DESC, c.nombre, p.nombre';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$rangoTiempo = rangoTiempoPreparacion($conn);
$tiempoMin = $rangoTiempo['min'];
$tiempoMax = $rangoTiempo['max'];
$textoTiempo = $tiempoMin === $tiempoMax
    ? '~' . $tiempoMin . ' min'
    : '~' . $tiempoMin . '–' . $tiempoMax . ' min';
$tiendaAbierta = strtolower((string) configSistema($conn, 'pagina_abierta', 'true')) !== 'false';
$esPersonal = puedeGestionar();
$carrito = $esPersonal
    ? ['items' => [], 'subtotal' => 0.0, 'unidades' => 0, 'ajustes' => []]
    : carritoDetallado($conn);
$cantidadesCarrito = [];
foreach ($carrito['items'] as $lineaCarrito) {
    $cantidadesCarrito[(int) $lineaCarrito['producto_id']] = (int) $lineaCarrito['cantidad'];
}
$ultimoPedido = $_SESSION['ultimo_pedido'] ?? null;

cabeceraHtml('CoffeeAndtea | Pide y recoge en tienda', 'tienda');
?>

<section class="hero" aria-labelledby="hero-titulo">
    <div class="hero-contenido">
        <p class="eyebrow">Café de barrio, pedidos sin filas</p>
        <h1 id="hero-titulo">Pide tu café y recógelo <em>caliente</em>.</h1>
        <p class="hero-texto">
            Elige tus productos, confirma tu pedido y paga en el mostrador.
            Te avisamos cuando esté listo. Sin domicilios: todo se recoge en la tienda.
        </p>
        <div class="hero-acciones">
            <a class="btn btn-primario" href="#menu">Ver el menú</a>
            <?php if ($esPersonal): ?>
                <a class="btn btn-secundario" href="<?= h(url('gestion.php')) ?>">Ir a gestión</a>
            <?php else: ?>
                <a class="btn btn-secundario" href="<?= h(url('carrito.php')) ?>">
                    Revisar carrito<?= $carrito['unidades'] > 0 ? ' (' . $carrito['unidades'] . ')' : '' ?>
                </a>
            <?php endif; ?>
        </div>
        <dl class="hero-datos">
            <div>
                <dt>Tiempo estimado</dt>
                <dd><?= h($textoTiempo) ?></dd>
            </div>
            <div>
                <dt>Entrega</dt>
                <dd>Solo recogida</dd>
            </div>
            <div>
                <dt>Pago</dt>
                <dd>En mostrador</dd>
            </div>
        </dl>
    </div>
    <div class="hero-visual" aria-hidden="true">
        <div class="hero-taza">
            <span class="hero-vapor"></span>
            <span class="hero-vapor"></span>
            <span class="hero-vapor"></span>
            <span class="hero-vapor"></span>
            <span class="hero-vapor"></span>
            <div class="hero-taza-logo">
                <img src="<?= h(url('media/logo-cafe-sabroso.png')) ?>"
                     alt="Café Sabroso: el sabor que enamora y conquista tu alma"
                     width="600" height="547" loading="eager" decoding="async">
            </div>
            <span class="hero-plato"></span>
        </div>
        <p class="hero-nota">Hecho al momento</p>
    </div>
</section>

<?php if (!$tiendaAbierta): ?>
    <div class="aviso-banda" role="status">
        La tienda no está recibiendo pedidos web en este momento. Puedes consultar el menú, pero el checkout está cerrado.
    </div>
<?php endif; ?>

<?php if (is_array($ultimoPedido) && isset($ultimoPedido['codigo'], $ultimoPedido['token'])): ?>
    <div class="aviso-banda aviso-exito">
        Tu pedido <strong><?= h((string) $ultimoPedido['codigo']) ?></strong> quedó registrado.
        <a href="<?= h(url('pedido.php?codigo=' . urlencode((string) $ultimoPedido['codigo']) . '&token=' . urlencode((string) $ultimoPedido['token']))) ?>">Ver estado</a>
        <?php if (usuarioActual() === null): ?>
            &middot; <a href="<?= h(url('pedidos-invitado.php')) ?>">Mis pedidos recientes</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php foreach ($carrito['ajustes'] as $ajuste): ?>
    <div class="aviso-banda" role="status"><?= h($ajuste) ?></div>
<?php endforeach; ?>

<section id="menu" class="section-menu" aria-labelledby="menu-titulo">
    <div class="section-head">
        <div>
            <p class="eyebrow">Menú de hoy</p>
            <h2 id="menu-titulo">Elige lo que se te antoje</h2>
            <p>
                <?= $esPersonal
                    ? 'Vista de consulta del menú. El carrito y el checkout están deshabilitados para cuentas de gestión.'
                    : 'Los productos se agregan al carrito y el pedido se confirma en el siguiente paso.' ?>
            </p>
        </div>
        <p class="menu-tiempo">Preparación base: <strong><?= h($textoTiempo) ?></strong>. El tiempo final se calcula al registrar el pedido.</p>
    </div>

    <nav class="filtros" aria-label="Filtrar por categoría">
        <a class="filtro<?= $categoriaId === 0 ? ' activo' : '' ?>" href="<?= h(url('index.php')) ?>#menu">Todo</a>
        <?php foreach ($categorias as $categoria): ?>
            <a class="filtro<?= $categoriaId === (int) $categoria['id'] ? ' activo' : '' ?>"
               href="<?= h(url('index.php?categoria=' . (int) $categoria['id'])) ?>#menu">
                <?= h((string) $categoria['nombre']) ?>
                <span><?= (int) $categoria['productos'] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($productos === []): ?>
        <div class="estado-vacio">
            <h3>No hay productos disponibles</h3>
            <p>Vuelve pronto o consulta en el mostrador.</p>
        </div>
    <?php else: ?>
        <div class="grid-productos">
            <?php foreach ($productos as $producto): ?>
                <?php
                $stock = (int) $producto['stock_actual'];
                $agotado = $stock < 1;
                $enCarrito = $cantidadesCarrito[(int) $producto['id']] ?? 0;
                $imagen = trim((string) ($producto['imagen_url'] ?? ''));
                $tieneImagen = imagenProductoDisponible($imagen);
                ?>
                <article class="tarjeta-producto<?= $agotado ? ' agotado' : '' ?>">
                    <div class="producto-imagen<?= $tieneImagen ? ' con-foto' : '' ?>">
                        <?php if ($tieneImagen): ?>
                            <img src="<?= h(urlImagenProducto($imagen)) ?>" alt="<?= h((string) $producto['nombre']) ?>" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span aria-hidden="true"><?= h(mb_substr((string) $producto['nombre'], 0, 1)) ?></span>
                            <small>Foto próximamente</small>
                        <?php endif; ?>
                    </div>
                    <div class="producto-cuerpo">
                        <div class="producto-meta">
                            <span class="producto-categoria"><?= h((string) $producto['categoria']) ?></span>
                            <?php if (!empty($producto['destacado'])): ?>
                                <span class="producto-destacado">Destacado</span>
                            <?php endif; ?>
                        </div>
                        <h3><?= h((string) $producto['nombre']) ?></h3>
                        <?php if (!empty($producto['descripcion'])): ?>
                            <p class="producto-descripcion"><?= h((string) $producto['descripcion']) ?></p>
                        <?php endif; ?>
                        <div class="producto-pie">
                            <p class="producto-precio"><?= h(moneda((float) $producto['precio'])) ?></p>
                            <p class="producto-stock">
                                <?php if ($agotado): ?>
                                    Agotado
                                <?php else: ?>
                                    <?= $stock ?> disponibles
                                    <?php if (!empty($producto['tiempo_preparacion_min'])): ?>
                                        · ~<?= (int) $producto['tiempo_preparacion_min'] ?> min
                                    <?php endif; ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <?php if (!$agotado && !$esPersonal): ?>
                            <form class="form-agregar" method="post" action="<?= h(url('carrito.php')) ?>">
                                <?= campoCsrf() ?>
                                <input type="hidden" name="accion" value="agregar">
                                <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>">
                                <input type="hidden" name="volver" value="<?= h($_SERVER['REQUEST_URI'] ?? url('index.php')) ?>">
                                <label class="sr-only" for="cantidad-<?= (int) $producto['id'] ?>">Cantidad de <?= h((string) $producto['nombre']) ?></label>
                                <input id="cantidad-<?= (int) $producto['id'] ?>" type="number" name="cantidad" value="1" min="1" max="<?= $stock ?>" inputmode="numeric">
                                <button class="btn btn-primario" type="submit"<?= !$tiendaAbierta ? ' disabled' : '' ?>>
                                    <?= $enCarrito > 0 ? 'Agregar otra' : 'Agregar' ?>
                                </button>
                            </form>
                            <?php if ($enCarrito > 0): ?>
                                <p class="producto-en-carrito"><?= $enCarrito ?> en tu carrito</p>
                            <?php endif; ?>
                        <?php elseif ($agotado): ?>
                            <button class="btn btn-secundario" type="button" disabled>Sin existencias</button>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="como-funciona" aria-labelledby="como-titulo">
    <h2 id="como-titulo">Así de fácil</h2>
    <ol>
        <li><strong>Elige</strong><span>Agrega productos al carrito.</span></li>
        <li><strong>Confirma</strong><span>Déjanos tu nombre y correo.</span></li>
        <li><strong>Paga</strong><span>En efectivo o tarjeta, en el mostrador.</span></li>
        <li><strong>Recoge</strong><span>Te avisamos cuando esté listo.</span></li>
    </ol>
</section>

<?php pieHtml(); ?>
