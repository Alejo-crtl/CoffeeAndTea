<?php
/**
 * BLOQUE B10 · DOCUMENTACIÓN, ANEXOS Y VIDEO
 * Qué hace: descarga el manual del rol en Markdown o Word.
 * Relacionados: manual_rol.php, docs/manual*.md, docs/word/*.docx.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';

$manualActual = manualActual();
$formato = strtolower((string) ($_GET['formato'] ?? 'md'));

if ($formato === 'docx') {
    $ruta = APP_ROOT . '/docs/word/' . $manualActual['descarga_docx'];
    $nombre = $manualActual['descarga_docx'];
    $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
} else {
    $formato = 'md';
    $ruta = APP_ROOT . '/docs/' . $manualActual['archivo'];
    $nombre = $manualActual['descarga'];
    $mime = 'text/markdown; charset=UTF-8';
}

if (!is_file($ruta) || !is_readable($ruta)) {
    http_response_code(404);
    exit('Manual no encontrado.');
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Length: ' . (string) filesize($ruta));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($ruta);
