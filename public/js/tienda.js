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

    // Bloquea el doble envío y avisa que el formulario se está procesando.
    document.addEventListener('submit', function (evento) {
        var form = evento.target;
        if (!form || form.dataset.sinBloqueo !== undefined) {
            return;
        }
        var boton = form.querySelector('button[type="submit"], input[type="submit"]');
        if (!boton || boton.disabled) {
            return;
        }
        if (boton.tagName === 'INPUT') {
            boton.value = 'Procesando…';
        } else {
            boton.textContent = 'Procesando…';
        }
        boton.disabled = true;
        form.setAttribute('aria-busy', 'true');
    });

    // Marca las tablas que se desplazan en horizontal.
    function marcarTablas() {
        document.querySelectorAll('.tabla-scroll').forEach(function (caja) {
            caja.classList.toggle('con-desborde', caja.scrollWidth > caja.clientWidth + 4);
        });
    }
    marcarTablas();
    window.addEventListener('resize', marcarTablas);

    // Habilita la aceptación de una política después de abrirla en otra pestaña.
    document.querySelectorAll('[data-politica]').forEach(function (bloque) {
        var check = bloque.querySelector('[data-politica-check]');
        var enlace = bloque.querySelector('[data-politica-enlace]');
        var nota = document.getElementById(bloque.getAttribute('data-politica-nota') || '');
        if (!check || !enlace) {
            return;
        }

        var clave = 'politica:' + (check.name || 'aceptar');
        var abierta = false;
        try {
            abierta = window.sessionStorage.getItem(clave) === '1';
        } catch (error) {
            abierta = true;
        }

        function habilitar() {
            check.disabled = false;
            if (nota) {
                nota.hidden = true;
            }
            try {
                window.sessionStorage.setItem(clave, '1');
            } catch (error) {
                // Sin sessionStorage se habilita solo en esta página.
            }
        }

        if (abierta) {
            habilitar();
            return;
        }

        check.disabled = true;
        if (nota) {
            nota.hidden = false;
            var ids = (check.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            if (ids.indexOf(nota.id) === -1) {
                ids.push(nota.id);
            }
            check.setAttribute('aria-describedby', ids.join(' '));
        }

        enlace.addEventListener('click', function () {
            habilitar();
            check.focus();
        });
    });

    // Lleva el foco al primer campo con error.
    var primerInvalido = document.querySelector('[aria-invalid="true"]');
    if (primerInvalido) {
        primerInvalido.focus();
    }
})();
