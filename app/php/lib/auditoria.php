<?php
/**
 * Registro de auditoría y utilidades de trazabilidad.
 */
declare(strict_types=1);

/** IP del cliente, respetando proxies de confianza solo si están configurados. */
function ipCliente(): ?string
{
    $confiarProxy = envBool('APP_CONFIAR_PROXY', false);
    if ($confiarProxy) {
        $reenviada = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (is_string($reenviada) && $reenviada !== '') {
            $primera = trim(explode(',', $reenviada)[0]);
            if (filter_var($primera, FILTER_VALIDATE_IP)) {
                return $primera;
            }
        }
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
}

/**
 * Inserta una fila de auditoría. Nunca interrumpe la operación principal.
 */
function registrarAuditoria(
    PDO $conn,
    ?int $usuarioId,
    string $accion,
    string $tabla,
    ?int $registroId = null,
    array $detalles = []
): void {
    try {
        $json = empty($detalles)
            ? null
            : json_encode($detalles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        $stmt = $conn->prepare(
            'INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, detalles, ip)
             VALUES (:usuario_id, :accion, :tabla, :registro_id, CAST(:detalles AS JSONB), :ip)'
        );
        $stmt->bindValue(':usuario_id', $usuarioId, $usuarioId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':accion', mb_substr($accion, 0, 50), PDO::PARAM_STR);
        $stmt->bindValue(':tabla', mb_substr($tabla, 0, 50), PDO::PARAM_STR);
        $stmt->bindValue(':registro_id', $registroId, $registroId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':detalles', $json, $json === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':ip', ipCliente(), ipCliente() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('CoffeeAndtea - no se pudo auditar: ' . $e->getMessage());
    }
}
