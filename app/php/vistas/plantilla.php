<?php
/**
 * Utilidades HTML y estructura visual compartida por CoffeeAndtea.
 */
declare(strict_types=1);

/** Escapa texto para mostrarlo seguro en HTML. */
function h(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Número de unidades en el carrito. */
function unidadesCarrito(): int
{
    $carrito = $_SESSION['carrito'] ?? [];
    if (!is_array($carrito)) {
        return 0;
    }
    $total = 0;
    foreach ($carrito as $cantidad) {
        if (is_numeric($cantidad)) {
            $total += (int) $cantidad;
        }
    }
    return $total;
}

/** Cabecera HTML, navegación por rol y apertura del contenido. */
function cabeceraHtml(string $titulo, string $seccionActiva = 'tienda', bool $mostrarNavegacion = true): void
{
    $usuario = function_exists('usuarioActual') ? usuarioActual() : null;
    $unidades = unidadesCarrito();
    $esGestion = $usuario !== null && in_array($usuario['rol'], ['ENCARGADO', 'ADMIN'], true);

    $secciones = [];
    $secciones['tienda'] = ['href' => url('index.php'), 'texto' => 'Tienda'];
    if (!$esGestion) {
        $secciones['carrito'] = ['href' => url('carrito.php'), 'texto' => 'Carrito' . ($unidades > 0 ? ' (' . $unidades . ')' : '')];
    }
    if ($usuario !== null && !$esGestion) {
        $secciones['cuenta'] = ['href' => url('cuenta.php'), 'texto' => 'Mi cuenta'];
    }
    if (!$esGestion && function_exists('pedidosInvitadoLeer') && pedidosInvitadoLeer() !== []) {
        $secciones['pedidos'] = [
            'href'  => url('pedidos-invitado.php'),
            'texto' => $usuario === null ? 'Mis pedidos' : 'Pedidos recientes',
        ];
    }
    if ($esGestion) {
        $secciones['gestion'] = ['href' => url('gestion.php'), 'texto' => 'Gestión'];
        $secciones['reportes'] = ['href' => url('reportes.php'), 'texto' => 'Reportes'];
    }
    if ($usuario !== null && $usuario['rol'] === 'ADMIN') {
        $secciones['admin'] = ['href' => url('admin.php'), 'texto' => 'Administración'];
    }
    $secciones['manual'] = ['href' => url('manual.php'), 'texto' => function_exists('manualActual') ? manualActual()['etiqueta'] : 'Manual'];

    echo "<!DOCTYPE html>\n";
    echo "<html lang=\"es\">\n<head>\n";
    echo "    <meta charset=\"UTF-8\">\n";
    echo "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    echo "    <meta name=\"theme-color\" content=\"#3b2415\">\n";
    echo "    <meta name=\"description\" content=\"CoffeeAndtea: pide tu café y recógelo en tienda.\">\n";
    echo '    <link rel="icon" href="' . h(url('media/favicon.svg')) . '" type="image/svg+xml">' . "\n";
    $rutaCss = APP_ROOT . '/public/css/app.css';
    $versionCss = is_file($rutaCss) ? (string) filemtime($rutaCss) : '1';
    echo '    <link rel="stylesheet" href="' . h(url('css/app.css?v=' . $versionCss)) . '">' . "\n";
    echo '    <title>' . h($titulo) . "</title>\n";
    echo "</head>\n\n<body class=\"pagina-" . h($seccionActiva) . "\">\n";
    echo '    <a class="skip-link" href="#contenido">Saltar al contenido</a>' . "\n";
    echo "    <header class=\"topbar\">\n";
    echo "        <div class=\"topbar-inner\">\n";
    echo '            <a class="brand" href="' . h(url('index.php')) . '" aria-label="CoffeeAndtea, ir a la tienda"><span class="brand-icono" aria-hidden="true"><img src="' . h(url('media/logo-coffeeandtea.png')) . '" alt="" width="46" height="46" decoding="async"></span><span class="brand-texto">CoffeeAndtea</span></a>' . "\n";

    if ($mostrarNavegacion) {
        echo "            <nav class=\"menu\" aria-label=\"Secciones del sistema\">\n";
        foreach ($secciones as $id => $seccion) {
            $activa = $id === $seccionActiva ? ' class="activo" aria-current="page"' : '';
            echo '                <a href="' . h($seccion['href']) . '"' . $activa . '>' . h($seccion['texto']) . "</a>\n";
        }
        if ($usuario !== null) {
            echo '                <span class="menu-usuario" title="' . h($usuario['correo']) . '">' . h($usuario['nombre']) . ' · ' . h($usuario['rol']) . "</span>\n";
            echo '                <a class="menu-salir" href="' . h(url('logout.php')) . '">Salir</a>' . "\n";
        } else {
            echo '                <a href="' . h(url('login.php')) . '">Ingresar</a>' . "\n";
        }
        echo "            </nav>\n";
    }
    echo "        </div>\n";
    echo "    </header>\n\n";

    if (function_exists('tomarFlashes')) {
        $flashes = tomarFlashes();
        if (!empty($flashes)) {
            toastsHtml($flashes);
        }
    }

    echo "    <main class=\"app-main\" id=\"contenido\">\n";
}

/** Cierra el contenido, imprime el pie y termina el documento. */
function pieHtml(): void
{
    echo "    </main>\n\n";
    echo "    <footer class=\"site-footer\">\n";
    echo "        <p><strong>CoffeeAndtea</strong> &middot; Pedidos para recoger en tienda &middot; Pago en mostrador</p>\n";
    echo '        <p><a href="' . h(url('manual.php')) . '">' . h(function_exists('manualActual') ? manualActual()['etiqueta'] : 'Manual') . '</a> &middot; '
        . '<a href="' . h(url('privacidad.php')) . '">Privacidad</a> &middot; '
        . '<a href="' . h(url('politica-cancelacion.php')) . '">Cancelación</a> &middot; '
        . date('Y') . "</p>\n";
    echo "    </footer>\n";
    echo '    <script src="' . h(url('js/tienda.js')) . "\"></script>\n";
    echo "    <script>\n";
    echo "        (function () {\n";
    echo "            function cerrarToast(toast) {\n";
    echo "                if (!toast || toast.classList.contains('toast-saliendo')) { return; }\n";
    echo "                toast.classList.add('toast-saliendo');\n";
    echo "                window.setTimeout(function () { toast.remove(); }, 260);\n";
    echo "            }\n";
    echo "            document.addEventListener('click', function (evento) {\n";
    echo "                var boton = evento.target.closest('.toast button');\n";
    echo "                if (boton) { cerrarToast(boton.closest('.toast')); }\n";
    echo "            });\n";
    echo "            Array.prototype.forEach.call(document.querySelectorAll('.toast'), function (toast) {\n";
    echo "                window.setTimeout(function () { cerrarToast(toast); }, 6000);\n";
    echo "            });\n";
    echo "        })();\n";
    echo "    </script>\n";
    echo "</body>\n</html>\n";
}

/** Imprime avisos flash como notificaciones flotantes. */
function toastsHtml(array $flashes): void
{
    $validos = [];
    foreach ($flashes as $flash) {
        if (!is_array($flash)) {
            continue;
        }
        $mensaje = trim((string) ($flash['mensaje'] ?? ''));
        if ($mensaje === '') {
            continue;
        }
        $tipo = (string) ($flash['tipo'] ?? 'info');
        if (!in_array($tipo, ['exito', 'error', 'info'], true)) {
            $tipo = 'info';
        }
        $validos[] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    }

    if (empty($validos)) {
        return;
    }

    echo '    <div class="toasts" aria-live="polite">' . "\n";
    foreach ($validos as $flash) {
        $rol = $flash['tipo'] === 'error' ? 'alert' : 'status';
        echo '        <div class="toast toast-' . $flash['tipo'] . '" role="' . $rol . '">' . "\n";
        echo '            <p>' . h($flash['mensaje']) . '</p>' . "\n";
        echo '            <button type="button" aria-label="Cerrar aviso">&times;</button>' . "\n";
        echo "        </div>\n";
    }
    echo "    </div>\n";
}

/** Avisos de éxito/error usados por formularios. */
function avisosHtml(?string $mensajeExito, array $errores): void
{
    if ($mensajeExito !== null) {
        echo '        <p role="status" class="alerta-exito">' . h($mensajeExito) . "</p>\n";
    }
    if (!empty($errores)) {
        echo '        <div role="alert" class="alerta-error">' . "\n";
        echo "            <strong>No se pudo completar la operación:</strong>\n";
        echo "            <ul>\n";
        foreach ($errores as $error) {
            echo '                <li>' . h($error) . "</li>\n";
        }
        echo "            </ul>\n";
        echo "        </div>\n";
    }
}

/** Distintivo visual del estado de un pedido. */
function badgeEstado(string $estado): string
{
    return '<span class="badge ' . h(claseEstado($estado)) . '">' . h(etiquetaEstado($estado)) . '</span>';
}
