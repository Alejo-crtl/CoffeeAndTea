<?php
/**
 * Pedidos recientes guardados en este navegador para visitantes.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/pedidos.php';

if (puedeGestionar()) {
    flash('info', 'Esta sección es para clientes y visitantes.');
    redirigir(url('gestion.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $accion = (string) ($_POST['accion'] ?? '');
    if ($accion === 'olvidar') {
        pedidosInvitadoOlvidar();
        flash('info', 'Eliminamos la lista de pedidos guardados en este navegador.');
    }
    redirigir(url('pedidos-invitado.php'));
}

$guardados = pedidosInvitadoLeer();
$pedidos = [];
foreach ($guardados as $item) {
    $pedido = obtenerPedidoPublico($conn, $item['codigo'], $item['token']);
    if ($pedido !== null) {
        $pedidos[] = [
            'pedido' => $pedido,
            'token'  => $item['token'],
        ];
    }
}

$usuario = usuarioActual();

cabeceraHtml('Pedidos recientes | CoffeeAndtea', 'pedidos');
?>

<section class="pagina-cabecera">
    <div>
        <p class="eyebrow">Consulta sin cuenta</p>
        <h1>Pedidos recientes</h1>
        <p>
            Estos son los pedidos que hiciste como invitado en este navegador.
            Se guardan aquí para que no pierdas el acceso al código y al estado.
        </p>
    </div>
    <a class="btn btn-secundario" href="<?= h(url('index.php')) ?>">Volver a la tienda</a>
</section>

<?php if ($usuario !== null): ?>
    <div class="alerta-info" role="status">
        <strong>También tienes una cuenta.</strong>
        <p>
            Los pedidos asociados a tu cuenta están en
            <a href="<?= h(url('cuenta.php')) ?>">Mi cuenta</a>.
            Esta lista solo muestra los pedidos guardados en este navegador.
        </p>
    </div>
<?php else: ?>
    <div class="alerta-info" role="status">
        <strong>¿Quieres conservar tu historial?</strong>
        <p>
            Crea una cuenta o inicia sesión para ver tus pedidos desde cualquier
            dispositivo: <a href="<?= h(url('registro.php')) ?>">crear cuenta</a> o
            <a href="<?= h(url('login.php')) ?>">iniciar sesión</a>.
        </p>
    </div>
<?php endif; ?>

<?php if ($pedidos === []): ?>
    <div class="estado-vacio">
        <h2>No hay pedidos guardados</h2>
        <p>
            Cuando hagas un pedido como invitado, aparecerá aquí en este navegador.
            Si cambiaste de dispositivo o borraste los datos del navegador,
            necesitarás el enlace de confirmación que se mostró al crear el pedido.
        </p>
        <a class="btn btn-primario" href="<?= h(url('index.php')) ?>#menu">Ver el menú</a>
    </div>
<?php else: ?>
    <section class="panel" aria-labelledby="recientes-titulo">
        <div class="panel-head">
            <h2 id="recientes-titulo">Tus últimos pedidos</h2>
            <p class="texto-suave">Se conservan los últimos <?= PEDIDOS_INVITADO_MAX ?> pedidos durante <?= PEDIDOS_INVITADO_DIAS ?> días.</p>
        </div>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $item): ?>
                        <?php $pedido = $item['pedido']; ?>
                        <tr>
                            <td><strong><?= h((string) $pedido['codigo']) ?></strong></td>
                            <td><?= h(fechaBonita($pedido['fecha_creacion'])) ?></td>
                            <td><?= badgeEstado((string) $pedido['estado']) ?></td>
                            <td><?= h(moneda((float) $pedido['total'])) ?></td>
                            <td>
                                <a class="btn btn-pequeno btn-secundario"
                                   href="<?= h(url('pedido.php?codigo=' . urlencode((string) $pedido['codigo']) . '&token=' . urlencode($item['token']))) ?>">
                                    Ver detalle
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="post" action="<?= h(url('pedidos-invitado.php')) ?>" class="acciones-carrito"
              onsubmit="return confirm('¿Eliminar la lista de pedidos guardados en este navegador?');">
            <?= campoCsrf() ?>
            <input type="hidden" name="accion" value="olvidar">
            <button class="btn btn-fantasma" type="submit">Olvidar pedidos de este navegador</button>
        </form>
    </section>
<?php endif; ?>

<?php pieHtml(); ?>
