<?php
/**
 * Carrito de compras: agregar, actualizar, eliminar y revisar.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/pedidos.php';

if (puedeGestionar()) {
    flash('info', 'El carrito no está disponible para cuentas de gestión.');
    redirigir(url('gestion.php'));
}

$tiendaAbierta = strtolower((string) configSistema($conn, 'pagina_abierta', 'true')) !== 'false';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $volver = urlVolver(url('carrito.php'));

    try {
        if (!$tiendaAbierta && in_array($accion, ['agregar', 'fijar'], true)) {
            throw new ReglaNegocioException('La tienda no está recibiendo pedidos en este momento.');
        }
        switch ($accion) {
            case 'agregar':
                carritoAgregar($conn, (int) ($_POST['producto_id'] ?? 0), (int) ($_POST['cantidad'] ?? 1));
                flash('exito', 'Producto agregado al carrito.');
                break;
            case 'fijar':
                carritoFijar($conn, (int) ($_POST['producto_id'] ?? 0), (int) ($_POST['cantidad'] ?? 0));
                flash('exito', 'Carrito actualizado.');
                $volver = url('carrito.php');
                break;
            case 'eliminar':
                carritoEliminar((int) ($_POST['producto_id'] ?? 0));
                flash('exito', 'Producto eliminado del carrito.');
                $volver = url('carrito.php');
                break;
            case 'vaciar':
                carritoVaciar();
                flash('info', 'Vaciaste el carrito.');
                $volver = url('carrito.php');
                break;
            default:
                throw new ReglaNegocioException('Acción no reconocida.');
        }
    } catch (ReglaNegocioException $e) {
        flash('error', $e->getMessage());
        $volver = url('carrito.php');
    }

    redirigir($volver);
}

$carrito = carritoDetallado($conn);

cabeceraHtml('Carrito | CoffeeAndtea', 'carrito');
?>

<section class="pagina-cabecera">
    <div>
        <p class="eyebrow">Tu pedido</p>
        <h1>Carrito</h1>
        <p>Revisa las cantidades antes de continuar. El pago se realiza en el mostrador.</p>
    </div>
    <a class="btn btn-secundario" href="<?= h(url('index.php')) ?>">Seguir eligiendo</a>
</section>

<?php if ($carrito['items'] === []): ?>
    <div class="estado-vacio">
        <h2>Tu carrito está vacío</h2>
        <p>Agrega un café, un pan o un postre para empezar.</p>
        <a class="btn btn-primario" href="<?= h(url('index.php')) ?>#menu">Ver el menú</a>
    </div>
<?php else: ?>
    <?php if (usuarioActual() === null): ?>
        <?= avisoCompraInvitadoHtml() ?>
    <?php endif; ?>

    <div class="layout-carrito">
        <section class="panel" aria-labelledby="carrito-titulo">
            <h2 id="carrito-titulo">Productos (<?= (int) $carrito['unidades'] ?>)</h2>
            <div class="tabla-scroll">
                <table class="tabla tabla-carrito">
                    <thead>
                        <tr>
                            <th scope="col">Producto</th>
                            <th scope="col">Precio</th>
                            <th scope="col">Cantidad</th>
                            <th scope="col">Subtotal</th>
                            <th scope="col"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($carrito['items'] as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= h($item['nombre']) ?></strong>
                                    <span class="texto-suave"><?= h($item['categoria']) ?></span>
                                </td>
                                <td><?= h(moneda($item['precio'])) ?></td>
                                <td>
                                    <form class="form-cantidad" method="post" action="<?= h(url('carrito.php')) ?>">
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="accion" value="fijar">
                                        <input type="hidden" name="producto_id" value="<?= (int) $item['producto_id'] ?>">
                                        <label class="sr-only" for="cant-<?= (int) $item['producto_id'] ?>">Cantidad</label>
                                        <input id="cant-<?= (int) $item['producto_id'] ?>" type="number" name="cantidad"
                                               value="<?= (int) $item['cantidad'] ?>" min="0" max="<?= (int) $item['stock'] ?>" inputmode="numeric">
                                        <button class="btn btn-pequeno btn-secundario" type="submit">Actualizar</button>
                                    </form>
                                </td>
                                <td><strong><?= h(moneda($item['subtotal'])) ?></strong></td>
                                <td>
                                    <form method="post" action="<?= h(url('carrito.php')) ?>">
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="producto_id" value="<?= (int) $item['producto_id'] ?>">
                                        <button class="btn btn-pequeno btn-peligro" type="submit">Quitar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="post" action="<?= h(url('carrito.php')) ?>" class="acciones-carrito">
                <?= campoCsrf() ?>
                <input type="hidden" name="accion" value="vaciar">
                <button class="btn btn-fantasma" type="submit">Vaciar carrito</button>
            </form>
        </section>

        <aside class="panel resumen-carrito" aria-labelledby="resumen-titulo">
            <h2 id="resumen-titulo">Resumen</h2>
            <dl>
                <div><dt>Unidades</dt><dd><?= (int) $carrito['unidades'] ?></dd></div>
                <div><dt>Subtotal</dt><dd><?= h(moneda($carrito['subtotal'])) ?></dd></div>
                <div class="total"><dt>Total</dt><dd><?= h(moneda($carrito['subtotal'])) ?></dd></div>
            </dl>
            <p class="texto-suave">El tiempo estimado se confirma al registrar el pedido.</p>
            <p class="texto-suave">
                Cancelación: 100% antes de preparación; 50% si el pedido ya está
                en preparación o listo.
            </p>
            <?php if ($tiendaAbierta): ?>
                <a class="btn btn-primario btn-bloque" href="<?= h(url('checkout.php')) ?>">Continuar con el pedido</a>
            <?php else: ?>
                <button class="btn btn-primario btn-bloque" type="button" disabled>Checkout cerrado</button>
            <?php endif; ?>
            <p class="nota-pago">Pago en efectivo o tarjeta en el mostrador. No se cobra en línea.</p>
        </aside>
    </div>
<?php endif; ?>

<?php pieHtml(); ?>
