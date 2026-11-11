<?php
/**
 * BLOQUE B01 · NÚCLEO
 * Qué hace: envía correos por SMTP o los escribe en el log de desarrollo.
 * Funciones: enviarCorreo(), plantillaCorreo(), registrarErrorCorreo().
 * Páginas: recuperación de contraseña, prueba SMTP y notificaciones.
 */
declare(strict_types=1);

/** Registra un error de correo sin exponer credenciales. */
function registrarErrorCorreo(string $mensaje): void
{
    $directorio = APP_ROOT . '/storage';
    if (!is_dir($directorio)) {
        @mkdir($directorio, 0775, true);
    }
    @file_put_contents($directorio . '/mail-error.log', date('c') . ' ' . $mensaje . PHP_EOL, FILE_APPEND);
}

/** Dirección remitente configurada. */
function correoRemitente(): array
{
    $from = (string) env('SMTP_FROM', 'noreply@coffeeandtea.local');
    $nombre = (string) env('SMTP_FROM_NAME', 'CoffeeAndtea');
    return [$from, $nombre];
}

/**
 * Envía un correo HTML con alternativa en texto plano.
 */
function enviarCorreo(string $para, string $asunto, string $html, ?string $textoPlano = null): bool
{
    $para = trim($para);
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        registrarErrorCorreo('Destinatario inválido: ' . $para);
        return false;
    }

    $driver = strtolower((string) env('MAIL_DRIVER', 'smtp'));
    [$from, $fromNombre] = correoRemitente();

    if ($driver === 'log') {
        $directorio = APP_ROOT . '/storage';
        if (!is_dir($directorio)) {
            @mkdir($directorio, 0775, true);
        }
        $contenido = '============================================================' . PHP_EOL
            . 'Fecha: ' . date('c') . PHP_EOL
            . 'Para: ' . $para . PHP_EOL
            . 'Asunto: ' . $asunto . PHP_EOL
            . '------------------------------------------------------------' . PHP_EOL
            . ($textoPlano ?? strip_tags($html)) . PHP_EOL . PHP_EOL;
        return @file_put_contents($directorio . '/mail.log', $contenido, FILE_APPEND) !== false;
    }

    $host = (string) env('SMTP_HOST', '127.0.0.1');
    $puerto = (int) env('SMTP_PORT', '1025');
    $usuario = (string) env('SMTP_USER', '');
    $password = (string) env('SMTP_PASS', '');
    $encriptacion = strtolower((string) env('SMTP_ENCRYPTION', 'none'));
    $timeout = (int) env('SMTP_TIMEOUT', '15');

    $textoPlano = $textoPlano ?? trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html)));
    $frontera = 'alt-' . bin2hex(random_bytes(12));

    $cabeceras = [
        'Date: ' . date('r'),
        'From: ' . sprintf('=?UTF-8?B?%s?=', base64_encode($fromNombre)) . ' <' . $from . '>',
        'To: <' . $para . '>',
        'Subject: =?UTF-8?B?' . base64_encode($asunto) . '?=',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (parse_url(urlBase(), PHP_URL_HOST) ?: 'coffeeandtea.local') . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $frontera . '"',
        'X-Mailer: CoffeeAndtea',
    ];

    $cuerpo = '--' . $frontera . PHP_EOL
        . 'Content-Type: text/plain; charset=UTF-8' . PHP_EOL
        . 'Content-Transfer-Encoding: base64' . PHP_EOL . PHP_EOL
        . chunk_split(base64_encode($textoPlano), 76, PHP_EOL)
        . '--' . $frontera . PHP_EOL
        . 'Content-Type: text/html; charset=UTF-8' . PHP_EOL
        . 'Content-Transfer-Encoding: base64' . PHP_EOL . PHP_EOL
        . chunk_split(base64_encode($html), 76, PHP_EOL)
        . '--' . $frontera . '--';

    $datos = implode(PHP_EOL, $cabeceras) . PHP_EOL . PHP_EOL . $cuerpo;

    $esquema = $encriptacion === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client(
        $esquema . $host . ':' . $puerto,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
    );
    if ($socket === false) {
        registrarErrorCorreo("No se pudo conectar a {$host}:{$puerto} ({$errno} {$errstr}).");
        return false;
    }
    stream_set_timeout($socket, $timeout);

    /** Lee una respuesta SMTP, incluidas las líneas multilínea. */
    $leer = static function () use ($socket): array {
        $lineas = [];
        $codigo = 0;
        while (($linea = fgets($socket, 2048)) !== false) {
            $lineas[] = rtrim($linea, "\r\n");
            if (preg_match('/^(\d{3})\s/', $linea, $m)) {
                $codigo = (int) $m[1];
                break;
            }
        }
        return [$codigo, implode(' ', $lineas)];
    };

    /** Envía un comando y valida el código esperado. */
    $comando = static function (string $cmd, array $esperados = [250]) use ($socket, $leer): array {
        fwrite($socket, $cmd . "\r\n");
        [$codigo, $respuesta] = $leer();
        return [in_array($codigo, $esperados, true), $codigo, $respuesta];
    };

    [$codigo, $saludo] = $leer();
    if ($codigo !== 220) {
        fclose($socket);
        registrarErrorCorreo('Saludo SMTP inesperado: ' . $saludo);
        return false;
    }

    $hostname = gethostname() ?: 'coffeeandtea.local';
    [$ok, , $respuesta] = $comando('EHLO ' . $hostname);
    if (!$ok) {
        [$ok, , $respuesta] = $comando('HELO ' . $hostname);
    }
    if (!$ok) {
        fclose($socket);
        registrarErrorCorreo('EHLO/HELO falló: ' . $respuesta);
        return false;
    }

    if ($encriptacion === 'tls') {
        [$ok, $codigo, $respuesta] = $comando('STARTTLS', [220]);
        if (!$ok) {
            fclose($socket);
            registrarErrorCorreo('STARTTLS falló: ' . $respuesta);
            return false;
        }
        $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($crypto !== true) {
            fclose($socket);
            registrarErrorCorreo('No se pudo negociar TLS.');
            return false;
        }
        [$ok, , $respuesta] = $comando('EHLO ' . $hostname);
        if (!$ok) {
            fclose($socket);
            registrarErrorCorreo('EHLO tras TLS falló: ' . $respuesta);
            return false;
        }
    }

    if ($usuario !== '') {
        [$ok, , $respuesta] = $comando('AUTH LOGIN', [334]);
        if (!$ok) {
            fclose($socket);
            registrarErrorCorreo('AUTH LOGIN falló: ' . $respuesta);
            return false;
        }
        [$ok, , $respuesta] = $comando(base64_encode($usuario), [334]);
        if (!$ok) {
            fclose($socket);
            registrarErrorCorreo('Usuario SMTP rechazado: ' . $respuesta);
            return false;
        }
        [$ok, , $respuesta] = $comando(base64_encode($password), [235]);
        if (!$ok) {
            fclose($socket);
            registrarErrorCorreo('Contraseña SMTP rechazada: ' . $respuesta);
            return false;
        }
    }

    [$ok, , $respuesta] = $comando('MAIL FROM:<' . $from . '>');
    if (!$ok) {
        fclose($socket);
        registrarErrorCorreo('MAIL FROM falló: ' . $respuesta);
        return false;
    }
    [$ok, , $respuesta] = $comando('RCPT TO:<' . $para . '>');
    if (!$ok) {
        fclose($socket);
        registrarErrorCorreo('RCPT TO falló: ' . $respuesta);
        return false;
    }
    [$ok, , $respuesta] = $comando('DATA', [354]);
    if (!$ok) {
        fclose($socket);
        registrarErrorCorreo('DATA falló: ' . $respuesta);
        return false;
    }

    $datos = preg_replace('/^\./m', '..', $datos) ?? $datos;
    fwrite($socket, $datos . "\r\n.\r\n");
    [$codigo, $respuesta] = $leer();
    if ($codigo !== 250) {
        fclose($socket);
        registrarErrorCorreo('Envío rechazado: ' . $respuesta);
        return false;
    }

    $comando('QUIT', [221]);
    fclose($socket);
    return true;
}

