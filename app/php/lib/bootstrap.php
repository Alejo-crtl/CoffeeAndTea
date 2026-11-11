<?php
/**
 * ============================================================
 * BLOQUE B01 · NÚCLEO
 * ============================================================
 * Qué hace:
 *   Arranque común: carga .env, configura errores, sesión, cabeceras
 *   de seguridad, conexión PDO y librerías base.
 *
 * Funciones principales:
 *   flash(), tomarFlashes(), csrfToken(), campoCsrf(), validarCsrf(),
 *   redirigir(), moneda(), fechaBonita(), slugificar(), urlVolver()
 *
 * Tablas:
 *   No aplica.
 *
 * Páginas que lo usan:
 *   Todas las páginas de public/.
 *
 * Reglas clave:
 *   - La sesión se configura antes de iniciarla.
 *   - Los errores no se muestran en producción.
 *   - El CSRF se valida en todos los POST.
 *
 * Mapa de bloques:
 *   B01 Núcleo · B02 Autenticación · B03 Catálogo · B04 Carrito y pedidos
 *   B05 Caja y pagos · B06 Cancelaciones · B07 Stock · B08 Administración
 *   B09 Reportes · B10 Documentación y video · B11 Base de datos
 *   B12 Pruebas y QA · B13 Frontend
 *
 * Orden de estudio recomendado:
 *   B01 → B02 → B03 → B04 → B05 → B06 → B07 → B08 → B09 → B10 → B11 → B12 → B13
 * ============================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Errores: nunca se muestran detalles en producción.
error_reporting(E_ALL);
ini_set('display_errors', depuracionActiva() ? '1' : '0');
ini_set('log_errors', '1');

$logDir = APP_ROOT . '/storage';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
if (is_dir($logDir) && is_writable($logDir)) {
    ini_set('error_log', $logDir . '/php-error.log');
}

date_default_timezone_set(zonaHorariaApp());

// B01.1 · Sesión y cabeceras de seguridad
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) env('APP_SESSION_NAME', 'coffeeandtea'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => envBool('APP_COOKIE_SECURE', false),
        'httponly' => true,
        'samesite' => (string) env('APP_COOKIE_SAMESITE', 'Lax'),
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    if (envBool('APP_COOKIE_SECURE', false)) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// B01.2 · Librerías base y conexión
require_once __DIR__ . '/conectarBD.php';
require_once __DIR__ . '/../vistas/plantilla.php';
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/manual_rol.php';
require_once __DIR__ . '/pedidos_invitado.php';
require_once __DIR__ . '/imagenes.php';
require_once __DIR__ . '/video.php';

if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    exit('conectarBD.php no devolvió una conexión PDO válida.');
}

$conn->exec('SET TIME ZONE ' . $conn->quote(zonaHorariaApp()));

// B01.3 · Redirección y mensajes flash
/** Redirige y detiene la ejecución. */
function redirigir(string $url): never
{
    if (!headers_sent()) {
        header('Location: ' . $url);
    }
    exit;
}

/** Guarda un mensaje flash para mostrarlo en la siguiente carga. */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = [
        'tipo'    => $tipo,
        'mensaje' => $mensaje,
    ];
}

/** Devuelve y vacía los mensajes flash pendientes. */
function tomarFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flashes) ? $flashes : [];
}

// B01.4 · Protección CSRF
/** Token CSRF de la sesión. */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo oculto con el token CSRF. */
function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrfToken()) . '">';
}

/** Valida el token CSRF de una petición POST. */
function validarCsrf(): bool
{
    $enviado = $_POST['csrf'] ?? null;
    return is_string($enviado) && hash_equals(csrfToken(), $enviado);
}

/** Corta la petición si el CSRF no es válido. */
function exigirCsrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!validarCsrf()) {
        http_response_code(419);
        flash('error', 'La página expiró. Inténtalo de nuevo.');
        redirigir(url('index.php'));
    }
}

// B01.5 · URLs y utilidades
/**
 * Ruta base relativa del proyecto (compatibilidad con enlaces existentes).
 */
function baseUrl(): string
{
    static $base = null;
    if ($base === null) {
        $directorio = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = rtrim($directorio, '/') . '/';
        if ($base === '//' || $base === '') {
            $base = '/';
        }
    }
    return $base;
}

