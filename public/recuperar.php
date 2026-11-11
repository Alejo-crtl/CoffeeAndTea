<?php
/**
 * BLOQUE B02 · AUTENTICACIÓN Y CUENTAS
 * Qué hace: solicita un enlace de recuperación de contraseña.
 * Relacionados: password_reset.php, correo.php.
 * Pruebas: CP-006.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/password_reset.php';

if (estaAutenticado()) {
    redirigir(inicioPorRol());
}

$enviado = false;
$correo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')), 'UTF-8');
    solicitarRecuperacion($conn, $correo);
    $enviado = true;
}

cabeceraHtml('Recuperar contraseña | CoffeeAndtea', 'login', false);
?>

<section class="auth-layout">
    <div class="auth-panel">
        <a class="btn btn-fantasma" href="<?= h(url('login.php')) ?>">&larr; Volver a ingresar</a>
        <p class="eyebrow">Recuperar acceso</p>
        <h1>Olvidé mi contraseña</h1>

        <?php if ($enviado): ?>
            <div class="alerta-exito" role="status">
                Si el correo está registrado, enviamos un enlace para restablecer la contraseña.
                Revisa también la carpeta de spam.
            </div>
            <p class="texto-suave">El enlace vence en 60 minutos y solo puede usarse una vez.</p>
            <a class="btn btn-secundario btn-bloque" href="<?= h(url('login.php')) ?>">Volver a ingresar</a>
        <?php else: ?>
            <p class="texto-suave">Escribe el correo de tu cuenta y te enviaremos un enlace seguro.</p>
            <form method="post" action="<?= h(url('recuperar.php')) ?>" class="formulario">
                <?= campoCsrf() ?>
                <div class="campo">
                    <label for="correo">Correo electrónico</label>
                    <input id="correo" name="correo" type="email" required autofocus autocomplete="email"
                           value="<?= h($correo) ?>">
                </div>
                <button class="btn btn-primario btn-bloque" type="submit">Enviar enlace</button>
            </form>
        <?php endif; ?>
    </div>

    <aside class="auth-lateral">
        <h2>¿No llega el correo?</h2>
        <p>Pide al encargado que verifique tu cuenta. El administrador puede generar una contraseña temporal.</p>
        <p class="texto-suave">Por seguridad, nunca enviamos contraseñas por correo.</p>
    </aside>
</section>

<?php pieHtml(); ?>
