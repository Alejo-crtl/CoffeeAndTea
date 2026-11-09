<?php
/**
 * Cambio de contraseña del usuario autenticado.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';

requerirLogin();
$usuario = usuarioActual();
if ($usuario === null) {
    redirigir(url('login.php'));
}

$forzado = $usuario['debe_cambiar_password'];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $actual = (string) ($_POST['actual'] ?? '');
    $nueva = (string) ($_POST['nueva'] ?? '');
    $confirmar = (string) ($_POST['confirmar'] ?? '');

    $stmt = $conn->prepare('SELECT password_hash FROM usuario WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $usuario['id']]);
    $hash = $stmt->fetchColumn();

    if ($hash === false || !password_verify($actual, (string) $hash)) {
        $errores[] = 'La contraseña actual no es correcta.';
    }
    if ($nueva !== $confirmar) {
        $errores[] = 'La nueva contraseña y su confirmación no coinciden.';
    }
    if ($nueva === $actual) {
        $errores[] = 'La nueva contraseña debe ser diferente a la actual.';
    }
    $errores = array_merge($errores, validarPassword($nueva));

    if ($errores === []) {
        cambiarPassword($conn, (int) $usuario['id'], $nueva);
        flash('exito', 'Contraseña actualizada correctamente.');
        redirigir(inicioPorRol());
    }
}

cabeceraHtml('Cambiar contraseña | CoffeeAndtea', 'cuenta', false);
?>

<section class="auth-layout">
    <div class="auth-panel">
        <p class="eyebrow">Seguridad</p>
        <h1><?= $forzado ? 'Cambia tu contraseña temporal' : 'Cambiar contraseña' ?></h1>
        <p class="texto-suave">
            <?= $forzado
                ? 'Tu cuenta se creó con una contraseña temporal. Elige una nueva antes de continuar.'
                : 'Usa una contraseña distinta a la de otros servicios.' ?>
        </p>

        <?php avisosHtml(null, $errores); ?>

        <form method="post" action="<?= h(url('cambiar-password.php')) ?>" class="formulario">
            <?= campoCsrf() ?>
            <div class="campo">
                <label for="actual">Contraseña actual</label>
                <input id="actual" name="actual" type="password" required autofocus autocomplete="current-password">
            </div>
            <div class="campo">
                <label for="nueva">Nueva contraseña</label>
                <input id="nueva" name="nueva" type="password" required autocomplete="new-password">
                <small>Mínimo 8 caracteres, con mayúscula, minúscula y número.</small>
            </div>
            <div class="campo">
                <label for="confirmar">Confirmar nueva contraseña</label>
                <input id="confirmar" name="confirmar" type="password" required autocomplete="new-password">
            </div>
            <button class="btn btn-primario btn-bloque" type="submit">Guardar contraseña</button>
        </form>

        <?php if (!$forzado): ?>
            <a class="auth-volver" href="<?= h(inicioPorRol()) ?>">Volver</a>
        <?php endif; ?>
    </div>
</section>

<?php pieHtml(); ?>
