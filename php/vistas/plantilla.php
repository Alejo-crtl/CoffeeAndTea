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

/** Imprime la cabecera HTML, la barra de navegación y abre el contenido. */
function cabeceraHtml(string $titulo, string $seccionActiva = 'inicio'): void
{
    $baseUrl = baseUrl();

    $secciones = [
        'inicio'   => ['href' => $baseUrl . 'index.php',    'texto' => 'Inicio'],
        'gestion'  => ['href' => $baseUrl . 'gestion.php',  'texto' => 'Gestión'],
        'reportes' => ['href' => $baseUrl . 'reportes.php', 'texto' => 'Reportes'],
        'manual'   => ['href' => $baseUrl . 'manual.php',   'texto' => 'Manual'],
    ];

    echo "<!DOCTYPE html>\n";
    echo "<html lang=\"es\">\n<head>\n";
    echo "    <meta charset=\"UTF-8\">\n";
    echo "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    echo "    <meta name=\"theme-color\" content=\"#3b2415\">\n";
    echo "    <meta name=\"description\" content=\"Sistema de gestión CoffeeAndtea: CRUD, reportes y manual.\">\n";
    echo '    <link rel="icon" href="' . h($baseUrl . 'media/favicon.svg') . '" type="image/svg+xml">' . "\n";
    $rutaCss = __DIR__ . '/../../css/app.css';
    $versionCss = is_file($rutaCss) ? (string) filemtime($rutaCss) : '1';
    echo '    <link rel="stylesheet" href="' . h($baseUrl . 'css/app.css?v=' . $versionCss) . '">' . "\n";
    echo '    <title>' . h($titulo) . "</title>\n";
    echo "</head>\n\n<body>\n";
    echo '    <a class="skip-link" href="#contenido">Saltar al contenido</a>' . "\n";
    echo "    <header class=\"topbar\">\n";
    echo "        <div class=\"topbar-inner\">\n";
    echo '            <a class="brand" href="' . h($baseUrl . 'index.php') . '" aria-label="CoffeeAndtea, ir al inicio"><span class="brand-icono" aria-hidden="true"><img src="' . h($baseUrl . 'media/logo-coffeeandtea.png') . '" alt="" width="46" height="46" decoding="async"></span><span class="brand-texto">CoffeeAndtea</span></a>' . "\n";
    echo "            <nav class=\"menu\" aria-label=\"Secciones del sistema\">\n";
    foreach ($secciones as $id => $seccion) {
        $activa = $id === $seccionActiva ? ' class="activo" aria-current="page"' : '';
        echo '                <a href="' . h($seccion['href']) . '"' . $activa . '>' . h($seccion['texto']) . "</a>\n";
    }
    echo "            </nav>\n";
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
    $baseUrl = baseUrl();

    echo "    </main>\n\n";
    echo "    <footer class=\"site-footer\">\n";
    echo "        <p><strong>CoffeeAndtea</strong> &middot; Sistema de gestión &middot; PHP 8 + PostgreSQL</p>\n";
    echo "        <p>Manual generado desde <code>manual.md</code></p>\n";
    echo "    </footer>\n";
    echo '    <script src="' . h($baseUrl . 'js/detalles.js') . "\"></script>\n";
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

/**
 * Imprime los avisos flash como notificaciones flotantes.
 *
 * @param array<int,array{tipo?:string,mensaje?:string}> $flashes
 */
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

/** Avisos de éxito/error usados por los formularios antiguos. */
function avisosHtml(?string $mensajeExito, array $errores): void
{
    if ($mensajeExito !== null) {
        echo '        <p role="status" class="alerta-exito">' . h($mensajeExito) . "</p>\n";
    }

    if (!empty($errores)) {
        echo '        <div role="alert" class="alerta-error">' . "\n";
        echo "            <strong>No se pudo completar el registro:</strong>\n";
        echo "            <ul>\n";
        foreach ($errores as $error) {
            echo '                <li>' . h($error) . "</li>\n";
        }
        echo "            </ul>\n";
        echo "        </div>\n";
    }
}
