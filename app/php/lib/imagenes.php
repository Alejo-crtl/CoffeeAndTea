<?php
/**
 * BLOQUE B03 · CATÁLOGO
 * Qué hace: valida, ajusta, guarda y elimina las imágenes de producto.
 * Funciones: guardarImagenProducto(), normalizarImagenProducto(),
 *            eliminarImagenProducto(), imagenProductoDisponible(),
 *            urlImagenProducto().
 * Tablas: producto (imagen_url).
 * Páginas: admin.php, index.php.
 * Reglas: JPG/PNG/WebP, máximo 2 MB, sin recortar, máximo 1400 px por lado.
 * Pruebas: CP-050 a CP-053, CP-055.
 */
declare(strict_types=1);

/** Tamaño máximo admitido para una imagen subida (2 MB). */
const IMAGEN_PRODUCTO_MAX_BYTES = 2 * 1024 * 1024;

/** Lado máximo al que se ajusta la imagen sin recortar. */
const IMAGEN_PRODUCTO_MAX_LADO = 1400;

/** Carpeta física donde viven las imágenes de producto. */
function carpetaImagenesProductos(): string
{
    return APP_ROOT . '/public/media/productos';
}

/**
 * Normaliza una ruta de producto.imagen_url.
 *
 * Solo se aceptan rutas relativas dentro de media/productos. Cualquier otro
 * valor devuelve cadena vacía para evitar rutas externas o saltos de carpeta.
 */
function rutaWebImagenProducto(?string $ruta): string
{
    $ruta = str_replace('\\', '/', trim((string) $ruta));
    $ruta = ltrim($ruta, '/');
    if ($ruta === '' || str_contains($ruta, '..')) {
        return '';
    }
    if (!preg_match('#^media/productos/[A-Za-z0-9._-]+\.(?:jpe?g|png|webp)$#i', $ruta)) {
        return '';
    }
    return $ruta;
}

/** Ruta absoluta segura de una imagen de producto, o null si no existe. */
function rutaAbsolutaImagenProducto(?string $ruta): ?string
{
    $rutaWeb = rutaWebImagenProducto($ruta);
    if ($rutaWeb === '') {
        return null;
    }
    $absoluta = APP_ROOT . '/public/' . $rutaWeb;
    if (!is_file($absoluta)) {
        return null;
    }
    $real = realpath($absoluta);
    $carpeta = realpath(carpetaImagenesProductos());
    if ($real === false || $carpeta === false) {
        return null;
    }
    $carpeta = rtrim(str_replace('\\', '/', $carpeta), '/') . '/';
    $real = str_replace('\\', '/', $real);
    return str_starts_with($real, $carpeta) ? $real : null;
}

/** Indica si la ruta corresponde a una imagen existente y válida. */
function imagenProductoDisponible(?string $ruta): bool
{
    return rutaAbsolutaImagenProducto($ruta) !== null;
}

/** URL pública de la imagen de un producto. */
function urlImagenProducto(?string $ruta): string
{
    return url(rutaWebImagenProducto($ruta));
}

/**
 * Valida y guarda una imagen subida desde el panel.
 *
 * Devuelve la ruta relativa para producto.imagen_url.
 *
 * @param array<string, mixed> $archivo Entrada de $_FILES.
 * @throws ReglaNegocioException cuando el archivo no es válido.
 */
