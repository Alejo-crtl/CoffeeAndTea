<?php
/**
 * Crea el administrador inicial de CoffeeAndtea.
 *
 * Uso:
 *   php bin/crear-admin.php --nombre="Kevin Vega" --correo="admin@dominio.com"
 *   php bin/crear-admin.php --nombre="Kevin Vega" --correo="admin@dominio.com" --password="Opcional123!"
 *
 * Si no se pasa --password se genera una contraseña temporal segura y se
 * muestra una sola vez. El usuario deberá cambiarla al iniciar sesión.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/php/lib/config.php';
require_once dirname(__DIR__) . '/app/php/lib/conectarBD.php';
require_once dirname(__DIR__) . '/app/php/lib/auditoria.php';
require_once dirname(__DIR__) . '/app/php/lib/auth.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Este script solo se ejecuta desde la línea de comandos.\n");
}

$opciones = getopt('', ['nombre:', 'correo:', 'password:', 'ayuda']);

if (isset($opciones['ayuda'])) {
    echo "Uso: php bin/crear-admin.php --nombre=\"Nombre\" --correo=\"correo@dominio.com\" [--password=\"...\"]\n";
    exit(0);
}

$nombre = trim((string) ($opciones['nombre'] ?? ''));
$correo = mb_strtolower(trim((string) ($opciones['correo'] ?? '')), 'UTF-8');
$password = (string) ($opciones['password'] ?? '');

if ($nombre === '') {
    fwrite(STDERR, "Falta --nombre.\n");
    exit(1);
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "El correo no es válido.\n");
    exit(1);
}

if ($password === '') {
    $password = generarPasswordTemporal(14);
    $generada = true;
} else {
    $generada = false;
    $errores = validarPassword($password);
    if ($errores !== []) {
        fwrite(STDERR, "La contraseña no cumple los requisitos:\n- " . implode("\n- ", $errores) . "\n");
        exit(1);
    }
}

$stmt = $conn->prepare('SELECT id, rol, activo FROM usuario WHERE lower(correo) = :correo LIMIT 1');
$stmt->execute([':correo' => $correo]);
$existente = $stmt->fetch();

if ($existente !== false) {
    fwrite(STDERR, "Ya existe un usuario con ese correo (id {$existente['id']}, rol {$existente['rol']}).\n");
    fwrite(STDERR, "Usa admin.php para cambiar su rol o restablecer su contraseña.\n");
    exit(1);
}

$stmt = $conn->prepare(
    'INSERT INTO usuario (nombre, correo, password_hash, rol, activo, debe_cambiar_password)
     VALUES (:nombre, :correo, :hash, \'ADMIN\', TRUE, TRUE)
     RETURNING id'
);
$stmt->execute([
    ':nombre' => $nombre,
    ':correo' => $correo,
    ':hash'   => password_hash($password, PASSWORD_DEFAULT),
]);
$id = (int) $stmt->fetchColumn();

registrarAuditoria($conn, null, 'ADMIN_CREADO_CLI', 'usuario', $id, [
    'correo' => $correo,
    'nombre' => $nombre,
]);

echo "Administrador creado correctamente.\n";
echo "  ID:      {$id}\n";
echo "  Nombre:  {$nombre}\n";
echo "  Correo:  {$correo}\n";
if ($generada) {
    echo "  Contraseña temporal: {$password}\n";
    echo "  (guárdala en un lugar seguro; no se volverá a mostrar)\n";
}
echo "\nAl iniciar sesión el sistema exigirá cambiarla.\n";
