<?php
/**
 * BLOQUE B01 · NÚCLEO
 * Qué hace: crea la conexión PDO a PostgreSQL y la deja en $conn.
 * Tablas: no aplica.
 * Páginas: todas (se carga desde bootstrap.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$dbHost = env('DB_HOST', '127.0.0.1');
$dbPort = env('DB_PORT', '5432');
$dbName = env('DB_NAME', 'CoffeeAndTea');
$dbUser = env('DB_USER', 'postgres');
$dbPass = env('DB_PASS', '');

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $dbHost, $dbPort, $dbName);

try {
    $conn = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ]);
    $conn->exec("SET client_encoding TO 'UTF8'");
} catch (PDOException $e) {
    error_log('CoffeeAndtea - error de conexión: ' . $e->getMessage());
    http_response_code(503);
    if (depuracionActiva()) {
        exit('No fue posible conectar con la base de datos: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
    exit('No fue posible conectar con la base de datos. Intenta de nuevo en unos minutos.');
}
