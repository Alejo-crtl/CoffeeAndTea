<?php
/**
 * BLOQUE B04 · CARRITO Y PEDIDOS
 * Qué hace: panel reutilizable con el resumen y los datos de un pedido.
 * Páginas: pedido.php, gestion.php.
 */
declare(strict_types=1);

$estado = (string) $pedidoVer['estado'];
$pagoConfirmado = null;
foreach (($pedidoVer['pagos'] ?? []) as $pago) {
    if ($pago['estado'] === 'CONFIRMADO') {
        $pagoConfirmado = $pago;
        break;
    }
}
$siguiente = [
    'PAGADO'         => 'EN_PREPARACION',
    'EN_PREPARACION' => 'LISTO',
    'LISTO'          => 'ENTREGADO',
][$estado] ?? null;
$textoSiguiente = [
    'PAGADO'         => 'Marcar en preparación',
    'EN_PREPARACION' => 'Marcar listo',
    'LISTO'          => 'Marcar entregado',
][$estado] ?? null;
$puedeCancelar = in_array($estado, ['PENDIENTE', 'PAGADO', 'EN_PREPARACION', 'LISTO'], true);
$metodoPagoSugerido = $pagoConfirmado['metodo'] ?? 'EFECTIVO';
$porcentajeReembolso = porcentajeReembolsoCancelacion($estado);
$montoReembolso = $pagoConfirmado !== null
    ? montoReembolsoCancelacion((float) $pagoConfirmado['monto'], $estado)
    : 0.0;
