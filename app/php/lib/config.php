<?php
/**
 * Configuración compartida de CoffeeAndtea.
 *
 * Carga el archivo .env de la raíz del proyecto sin dependencias externas.
 * Las variables ya definidas en el entorno del sistema tienen prioridad.
 */
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}

/** Lee una variable de entorno con valor por defecto. */
function env(string $clave, ?string $porDefecto = null): ?string
{
    $valor = $_ENV[$clave] ?? $_SERVER[$clave] ?? getenv($clave);
    if ($valor === false || $valor === null || $valor === '') {
        return $porDefecto;
    }
    return (string) $valor;
}

/** Interpreta un valor booleano del entorno. */
function envBool(string $clave, bool $porDefecto = false): bool
{
    $valor = env($clave);
    if ($valor === null) {
        return $porDefecto;
    }
    return in_array(strtolower(trim($valor)), ['1', 'true', 'si', 'sí', 'yes', 'on'], true);
}

/**
 * Carga el .env una sola vez.
 *
 * Formato admitido: CLAVE=valor, comentarios con # y valores entre comillas.
 */
function cargarEntorno(string $ruta): void
{
    static $cargado = false;
    if ($cargado || !is_file($ruta) || !is_readable($ruta)) {
        return;
    }
    $cargado = true;

    $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lineas === false) {
        return;
    }

    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#')) {
            continue;
        }
        $posicion = strpos($linea, '=');
        if ($posicion === false) {
            continue;
        }
        $clave = trim(substr($linea, 0, $posicion));
        $valor = trim(substr($linea, $posicion + 1));

        if ($clave === '' || !preg_match('/^[A-Z0-9_]+$/i', $clave)) {
            continue;
        }
        if (strlen($valor) >= 2) {
            $primero = $valor[0];
            $ultimo = $valor[strlen($valor) - 1];
            if (($primero === '"' && $ultimo === '"') || ($primero === "'" && $ultimo === "'")) {
                $valor = substr($valor, 1, -1);
                if ($primero === '"') {
                    $valor = stripcslashes($valor);
                }
            }
        }

        if (!array_key_exists($clave, $_ENV)) {
            $_ENV[$clave] = $valor;
        }
        if (getenv($clave) === false) {
            putenv($clave . '=' . $valor);
        }
        $_SERVER[$clave] = $_SERVER[$clave] ?? $valor;
    }
}

cargarEntorno(APP_ROOT . '/.env');

/** URL base pública del sitio. */
function urlBase(): string
{
    static $url = null;
    if ($url === null) {
        $configurada = env('APP_BASE_URL');
        if ($configurada !== null) {
            $url = rtrim($configurada, '/') . '/';
        } else {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $directorio = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            $directorio = rtrim($directorio, '/') . '/';
            $url = ($https ? 'https://' : 'http://') . $host . ($directorio === '//' ? '/' : $directorio);
        }
    }
    return $url;
}

/** URL absoluta a partir de una ruta relativa del proyecto. */
function url(string $ruta = ''): string
{
    return urlBase() . ltrim($ruta, '/');
}

/** Zona horaria configurada. */
function zonaHorariaApp(): string
{
    $zona = env('APP_TIMEZONE', 'America/Bogota') ?? 'America/Bogota';
    return in_array($zona, timezone_identifiers_list(), true) ? $zona : 'America/Bogota';
}

/** Indica si la aplicación está en producción. */
function esProduccion(): bool
{
    return strtolower((string) env('APP_ENV', 'production')) === 'production';
}

/** Indica si se muestran errores detallados. */
function depuracionActiva(): bool
{
    return envBool('APP_DEBUG', false) && !esProduccion();
}