function guardarImagenProducto(array $archivo): string
{
    $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new ReglaNegocioException('Selecciona una imagen para subir.');
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new ReglaNegocioException('La imagen supera el tamaño permitido de 2 MB.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new ReglaNegocioException('No se pudo recibir la imagen. Inténtalo de nuevo.');
    }

    $temporal = (string) ($archivo['tmp_name'] ?? '');
    $tamano = (int) ($archivo['size'] ?? 0);
    if ($temporal === '' || !is_uploaded_file($temporal)) {
        throw new ReglaNegocioException('La imagen no llegó correctamente al servidor.');
    }
    if ($tamano <= 0) {
        throw new ReglaNegocioException('La imagen está vacía o dañada.');
    }
    if ($tamano > IMAGEN_PRODUCTO_MAX_BYTES) {
        throw new ReglaNegocioException('La imagen no puede superar los 2 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($temporal);
    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($permitidos[$mime])) {
        throw new ReglaNegocioException('Formato no permitido. Usa JPG, PNG o WebP.');
    }

    $medidas = @getimagesize($temporal);
    if ($medidas === false || (int) $medidas[0] < 1 || (int) $medidas[1] < 1) {
        throw new ReglaNegocioException('El archivo no es una imagen válida.');
    }
    if ((int) $medidas[0] > 8000 || (int) $medidas[1] > 8000) {
        throw new ReglaNegocioException('La imagen es demasiado grande. Usa una de hasta 8000 px por lado.');
    }

    $carpeta = carpetaImagenesProductos();
    if (!is_dir($carpeta) && !@mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
        throw new ReglaNegocioException('No se pudo preparar la carpeta de imágenes.');
    }
    if (!is_writable($carpeta)) {
        throw new ReglaNegocioException('La carpeta de imágenes no tiene permisos de escritura.');
    }

    $nombre = 'producto-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.jpg';
    $destino = $carpeta . '/' . $nombre;

    if (!normalizarImagenProducto($temporal, $destino)) {
        throw new ReglaNegocioException('No se pudo procesar la imagen. Prueba con otro archivo.');
    }

    return 'media/productos/' . $nombre;
}

/**
 * Ajusta la imagen a un máximo de 1400 px por lado, sin recortarla, y la
 * guarda como JPEG. Conserva la proporción original.
 *
 * Requiere la extensión GD con soporte para JPEG, PNG y WebP.
 */
function normalizarImagenProducto(string $origen, string $destino): bool
{
    if (!function_exists('imagecreatefromstring')) {
        return false;
    }

    $contenido = @file_get_contents($origen);
    if ($contenido === false) {
        return false;
    }
    $imagen = @imagecreatefromstring($contenido);
    if ($imagen === false) {
        return false;
    }

    $anchoOrigen = imagesx($imagen);
    $altoOrigen = imagesy($imagen);
    $escala = min(1.0, IMAGEN_PRODUCTO_MAX_LADO / $anchoOrigen, IMAGEN_PRODUCTO_MAX_LADO / $altoOrigen);
    $anchoDestino = max(1, (int) round($anchoOrigen * $escala));
    $altoDestino = max(1, (int) round($altoOrigen * $escala));

    $lienzo = imagecreatetruecolor($anchoDestino, $altoDestino);
    if ($lienzo === false) {
        imagedestroy($imagen);
        return false;
    }

    // Fondo crema por si la imagen original tiene transparencia.
    $fondo = imagecolorallocate($lienzo, 244, 234, 220);
    imagefilledrectangle($lienzo, 0, 0, $anchoDestino, $altoDestino, $fondo);

    imagecopyresampled(
        $lienzo,
        $imagen,
        0,
        0,
        0,
        0,
        $anchoDestino,
        $altoDestino,
        $anchoOrigen,
        $altoOrigen
    );

    $ok = imagejpeg($lienzo, $destino, 85);
    imagedestroy($lienzo);
    imagedestroy($imagen);
    return $ok;
}

/**
 * Borra el archivo de una imagen de producto si ya nadie la referencia.
 *
 * @param PDO|null $conn Conexión para comprobar otros productos.
 * @param int|null $excluirProductoId Producto que se está editando.
 */
function eliminarImagenProducto(?string $ruta, ?PDO $conn = null, ?int $excluirProductoId = null): bool
{
    $absoluta = rutaAbsolutaImagenProducto($ruta);
    if ($absoluta === null) {
        return false;
    }

    if ($conn !== null) {
        $rutaWeb = rutaWebImagenProducto($ruta);
        $sql = 'SELECT COUNT(*) FROM producto WHERE imagen_url = :ruta';
        $params = [':ruta' => $rutaWeb];
        if ($excluirProductoId !== null && $excluirProductoId > 0) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excluirProductoId;
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }
    }

    return @unlink($absoluta);
}