?>
<section class="panel panel-pedido" aria-labelledby="pedido-titulo">
    <div class="panel-head">
        <div>
            <p class="eyebrow">Pedido <?= h((string) $pedidoVer['codigo']) ?> · #<?= (int) $pedidoVer['id'] ?></p>
            <h2 id="pedido-titulo"><?= h((string) $pedidoVer['cliente_nombre']) ?></h2>
            <p class="texto-suave">
                <?= h((string) $pedidoVer['cliente_correo']) ?>
                <?php if (!empty($pedidoVer['cliente_telefono'])): ?> · <?= h((string) $pedidoVer['cliente_telefono']) ?><?php endif; ?>
            </p>
        </div>
        <div class="pedido-estado">
            <a class="btn btn-pequeno btn-secundario" href="<?= h(url('gestion.php?tab=pedidos')) ?>">Volver a pedidos</a>
            <?= badgeEstado($estado) ?>
        </div>
    </div>

    <div class="pedido-resumen-grid">
        <div><span>Creado</span><strong><?= h(fechaBonita($pedidoVer['fecha_creacion'])) ?></strong></div>
        <div><span>Tiempo estimado</span><strong>~<?= (int) $pedidoVer['tiempo_estimado_min'] ?> min</strong></div>
        <div><span>Total</span><strong><?= h(moneda((float) $pedidoVer['total'])) ?></strong></div>
        <div><span>Pago</span><strong><?= $pagoConfirmado !== null ? h((string) $pagoConfirmado['metodo']) : 'Pendiente' ?></strong></div>
    </div>

    <?php if (!empty($pedidoVer['observaciones'])): ?>
        <p class="nota"><strong>Observaciones:</strong> <?= nl2br(h((string) $pedidoVer['observaciones'])) ?></p>
    <?php endif; ?>

    <div class="tabla-scroll">
        <table class="tabla">
            <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio histórico</th><th>Subtotal</th></tr></thead>
            <tbody>
                <?php foreach (($pedidoVer['detalles'] ?? []) as $detalle): ?>
                    <tr>
                        <td><?= h((string) $detalle['producto']) ?></td>
                        <td><?= (int) $detalle['cantidad'] ?></td>
                        <td><?= h(moneda((float) $detalle['precio_unitario'])) ?></td>
                        <td><?= h(moneda((float) $detalle['cantidad'] * (float) $detalle['precio_unitario'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="3">Total</th><th><?= h(moneda((float) $pedidoVer['total'])) ?></th></tr></tfoot>
        </table>
    </div>

    <div class="pedido-acciones">
        <?php if ($estado === 'PENDIENTE'): ?>
            <div class="accion-caja">
                <h3>Cobrar pedido</h3>
                <form method="post" action="<?= h(url('gestion.php')) ?>" class="formulario" data-form-pago>
                    <?= campoCsrf() ?>
                    <input type="hidden" name="accion" value="confirmar_pago">
                    <input type="hidden" name="tab" value="pedidos">
                    <input type="hidden" name="pedido_id" value="<?= (int) $pedidoVer['id'] ?>">
                    <div class="campo">
                        <label for="metodo">Método de pago</label>
                        <select id="metodo" name="metodo" data-metodo-pago required>
                            <option value="EFECTIVO">Efectivo</option>
                            <option value="TARJETA">Tarjeta (datáfono externo)</option>
                        </select>
                    </div>
                    <div class="campo" data-campo-efectivo>
                        <label for="recibido">Efectivo recibido (COP)</label>
                        <input id="recibido" name="recibido" type="number" min="<?= (float) $pedidoVer['total'] ?>" step="100"
                               value="<?= (float) $pedidoVer['total'] ?>" data-recibido data-total="<?= (float) $pedidoVer['total'] ?>">
                        <small>Cambio estimado: <strong data-cambio><?= h(moneda(0)) ?></strong></small>
                    </div>
                    <div class="campo" data-campo-tarjeta hidden>
                        <label for="referencia">Referencia del datáfono</label>
                        <input id="referencia" name="referencia" type="text" maxlength="100" placeholder="Ej. TRX-4821">
                        <small>Opcional, pero recomendable para conciliar el pago.</small>
                    </div>
                    <?php if ($caja === null): ?>
                        <p class="nota nota-alerta">No hay caja abierta. Para cobrar en efectivo primero debes abrirla.</p>
                    <?php endif; ?>
                    <button class="btn btn-primario" type="submit">Confirmar pago y descontar stock</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($siguiente !== null): ?>
            <form method="post" action="<?= h(url('gestion.php')) ?>" class="accion-estado">
                <?= campoCsrf() ?>
                <input type="hidden" name="accion" value="avanzar">
                <input type="hidden" name="tab" value="pedidos">
                <input type="hidden" name="pedido_id" value="<?= (int) $pedidoVer['id'] ?>">
                <input type="hidden" name="nuevo_estado" value="<?= h($siguiente) ?>">
                <button class="btn btn-primario" type="submit"><?= h((string) $textoSiguiente) ?></button>
            </form>
        <?php endif; ?>

        <?php if ($estado === 'ENTREGADO'): ?>
            <p class="pastilla pastilla-verde">Pedido completado</p>
        <?php endif; ?>

        <?php if ($puedeCancelar): ?>
            <details class="cancelar-pedido">
                <summary>Cancelar pedido</summary>
                <form method="post" action="<?= h(url('gestion.php')) ?>" class="formulario"
                      onsubmit="return confirm('¿Cancelar este pedido? La acción queda auditada y no se puede deshacer.')">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="accion" value="cancelar">
                    <input type="hidden" name="tab" value="pedidos">
                    <input type="hidden" name="pedido_id" value="<?= (int) $pedidoVer['id'] ?>">
                    <div class="campo">
                        <label for="motivo">Motivo de la cancelación</label>
                        <textarea id="motivo" name="motivo" rows="2" maxlength="500" required
                                  placeholder="Cliente no se presentó, error en el pedido..."></textarea>
                    </div>
                    <?php if ($pagoConfirmado !== null): ?>
                        <input type="hidden" name="metodo_reembolso" value="<?= h((string) $metodoPagoSugerido) ?>">
                        <p class="nota <?= $porcentajeReembolso < 100 ? 'nota-alerta' : '' ?>">
                            <?php if ($porcentajeReembolso < 100): ?>
                                El pedido ya está en preparación o listo. Se devolverá el stock y se reembolsará
                                <strong><?= h(moneda($montoReembolso)) ?></strong>
                                (<strong><?= (int) $porcentajeReembolso ?>%</strong> de lo pagado)
                                por <strong><?= h((string) $metodoPagoSugerido) ?></strong>.
                            <?php else: ?>
                                Se devolverá el stock y se reembolsará
                                <strong><?= h(moneda($montoReembolso)) ?></strong>
                                (<strong>100%</strong> de lo pagado)
                                por <strong><?= h((string) $metodoPagoSugerido) ?></strong>.
                            <?php endif; ?>
                            <?php if ($metodoPagoSugerido === 'EFECTIVO'): ?>Se registrará un egreso en la caja abierta.<?php endif; ?>
                        </p>
                        <?php if ($metodoPagoSugerido === 'TARJETA'): ?>
                            <div class="campo">
                                <label for="referencia_reembolso">Referencia del reembolso (opcional)</label>
                                <input id="referencia_reembolso" name="referencia_reembolso" type="text" maxlength="100">
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <input type="hidden" name="metodo_reembolso" value="NINGUNO">
                        <p class="nota">El pedido no tiene pago confirmado: no hay dinero que reembolsar ni stock que devolver.</p>
                    <?php endif; ?>
                    <button class="btn btn-peligro" type="submit">Confirmar cancelación</button>
                </form>
            </details>
        <?php endif; ?>
    </div>

    <div class="pedido-secundario">
        <details>
            <summary>Pagos registrados (<?= count($pedidoVer['pagos'] ?? []) ?>)</summary>
            <?php if (empty($pedidoVer['pagos'])): ?>
                <p class="texto-suave">Sin pagos.</p>
            <?php else: ?>
                <ul class="lista-pagos">
                    <?php foreach ($pedidoVer['pagos'] as $pago): ?>
                        <li>
                            <strong><?= h((string) $pago['metodo']) ?> · <?= h(moneda((float) $pago['monto'])) ?></strong>
                            <span class="pastilla <?= $pago['estado'] === 'CONFIRMADO' ? 'pastilla-verde' : 'pastilla-roja' ?>"><?= h((string) $pago['estado']) ?></span>
                            <span class="texto-suave"><?= h(fechaBonita($pago['creado_en'])) ?><?= !empty($pago['referencia']) ? ' · ' . h((string) $pago['referencia']) : '' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </details>
        <details>
            <summary>Historial (<?= count($pedidoVer['historial'] ?? []) ?>)</summary>
            <ul class="lista-historial">
                <?php foreach (($pedidoVer['historial'] ?? []) as $paso): ?>
                    <li>
                        <strong><?= h(etiquetaEstado((string) $paso['estado_nuevo'])) ?></strong>
                        <span><?= h(fechaBonita($paso['cambiado_en'])) ?></span>
                        <span class="texto-suave"><?= h((string) ($paso['usuario'] ?? 'Sistema')) ?><?= !empty($paso['motivo']) ? ' · ' . h((string) $paso['motivo']) : '' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </details>
    </div>
</section>
