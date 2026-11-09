<?php
/**
 * Pedidos recientes de un visitante.
 *
 * El visitante no tiene cuenta ni historial en la base. Para no perder el
 * acceso a sus pedidos, se guardan el código y el token en una cookie
 * HttpOnly de este navegador (máximo 5 pedidos, 90 días).
 */
declare(strict_types=1);

const COOKIE_PEDIDOS_INVITADO = 'coffeeandtea_pedidos';
const PEDIDOS_INVITADO_MAX = 5;
const PEDIDOS_INVITADO_DIAS = 90;

/** Lee y valida la lista de pedidos guardados en la cookie. */
function pedidosInvitadoLeer(): array
{
    $raw = $_COOKIE[COOKIE_PEDIDOS_INVITADO] ?? '';
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $raw = strtr($raw, '-_', '+/');
    $raw .= str_repeat('=', (4 - strlen($raw) % 4) % 4);
    $json = base64_decode($raw, true);
    if ($json === false) {
        return [];
    }

    $datos = json_decode($json, true);
    if (!is_array($datos) || !isset($datos['pedidos']) || !is_array($datos['pedidos'])) {
        return [];
    }

    $lista = [];
    foreach ($datos['pedidos'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $codigo = strtoupper(trim((string) ($item['codigo'] ?? '')));
        $token = trim((string) ($item['token'] ?? ''));
        if (!preg_match('/^CAF-[A-Z0-9]{3,10}$/', $codigo)) {
            continue;
        }
        if (!preg_match('/^[A-Za-z0-9]{20,64}$/', $token)) {
            continue;
        }
        $lista[$codigo] = [
            'codigo'   => $codigo,
            'token'    => $token,
            'agregado' => (int) ($item['agregado'] ?? 0),
        ];
    }

    return array_slice(array_values($lista), 0, PEDIDOS_INVITADO_MAX);
}

/** Agrega un pedido a la lista del navegador, sin duplicados. */
function pedidosInvitadoGuardar(string $codigo, string $token): void
{
    $codigo = strtoupper(trim($codigo));
    $token = trim($token);
    if (!preg_match('/^CAF-[A-Z0-9]{3,10}$/', $codigo) || $token === '') {
        return;
    }

    $lista = array_values(array_filter(
        pedidosInvitadoLeer(),
        static fn(array $item): bool => $item['codigo'] !== $codigo
    ));
    array_unshift($lista, [
        'codigo'   => $codigo,
        'token'    => $token,
        'agregado' => time(),
    ]);
    $lista = array_slice($lista, 0, PEDIDOS_INVITADO_MAX);

    $payload = rtrim(strtr(base64_encode(json_encode([
        'v'       => 1,
        'pedidos' => $lista,
    ], JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');

    setcookie(COOKIE_PEDIDOS_INVITADO, $payload, [
        'expires'  => time() + (PEDIDOS_INVITADO_DIAS * 86400),
        'path'     => '/',
        'secure'   => envBool('APP_COOKIE_SECURE', false),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[COOKIE_PEDIDOS_INVITADO] = $payload;
}

/** Elimina del navegador la lista de pedidos recientes. */
function pedidosInvitadoOlvidar(): void
{
    setcookie(COOKIE_PEDIDOS_INVITADO, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => envBool('APP_COOKIE_SECURE', false),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[COOKIE_PEDIDOS_INVITADO]);
}

/** Aviso para el checkout de invitado. */
function avisoCompraInvitadoHtml(): string
{
    return '<div class="alerta-info" role="status">'
        . '<p>Guardaremos este pedido en este navegador para que puedas consultarlo. '
        . 'Si cambias de dispositivo o borras los datos del navegador, necesitarás el enlace de confirmación.</p>'
        . '<p>Para conservar tu historial permanentemente, '
        . '<a href="' . h(url('login.php')) . '">inicia sesión</a> o '
        . '<a href="' . h(url('registro.php')) . '">crea una cuenta</a>.</p>'
        . '</div>';
}
