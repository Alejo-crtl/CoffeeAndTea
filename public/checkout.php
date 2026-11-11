<?php
/**
 * BLOQUE B04 · CARRITO Y PEDIDOS
 * Qué hace: confirma los datos del cliente y crea el pedido.
 * Relacionados: pedidos.php, plantilla.php.
 * Pruebas: CP-016, CP-017.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/pedidos.php';

if (puedeGestionar()) {
    flash('info', 'El checkout no está disponible para cuentas de gestión.');
    redirigir(url('gestion.php'));
}

$carrito = carritoDetallado($conn);
if ($carrito['items'] === []) {
    flash('info', 'Agrega productos antes de continuar.');
    redirigir(url('carrito.php'));
}

$tiendaAbierta = strtolower((string) configSistema($conn, 'pagina_abierta', 'true')) !== 'false';
if (!$tiendaAbierta) {
    flash('error', 'La tienda no está recibiendo pedidos web en este momento.');
    redirigir(url('carrito.php'));
}

$usuario = usuarioActual();
$valores = [
    'nombre'         => (string) ($usuario['nombre'] ?? ''),
    'correo'         => (string) ($usuario['correo'] ?? ''),
    'telefono'       => '',
    'observaciones'  => '',
    'consentimiento' => false,
    'acepta_cancelacion' => false,
];
$errores = [];

if ($usuario !== null) {
    $stmt = $conn->prepare('SELECT telefono FROM cliente WHERE usuario_id = :id LIMIT 1');
    $stmt->execute([':id' => $usuario['id']]);
    $telefono = $stmt->fetchColumn();
    if ($telefono !== false && $telefono !== null) {
        $valores['telefono'] = (string) $telefono;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $valores = [
        'nombre'         => trim((string) ($_POST['nombre'] ?? '')),
        'correo'         => mb_strtolower(trim((string) ($_POST['correo'] ?? '')), 'UTF-8'),
        'telefono'       => trim((string) ($_POST['telefono'] ?? '')),
        'observaciones'  => trim((string) ($_POST['observaciones'] ?? '')),
        'consentimiento' => isset($_POST['consentimiento']),
        'acepta_cancelacion' => isset($_POST['acepta_cancelacion']),
    ];

    try {
        $resultado = crearPedido($conn, $valores, $usuario['id'] ?? null);
        $_SESSION['ultimo_pedido'] = [
            'codigo' => $resultado['codigo'],
            'token'  => $resultado['token'],
        ];
        if ($usuario === null) {
            pedidosInvitadoGuardar((string) $resultado['codigo'], (string) $resultado['token']);
        }
        redirigir(url('pedido.php?codigo=' . urlencode($resultado['codigo']) . '&token=' . urlencode($resultado['token']) . '&nuevo=1'));
    } catch (ReglaNegocioException $e) {
        $errores[] = $e->getMessage();
        $carrito = carritoDetallado($conn);
    }
}

$tiempo = calcularTiempoEstimado($conn, $carrito['items']);

cabeceraHtml('Confirmar pedido | CoffeeAndtea', 'carrito');
?>

<section class="pagina-cabecera">
    <div>
        <p class="eyebrow">Paso final</p>
        <h1>Confirmar pedido</h1>
        <p>Déjanos tus datos. El pago se hace en el mostrador cuando llegues.</p>
    </div>
    <a class="btn btn-secundario" href="<?= h(url('carrito.php')) ?>">Volver al carrito</a>
</section>

<?php avisosHtml(null, $errores); ?>

<div class="layout-checkout">
    <section class="panel" aria-labelledby="datos-titulo">
        <h2 id="datos-titulo">Datos de contacto</h2>
        <?php if ($usuario === null): ?>
            <p class="texto-suave">
                Puedes pedir como invitado. Si quieres guardar tu historial,
                <a href="<?= h(url('login.php')) ?>">inicia sesión</a> o
                <a href="<?= h(url('registro.php')) ?>">crea una cuenta</a>.
            </p>
        <?php endif; ?>

        <form method="post" action="<?= h(url('checkout.php')) ?>" class="formulario" novalidate>
            <?= campoCsrf() ?>
            <div class="campo">
                <label for="nombre">Nombre completo <span aria-hidden="true">*</span></label>
                <input id="nombre" name="nombre" type="text" maxlength="150" required autocomplete="name"
                       value="<?= h($valores['nombre']) ?>">
            </div>
            <div class="campo">
                <label for="correo">Correo electrónico <span aria-hidden="true">*</span></label>
                <input id="correo" name="correo" type="email" maxlength="150" required autocomplete="email"
                       value="<?= h($valores['correo']) ?>">
                <small>Lo usamos para identificar tu pedido y avisarte novedades.</small>
            </div>
            <div class="campo">
                <label for="telefono">Teléfono (opcional)</label>
                <input id="telefono" name="telefono" type="tel" maxlength="30" autocomplete="tel"
                       value="<?= h($valores['telefono']) ?>">
            </div>
            <div class="campo">
                <label for="observaciones">Observaciones (opcional)</label>
                <textarea id="observaciones" name="observaciones" maxlength="500" rows="3"
                          placeholder="Sin azúcar, para llevar, alguna alergia..."><?= h($valores['observaciones']) ?></textarea>
            </div>
            <label class="check">
                <input type="checkbox" name="consentimiento" value="1"<?= $valores['consentimiento'] ? ' checked' : '' ?> required>
                <span>Autorizo el tratamiento de mis datos personales para gestionar este pedido
                    (<a href="<?= h(url('privacidad.php')) ?>" target="_blank" rel="noopener">ver política</a>).</span>
            </label>
            <label class="check">
                <input type="checkbox" name="acepta_cancelacion" value="1"<?= $valores['acepta_cancelacion'] ? ' checked' : '' ?> required>
                <span>Acepto la política de cancelación: si el pedido ya está en preparación o listo,
                    el reembolso será del 50% de lo pagado
                    (<a href="<?= h(url('politica-cancelacion.php')) ?>" target="_blank" rel="noopener">ver política</a>).</span>
            </label>
            <button class="btn btn-primario" type="submit">Registrar pedido</button>
        </form>
    </section>

    <aside class="panel resumen-carrito" aria-labelledby="resumen-checkout">
        <h2 id="resumen-checkout">Tu pedido</h2>
        <ul class="lista-resumen">
            <?php foreach ($carrito['items'] as $item): ?>
                <li>
                    <span><?= (int) $item['cantidad'] ?> × <?= h($item['nombre']) ?></span>
                    <strong><?= h(moneda($item['subtotal'])) ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
        <dl>
            <div class="total"><dt>Total</dt><dd><?= h(moneda($carrito['subtotal'])) ?></dd></div>
        </dl>
        <div class="caja-tiempo">
            <span>Tiempo estimado de preparación</span>
            <strong>~<?= (int) $tiempo ?> min</strong>
            <small>Se cuenta desde que confirmas el pedido.</small>
        </div>
        <p class="nota-pago"><strong>Pago en mostrador:</strong> efectivo o tarjeta. El pedido queda como pendiente hasta que el encargado confirme el pago.</p>
    </aside>
</div>

<?php pieHtml(); ?>
