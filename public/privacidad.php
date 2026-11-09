<?php
/**
 * Política de privacidad y tratamiento de datos.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/pedidos.php';

$correoContacto = (string) (configSistema($conn, 'correo_contacto', (string) env('APP_ADMIN_EMAIL', 'vegaperezkevinsantiago@gmail.com')) ?? '');
$direccion = (string) (configSistema($conn, 'direccion_tienda', '') ?? '');

cabeceraHtml('Privacidad | CoffeeAndtea', 'privacidad');
?>

<section class="pagina-cabecera">
    <div>
        <p class="eyebrow">Información legal</p>
        <h1>Privacidad y tratamiento de datos</h1>
        <p>Última actualización: <?= h(date('d/m/Y')) ?>.</p>
    </div>
    <a class="btn btn-secundario" href="<?= h(url('index.php')) ?>">Volver a la tienda</a>
</section>

<div class="panel prosa">
    <h2>Qué datos recolectamos</h2>
    <p>Para gestionar tu pedido guardamos tu nombre, correo electrónico, teléfono (si lo proporcionas),
        las observaciones que escribas y el detalle de tus compras. Si creas una cuenta, guardamos también
        una contraseña cifrada; nunca la vemos en texto plano.</p>

    <h2>Para qué los usamos</h2>
    <ul>
        <li>Registrar, preparar y entregar tu pedido.</li>
        <li>Contactarte si hay un problema con tu pedido.</li>
        <li>Enviarte el enlace de recuperación de contraseña, si lo solicitas.</li>
        <li>Llevar controles internos de caja, inventario y auditoría.</li>
    </ul>

    <h2>Pagos</h2>
    <p>No procesamos pagos en línea ni almacenamos números de tarjeta. El pago se realiza en el mostrador.
        Cuando pagas con tarjeta, el datáfono externo es el que procesa la transacción; en el sistema solo
        queda una referencia escrita por el encargado.</p>

    <h2>Conservación y acceso</h2>
    <p>Los datos se conservan mientras sean necesarios para la operación y las obligaciones contables.
        Solo el personal autorizado (encargado y administrador) accede a la gestión. Cada acción sensible
        queda registrada en la auditoría del sistema.</p>

    <h2>Tus derechos</h2>
    <p>Puedes solicitar acceso, corrección o eliminación de tus datos escribiendo a
        <a href="mailto:<?= h($correoContacto) ?>"><?= h($correoContacto) ?></a>.
        También puedes actualizar tu nombre y teléfono desde <a href="<?= h(url('cuenta.php')) ?>">tu cuenta</a>.</p>

    <?php if ($direccion !== ''): ?>
        <h2>Contacto de la tienda</h2>
        <p><?= nl2br(h($direccion)) ?></p>
    <?php endif; ?>

    <h2>Cookies y sesión</h2>
    <p>Usamos una cookie de sesión necesaria para mantener tu carrito, tu inicio de sesión y la protección
        contra falsificación de peticiones (CSRF). No usamos cookies publicitarias ni rastreadores de terceros.</p>

    <p>
        <a class="btn btn-primario" href="<?= h(url('checkout.php')) ?>">Volver al pedido</a>
    </p>
</div>

<?php pieHtml(); ?>
