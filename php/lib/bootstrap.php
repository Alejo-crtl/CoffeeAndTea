<?php
/**
 * Arranque común de la aplicación CoffeeAndtea.
 *
 * - Inicia la sesión (mensajes flash y token CSRF).
 * - Carga la conexión PDO ($conn).
 * - Carga las funciones de la plantilla.
 * - Define utilidades compartidas por el CRUD y los reportes.
 */
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
 * La cafetería trabaja en horario de Colombia. La zona se fija antes de
 * abrir la base para que las fechas nuevas usen la misma hora que se
 * muestra en pantalla.
 */
$zonaHorariaApp = getenv('APP_TIMEZONE') ?: 'America/Bogota';
if (!in_array($zonaHorariaApp, timezone_identifiers_list(), true)) {
    $zonaHorariaApp = 'America/Bogota';
}
date_default_timezone_set($zonaHorariaApp);

require_once __DIR__ . '/conectarBD.php';
require_once __DIR__ . '/../vistas/plantilla.php';
require_once __DIR__ . '/consultas.php';

if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    exit('conectarBD.php no devolvió una conexión PDO válida.');
}

/*
 * La base y la cafetería trabajan en horario de Colombia. Se alinea la sesión
 * de PostgreSQL para que las fechas se muestren y se registren con la misma
 * hora. No se modifica la estructura ni los datos.
 */
$conn->exec('SET TIME ZONE ' . $conn->quote($zonaHorariaApp));

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
    return $flashes;
}

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

/**
 * Ruta base pública del proyecto.
 *
 * Sirve tanto si se abre en la raíz del vhost (http://coffeeandtea.test/)
 * como en una subcarpeta (http://localhost/CoffeeAndtea/).
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

/** Formatea un valor monetario. */
function moneda(float|int|string|null $valor): string
{
    return '$' . number_format((float) $valor, 2, '.', ',');
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

/**
 * Devuelve las filas activas y, si se está editando, también la fila actual
 * aunque esté inactiva. Así no se rompen las relaciones existentes.
 */
function opcionesActivas(array $filas, ?int $idActual = null): array
{
    return array_values(array_filter(
        $filas,
        static fn(array $fila): bool => esVerdadero($fila['activo'] ?? false)
            || (int) $fila['id'] === $idActual
    ));
}

/**
 * Recalcula el total de un pedido a partir de sus detalles.
 *
 * El trigger de la base actualiza el pedido nuevo en un UPDATE de detalle,
 * pero no el anterior si el detalle cambia de pedido. Esta función cubre
 * ambos casos y también sirve como refuerzo después de borrar.
 */
function recalcularTotalPedido(PDO $conn, int $idPedido): void
{
    $stmt = $conn->prepare(
        'UPDATE pedido
         SET total = COALESCE((
             SELECT SUM(dp.cantidad * pr.precio)
             FROM detallepedido dp
             INNER JOIN producto pr ON pr.id = dp.id_producto
             WHERE dp.id_pedido = :id_calculo
         ), 0)
         WHERE id = :id_pedido'
    );
    $stmt->bindValue(':id_calculo', $idPedido, PDO::PARAM_INT);
    $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
    $stmt->execute();
}

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
