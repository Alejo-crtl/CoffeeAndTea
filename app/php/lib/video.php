<?php
/**
 * BLOQUE B10 · DOCUMENTACIÓN, ANEXOS Y VIDEO
 * Qué hace: valida enlaces de YouTube/Drive y obtiene la URL embebible.
 * Funciones: normalizarUrlVideo(), urlVideoPresentacion().
 * Tablas: config_sistema (video_presentacion_url).
 * Páginas: video.php, admin.php.
 * Pruebas: CP-054.
 */
declare(strict_types=1);

/**
 * Convierte un enlace de YouTube o Google Drive a una URL embebible.
 *
 * Devuelve null si el enlace está vacío o no pertenece a un dominio permitido.
 */
function normalizarUrlVideo(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }

    // YouTube: youtu.be/ID, youtube.com/watch?v=ID, embed/ID o shorts/ID.
    if (preg_match('~^https?://(?:www\.)?youtu\.be/([A-Za-z0-9_-]{6,})~i', $url, $coincidencia)) {
        return 'https://www.youtube-nocookie.com/embed/' . $coincidencia[1] . '?rel=0';
    }
    if (preg_match(
        '~^https?://(?:www\.)?youtube\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/)([A-Za-z0-9_-]{6,})~i',
        $url,
        $coincidencia
    )) {
        return 'https://www.youtube-nocookie.com/embed/' . $coincidencia[1] . '?rel=0';
    }

    // Google Drive: /file/d/ID/... o /open?id=ID.
    if (preg_match('~^https?://drive\.google\.com/file/d/([A-Za-z0-9_-]{10,})~i', $url, $coincidencia)) {
        return 'https://drive.google.com/file/d/' . $coincidencia[1] . '/preview';
    }
    if (preg_match('~^https?://drive\.google\.com/open\?id=([A-Za-z0-9_-]{10,})~i', $url, $coincidencia)) {
        return 'https://drive.google.com/file/d/' . $coincidencia[1] . '/preview';
    }

    return null;
}

/** Devuelve la URL embebible configurada, o null si no hay video. */
function urlVideoPresentacion(PDO $conn): ?string
{
    $url = '';
    try {
        $stmt = $conn->prepare('SELECT valor FROM config_sistema WHERE clave = :clave');
        $stmt->execute([':clave' => 'video_presentacion_url']);
        $url = (string) ($stmt->fetchColumn() ?: '');
    } catch (Throwable $e) {
        error_log('CoffeeAndtea - video: ' . $e->getMessage());
    }

    if ($url === '') {
        $url = (string) env('APP_VIDEO_URL', '');
    }

    return normalizarUrlVideo($url);
}
