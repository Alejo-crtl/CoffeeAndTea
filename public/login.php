<?php
/**
 * Inicio de sesión.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';

if (estaAutenticado()) {
    redirigir(inicioPorRol());
}

$correo = trim((string) ($_POST['correo'] ?? $_GET['correo'] ?? ''));
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $correo = trim((string) ($_POST['correo'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $resultado = autenticar($conn, $correo, $password);
    if ($resultado['ok']) {
        $usuario = usuarioActual();
        if ($usuario !== null && $usuario['debe_cambiar_password']) {
            flash('info', 'Por seguridad, cambia la contraseña temporal antes de continuar.');
            redirigir(url('cambiar-password.php'));
        }
        $destino = $_SESSION['url_destino'] ?? null;
        unset($_SESSION['url_destino']);
        if (!is_string($destino) || $destino === '' || $destino[0] !== '/' || str_starts_with($destino, '//')) {
            $destino = inicioPorRol();
        }
        flash('exito', 'Bienvenido de nuevo, ' . ($usuario['nombre'] ?? '') . '.');
        redirigir($destino);
    }
    $error = $resultado['error'] ?? 'No se pudo iniciar sesión.';
}

cabeceraHtml('Ingresar | CoffeeAndtea', 'login', false);
?>

<section class="auth-layout">
    <div class="auth-panel">
        <a class="btn btn-fantasma" href="<?= h(url('index.php')) ?>">&larr; Volver a la tienda</a>
        <p class="eyebrow">Cuenta CoffeeAndtea</p>
        <h1>Ingresar</h1>
        <p class="texto-suave">Accede para ver tus pedidos o para gestionar la tienda.</p>

        <?php if ($error !== null): ?>
            <div class="alerta-error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= h(url('login.php')) ?>" class="formulario">
            <?= campoCsrf() ?>
            <div class="campo">
                <label for="correo">Correo electrónico</label>
                <input id="correo" name="correo" type="email" required autofocus autocomplete="username"
                       value="<?= h($correo) ?>">
            </div>
            <div class="campo">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-primario btn-bloque" type="submit">Entrar</button>
        </form>

        <div class="auth-enlaces">
            <a href="<?= h(url('recuperar.php')) ?>">Olvidé mi contraseña</a>
            <a href="<?= h(url('registro.php')) ?>">Crear una cuenta</a>
        </div>
    </div>

    <aside class="auth-lateral">
        <h2>Pedir es más fácil con cuenta</h2>
        <ul>
            <li>Consulta el estado de tus pedidos.</li>
            <li>Repite tu pedido habitual.</li>
            <li>Guarda tus datos de contacto.</li>
        </ul>
        <p class="texto-suave">El pago siempre se realiza en el mostrador.</p>
    </aside>
</section>

<?php pieHtml(); ?>
