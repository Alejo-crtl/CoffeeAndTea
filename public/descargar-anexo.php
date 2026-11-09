<?php
/**
 * Descarga controlada de los documentos de anexos/.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/config.php';
require_once __DIR__ . '/../app/php/lib/anexos.php';

$id = (string) ($_GET['id'] ?? '');
$anexos = listaAnexos();

if (!isset($anexos[$id])) {
    http_response_code(404);
    exit('Anexo no encontrado.');
}

$anexo = $anexos[$id];
$ruta = APP_ROOT . '/anexos/' . $anexo['archivo'];

if (!is_file($ruta) || !is_readable($ruta)) {
    http_response_code(404);
    exit('El archivo no está disponible.');
}

header('Content-Type: ' . $anexo['mime']);
header('Content-Disposition: attachment; filename="' . $anexo['archivo'] . '"');
header('Content-Length: ' . (string) filesize($ruta));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($ruta);