/** Plantilla HTML sencilla y compatible con clientes de correo. */
function plantillaCorreo(string $titulo, string $contenidoHtml, ?string $urlAccion = null, ?string $textoAccion = null): string
{
    $boton = '';
    if ($urlAccion !== null && $textoAccion !== null) {
        $boton = '<p style="margin:28px 0"><a href="' . htmlspecialchars($urlAccion, ENT_QUOTES, 'UTF-8')
            . '" style="background:#3b2415;color:#fff7ec;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:600">'
            . htmlspecialchars($textoAccion, ENT_QUOTES, 'UTF-8') . '</a></p>'
            . '<p style="font-size:12px;color:#7b6a5c">Si el botón no funciona, copia este enlace:<br>'
            . '<span style="word-break:break-all">' . htmlspecialchars($urlAccion, ENT_QUOTES, 'UTF-8') . '</span></p>';
    }

    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>'
        . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</title></head>'
        . '<body style="margin:0;padding:24px;background:#f4ece2;font-family:Arial,Helvetica,sans-serif;color:#2b1d13">'
        . '<div style="max-width:560px;margin:0 auto;background:#fffaf3;border-radius:18px;padding:32px;border:1px solid #e6d7c6">'
        . '<p style="margin:0 0 8px;font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:#8a6a4f">CoffeeAndtea</p>'
        . '<h1 style="margin:0 0 18px;font-size:24px;line-height:1.25">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h1>'
        . $contenidoHtml . $boton
        . '<hr style="border:none;border-top:1px solid #e6d7c6;margin:28px 0">'
        . '<p style="font-size:12px;color:#7b6a5c;margin:0">Este mensaje se generó automáticamente. '
        . 'Si no solicitaste esta acción, puedes ignorarlo.</p>'
        . '</div></body></html>';
}
