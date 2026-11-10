<?php
/**
 * Política de cancelación y reembolsos.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';

cabeceraHtml('Política de cancelación | CoffeeAndtea', 'manual');
?>

<section class="pagina-cabecera">
    <div>
        <p class="eyebrow">Condiciones del pedido</p>
        <h1>Política de cancelación</h1>
        <p>Estas condiciones aplican a todos los pedidos web de CoffeeAndtea.</p>
    </div>
    <a class="btn btn-secundario" href="<?= h(url('index.php')) ?>">Volver a la tienda</a>
</section>

<div class="panel" style="max-width: 860px; margin: 0 auto 32px;">
    <h2>Reembolsos</h2>
    <ul class="lista-beneficios">
        <li><strong>Antes de preparación:</strong> se reembolsa el <strong>100%</strong> de lo pagado.</li>
        <li><strong>En preparación o listo:</strong> se reembolsa el <strong>50%</strong>, porque los productos ya fueron preparados.</li>
        <li><strong>Pedido entregado:</strong> no se puede cancelar.</li>
        <li><strong>Pedido sin pago:</strong> se anula sin reembolso.</li>
    </ul>

    <h2>Cómo se procesa</h2>
    <p>
        La cancelación se realiza en el mostrador. El personal registra el motivo,
        devuelve el stock y procesa el reembolso por el mismo medio de pago.
    </p>
    <ul class="lista-beneficios">
        <li>En efectivo: se registra un egreso en la caja abierta.</li>
        <li>Con tarjeta: se guarda la referencia del reembolso del datáfono.</li>
        <li>El monto y el responsable quedan registrados en la auditoría.</li>
    </ul>

    <h2>Aceptación</h2>
    <p>
        Al registrar un pedido debes aceptar esta política. Si tienes dudas,
        consulta en el mostrador antes de pagar.
    </p>

    <p class="texto-suave">
        Última actualización: 09/11/2026.
    </p>

    <a class="btn btn-primario" href="<?= h(url('checkout.php')) ?>">Volver al pedido</a>
</div>

<?php pieHtml(); ?>
