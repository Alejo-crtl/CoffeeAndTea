<?php
/**
 * Registro y perfil de clientes.
 */
declare(strict_types=1);

require_once __DIR__ . '/correo.php';

/** Devuelve el cliente vinculado a una cuenta. */
function clienteDeUsuario(PDO $conn, int $usuarioId): ?array
{
    $stmt = $conn->prepare('SELECT * FROM cliente WHERE usuario_id = :id LIMIT 1');
    $stmt->execute([':id' => $usuarioId]);
    $fila = $stmt->fetch();
    return $fila === false ? null : $fila;
}

/**
 * Crea una cuenta CLIENTE y la vincula con el registro de cliente.
 *
 * @param array{nombre:string,correo:string,telefono:string,password:string,password2:string,consentimiento:bool} $datos
 */
function registrarCuentaCliente(PDO $conn, array $datos): int
{
    $nombre = trim($datos['nombre'] ?? '');
    $correo = mb_strtolower(trim($datos['correo'] ?? ''), 'UTF-8');
    $telefono = trim($datos['telefono'] ?? '');
    $password = (string) ($datos['password'] ?? '');
    $password2 = (string) ($datos['password2'] ?? '');
    $consentimiento = !empty($datos['consentimiento']);

    $errores = [];
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 150) {
        $errores[] = 'Escribe tu nombre completo.';
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150) {
        $errores[] = 'Escribe un correo electrónico válido.';
    }
    if ($telefono !== '' && mb_strlen($telefono) > 30) {
        $errores[] = 'El teléfono no puede superar 30 caracteres.';
    }
    $errores = array_merge($errores, validarPassword($password));
    if ($password !== $password2) {
        $errores[] = 'Las contraseñas no coinciden.';
    }
    if (!$consentimiento) {
        $errores[] = 'Debes autorizar el tratamiento de tus datos.';
    }
    if ($errores !== []) {
        throw new ReglaNegocioException(implode(' ', $errores));
    }

    $stmt = $conn->prepare('SELECT id FROM usuario WHERE lower(correo) = :correo LIMIT 1');
    $stmt->execute([':correo' => $correo]);
    if ($stmt->fetchColumn() !== false) {
        throw new ReglaNegocioException('Ya existe una cuenta con ese correo. Inicia sesión o recupera tu contraseña.');
    }

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare(
            'INSERT INTO usuario (nombre, correo, password_hash, rol, activo)
             VALUES (:nombre, :correo, :hash, \'CLIENTE\', TRUE)
             RETURNING id'
        );
        $stmt->execute([
            ':nombre' => $nombre,
            ':correo' => $correo,
            ':hash'   => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $usuarioId = (int) $stmt->fetchColumn();

        $stmt = $conn->prepare('SELECT id, usuario_id FROM cliente WHERE lower(correo) = :correo FOR UPDATE');
        $stmt->execute([':correo' => $correo]);
        $cliente = $stmt->fetch();

        if ($cliente === false) {
            $stmt = $conn->prepare(
                'INSERT INTO cliente (nombre, correo, telefono, consentimiento, activo, usuario_id, reclamado_en)
                 VALUES (:nombre, :correo, :telefono, TRUE, TRUE, :usuario_id, CURRENT_TIMESTAMP)'
            );
            $stmt->execute([
                ':nombre'     => $nombre,
                ':correo'     => $correo,
                ':telefono'   => $telefono !== '' ? $telefono : null,
                ':usuario_id' => $usuarioId,
            ]);
        } else {
            if ($cliente['usuario_id'] !== null) {
                throw new ReglaNegocioException('Ese correo ya está vinculado a otra cuenta.');
            }
            $stmt = $conn->prepare(
                'UPDATE cliente
                 SET usuario_id = :usuario_id, reclamado_en = CURRENT_TIMESTAMP,
                     nombre = :nombre, telefono = :telefono
                 WHERE id = :id'
            );
            $stmt->execute([
                ':usuario_id' => $usuarioId,
                ':nombre'     => $nombre,
                ':telefono'   => $telefono !== '' ? $telefono : null,
                ':id'         => (int) $cliente['id'],
            ]);
        }

        registrarAuditoria($conn, $usuarioId, 'CUENTA_CREADA', 'usuario', $usuarioId, ['correo' => $correo]);
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if ($e instanceof ReglaNegocioException) {
            throw $e;
        }
        error_log('CoffeeAndtea - registro: ' . $e->getMessage());
        throw new ReglaNegocioException('No se pudo crear la cuenta. Inténtalo de nuevo.');
    }

    $html = plantillaCorreo(
        'Tu cuenta está lista',
        '<p>Hola, <strong>' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
        . '<p>Tu cuenta de CoffeeAndtea se creó correctamente. Ya puedes consultar tus pedidos y repetir tus favoritos.</p>'
        . '<p>Recuerda: el pago se realiza en el mostrador al recoger.</p>',
        url('login.php'),
        'Ingresar a mi cuenta'
    );
    enviarCorreo($correo, 'Bienvenido a CoffeeAndtea', $html);

    return $usuarioId;
}

/** Actualiza nombre y teléfono del cliente y su cuenta. */
function actualizarPerfilCliente(PDO $conn, int $usuarioId, string $nombre, string $telefono): void
{
    $nombre = trim($nombre);
    $telefono = trim($telefono);
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 150) {
        throw new ReglaNegocioException('Escribe tu nombre completo.');
    }
    if ($telefono !== '' && mb_strlen($telefono) > 30) {
        throw new ReglaNegocioException('El teléfono no puede superar 30 caracteres.');
    }

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare('UPDATE usuario SET nombre = :nombre WHERE id = :id');
        $stmt->execute([':nombre' => $nombre, ':id' => $usuarioId]);

        $stmt = $conn->prepare(
            'UPDATE cliente SET nombre = :nombre, telefono = :telefono WHERE usuario_id = :usuario_id'
        );
        $stmt->execute([
            ':nombre'     => $nombre,
            ':telefono'   => $telefono !== '' ? $telefono : null,
            ':usuario_id' => $usuarioId,
        ]);

        registrarAuditoria($conn, $usuarioId, 'PERFIL_ACTUALIZADO', 'usuario', $usuarioId);
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if ($e instanceof ReglaNegocioException) {
            throw $e;
        }
        error_log('CoffeeAndtea - perfil: ' . $e->getMessage());
        throw new ReglaNegocioException('No se pudo actualizar tu perfil.');
    }
}
