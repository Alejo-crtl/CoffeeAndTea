<?php
/**
 * BLOQUE B02 · AUTENTICACIÓN Y CUENTAS
 * Qué hace: registro de clientes con consentimiento de datos.
 * Relacionados: cuenta.php, auth.php.
 * Pruebas: CP-001, CP-002.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/cuenta.php';

if (estaAutenticado()) {
    redirigir(inicioPorRol());
}

$valores = [
    'nombre'         => '',
    'correo'         => '',
    'telefono'       => '',
    'consentimiento' => false,
];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();

    // Campo trampa: los bots lo completan, las personas no lo ven.
    if (trim((string) ($_POST['sitio_web'] ?? '')) !== '') {
        redirigir(url('registro.php'));
    }

    $valores = [
        'nombre'         => trim((string) ($_POST['nombre'] ?? '')),
        'correo'         => mb_strtolower(trim((string) ($_POST['correo'] ?? '')), 'UTF-8'),
        'telefono'       => trim((string) ($_POST['telefono'] ?? '')),
        'consentimiento' => isset($_POST['consentimiento']),
    ];

    try {
        $usuarioId = registrarCuentaCliente($conn, [
            'nombre'         => $valores['nombre'],
            'correo'         => $valores['correo'],
            'telefono'       => $valores['telefono'],
            'password'       => (string) ($_POST['password'] ?? ''),
            'password2'      => (string) ($_POST['password2'] ?? ''),
            'consentimiento' => $valores['consentimiento'],
        ]);

        $stmt = $conn->prepare('SELECT id, nombre, correo, rol, session_version FROM usuario WHERE id = :id');
        $stmt->execute([':id' => $usuarioId]);
        $fila = $stmt->fetch();
        if ($fila !== false) {
            iniciarSesionUsuario($fila);
        }

        flash('exito', 'Tu cuenta se creó correctamente. ¡Bienvenido!');
        redirigir(url('cuenta.php'));
    } catch (ReglaNegocioException $e) {
        $errores[] = $e->getMessage();
    }
}

cabeceraHtml('Crear cuenta | CoffeeAndtea', 'login', false);
?>

<section class="auth-layout">
    <div class="auth-panel">
        <a class="btn btn-fantasma" href="<?= h(url('index.php')) ?>">&larr; Volver a la tienda</a>
        <p class="eyebrow">Cuenta CoffeeAndtea</p>
        <h1>Crear cuenta</h1>
        <p class="texto-suave">Guarda tus datos y consulta tus pedidos cuando quieras.</p>

        <?php avisosHtml(null, $errores); ?>

        <form method="post" action="<?= h(url('registro.php')) ?>" class="formulario" novalidate>
            <?= campoCsrf() ?>
            <div class="campo-trampa" aria-hidden="true">
                <label for="sitio_web">No completar</label>
                <input id="sitio_web" name="sitio_web" type="text" tabindex="-1" autocomplete="off">
            </div>
            <div class="campo">
                <label for="nombre">Nombre completo</label>
                <input id="nombre" name="nombre" type="text" maxlength="150" required autocomplete="name"
                       value="<?= h($valores['nombre']) ?>">
            </div>
            <div class="campo">
                <label for="correo">Correo electrónico</label>
                <input id="correo" name="correo" type="email" maxlength="150" required autocomplete="email"
                       value="<?= h($valores['correo']) ?>">
            </div>
            <div class="campo">
                <label for="telefono">Teléfono (opcional)</label>
                <input id="telefono" name="telefono" type="tel" maxlength="30" autocomplete="tel"
                       value="<?= h($valores['telefono']) ?>">
            </div>
            <div class="campo">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="new-password">
                <small>Mínimo 8 caracteres, con mayúscula, minúscula y número.</small>
            </div>
            <div class="campo">
                <label for="password2">Confirmar contraseña</label>
                <input id="password2" name="password2" type="password" required autocomplete="new-password">
            </div>
            <label class="check">
                <input type="checkbox" name="consentimiento" value="1"<?= $valores['consentimiento'] ? ' checked' : '' ?> required>
                <span>Autorizo el tratamiento de mis datos personales
                    (<a href="<?= h(url('privacidad.php')) ?>" target="_blank" rel="noopener">ver política</a>).</span>
            </label>
            <button class="btn btn-primario btn-bloque" type="submit">Crear mi cuenta</button>
        </form>

        <div class="auth-enlaces">
            <span>¿Ya tienes cuenta?</span>
            <a href="<?= h(url('login.php')) ?>">Ingresar</a>
        </div>
    </div>

    <aside class="auth-lateral">
        <h2>Sin cobros en línea</h2>
        <p>No pedimos datos de tarjeta ni transferencias. Pagas en el mostrador cuando recoges tu pedido.</p>
        <h2>Recogida en tienda</h2>
        <p>Te damos un tiempo estimado y un código para consultar el estado.</p>
    </aside>
</section>

<?php pieHtml(); ?>
