<?php
/**
 * BLOQUE B02 · AUTENTICACIÓN Y CUENTAS
 * Qué hace: login, sesión, roles, cambio de contraseña y protección de páginas.
 * Funciones: usuarioActual(), autenticar(), requerirLogin(), requerirRol(),
 *            puedeGestionar(), cambiarPassword(), validarPassword().
 * Tablas: usuario, cliente.
 * Páginas: login.php, logout.php, cambiar-password.php, cuenta.php.
 * Reglas: sesión versionada, bloqueo de intentos, RBAC por rol.
 * Pruebas: CP-001 a CP-011, CP-043.
 */
declare(strict_types=1);

const ROL_CLIENTE = 'CLIENTE';
const ROL_ENCARGADO = 'ENCARGADO';
const ROL_ADMIN = 'ADMIN';

/** Devuelve el usuario autenticado, validando su estado contra la base. */
function usuarioActual(): ?array
{
    static $cacheCargado = false;
    static $cacheId = null;
    static $usuario = null;

    $idSesion = $_SESSION['auth_user_id'] ?? null;
    $idNormalizado = is_int($idSesion) || (is_string($idSesion) && ctype_digit($idSesion)) ? (int) $idSesion : 0;

    if ($cacheCargado && $cacheId === $idNormalizado) {
        return $usuario;
    }
    $cacheCargado = true;
    $cacheId = $idNormalizado;

    if ($idNormalizado < 1) {
        return $usuario = null;
    }
    $id = $idNormalizado;

    // Límite de inactividad y duración máxima de la sesión.
    $ahora = time();
    $ultimo = (int) ($_SESSION['auth_ultimo'] ?? 0);
    $inicio = (int) ($_SESSION['auth_inicio'] ?? 0);
    $maxInactividad = 2 * 60 * 60;
    $maxTotal = 8 * 60 * 60;
    if ($ultimo > 0 && ($ahora - $ultimo > $maxInactividad || ($inicio > 0 && $ahora - $inicio > $maxTotal))) {
        cerrarSesionUsuario();
        flash('info', 'Tu sesión expiró por inactividad. Vuelve a iniciar sesión.');
        return $usuario = null;
    }
    $_SESSION['auth_ultimo'] = $ahora;

    global $conn;
    if (!($conn instanceof PDO)) {
        return $usuario = null;
    }

    try {
        $stmt = $conn->prepare(
            'SELECT id, nombre, correo, rol, activo, debe_cambiar_password, session_version, ultimo_acceso
             FROM usuario WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();
    } catch (Throwable $e) {
        error_log('CoffeeAndtea - error validando sesión: ' . $e->getMessage());
        return $usuario = null;
    }

    if ($fila === false || !esVerdadero($fila['activo'])) {
        cerrarSesionUsuario();
        return $usuario = null;
    }

    $versionSesion = (int) ($_SESSION['auth_session_version'] ?? -1);
    if ($versionSesion !== (int) $fila['session_version']) {
        cerrarSesionUsuario();
        flash('info', 'Tu sesión cambió. Inicia sesión de nuevo.');
        return $usuario = null;
    }

    return $usuario = [
        'id'                     => (int) $fila['id'],
        'nombre'                 => (string) $fila['nombre'],
        'correo'                 => (string) $fila['correo'],
        'rol'                    => (string) $fila['rol'],
        'debe_cambiar_password'  => esVerdadero($fila['debe_cambiar_password']),
        'session_version'        => (int) $fila['session_version'],
    ];
}

/** Indica si hay una sesión autenticada. */
function estaAutenticado(): bool
{
    return usuarioActual() !== null;
}

/** Indica si el usuario tiene uno de los roles indicados. */
function tieneRol(string ...$roles): bool
{
    $usuario = usuarioActual();
    return $usuario !== null && in_array($usuario['rol'], $roles, true);
}

/** Atajo para el rol administrador. */
function esAdmin(): bool
{
    return tieneRol(ROL_ADMIN);
}

/** Atajo para roles con acceso a la gestión diaria. */
function puedeGestionar(): bool
{
    return tieneRol(ROL_ENCARGADO, ROL_ADMIN);
}

/** Inicia la sesión de un usuario ya validado. */
function iniciarSesionUsuario(array $fila): void
{
    session_regenerate_id(true);
    $_SESSION = [
        'auth_user_id'          => (int) $fila['id'],
        'auth_session_version'  => (int) $fila['session_version'],
        'auth_inicio'           => time(),
        'auth_ultimo'           => time(),
        'csrf_token'            => bin2hex(random_bytes(32)),
    ];
}

/** Cierra la sesión y elimina la cookie. */
function cerrarSesionUsuario(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
        session_destroy();
    }
}

/**
 * Valida credenciales.
 *
 * Devuelve ok=false con un mensaje genérico para no revelar si el correo existe.
 */