/** URL canónica de una pestaña del CRUD. */
function urlGestion(string $tabla): string
{
    return baseUrl() . 'gestion.php?tabla=' . rawurlencode($tabla) . '#gestion';
}

/** Convierte un booleano al literal que espera PostgreSQL. */
function boolSql(bool $valor): string
{
    return $valor ? 'true' : 'false';
}

/** Interpreta de forma segura un valor booleano devuelto por PostgreSQL. */
function esVerdadero(mixed $valor): bool
{
    return $valor === true
        || $valor === 1
        || $valor === '1'
        || $valor === 't'
        || $valor === 'true';
}

// B01.6 · Formato para vistas
/** Formatea un valor monetario en pesos colombianos. */
function moneda(float|int|string|null $valor): string
{
    return '$' . number_format((float) $valor, 0, ',', '.');
}

/** Formatea una fecha/hora de PostgreSQL para mostrarla. */
function fechaBonita(?string $fecha): string
{
    if ($fecha === null || trim($fecha) === '') {
        return '—';
    }
    $timestamp = strtotime($fecha);
    return $timestamp === false ? $fecha : date('d/m/Y H:i', $timestamp);
}

/** Convierte una fecha de PostgreSQL al formato de <input type="datetime-local">. */
function fechaInput(?string $fecha): string
{
    if ($fecha === null || trim($fecha) === '') {
        return '';
    }
    $timestamp = strtotime($fecha);
    return $timestamp === false ? '' : date('Y-m-d\TH:i', $timestamp);
}

/** Etiqueta legible de una tabla del CRUD. */
function etiquetaTabla(string $tabla): string
{
    return [
        'categoria' => 'Categoría',
        'cliente'   => 'Cliente',
        'producto'  => 'Producto',
        'pedido'    => 'Pedido',
        'detalle'   => 'Detalle de pedido',
    ][$tabla] ?? 'Registro';
}

/** Devuelve las filas activas y, si se edita, también la fila actual. */
function opcionesActivas(array $filas, ?int $idActual = null): array
{
    return array_values(array_filter(
        $filas,
        static fn(array $fila): bool => esVerdadero($fila['activo'] ?? false)
            || (int) $fila['id'] === $idActual
    ));
}

// B01.7 · Cálculos compartidos
/**
 * Recalcula el total de un pedido a partir del precio histórico de sus detalles.
 */
function recalcularTotalPedido(PDO $conn, int $idPedido): void
{
    $stmt = $conn->prepare(
        'UPDATE pedido
         SET total = COALESCE((
             SELECT SUM(dp.cantidad * dp.precio_unitario)
             FROM detallepedido dp
             WHERE dp.id_pedido = :id_calculo
         ), 0)
         WHERE id = :id_pedido'
    );
    $stmt->bindValue(':id_calculo', $idPedido, PDO::PARAM_INT);
    $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
    $stmt->execute();
}

// B01.8 · Texto y navegación
/** Genera un identificador seguro para los títulos del manual. */
function slugificar(string $texto): string
{
    $texto = trim(strip_tags(html_entity_decode($texto, ENT_QUOTES, 'UTF-8')));
    $texto = strtr($texto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ü' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
        'Ü' => 'u', 'Ñ' => 'n',
    ]);
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
    $texto = trim($texto, '-');
    return $texto !== '' ? $texto : 'seccion';
}

/** Devuelve una URL local segura para volver después de una acción. */
function urlVolver(string $porDefecto): string
{
    $valor = $_POST['volver'] ?? $_GET['volver'] ?? '';
    if (is_string($valor) && $valor !== '' && $valor[0] === '/' && !str_starts_with($valor, '//')) {
        return $valor;
    }
    return $porDefecto;
}

/** Etiqueta legible de un estado de pedido. */
function etiquetaEstado(string $estado): string
{
    return [
        'PENDIENTE'      => 'Pendiente de pago',
        'PAGADO'         => 'Pagado',
        'EN_PREPARACION' => 'En preparación',
        'LISTO'          => 'Listo para recoger',
        'ENTREGADO'      => 'Entregado',
        'CANCELADO'      => 'Cancelado',
    ][$estado] ?? $estado;
}

/** Clase CSS de un estado de pedido. */
function claseEstado(string $estado): string
{
    return 'estado-' . strtolower(str_replace('_', '-', $estado));
}
