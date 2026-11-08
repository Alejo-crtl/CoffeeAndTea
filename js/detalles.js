/**
 * Formulario multi-producto de detalles de pedido.
 *
 * - Agrega y quita filas sin recargar.
 * - Renumera los nombres (detalles[n][...]) para que el servidor reciba una
 *   lista continua después de quitar una fila.
 * - Deshabilita los productos ya elegidos en otra fila y los que ya están
 *   guardados en el pedido seleccionado.
 *
 * Si este archivo no se carga, el formulario sigue funcionando con la primera
 * fila y el servidor valida el lote igual.
 */
(function () {
    'use strict';

    var formulario = document.getElementById('detalle-form');
    if (!formulario || formulario.dataset.modo !== 'crear') {
        return;
    }

    var contenedor = document.getElementById('detalle-lineas');
    var plantilla = document.getElementById('detalle-linea-plantilla');
    var botonAgregar = document.getElementById('detalle-agregar');
    var pedido = document.getElementById('det-pedido');
    var datosOcupados = document.getElementById('detalle-ocupados');

    if (!contenedor || !plantilla || !botonAgregar || !pedido) {
        return;
    }

    var ocupados = {};
    if (datosOcupados) {
        try {
            ocupados = JSON.parse(datosOcupados.textContent || '{}') || {};
        } catch (error) {
            ocupados = {};
        }
    }

    var contador = contenedor.querySelectorAll('.detalle-linea').length;

    function filas() {
        return Array.prototype.slice.call(contenedor.querySelectorAll('.detalle-linea'));
    }

    function renumerar() {
        var lista = filas();

        lista.forEach(function (fila, indice) {
            Array.prototype.forEach.call(fila.querySelectorAll('[name]'), function (campo) {
                campo.name = campo.name.replace(/detalles\[\d+\]/, 'detalles[' + indice + ']');
            });

            var quitar = fila.querySelector('.btn-quitar');
            if (quitar) {
                quitar.setAttribute('aria-label', 'Quitar el producto ' + (indice + 1));
                quitar.disabled = lista.length === 1;
            }
        });
    }

    function productosOcupadosDelPedido() {
        var lista = ocupados[pedido.value] || [];
        return new Set(lista.map(String));
    }

    function actualizarOpciones() {
        var selects = filas().map(function (fila) {
            return fila.querySelector('select');
        }).filter(Boolean);

        var ocupadosPedido = productosOcupadosDelPedido();

        selects.forEach(function (select) {
            var enOtrasFilas = new Set();
            selects.forEach(function (otro) {
                if (otro !== select && otro.value) {
                    enOtrasFilas.add(otro.value);
                }
            });

            Array.prototype.forEach.call(select.querySelectorAll('option'), function (opcion) {
                if (!opcion.value) {
                    return;
                }
                var usado = ocupadosPedido.has(opcion.value) || enOtrasFilas.has(opcion.value);
                // La opción elegida en esta misma fila se deja habilitada para
                // que el error del servidor (si lo hay) siga siendo visible.
                opcion.disabled = usado && opcion.value !== select.value;
            });
        });
    }

    function agregarFila() {
        var html = plantilla.innerHTML.replace(/__i__/g, String(contador));
        contador += 1;

        var temporal = document.createElement('div');
        temporal.innerHTML = html.trim();
        var nueva = temporal.firstElementChild;
        if (!nueva) {
            return;
        }

        contenedor.appendChild(nueva);
        renumerar();
        actualizarOpciones();

        var select = nueva.querySelector('select');
        if (select) {
            select.focus();
        }
    }

    contenedor.addEventListener('click', function (evento) {
        var boton = evento.target.closest('.btn-quitar');
        if (!boton || !contenedor.contains(boton)) {
            return;
        }

        var fila = boton.closest('.detalle-linea');
        if (!fila || filas().length === 1) {
            return;
        }

        fila.remove();
        renumerar();
        actualizarOpciones();
    });

    botonAgregar.addEventListener('click', agregarFila);
    pedido.addEventListener('change', actualizarOpciones);

    contenedor.addEventListener('change', function (evento) {
        if (evento.target && evento.target.tagName === 'SELECT') {
            actualizarOpciones();
        }
    });

    formulario.addEventListener('reset', function () {
        window.setTimeout(function () {
            var lista = filas();

            for (var i = lista.length - 1; i >= 1; i -= 1) {
                lista[i].remove();
            }

            var primera = filas()[0];
            if (primera) {
                var select = primera.querySelector('select');
                if (select) {
                    select.value = '';
                }
                var cantidad = primera.querySelector('input[type="number"]');
                if (cantidad) {
                    cantidad.value = '1';
                }
            }

            renumerar();
            actualizarOpciones();
        }, 0);
    });

    renumerar();
    actualizarOpciones();
})();
