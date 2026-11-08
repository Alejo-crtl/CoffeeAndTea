<?php
/**
 * Conexión a la base de datos CoffeeAndTea (PostgreSQL) mediante PDO.
 *
 * Devuelve la conexión en la variable $conn, igual que el conectarBD.php
 * original, para no tener que cambiar la forma de incluirlo.
 *
 * Puedes ajustar los datos aquí o definir las variables de entorno
 * DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASS.
 */
declare(strict_types=1);

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '5432';
// Ojo: la base existe como "CoffeeAndTea" y PostgreSQL distingue mayúsculas.
$dbName = getenv('DB_NAME') ?: 'CoffeeAndTea';
$dbUser = getenv('DB_USER') ?: 'postgres';
$dbPass = getenv('DB_PASS') ?: 'MySQLcontra26'; // Contraseña local de PostgreSQL en este equipo.

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $dbHost, $dbPort, $dbName);

try {
    $conn = new PDO($dsn, $dbUser, $dbPass, [
        // Lanza excepciones en lugar de devolver false silenciosamente
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // fetch() devuelve arreglos asociativos
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Usa consultas preparadas reales del servidor
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // El detalle técnico va al log; al usuario no se le muestran credenciales
    error_log('CoffeeAndtea - error de conexión: ' . $e->getMessage());

    http_response_code(500);
    exit(
        'No fue posible conectar con la base de datos. '
        . 'Revisa que PostgreSQL esté iniciado y los datos de conexión en php/lib/conectarBD.php.'
    );
}
