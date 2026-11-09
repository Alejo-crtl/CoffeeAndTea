<?php
/**
 * Recuperación de contraseña por correo.
 *
 * El token se guarda hasheado (SHA-256), vence en 60 minutos y es de un solo
 * uso. La respuesta al usuario es siempre genérica para no revelar correos.
 */
declare(strict_types=1);

require_once __DIR__ . '/correo.php';

/** Crea y envía un enlace de recuperación si el correo pertenece a un usuario activo. */
function solicitarRecuperacion(PDO $conn, string $correo): void
{
    $correo = mb_strtolower(trim($correo), 'UTF-8');
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $stmt = $conn->prepare('SELECT id, nombre FROM usuario WHERE lower(correo) = :correo AND activo = TRUE LIMIT 1');
    $stmt->execute([':correo' => $correo]);
    $usuario = $stmt->fetch();
    if ($usuario === false) {
        return;
    }

    $usuarioId = (int) $usuario['id'];
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare('UPDATE password_reset SET usado_en = CURRENT_TIMESTAMP WHERE usuario_id = :id AND usado_en IS NULL');
        $stmt->execute([':id' => $usuarioId]);

        $stmt = $conn->prepare(
            "INSERT INTO password_reset (usuario_id, token_hash, expira_en, ip)
             VALUES (:usuario_id, :token_hash, CURRENT_TIMESTAMP + INTERVAL '60 minutes', :ip)"
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':token_hash' => $hash,
            ':ip'         => ipCliente(),
        ]);

        registrarAuditoria($conn, $usuarioId, 'RESET_SOLICITADO', 'password_reset', null, ['correo' => $correo]);
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('CoffeeAndtea - solicitar recuperación: ' . $e->getMessage());
        return;
    }

    $enlace = url('restablecer.php?token=' . urlencode($token));
    $html = plantillaCorreo(
        'Recupera tu contraseña',
        '<p>Hola, <strong>' . htmlspecialchars((string) $usuario['nombre'], ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
        . '<p>Recibimos una solicitud para restablecer tu contraseña. El enlace vence en 60 minutos y solo puede usarse una vez.</p>',
        $enlace,
        'Crear nueva contraseña'
    );
    enviarCorreo($correo, 'Recupera tu contraseña de CoffeeAndtea', $html);
}

/** Devuelve el usuario asociado a un token válido, o null. */
function buscarResetValido(PDO $conn, string $token): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare(
        "SELECT pr.id AS reset_id, pr.expira_en, u.id, u.nombre, u.correo, u.activo
         FROM password_reset pr
         INNER JOIN usuario u ON u.id = pr.usuario_id
         WHERE pr.token_hash = :hash AND pr.usado_en IS NULL AND pr.expira_en > CURRENT_TIMESTAMP
         ORDER BY pr.id DESC LIMIT 1"
    );
    $stmt->execute([':hash' => $hash]);
    $fila = $stmt->fetch();
    return $fila === false ? null : $fila;
}

/** Consume el token y cambia la contraseña. */
function restablecerPassword(PDO $conn, string $token, string $password): void
{
    $reset = buscarResetValido($conn, $token);
    if ($reset === null) {
        throw new ReglaNegocioException('El enlace no es válido o ya venció. Solicita uno nuevo.');
    }
    $errores = validarPassword($password);
    if ($errores !== []) {
        throw new ReglaNegocioException(implode(' ', $errores));
    }

    $usuarioId = (int) $reset['id'];
    $conn->beginTransaction();
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            'UPDATE usuario
             SET password_hash = :hash, debe_cambiar_password = false,
                 session_version = session_version + 1
             WHERE id = :id AND activo = TRUE'
        );
        $stmt->execute([':hash' => $hash, ':id' => $usuarioId]);
        if ($stmt->rowCount() !== 1) {
            throw new ReglaNegocioException('La cuenta ya no está activa.');
        }

        $stmt = $conn->prepare('UPDATE password_reset SET usado_en = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute([':id' => (int) $reset['reset_id']]);

        registrarAuditoria($conn, $usuarioId, 'RESET_COMPLETADO', 'usuario', $usuarioId);
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if ($e instanceof ReglaNegocioException) {
            throw $e;
        }
        error_log('CoffeeAndtea - restablecer contraseña: ' . $e->getMessage());
        throw new ReglaNegocioException('No se pudo cambiar la contraseña. Inténtalo de nuevo.');
    }

    $html = plantillaCorreo(
        'Tu contraseña cambió',
        '<p>La contraseña de tu cuenta se actualizó correctamente.</p>'
        . '<p>Si no reconoces este cambio, contacta de inmediato a la tienda.</p>'
    );
    enviarCorreo((string) $reset['correo'], 'Tu contraseña de CoffeeAndtea cambió', $html);
}
