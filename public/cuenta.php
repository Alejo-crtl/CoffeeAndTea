<?php
/**
 * Cuenta del cliente: perfil e historial de pedidos.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/cuenta.php';
require_once __DIR__ . '/../app/php/lib/pedidos.php';

if (puedeGestionar()) {
    flash('info', 'La cuenta de cliente no está disponible para cuentas de gestión.');
    redirigir(url('gestion.php'));
}

requerirLogin();
exigirCambioPassword();
$usuario = usuarioActual();
if ($usuario === null) {
    redirigir(url('login.php'));
}

// Vincula o crea el registro de cliente para que el historial quede asociado.
$cliente = clienteDeUsuario($conn, (int) $usuario['id']);
if ($cliente === null) {
    $stmt = $conn->prepare('SELECT id, usuario_id FROM cliente WHERE lower(correo) = lower(:correo) LIMIT 1');
    $stmt->execute([':correo' => $usuario['correo']]);
    $existente = $stmt->fetch();
    if ($existente !== false && $existente['usuario_id'] === null) {
        $stmt = $conn->prepare('UPDATE cliente SET usuario_id = :uid, reclamado_en = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute([':uid' => $usuario['id'], ':id' => (int) $existente['id']]);
    } elseif ($existente === false) {
        $stmt = $conn->prepare(
            'INSERT INTO cliente (nombre, correo, consentimiento, activo, usuario_id, reclamado_en)
             VALUES (:nombre, :correo, FALSE, TRUE, :uid, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([':nombre' => $usuario['nombre'], ':correo' => $usuario['correo'], ':uid' => $usuario['id']]);
    }
    $cliente = clienteDeUsuario($conn, (int) $usuario['id']);
}

$errores = [];
$mensaje = null;
$nombre = $usuario['nombre'];
$telefono = (string) ($cliente['telefono'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $telefono = trim((string) ($_POST['telefono'] ?? ''));
    try {
        actualizarPerfilCliente($conn, (int) $usuario['id'], $nombre, $telefono);
        flash('exito', 'Tus datos se actualizaron.');
        redirigir(url('cuenta.php'));
    } catch (ReglaNegocioException $e) {
        $errores[] = $e->getMessage();
    }
}

$pedidos = pedidosDeUsuario($conn, (int) $usuario['id']);

cabeceraHtml('Mi cuenta | CoffeeAndtea', 'cuenta');
?>

<section class="pagina-cabecera">
    <div>
        <p class="eyebrow">Mi cuenta</p>
        <h1>Hola, <?= h($usuario['nombre']) ?></h1>
        <p><?= h($usuario['correo']) ?> · Rol: <?= h($usuario['rol']) ?></p>
    </div>
    <div class="cabecera-acciones">
        <a class="btn btn-secundario" href="<?= h(url('cambiar-password.php')) ?>">Cambiar contraseña</a>
        <?php if (puedeGestionar()): ?>
            <a class="btn btn-primario" href="<?= h(url('gestion.php')) ?>">Ir a gestión</a>
        <?php endif; ?>
    </div>
</section>

<?php avisosHtml($mensaje, $errores); ?>

<div class="layout-cuenta">
    <section class="panel" aria-labelledby="historial-titulo">
        <div class="panel-head">
            <h2 id="historial-titulo">Mis pedidos</h2>
            <a class="btn btn-pequeno btn-secundario" href="<?= h(url('index.php')) ?>#menu">Pedir de nuevo</a>
        </div>

        <?php if ($pedidos === []): ?>
            <div class="estado-vacio compacto">
                <h3>Aún no tienes pedidos</h3>
                <p>Cuando hagas tu primer pedido aparecerá aquí.</p>
            </div>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead>
                        <tr><th>Código</th><th>Fecha</th><th>Estado</th><th>Total</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $pedido): ?>
                            <tr>
                                <td><strong><?= h((string) $pedido['codigo']) ?></strong></td>
                                <td><?= h(fechaBonita($pedido['fecha_creacion'])) ?></td>
                                <td><?= badgeEstado((string) $pedido['estado']) ?></td>
                                <td><?= h(moneda((float) $pedido['total'])) ?></td>
                                <td>
                                    <a class="btn btn-pequeno btn-secundario"
                                       href="<?= h(url('pedido.php?codigo=' . urlencode((string) $pedido['codigo']) . '&token=' . urlencode((string) $pedido['token_publico']))) ?>">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <aside class="panel" aria-labelledby="perfil-titulo">
        <h2 id="perfil-titulo">Mis datos</h2>
        <form method="post" action="<?= h(url('cuenta.php')) ?>" class="formulario">
            <?= campoCsrf() ?>
            <div class="campo">
                <label for="nombre">Nombre</label>
                <input id="nombre" name="nombre" type="text" maxlength="150" required value="<?= h($nombre) ?>">
            </div>
            <div class="campo">
                <label for="telefono">Teléfono</label>
                <input id="telefono" name="telefono" type="tel" maxlength="30" value="<?= h($telefono) ?>">
            </div>
            <div class="campo">
                <label>Correo</label>
                <input type="email" value="<?= h($usuario['correo']) ?>" disabled>
                <small>El correo identifica tu cuenta y no se edita desde aquí.</small>
            </div>
            <button class="btn btn-primario btn-bloque" type="submit">Guardar cambios</button>
        </form>
    </aside>
</div>

<?php pieHtml(); ?>
