<?php
/**
 * BLOQUE B10 · DOCUMENTACIÓN, ANEXOS Y VIDEO
 * Qué hace: descarga el manual del rol en Markdown.
 * Relacionados: manual_rol.php, docs/manual*.md.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';

$manualActual = manualActual();
$ruta = APP_ROOT . '/docs/' . $manualActual['archivo'];

if (!is_file($ruta) || !is_readable($ruta)) {
    http_response_code(404);
    exit('Manual no encontrado.');
}

header('Content-Type: text/markdown; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $manualActual['descarga'] . '"');
header('Content-Length: ' . (string) filesize($ruta));
header('X-Content-Type-Options: nosniff');

readfile($ruta);
