<?php
/**
 * Router para el servidor PHP de respaldo (php -S).
 *
 * - Sirve los archivos existentes (CSS, JS, imágenes, páginas PHP).
 * - Bloquea el acceso HTTP a la carpeta data/.
 * - Devuelve 404 para rutas que no existen.
 */
declare(strict_types=1);

$uri = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$uri = rawurldecode($uri);

if (preg_match('#^/data(/|$)#', $uri)) {
    http_response_code(403);
    echo 'Acceso denegado.';
    return true;
}

$raiz = realpath(__DIR__ . '/..');
if ($raiz === false) {
    http_response_code(500);
    echo 'No se encontró la raíz del proyecto.';
    return true;
}

$ruta = realpath($raiz . str_replace('/', DIRECTORY_SEPARATOR, $uri));
if ($ruta !== false
    && str_starts_with($ruta, $raiz)
    && is_file($ruta)
) {
    // Deja que el servidor integrado ejecute el PHP o entregue el archivo.
    return false;
}

if ($uri === '/' || $uri === '') {
    require $raiz . DIRECTORY_SEPARATOR . 'index.php';
    return true;
}

http_response_code(404);
echo 'No encontrado.';
return true;
