<?php
/**
 * BLOQUE B02 · AUTENTICACIÓN Y CUENTAS
 * Qué hace: valida el token y define una nueva contraseña.
 * Relacionados: password_reset.php.
 * Pruebas: CP-007.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/password_reset.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$reset = buscarResetValido($conn, $token);
$errores = [];

if ($reset === null) {
    http_response_code(410);
    cabeceraHtml('Enlace vencido | CoffeeAndtea', 'login', false);
    ?>
    <section class="auth-layout">
        <div class="auth-panel">
            <p class="eyebrow">Enlace no válido</p>
            <h1>Este enlace ya no sirve</h1>
            <p class="texto-suave">Puede haber vencido o haberse usado antes. Solicita uno nuevo.</p>
            <a class="btn btn-primario btn-bloque" href="<?= h(url('recuperar.php')) ?>">Solicitar otro enlace</a>
            <a class="btn btn-fantasma btn-bloque" href="<?= h(url('login.php')) ?>">Volver a ingresar</a>
            <a class="btn btn-fantasma btn-bloque" href="<?= h(url('index.php')) ?>">Volver a la tienda</a>
        </div>
    </section>
    <?php
    pieHtml();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');

    if ($password !== $password2) {
        $errores[] = 'Las contraseñas no coinciden.';
    } else {
        try {
            restablecerPassword($conn, $token, $password);
            flash('exito', 'Tu contraseña se actualizó. Ya puedes ingresar.');
            redirigir(url('login.php'));
        } catch (ReglaNegocioException $e) {
            $errores[] = $e->getMessage();
        }
    }
}

cabeceraHtml('Nueva contraseña | CoffeeAndtea', 'login', false);
?>

<section class="auth-layout">
    <div class="auth-panel">
        <p class="eyebrow">Recuperar acceso</p>
        <h1>Crea una nueva contraseña</h1>
        <p class="texto-suave">Cuenta: <?= h((string) $reset['correo']) ?></p>

        <?php avisosHtml(null, $errores); ?>

        <form method="post" action="<?= h(url('restablecer.php')) ?>" class="formulario">
            <?= campoCsrf() ?>
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <div class="campo">
                <label for="password">Nueva contraseña</label>
                <input id="password" name="password" type="password" required autofocus autocomplete="new-password">
                <small>Mínimo 8 caracteres, con mayúscula, minúscula y número.</small>
            </div>
            <div class="campo">
                <label for="password2">Confirmar contraseña</label>
                <input id="password2" name="password2" type="password" required autocomplete="new-password">
            </div>
            <button class="btn btn-primario btn-bloque" type="submit">Guardar contraseña</button>
        </form>
        <a class="btn btn-fantasma btn-bloque" href="<?= h(url('login.php')) ?>">Volver a ingresar</a>
        <a class="btn btn-fantasma btn-bloque" href="<?= h(url('index.php')) ?>">Volver a la tienda</a>
    </div>
</section>

<?php pieHtml(); ?>
