/**
 * BLOQUE B13 · FRONTEND
 * Qué hace: interacciones de la tienda y del panel de gestión.
 *  - Formatea montos en pesos.
 *  - Muestra u oculta los campos de efectivo/tarjeta.
 *  - Calcula el cambio del pago en efectivo.
 * Páginas: gestion.php y formularios de pago.
 */
(function () {
    'use strict';

    /** Formatea un número como pesos colombianos sin decimales. */
    function formatoMoneda(valor) {
        return '$' + new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(valor || 0);
    }

    /** Muestra el campo correspondiente al método de pago elegido. */
    function actualizarPago(form) {
        var metodo = form.querySelector('[data-metodo-pago]');
        if (!metodo) {
            return;
        }
        var efectivo = form.querySelector('[data-campo-efectivo]');
        var tarjeta = form.querySelector('[data-campo-tarjeta]');
        var esEfectivo = metodo.value === 'EFECTIVO';
        if (efectivo) {
            efectivo.hidden = !esEfectivo;
            var input = efectivo.querySelector('[data-recibido]');
            if (input) {
                input.required = esEfectivo;
            }
        }
        if (tarjeta) {
            tarjeta.hidden = esEfectivo;
        }
        actualizarCambio(form);
    }

    /** Calcula y muestra el cambio cuando el pago es en efectivo. */
    function actualizarCambio(form) {
        var recibido = form.querySelector('[data-recibido]');
        var salida = form.querySelector('[data-cambio]');
        if (!recibido || !salida) {
            return;
        }
        var total = parseFloat(recibido.getAttribute('data-total') || '0');
        var valor = parseFloat(recibido.value || '0');
        salida.textContent = formatoMoneda(Math.max(0, valor - total));
    }

    // Inicializa todos los formularios de pago de la página.
    document.querySelectorAll('[data-form-pago]').forEach(function (form) {
        var metodo = form.querySelector('[data-metodo-pago]');
        if (metodo) {
            metodo.addEventListener('change', function () {
                actualizarPago(form);
            });
        }
        var recibido = form.querySelector('[data-recibido]');
        if (recibido) {
            recibido.addEventListener('input', function () {
                actualizarCambio(form);
            });
        }
        actualizarPago(form);
    });
})();
