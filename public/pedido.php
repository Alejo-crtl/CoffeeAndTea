<?php
/**
 * BLOQUE B04 · CARRITO Y PEDIDOS
 * Qué hace: consulta pública de un pedido por código + token.
 * Relacionados: pedidos.php, panel_pedido.php, plantilla.php.
 * Pruebas: CP-019, CP-020.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/pedidos.php';

$codigo = trim((string) ($_GET['codigo'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$pedido = ($codigo !== '' && $token !== '') ? obtenerPedidoPublico($conn, $codigo, $token) : null;

if ($pedido === null) {
    http_response_code(404);
    cabeceraHtml('Pedido no encontrado | CoffeeAndtea', 'tienda');
    ?>
    <div class="estado-vacio">
        <h1>No encontramos ese pedido</h1>
        <p>Revisa el enlace o consulta en el mostrador con tu código de pedido.</p>
        <a class="btn btn-primario" href="<?= h(url('index.php')) ?>">Volver a la tienda</a>
    </div>
    <?php
    pieHtml();
    exit;
}

$pedidoId = (int) $pedido['id'];
$completo = obtenerPedidoCompleto($conn, $pedidoId);
$detalles = $completo['detalles'] ?? [];
$pagos = $completo['pagos'] ?? [];
$historial = $completo['historial'] ?? [];
$estado = (string) $pedido['estado'];
$pagoConfirmado = null;
foreach ($pagos as $pago) {
    if ($pago['estado'] === 'CONFIRMADO') {
        $pagoConfirmado = $pago;
        break;
    }
}
$pasos = ['PENDIENTE', 'PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO'];
$indiceActual = array_search($estado, $pasos, true);
$cancelado = $estado === 'CANCELADO';
$fechaEstimada = null;
if (!empty($pedido['fecha_creacion'])) {
    $marca = strtotime((string) $pedido['fecha_creacion']);
    if ($marca !== false) {
        $fechaEstimada = $marca + ((int) $pedido['tiempo_estimado_min'] * 60);
    }
}

$usuarioPedido = usuarioActual();
$seccionPedido = 'tienda';
if ($usuarioPedido !== null && in_array($usuarioPedido['rol'], [ROL_ENCARGADO, ROL_ADMIN], true)) {
    $seccionPedido = 'tienda';
} elseif ($usuarioPedido !== null) {
    $seccionPedido = 'cuenta';
} elseif (pedidosInvitadoLeer() !== []) {
    $seccionPedido = 'pedidos';
}

cabeceraHtml('Pedido ' . $pedido['codigo'] . ' | CoffeeAndtea', $seccionPedido);
?>

<section class="pagina-cabecera confirmacion">
    <div>
        <p class="eyebrow">Pedido <?= h((string) $pedido['codigo']) ?></p>
        <h1>
            <?php if ($cancelado): ?>
                Este pedido fue cancelado
            <?php elseif ($estado === 'ENTREGADO'): ?>
                ¡Gracias por tu compra!
            <?php elseif ($estado === 'LISTO'): ?>
                Tu pedido está listo
            <?php elseif ($estado === 'PENDIENTE'): ?>
                Pedido registrado
            <?php else: ?>
                Estamos preparando tu pedido
            <?php endif; ?>
        </h1>
        <p>
            <?php if ($cancelado): ?>
                Si tienes dudas, consulta en el mostrador con este código.
            <?php elseif ($estado === 'PENDIENTE'): ?>
                Acércate al mostrador para pagar en efectivo o con tarjeta. El pedido se prepara al confirmar el pago.
            <?php else: ?>
                Muéstrale este código al encargado cuando llegues a recoger.
            <?php endif; ?>
        </p>
    </div>
    <?= badgeEstado($estado) ?>
</section>

<?php if (!empty($_GET['nuevo'])): ?>
    <div class="aviso-banda aviso-exito" role="status">
        <p><strong>Guarda este enlace.</strong> Lo necesitas para consultar el estado de tu pedido.</p>
        <?php if (usuarioActual() === null): ?>
            <p>También guardamos este pedido en este navegador.</p>
        <?php endif; ?>
        <p>
            <button type="button" class="btn btn-pequeno btn-secundario" id="copiar-enlace"
                    data-enlace="<?= h(url('pedido.php?codigo=' . urlencode((string) $pedido['codigo']) . '&token=' . urlencode((string) $pedido['token_publico']))) ?>">
                Copiar enlace
            </button>
            <span id="copiar-estado" class="texto-suave" aria-live="polite"></span>
            <?php if (usuarioActual() === null): ?>
                <a class="btn btn-pequeno btn-secundario" href="<?= h(url('pedidos-invitado.php')) ?>">
                    Mis pedidos recientes
                </a>
            <?php endif; ?>
        </p>
    </div>
    <script>
        (function () {
            var boton = document.getElementById('copiar-enlace');
            var estado = document.getElementById('copiar-estado');
            if (!boton || !estado) {
                return;
            }

            function mostrarEnlaceManual(texto) {
                estado.textContent = 'No se pudo copiar automáticamente. Selecciona el enlace: ';
                var campo = document.createElement('input');
                campo.type = 'text';
                campo.value = texto;
                campo.readOnly = true;
                campo.className = 'campo-enlace-manual';
                estado.appendChild(campo);
                campo.focus();
                campo.select();
            }

            function copiarConRespaldo(texto) {
                var campo = document.createElement('textarea');
                campo.value = texto;
                campo.setAttribute('readonly', 'readonly');
                campo.style.position = 'fixed';
                campo.style.opacity = '0';
                document.body.appendChild(campo);
                campo.select();
                var copiado = false;
                try {
                    copiado = document.execCommand('copy');
                } catch (error) {
                    copiado = false;
                }
                campo.remove();
                if (copiado) {
                    estado.textContent = 'Enlace copiado.';
                } else {
                    mostrarEnlaceManual(texto);
                }
            }

            boton.addEventListener('click', function () {
                var texto = boton.dataset.enlace || window.location.href;
                estado.textContent = '';
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(texto).then(function () {
                        estado.textContent = 'Enlace copiado.';
                    }).catch(function () {
                        copiarConRespaldo(texto);
                    });
                    return;
                }
                copiarConRespaldo(texto);
            });
        })();
    </script>
<?php endif; ?>

<?php if ($cancelado): ?>
    <div class="alerta-error" role="alert">
        <strong>Cancelado</strong>
        <p><?= h((string) ($pedido['motivo_cancelacion'] ?? 'Sin motivo registrado.')) ?></p>
        <p class="texto-suave">Fecha: <?= h(fechaBonita($pedido['fecha_cancelacion'] ?? null)) ?></p>
    </div>
<?php else: ?>
    <ol class="linea-tiempo" aria-label="Estado del pedido">
        <?php foreach ($pasos as $i => $paso): ?>
            <?php
            $completado = $indiceActual !== false && $i <= $indiceActual;
            $actual = $indiceActual !== false && $i === $indiceActual;
            $clases = [];
            if ($completado) {
                $clases[] = 'completado';
            }
            if ($actual) {
                $clases[] = 'actual';
            }
            if (!$completado && !$actual) {
                $clases[] = 'pendiente';
            }
            ?>
            <li class="<?= h(implode(' ', $clases)) ?>"<?= $actual ? ' aria-current="step"' : '' ?>>
                <span class="punto" aria-hidden="true"><?= $completado ? '✓' : $i + 1 ?></span>
                <div>
                    <strong><?= h(etiquetaEstado($paso)) ?></strong>
                    <?php if ($paso === 'PENDIENTE'): ?>
                        <small>Paga en el mostrador</small>
                    <?php elseif ($paso === 'PAGADO'): ?>
                        <small>Pago confirmado por el encargado</small>
                    <?php elseif ($paso === 'EN_PREPARACION'): ?>
                        <small>Estamos trabajando en tu pedido</small>
                    <?php elseif ($paso === 'LISTO'): ?>
                        <small>Puedes pasar a recogerlo</small>
                    <?php else: ?>
                        <small>Pedido entregado</small>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<div class="layout-pedido">
    <section class="panel" aria-labelledby="detalle-titulo">
        <div class="panel-head">
            <h2 id="detalle-titulo">Detalle del pedido</h2>
            <span class="texto-suave"><?= h(fechaBonita($pedido['fecha_creacion'])) ?></span>
        </div>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead>
                    <tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $detalle): ?>
                        <tr>
                            <td><?= h((string) $detalle['producto']) ?></td>
                            <td><?= (int) $detalle['cantidad'] ?></td>
                            <td><?= h(moneda((float) $detalle['precio_unitario'])) ?></td>
                            <td><?= h(moneda((float) $detalle['cantidad'] * (float) $detalle['precio_unitario'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><th colspan="3">Total</th><th><?= h(moneda((float) $pedido['total'])) ?></th></tr>
                </tfoot>
            </table>
        </div>
        <?php if (!empty($pedido['observaciones'])): ?>
            <p class="nota"><strong>Observaciones:</strong> <?= nl2br(h((string) $pedido['observaciones'])) ?></p>
        <?php endif; ?>
    </section>

    <aside class="panel" aria-labelledby="resumen-pedido">
        <h2 id="resumen-pedido">Resumen</h2>
        <dl class="detalle-lista">
            <div><dt>Código</dt><dd><strong><?= h((string) $pedido['codigo']) ?></strong></dd></div>
            <div><dt>Tiempo estimado</dt><dd>~<?= (int) $pedido['tiempo_estimado_min'] ?> min</dd></div>
            <?php if ($fechaEstimada !== null && !$cancelado): ?>
                <div><dt>Listo aprox.</dt><dd><?= h(date('H:i', $fechaEstimada)) ?></dd></div>
            <?php endif; ?>
            <div><dt>Entrega</dt><dd>Recogida en tienda</dd></div>
            <div>
                <dt>Pago</dt>
                <dd>
                    <?php if ($pagoConfirmado !== null): ?>
                        <?= h((string) $pagoConfirmado['metodo']) ?> · <?= h(moneda((float) $pagoConfirmado['monto'])) ?>
                        <?php if ($pagoConfirmado['metodo'] === 'EFECTIVO' && $pagoConfirmado['cambio'] !== null): ?>
                            <small>Cambio: <?= h(moneda((float) $pagoConfirmado['cambio'])) ?></small>
                        <?php endif; ?>
                    <?php elseif ($cancelado): ?>
                        Sin pago
                    <?php else: ?>
                        Pendiente en mostrador
                    <?php endif; ?>
                </dd>
            </div>
            <div><dt>Cliente</dt><dd><?= h((string) $pedido['cliente_nombre']) ?></dd></div>
        </dl>

        <?php if ($historial !== []): ?>
            <details class="historial">
                <summary>Historial del pedido</summary>
                <ul>
                    <?php foreach ($historial as $paso): ?>
                        <li>
                            <strong><?= h(etiquetaEstado((string) $paso['estado_nuevo'])) ?></strong>
                            <span><?= h(fechaBonita($paso['cambiado_en'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>

        <a class="btn btn-secundario btn-bloque" href="<?= h(url('index.php')) ?>">Volver a la tienda</a>
        <?php if ($usuario !== null): ?>
            <a class="btn btn-fantasma btn-bloque" href="<?= h(url('cuenta.php')) ?>">Ir a mi cuenta</a>
        <?php elseif (pedidosInvitadoLeer() !== []): ?>
            <a class="btn btn-fantasma btn-bloque" href="<?= h(url('pedidos-invitado.php')) ?>">Mis pedidos recientes</a>
        <?php endif; ?>
    </aside>
</div>

<?php pieHtml(); ?>