function autenticar(PDO $conn, string $correo, string $password): array
{
    $correo = mb_strtolower(trim($correo), 'UTF-8');

    if ($correo === '' || $password === '') {
        return ['ok' => false, 'error' => 'Escribe tu correo y contraseña.'];
    }

    $intentos = $_SESSION['login_intentos'] ?? [];
    $ahora = time();
    $intentos = array_values(array_filter(
        is_array($intentos) ? $intentos : [],
        static fn($marca): bool => is_int($marca) && $ahora - $marca < 600
    ));
    if (count($intentos) >= 8) {
        return ['ok' => false, 'error' => 'Demasiados intentos. Espera 10 minutos e inténtalo de nuevo.'];
    }

    $stmt = $conn->prepare(
        'SELECT id, nombre, correo, password_hash, rol, activo, debe_cambiar_password, session_version
         FROM usuario WHERE lower(correo) = :correo LIMIT 1'
    );
    $stmt->execute([':correo' => $correo]);
    $fila = $stmt->fetch();

    $hash = $fila['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinva';
    $valida = password_verify($password, (string) $hash);

    if ($fila === false || !$valida || !esVerdadero($fila['activo'])) {
        $intentos[] = $ahora;
        $_SESSION['login_intentos'] = $intentos;
        registrarAuditoria($conn, $fila !== false ? (int) $fila['id'] : null, 'LOGIN_FALLIDO', 'usuario', $fila !== false ? (int) $fila['id'] : null, [
            'correo' => $correo,
        ]);
        return ['ok' => false, 'error' => 'Correo o contraseña incorrectos.'];
    }

    if (password_needs_rehash((string) $fila['password_hash'], PASSWORD_DEFAULT)) {
        $nuevo = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE usuario SET password_hash = :hash WHERE id = :id');
        $stmt->execute([':hash' => $nuevo, ':id' => (int) $fila['id']]);
    }

    unset($_SESSION['login_intentos']);
    $stmt = $conn->prepare('UPDATE usuario SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id = :id');
    $stmt->execute([':id' => (int) $fila['id']]);

    iniciarSesionUsuario($fila);
    registrarAuditoria($conn, (int) $fila['id'], 'LOGIN', 'usuario', (int) $fila['id']);

    return ['ok' => true, 'usuario' => $fila];
}

/** Exige sesión iniciada. Guarda el destino para volver después del login. */
function requerirLogin(): void
{
    if (estaAutenticado()) {
        return;
    }
    $_SESSION['url_destino'] = $_SERVER['REQUEST_URI'] ?? null;
    flash('info', 'Inicia sesión para continuar.');
    redirigir(url('login.php'));
}

/** Exige uno de los roles indicados. */
function requerirRol(string ...$roles): void
{
    requerirLogin();
    if (!tieneRol(...$roles)) {
        http_response_code(403);
        flash('error', 'No tienes permisos para acceder a esa sección.');
        redirigir(url('index.php'));
    }
}

/** Redirige si el usuario debe cambiar la contraseña temporal. */
function exigirCambioPassword(): void
{
    $usuario = usuarioActual();
    if ($usuario !== null && $usuario['debe_cambiar_password']) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (!in_array($script, ['cambiar-password.php', 'logout.php'], true)) {
            flash('info', 'Debes cambiar tu contraseña antes de continuar.');
            redirigir(url('cambiar-password.php'));
        }
    }
}

/** Página de inicio según el rol. */
function inicioPorRol(): string
{
    return puedeGestionar() ? url('gestion.php') : url('cuenta.php');
}

/** Valida la fortaleza de una contraseña. */
function validarPassword(string $password): array
{
    $errores = [];
    if (mb_strlen($password) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-ZÁÉÍÓÚÑ]/u', $password)) {
        $errores[] = 'La contraseña debe incluir al menos una letra mayúscula.';
    }
    if (!preg_match('/[a-záéíóúñ]/u', $password)) {
        $errores[] = 'La contraseña debe incluir al menos una letra minúscula.';
    }
    if (!preg_match('/\d/', $password)) {
        $errores[] = 'La contraseña debe incluir al menos un número.';
    }
    return $errores;
}

/** Genera una contraseña temporal legible y fuerte. */
function generarPasswordTemporal(int $longitud = 12): string
{
    $mayusculas = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $minusculas = 'abcdefghijkmnopqrstuvwxyz';
    $numeros = '23456789';
    $simbolos = '!@#$%&*';
    $todos = $mayusculas . $minusculas . $numeros . $simbolos;

    $password = [
        $mayusculas[random_int(0, strlen($mayusculas) - 1)],
        $minusculas[random_int(0, strlen($minusculas) - 1)],
        $numeros[random_int(0, strlen($numeros) - 1)],
        $simbolos[random_int(0, strlen($simbolos) - 1)],
    ];
    for ($i = count($password); $i < max(8, $longitud); $i++) {
        $password[] = $todos[random_int(0, strlen($todos) - 1)];
    }
    shuffle($password);
    return implode('', $password);
}

/** Cambia la contraseña del usuario y cierra sus otras sesiones. */
function cambiarPassword(PDO $conn, int $usuarioId, string $password): void
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        'UPDATE usuario
         SET password_hash = :hash,
             debe_cambiar_password = false,
             session_version = session_version + 1
         WHERE id = :id'
    );
    $stmt->execute([':hash' => $hash, ':id' => $usuarioId]);

    $stmt = $conn->prepare('SELECT session_version FROM usuario WHERE id = :id');
    $stmt->execute([':id' => $usuarioId]);
    $_SESSION['auth_session_version'] = (int) $stmt->fetchColumn();
    session_regenerate_id(true);

    registrarAuditoria($conn, $usuarioId, 'CAMBIO_PASSWORD', 'usuario', $usuarioId);
}
