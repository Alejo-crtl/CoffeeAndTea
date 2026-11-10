<?php
/**
 * Administración: usuarios, catálogo, configuración y auditoría.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/pedidos.php';
require_once __DIR__ . '/../app/php/lib/password_reset.php';

requerirRol(ROL_ADMIN);
exigirCambioPassword();
$usuario = usuarioActual();
if ($usuario === null) {
    redirigir(url('login.php'));
}

$tabs = ['usuarios', 'productos', 'categorias', 'configuracion', 'auditoria'];
$tab = (string) ($_GET['tab'] ?? 'usuarios');
if (!in_array($tab, $tabs, true)) {
    $tab = 'usuarios';
}

/** Cuenta administradores activos, excluyendo opcionalmente un id. */
function administradoresActivos(PDO $conn, ?int $excluir = null): int
{
    $sql = "SELECT COUNT(*) FROM usuario WHERE rol = 'ADMIN' AND activo = TRUE";
    $params = [];
    if ($excluir !== null) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $excluir;
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $destino = url('admin.php?tab=' . rawurlencode((string) ($_POST['tab'] ?? $tab)));

    try {
        switch ($accion) {
            case 'usuario_crear':
                $nombre = trim((string) ($_POST['nombre'] ?? ''));
                $correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')), 'UTF-8');
                $rol = (string) ($_POST['rol'] ?? ROL_CLIENTE);
                $password = (string) ($_POST['password'] ?? '');
                $temporal = false;

                if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 150) {
                    throw new ReglaNegocioException('El nombre debe tener entre 3 y 150 caracteres.');
                }
                if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                    throw new ReglaNegocioException('El correo no es válido.');
                }
                if (!in_array($rol, [ROL_CLIENTE, ROL_ENCARGADO, ROL_ADMIN], true)) {
                    throw new ReglaNegocioException('Rol no válido.');
                }
                if ($password === '') {
                    $password = generarPasswordTemporal(12);
                    $temporal = true;
                } else {
                    $erroresPassword = validarPassword($password);
                    if ($erroresPassword !== []) {
                        throw new ReglaNegocioException(implode(' ', $erroresPassword));
                    }
                }

                $stmt = $conn->prepare('SELECT 1 FROM usuario WHERE lower(correo) = :correo');
                $stmt->execute([':correo' => $correo]);
                if ($stmt->fetchColumn() !== false) {
                    throw new ReglaNegocioException('Ya existe un usuario con ese correo.');
                }

                $conn->beginTransaction();
                try {
                    $stmt = $conn->prepare(
                        'INSERT INTO usuario (nombre, correo, password_hash, rol, activo, debe_cambiar_password)
                         VALUES (:nombre, :correo, :hash, :rol, TRUE, :debe_cambiar)
                         RETURNING id'
                    );
                    $stmt->execute([
                        ':nombre'        => $nombre,
                        ':correo'        => $correo,
                        ':hash'          => password_hash($password, PASSWORD_DEFAULT),
                        ':rol'           => $rol,
                        ':debe_cambiar'  => boolSql($temporal),
                    ]);
                    $nuevoId = (int) $stmt->fetchColumn();

                    if ($rol === ROL_CLIENTE) {
                        $stmt = $conn->prepare('SELECT id, usuario_id FROM cliente WHERE lower(correo) = :correo');
                        $stmt->execute([':correo' => $correo]);
                        $cliente = $stmt->fetch();
                        if ($cliente !== false && $cliente['usuario_id'] === null) {
                            $stmt = $conn->prepare('UPDATE cliente SET usuario_id = :uid, reclamado_en = CURRENT_TIMESTAMP WHERE id = :id');
                            $stmt->execute([':uid' => $nuevoId, ':id' => (int) $cliente['id']]);
                        }
                    }

                    registrarAuditoria($conn, (int) $usuario['id'], 'USUARIO_CREADO', 'usuario', $nuevoId, [
                        'correo' => $correo,
                        'rol'    => $rol,
                        'temporal' => $temporal,
                    ]);
                    $conn->commit();
                } catch (Throwable $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    throw $e;
                }

                if ($temporal) {
                    flash('exito', 'Usuario creado. Contraseña temporal (se muestra una sola vez): ' . $password);
                } else {
                    flash('exito', 'Usuario creado correctamente.');
                }
                $destino = url('admin.php?tab=usuarios');
                break;

            case 'usuario_editar':
                $id = (int) ($_POST['id'] ?? 0);
                $nombre = trim((string) ($_POST['nombre'] ?? ''));
                $rol = (string) ($_POST['rol'] ?? '');
                $activo = isset($_POST['activo']);

                if ($id < 1 || mb_strlen($nombre) < 3) {
                    throw new ReglaNegocioException('Datos del usuario no válidos.');
                }
                if (!in_array($rol, [ROL_CLIENTE, ROL_ENCARGADO, ROL_ADMIN], true)) {
                    throw new ReglaNegocioException('Rol no válido.');
                }
                if ($id === (int) $usuario['id'] && (!$activo || $rol !== ROL_ADMIN)) {
                    throw new ReglaNegocioException('No puedes quitarte el rol de administrador ni desactivar tu propia cuenta.');
                }
                $stmt = $conn->prepare('SELECT rol, activo FROM usuario WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $anterior = $stmt->fetch();
                if ($anterior === false) {
                    throw new ReglaNegocioException('El usuario no existe.');
                }
                if ($anterior['rol'] === ROL_ADMIN && ($rol !== ROL_ADMIN || !$activo) && administradoresActivos($conn, $id) === 0) {
                    throw new ReglaNegocioException('Debe quedar al menos un administrador activo.');
                }

                $stmt = $conn->prepare(
                    'UPDATE usuario SET nombre = :nombre, rol = :rol, activo = :activo,
                        session_version = session_version + 1
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':rol'    => $rol,
                    ':activo' => boolSql($activo),
                    ':id'     => $id,
                ]);
                registrarAuditoria($conn, (int) $usuario['id'], 'USUARIO_EDITADO', 'usuario', $id, [
                    'rol_anterior' => $anterior['rol'],
                    'rol_nuevo'    => $rol,
                    'activo'       => $activo,
                ]);
                flash('exito', 'Usuario actualizado. Sus sesiones se reiniciaron por seguridad.');
                $destino = url('admin.php?tab=usuarios');
                break;

            case 'usuario_reset':
                $id = (int) ($_POST['id'] ?? 0);
                $stmt = $conn->prepare('SELECT nombre, correo, activo FROM usuario WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $cuenta = $stmt->fetch();
                if ($cuenta === false) {
                    throw new ReglaNegocioException('El usuario no existe.');
                }
                if (!esVerdadero($cuenta['activo'])) {
                    throw new ReglaNegocioException('No se puede resetear la contraseña de un usuario inactivo.');
                }
                $temporal = generarPasswordTemporal(12);
                $stmt = $conn->prepare(
                    'UPDATE usuario
                     SET password_hash = :hash, debe_cambiar_password = TRUE,
                         session_version = session_version + 1
                     WHERE id = :id'
                );
                $stmt->execute([':hash' => password_hash($temporal, PASSWORD_DEFAULT), ':id' => $id]);
                registrarAuditoria($conn, (int) $usuario['id'], 'PASSWORD_RESET_ADMIN', 'usuario', $id);

                $html = plantillaCorreo(
                    'Contraseña temporal',
                    '<p>Hola, <strong>' . htmlspecialchars((string) $cuenta['nombre'], ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
                    . '<p>Un administrador generó una contraseña temporal para tu cuenta. Debes cambiarla al ingresar.</p>'
                    . '<p style="font-size:20px;font-family:monospace;background:#f4ece2;padding:12px 16px;border-radius:10px">'
                    . htmlspecialchars($temporal, ENT_QUOTES, 'UTF-8') . '</p>',
                    url('login.php'),
                    'Ingresar y cambiarla'
                );
                enviarCorreo((string) $cuenta['correo'], 'Tu contraseña temporal de CoffeeAndtea', $html);

                flash('exito', 'Contraseña temporal de ' . $cuenta['correo'] . ' (se muestra una sola vez): ' . $temporal);
                $destino = url('admin.php?tab=usuarios');
                break;

            case 'categoria_guardar':
                $id = (int) ($_POST['id'] ?? 0);
                $nombre = trim((string) ($_POST['nombre'] ?? ''));
                $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
                $activo = isset($_POST['activo']);

                if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
                    throw new ReglaNegocioException('El nombre de la categoría debe tener entre 2 y 100 caracteres.');
                }
                $stmt = $conn->prepare('SELECT 1 FROM categoria WHERE lower(nombre) = lower(:nombre) AND id <> :id');
                $stmt->execute([':nombre' => $nombre, ':id' => $id]);
                if ($stmt->fetchColumn() !== false) {
                    throw new ReglaNegocioException('Ya existe una categoría con ese nombre.');
                }

                if ($id > 0) {
                    $stmt = $conn->prepare(
                        'UPDATE categoria SET nombre = :nombre, descripcion = :descripcion, activo = :activo WHERE id = :id'
                    );
                    $stmt->execute([
                        ':nombre'      => $nombre,
                        ':descripcion' => $descripcion !== '' ? $descripcion : null,
                        ':activo'      => boolSql($activo),
                        ':id'          => $id,
                    ]);
                    registrarAuditoria($conn, (int) $usuario['id'], 'CATEGORIA_EDITADA', 'categoria', $id);
                    flash('exito', 'Categoría actualizada.');
                } else {
                    $stmt = $conn->prepare(
                        'INSERT INTO categoria (nombre, descripcion, activo) VALUES (:nombre, :descripcion, :activo) RETURNING id'
                    );
                    $stmt->execute([
                        ':nombre'      => $nombre,
                        ':descripcion' => $descripcion !== '' ? $descripcion : null,
                        ':activo'      => boolSql($activo),
                    ]);
                    $id = (int) $stmt->fetchColumn();
                    registrarAuditoria($conn, (int) $usuario['id'], 'CATEGORIA_CREADA', 'categoria', $id);
                    flash('exito', 'Categoría creada.');
                }
                $destino = url('admin.php?tab=categorias');
                break;

            case 'producto_guardar':
                $id = (int) ($_POST['id'] ?? 0);
                $nombre = trim((string) ($_POST['nombre'] ?? ''));
                $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
                $precio = filter_var(str_replace(',', '.', (string) ($_POST['precio'] ?? '')), FILTER_VALIDATE_FLOAT);
                $stock = filter_var($_POST['stock_actual'] ?? null, FILTER_VALIDATE_INT);
                $stockMinimo = filter_var($_POST['stock_minimo'] ?? null, FILTER_VALIDATE_INT);
                $categoriaId = filter_var($_POST['categoria_id'] ?? null, FILTER_VALIDATE_INT);
                $tiempo = trim((string) ($_POST['tiempo_preparacion_min'] ?? ''));
                $activo = isset($_POST['activo']);
                $destacado = isset($_POST['destacado']);
                $quitarImagen = isset($_POST['quitar_imagen']);
                $archivoImagen = $_FILES['imagen'] ?? null;
                $subeImagen = is_array($archivoImagen)
                    && (int) ($archivoImagen['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                $imagenNueva = null;
                if ($subeImagen) {
                    $imagenNueva = guardarImagenProducto($archivoImagen);
                }
                $imagenAnteriorGuardada = '';

                if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 150) {
                    throw new ReglaNegocioException('El nombre del producto debe tener entre 2 y 150 caracteres.');
                }
                if ($precio === false || $precio <= 0) {
                    throw new ReglaNegocioException('El precio debe ser mayor que cero.');
                }
                if ($stock === false || $stock < 0) {
                    throw new ReglaNegocioException('El stock debe ser un entero mayor o igual a cero.');
                }
                if ($stockMinimo === false || $stockMinimo < 0) {
                    throw new ReglaNegocioException('El stock mínimo debe ser un entero mayor o igual a cero.');
                }
                if ($categoriaId === false || $categoriaId < 1) {
                    throw new ReglaNegocioException('Selecciona una categoría.');
                }
                $tiempoValor = null;
                if ($tiempo !== '') {
                    $tiempoInt = filter_var($tiempo, FILTER_VALIDATE_INT);
                    if ($tiempoInt === false || $tiempoInt < 1 || $tiempoInt > 240) {
                        throw new ReglaNegocioException('El tiempo de preparación debe estar entre 1 y 240 minutos.');
                    }
                    $tiempoValor = $tiempoInt;
                }

                $stmt = $conn->prepare('SELECT 1 FROM categoria WHERE id = :id AND activo = TRUE');
                $stmt->execute([':id' => $categoriaId]);
                if ($stmt->fetchColumn() === false) {
                    throw new ReglaNegocioException('La categoría seleccionada no existe o está inactiva.');
                }

                $conn->beginTransaction();
                try {
                    if ($id > 0) {
                        $stmt = $conn->prepare('SELECT stock_actual, imagen_url FROM producto WHERE id = :id FOR UPDATE');
                        $stmt->execute([':id' => $id]);
                        $filaAnterior = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($filaAnterior === false) {
                            throw new ReglaNegocioException('El producto no existe.');
                        }
                        $stockAnterior = (int) $filaAnterior['stock_actual'];
                        $imagenAnteriorGuardada = (string) ($filaAnterior['imagen_url'] ?? '');
                        if ($subeImagen) {
                            $imagenFinal = $imagenNueva;
                        } elseif ($quitarImagen) {
                            $imagenFinal = null;
                        } else {
                            $imagenFinal = $imagenAnteriorGuardada !== '' ? $imagenAnteriorGuardada : null;
                        }
                        $stmt = $conn->prepare(
                            'UPDATE producto
                             SET nombre = :nombre, descripcion = :descripcion, precio = :precio,
                                 stock_actual = :stock, stock_minimo = :stock_minimo,
                                 categoria_id = :categoria_id, activo = :activo, destacado = :destacado,
                                 tiempo_preparacion_min = :tiempo, imagen_url = :imagen_url
                             WHERE id = :id'
                        );
                        $stmt->execute([
                            ':nombre'       => $nombre,
                            ':descripcion'  => $descripcion !== '' ? $descripcion : null,
                            ':precio'       => number_format((float) $precio, 2, '.', ''),
                            ':stock'        => $stock,
                            ':stock_minimo' => $stockMinimo,
                            ':categoria_id' => $categoriaId,
                            ':activo'       => boolSql($activo),
                            ':destacado'    => boolSql($destacado),
                            ':tiempo'       => $tiempoValor,
                            ':imagen_url'   => $imagenFinal,
                            ':id'           => $id,
                        ]);
                        $diferencia = (int) $stock - (int) $stockAnterior;
                        if ($diferencia !== 0) {
                            $stmt = $conn->prepare(
                                'INSERT INTO movimiento_stock (producto_id, tipo, cantidad, referencia, creado_por)
                                 VALUES (:producto_id, \'AJUSTE\', :cantidad, :referencia, :usuario_id)'
                            );
                            $stmt->execute([
                                ':producto_id' => $id,
                                ':cantidad'    => abs($diferencia),
                                ':referencia'  => 'Edición de producto: ' . $stockAnterior . ' -> ' . $stock,
                                ':usuario_id'  => (int) $usuario['id'],
                            ]);
                        }
                        registrarAuditoria($conn, (int) $usuario['id'], 'PRODUCTO_EDITADO', 'producto', $id, [
                            'precio' => (float) $precio,
                            'stock'  => $stock,
                        ]);
                        flash('exito', 'Producto actualizado. Los pedidos anteriores conservan su precio histórico.');
                    } else {
                        $stmt = $conn->prepare(
                            'INSERT INTO producto
                                (nombre, descripcion, precio, stock_actual, stock_minimo, categoria_id,
                                 activo, destacado, unidades_medida, tiempo_preparacion_min, imagen_url)
                             VALUES (:nombre, :descripcion, :precio, :stock, :stock_minimo, :categoria_id,
                                     :activo, :destacado, \'unidades\', :tiempo, :imagen_url)
                             RETURNING id'
                        );
                        $stmt->execute([
                            ':nombre'       => $nombre,
                            ':descripcion'  => $descripcion !== '' ? $descripcion : null,
                            ':precio'       => number_format((float) $precio, 2, '.', ''),
                            ':stock'        => $stock,
                            ':stock_minimo' => $stockMinimo,
                            ':categoria_id' => $categoriaId,
                            ':activo'       => boolSql($activo),
                            ':destacado'    => boolSql($destacado),
                            ':tiempo'       => $tiempoValor,
                            ':imagen_url'   => $imagenNueva,
                        ]);
                        $id = (int) $stmt->fetchColumn();
                        if ($stock > 0) {
                            $stmt = $conn->prepare(
                                'INSERT INTO movimiento_stock (producto_id, tipo, cantidad, referencia, creado_por)
                                 VALUES (:producto_id, \'AJUSTE\', :cantidad, \'Stock inicial\', :usuario_id)'
                            );
                            $stmt->execute([
                                ':producto_id' => $id,
                                ':cantidad'    => $stock,
                                ':usuario_id'  => (int) $usuario['id'],
                            ]);
                        }
                        registrarAuditoria($conn, (int) $usuario['id'], 'PRODUCTO_CREADO', 'producto', $id);
                        flash('exito', 'Producto creado.');
                    }
                    $conn->commit();
                } catch (Throwable $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    if ($subeImagen && $imagenNueva !== null) {
                        eliminarImagenProducto($imagenNueva);
                    }
                    throw $e;
                }
                if ($imagenAnteriorGuardada !== ''
                    && $imagenAnteriorGuardada !== ($imagenFinal ?? null)) {
                    eliminarImagenProducto($imagenAnteriorGuardada, $conn, $id);
                }
                $destino = url('admin.php?tab=productos');
                break;

            case 'config_guardar':
                $urlVideo = null;
                if (array_key_exists('video_presentacion_url', $_POST)) {
                    $urlVideo = trim((string) $_POST['video_presentacion_url']);
                    if ($urlVideo !== '' && normalizarUrlVideo($urlVideo) === null) {
                        throw new ReglaNegocioException('El enlace del video debe ser de YouTube o Google Drive.');
                    }
                }
                $permitidas = [
                    'nombre_tienda', 'tiempo_estimado_pedidos_min',
                    'capacidad_preparacion_simultanea', 'buffer_preparacion_porcentaje',
                    'direccion_tienda', 'telefono_tienda', 'correo_contacto',
                    'horario_tienda', 'moneda',
                ];
                foreach ($permitidas as $clave) {
                    if (array_key_exists($clave, $_POST)) {
                        guardarConfigSistema($conn, $clave, trim((string) $_POST[$clave]));
                    }
                }
                if ($urlVideo !== null) {
                    guardarConfigSistema($conn, 'video_presentacion_url', $urlVideo);
                }
                $abierta = isset($_POST['pagina_abierta']) ? 'true' : 'false';
                guardarConfigSistema($conn, 'pagina_abierta', $abierta);
                registrarAuditoria($conn, (int) $usuario['id'], 'CONFIG_ACTUALIZADA', 'config_sistema', null, [
                    'pagina_abierta' => $abierta,
                ]);
                flash('exito', 'Configuración guardada.');
                $destino = url('admin.php?tab=configuracion');
                break;

            case 'probar_correo':
                $destinoCorreo = mb_strtolower(trim((string) ($_POST['destino'] ?? $usuario['correo'])), 'UTF-8');
                $html = plantillaCorreo(
                    'Correo de prueba',
                    '<p>Este es un correo de prueba enviado desde el panel de administración.</p>'
                    . '<p>Si lo recibes, la configuración SMTP funciona.</p>'
                );
                $ok = enviarCorreo($destinoCorreo, 'Prueba SMTP CoffeeAndtea', $html);
                flash($ok ? 'exito' : 'error', $ok
                    ? 'Correo de prueba enviado a ' . $destinoCorreo . '.'
                    : 'No se pudo enviar. Revisa storage/mail-error.log y la configuración SMTP.');
                $destino = url('admin.php?tab=configuracion');
                break;

            default:
                throw new ReglaNegocioException('Acción no reconocida.');
        }
    } catch (ReglaNegocioException $e) {
        flash('error', $e->getMessage());
    } catch (Throwable $e) {
        error_log('CoffeeAndtea - admin: ' . $e->getMessage());
        flash('error', 'Ocurrió un error inesperado. Revisa el registro del servidor.');
    }

    redirigir($destino);
}

$editarProducto = null;
$editarCategoria = null;
if ($tab === 'productos') {
    $id = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;
    if ($id > 0) {
        $stmt = $conn->prepare('SELECT * FROM producto WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $editarProducto = $stmt->fetch() ?: null;
    }
}
if ($tab === 'categorias') {
    $id = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;
    if ($id > 0) {
        $stmt = $conn->prepare('SELECT * FROM categoria WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $editarCategoria = $stmt->fetch() ?: null;
    }
}

$usuarios = $conn->query(
    'SELECT id, nombre, correo, rol, activo, debe_cambiar_password, creado_en, ultimo_acceso
     FROM usuario ORDER BY activo DESC, rol, nombre'
)->fetchAll();
$categorias = $conn->query('SELECT * FROM categoria ORDER BY activo DESC, nombre')->fetchAll();
$productos = $conn->query(
    'SELECT p.*, c.nombre AS categoria FROM producto p
     INNER JOIN categoria c ON c.id = p.categoria_id
     ORDER BY p.activo DESC, c.nombre, p.nombre'
)->fetchAll();
$config = [];
foreach ($conn->query('SELECT clave, valor FROM config_sistema')->fetchAll() as $fila) {
    $config[$fila['clave']] = $fila['valor'];
}
$auditoria = $conn->query(
    'SELECT a.*, u.nombre AS usuario FROM auditoria a
     LEFT JOIN usuario u ON u.id = a.usuario_id
     ORDER BY a.id DESC LIMIT 200'
)->fetchAll();

cabeceraHtml('Administración | CoffeeAndtea', 'admin');
?>

<section class="pagina-cabecera">
    <div>
        <p class="eyebrow">Configuración del sistema</p>
        <h1>Administración</h1>
        <p>Solo el rol ADMIN puede entrar aquí. Todas las acciones quedan auditadas.</p>
    </div>
    <a class="btn btn-secundario" href="<?= h(url('gestion.php')) ?>">Ir a gestión</a>
</section>

<nav class="tabs" aria-label="Secciones de administración">
    <?php
    $etiquetasTabs = [
        'usuarios'      => 'Usuarios',
        'productos'     => 'Productos',
        'categorias'    => 'Categorías',
        'configuracion' => 'Configuración',
        'auditoria'     => 'Auditoría',
    ];
    foreach ($etiquetasTabs as $id => $etiqueta): ?>
        <a class="tab<?= $tab === $id ? ' activo' : '' ?>" href="<?= h(url('admin.php?tab=' . $id)) ?>"><?= h($etiqueta) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'usuarios'): ?>
    <div class="layout-admin">
        <section class="panel">
            <h2>Usuarios (<?= count($usuarios) ?>)</h2>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Nombre</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($usuarios as $cuenta): ?>
                            <tr class="<?= !esVerdadero($cuenta['activo']) ? 'fila-inactiva' : '' ?>">
                                <td>
                                    <strong><?= h((string) $cuenta['nombre']) ?></strong>
                                    <span class="texto-suave"><?= h((string) $cuenta['correo']) ?></span>
                                    <?php if (esVerdadero($cuenta['debe_cambiar_password'])): ?>
                                        <span class="pastilla pastilla-ambar">Debe cambiar contraseña</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h((string) $cuenta['rol']) ?></td>
                                <td>
                                    <span class="pastilla <?= esVerdadero($cuenta['activo']) ? 'pastilla-verde' : 'pastilla-gris' ?>">
                                        <?= esVerdadero($cuenta['activo']) ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td><?= h(fechaBonita($cuenta['ultimo_acceso'])) ?></td>
                                <td class="acciones-tabla">
                                    <button class="btn btn-pequeno btn-secundario" type="button"
                                            onclick="document.getElementById('editar-usuario-<?= (int) $cuenta['id'] ?>').hidden = !document.getElementById('editar-usuario-<?= (int) $cuenta['id'] ?>').hidden">
                                        Editar
                                    </button>
                                    <form method="post" action="<?= h(url('admin.php')) ?>"
                                          onsubmit="return confirm('¿Generar una contraseña temporal para <?= h((string) $cuenta['correo']) ?>? Se cerrarán sus sesiones.')">
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="accion" value="usuario_reset">
                                        <input type="hidden" name="tab" value="usuarios">
                                        <input type="hidden" name="id" value="<?= (int) $cuenta['id'] ?>">
                                        <button class="btn btn-pequeno btn-peligro" type="submit">Resetear</button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="editar-usuario-<?= (int) $cuenta['id'] ?>" hidden>
                                <td colspan="5">
                                    <form method="post" action="<?= h(url('admin.php')) ?>" class="form-inline-admin">
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="accion" value="usuario_editar">
                                        <input type="hidden" name="tab" value="usuarios">
                                        <input type="hidden" name="id" value="<?= (int) $cuenta['id'] ?>">
                                        <label>Nombre <input type="text" name="nombre" value="<?= h((string) $cuenta['nombre']) ?>" maxlength="150" required></label>
                                        <label>Rol
                                            <select name="rol">
                                                <?php foreach ([ROL_CLIENTE, ROL_ENCARGADO, ROL_ADMIN] as $rol): ?>
                                                    <option value="<?= h($rol) ?>"<?= $cuenta['rol'] === $rol ? ' selected' : '' ?>><?= h($rol) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label class="check-inline"><input type="checkbox" name="activo" value="1"<?= esVerdadero($cuenta['activo']) ? ' checked' : '' ?>> Activo</label>
                                        <button class="btn btn-pequeno btn-primario" type="submit">Guardar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="panel">
            <h2>Crear usuario</h2>
            <p class="texto-suave">Si dejas la contraseña vacía, se genera una temporal y se muestra una sola vez.</p>
            <form method="post" action="<?= h(url('admin.php')) ?>" class="formulario">
                <?= campoCsrf() ?>
                <input type="hidden" name="accion" value="usuario_crear">
                <input type="hidden" name="tab" value="usuarios">
                <div class="campo">
                    <label for="u-nombre">Nombre</label>
                    <input id="u-nombre" name="nombre" type="text" maxlength="150" required>
                </div>
                <div class="campo">
                    <label for="u-correo">Correo</label>
                    <input id="u-correo" name="correo" type="email" maxlength="150" required>
                </div>
                <div class="campo">
                    <label for="u-rol">Rol</label>
                    <select id="u-rol" name="rol">
                        <option value="CLIENTE">CLIENTE</option>
                        <option value="ENCARGADO">ENCARGADO</option>
                        <option value="ADMIN">ADMIN</option>
                    </select>
                </div>
                <div class="campo">
                    <label for="u-password">Contraseña (opcional)</label>
                    <input id="u-password" name="password" type="text" autocomplete="new-password">
                </div>
                <button class="btn btn-primario btn-bloque" type="submit">Crear usuario</button>
            </form>
        </aside>
    </div>

<?php elseif ($tab === 'productos'): ?>
    <div class="layout-admin">
        <section class="panel">
            <h2>Productos (<?= count($productos) ?>)</h2>
            <p class="texto-suave">Cada producto puede tener una imagen JPG, PNG o WebP de hasta 2 MB. Al subirla se ajusta sin recortar, con un máximo de 1400 px por lado, para que la foto completa se vea en la tarjeta.</p>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Producto</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td>
                                    <div class="producto-tabla">
                                        <?php $imagenFila = trim((string) ($producto['imagen_url'] ?? '')); ?>
                                        <?php if (imagenProductoDisponible($imagenFila)): ?>
                                            <img class="miniatura-producto" src="<?= h(urlImagenProducto($imagenFila)) ?>" alt="" loading="lazy" decoding="async">
                                        <?php else: ?>
                                            <span class="miniatura-producto miniatura-vacia" aria-hidden="true"><?= h(mb_substr((string) $producto['nombre'], 0, 1)) ?></span>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= h((string) $producto['nombre']) ?></strong>
                                            <span class="texto-suave"><?= h((string) $producto['descripcion']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= h((string) $producto['categoria']) ?></td>
                                <td><?= h(moneda((float) $producto['precio'])) ?></td>
                                <td><?= (int) $producto['stock_actual'] ?> / mín. <?= (int) $producto['stock_minimo'] ?></td>
                                <td>
                                    <span class="pastilla <?= esVerdadero($producto['activo']) ? 'pastilla-verde' : 'pastilla-gris' ?>">
                                        <?= esVerdadero($producto['activo']) ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                    <?php if (esVerdadero($producto['destacado'])): ?><span class="pastilla pastilla-ambar">Destacado</span><?php endif; ?>
                                </td>
                                <td><a class="btn btn-pequeno btn-secundario" href="<?= h(url('admin.php?tab=productos&editar=' . (int) $producto['id'])) ?>">Editar</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="panel">
            <h2><?= $editarProducto !== null ? 'Editar producto' : 'Nuevo producto' ?></h2>
            <?php
            $valoresProducto = [
                'nombre'                => (string) ($editarProducto['nombre'] ?? ''),
                'descripcion'           => (string) ($editarProducto['descripcion'] ?? ''),
                'precio'                => (string) ($editarProducto['precio'] ?? ''),
                'stock_actual'          => (string) ($editarProducto['stock_actual'] ?? '0'),
                'stock_minimo'          => (string) ($editarProducto['stock_minimo'] ?? '5'),
                'categoria_id'          => (string) ($editarProducto['categoria_id'] ?? ''),
                'tiempo_preparacion_min' => (string) ($editarProducto['tiempo_preparacion_min'] ?? ''),
                'activo'                => $editarProducto === null ? true : esVerdadero($editarProducto['activo']),
                'destacado'             => $editarProducto !== null && esVerdadero($editarProducto['destacado']),
                'imagen_url'            => (string) ($editarProducto['imagen_url'] ?? ''),
            ];
            $imagenActualProducto = trim($valoresProducto['imagen_url']);
            ?>
            <form method="post" action="<?= h(url('admin.php')) ?>" class="formulario" enctype="multipart/form-data">
                <?= campoCsrf() ?>
                <input type="hidden" name="accion" value="producto_guardar">
                <input type="hidden" name="tab" value="productos">
                <input type="hidden" name="id" value="<?= (int) ($editarProducto['id'] ?? 0) ?>">
                <div class="campo">
                    <label for="p-nombre">Nombre</label>
                    <input id="p-nombre" name="nombre" type="text" maxlength="150" required value="<?= h($valoresProducto['nombre']) ?>">
                </div>
                <div class="campo">
                    <label for="p-descripcion">Descripción</label>
                    <textarea id="p-descripcion" name="descripcion" rows="2" maxlength="1000"><?= h($valoresProducto['descripcion']) ?></textarea>
                </div>
                <div class="campo">
                    <label for="p-imagen">Imagen del producto (JPG, PNG o WebP, máx. 2 MB)</label>
                    <?php if (imagenProductoDisponible($imagenActualProducto)): ?>
                        <div class="vista-previa-imagen">
                            <img src="<?= h(urlImagenProducto($imagenActualProducto)) ?>" alt="Vista previa de <?= h($valoresProducto['nombre']) ?>">
                        </div>
                    <?php endif; ?>
                    <input id="p-imagen" name="imagen" type="file" accept="image/jpeg,image/png,image/webp">
                    <small><?= imagenProductoDisponible($imagenActualProducto) ? 'Sube otra imagen para reemplazar la actual.' : 'Se ajusta automáticamente sin recortar.' ?></small>
                    <?php if (imagenProductoDisponible($imagenActualProducto)): ?>
                        <label class="check">
                            <input type="checkbox" name="quitar_imagen" value="1">
                            Quitar la imagen actual
                        </label>
                    <?php endif; ?>
                </div>
                <div class="campo-doble">
                    <div class="campo">
                        <label for="p-precio">Precio (COP)</label>
                        <input id="p-precio" name="precio" type="number" min="1" step="1" required value="<?= h($valoresProducto['precio']) ?>">
                    </div>
                    <div class="campo">
                        <label for="p-categoria">Categoría</label>
                        <select id="p-categoria" name="categoria_id" required>
                            <option value="">Selecciona</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= (int) $categoria['id'] ?>"
                                    <?= (int) $valoresProducto['categoria_id'] === (int) $categoria['id'] ? ' selected' : '' ?>
                                    <?= !esVerdadero($categoria['activo']) ? ' disabled' : '' ?>>
                                    <?= h((string) $categoria['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="campo-doble">
                    <div class="campo">
                        <label for="p-stock">Stock actual</label>
                        <input id="p-stock" name="stock_actual" type="number" min="0" step="1" required value="<?= h($valoresProducto['stock_actual']) ?>">
                    </div>
                    <div class="campo">
                        <label for="p-minimo">Stock mínimo</label>
                        <input id="p-minimo" name="stock_minimo" type="number" min="0" step="1" required value="<?= h($valoresProducto['stock_minimo']) ?>">
                    </div>
                </div>
                <div class="campo">
                    <label for="p-tiempo">Tiempo de preparación (min, opcional)</label>
                    <input id="p-tiempo" name="tiempo_preparacion_min" type="number" min="1" max="240" step="1" value="<?= h($valoresProducto['tiempo_preparacion_min']) ?>">
                </div>
                <label class="check"><input type="checkbox" name="activo" value="1"<?= $valoresProducto['activo'] ? ' checked' : '' ?>> Producto activo</label>
                <label class="check"><input type="checkbox" name="destacado" value="1"<?= $valoresProducto['destacado'] ? ' checked' : '' ?>> Mostrar como destacado</label>
                <button class="btn btn-primario btn-bloque" type="submit"><?= $editarProducto !== null ? 'Guardar cambios' : 'Crear producto' ?></button>
                <?php if ($editarProducto !== null): ?>
                    <a class="btn btn-fantasma btn-bloque" href="<?= h(url('admin.php?tab=productos')) ?>">Cancelar edición</a>
                <?php endif; ?>
            </form>
        </aside>
    </div>

<?php elseif ($tab === 'categorias'): ?>
    <div class="layout-admin">
        <section class="panel">
            <h2>Categorías (<?= count($categorias) ?>)</h2>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead><tr><th>Nombre</th><th>Descripción</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($categorias as $categoria): ?>
                            <tr>
                                <td><strong><?= h((string) $categoria['nombre']) ?></strong></td>
                                <td><?= h((string) ($categoria['descripcion'] ?? '')) ?></td>
                                <td><span class="pastilla <?= esVerdadero($categoria['activo']) ? 'pastilla-verde' : 'pastilla-gris' ?>"><?= esVerdadero($categoria['activo']) ? 'Activa' : 'Inactiva' ?></span></td>
                                <td><a class="btn btn-pequeno btn-secundario" href="<?= h(url('admin.php?tab=categorias&editar=' . (int) $categoria['id'])) ?>">Editar</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="panel">
            <h2><?= $editarCategoria !== null ? 'Editar categoría' : 'Nueva categoría' ?></h2>
            <form method="post" action="<?= h(url('admin.php')) ?>" class="formulario">
                <?= campoCsrf() ?>
                <input type="hidden" name="accion" value="categoria_guardar">
                <input type="hidden" name="tab" value="categorias">
                <input type="hidden" name="id" value="<?= (int) ($editarCategoria['id'] ?? 0) ?>">
                <div class="campo">
                    <label for="c-nombre">Nombre</label>
                    <input id="c-nombre" name="nombre" type="text" maxlength="100" required
                           value="<?= h((string) ($editarCategoria['nombre'] ?? '')) ?>">
                </div>
                <div class="campo">
                    <label for="c-descripcion">Descripción</label>
                    <textarea id="c-descripcion" name="descripcion" rows="3" maxlength="500"><?= h((string) ($editarCategoria['descripcion'] ?? '')) ?></textarea>
                </div>
                <label class="check">
                    <input type="checkbox" name="activo" value="1"<?= $editarCategoria === null || esVerdadero($editarCategoria['activo']) ? ' checked' : '' ?>>
                    Categoría activa
                </label>
                <button class="btn btn-primario btn-bloque" type="submit"><?= $editarCategoria !== null ? 'Guardar cambios' : 'Crear categoría' ?></button>
                <?php if ($editarCategoria !== null): ?>
                    <a class="btn btn-fantasma btn-bloque" href="<?= h(url('admin.php?tab=categorias')) ?>">Cancelar edición</a>
                <?php endif; ?>
            </form>
        </aside>
    </div>

<?php elseif ($tab === 'configuracion'): ?>
    <div class="layout-admin">
        <section class="panel">
            <h2>Configuración de la tienda</h2>
            <form method="post" action="<?= h(url('admin.php')) ?>" class="formulario">
                <?= campoCsrf() ?>
                <input type="hidden" name="accion" value="config_guardar">
                <input type="hidden" name="tab" value="configuracion">
                <div class="campo-doble">
                    <div class="campo">
                        <label for="cfg-nombre">Nombre de la tienda</label>
                        <input id="cfg-nombre" name="nombre_tienda" type="text" maxlength="100"
                               value="<?= h($config['nombre_tienda'] ?? 'CoffeeAndtea') ?>">
                    </div>
                    <div class="campo">
                        <label for="cfg-tiempo">Tiempo de respaldo (min)</label>
                        <input id="cfg-tiempo" name="tiempo_estimado_pedidos_min" type="number" min="1" max="240"
                               value="<?= h($config['tiempo_estimado_pedidos_min'] ?? '20') ?>">
                        <small>Solo se usa si un producto no tiene tiempo configurado.</small>
                    </div>
                </div>
                <div class="campo-doble">
                    <div class="campo">
                        <label for="cfg-capacidad">Capacidad simultánea</label>
                        <input id="cfg-capacidad" name="capacidad_preparacion_simultanea" type="number" min="1" max="10"
                               value="<?= h($config['capacidad_preparacion_simultanea'] ?? '2') ?>">
                        <small>Productos que la cocina puede preparar al mismo tiempo.</small>
                    </div>
                    <div class="campo">
                        <label for="cfg-buffer">Buffer de seguridad (%)</label>
                        <input id="cfg-buffer" name="buffer_preparacion_porcentaje" type="number" min="0" max="100"
                               value="<?= h($config['buffer_preparacion_porcentaje'] ?? '15') ?>">
                        <small>Margen para montaje, limpieza e imprevistos.</small>
                    </div>
                </div>
                <div class="campo">
                    <label for="cfg-direccion">Dirección de la tienda</label>
                    <input id="cfg-direccion" name="direccion_tienda" type="text" maxlength="255"
                           value="<?= h($config['direccion_tienda'] ?? '') ?>">
                </div>
                <div class="campo-doble">
                    <div class="campo">
                        <label for="cfg-telefono">Teléfono</label>
                        <input id="cfg-telefono" name="telefono_tienda" type="text" maxlength="30"
                               value="<?= h($config['telefono_tienda'] ?? '') ?>">
                    </div>
                    <div class="campo">
                        <label for="cfg-correo">Correo de contacto</label>
                        <input id="cfg-correo" name="correo_contacto" type="email" maxlength="150"
                               value="<?= h($config['correo_contacto'] ?? env('APP_ADMIN_EMAIL', '')) ?>">
                    </div>
                </div>
                <div class="campo">
                    <label for="cfg-horario">Horario</label>
                    <input id="cfg-horario" name="horario_tienda" type="text" maxlength="150"
                           placeholder="Lun a sáb, 8:00 a 20:00"
                           value="<?= h($config['horario_tienda'] ?? '') ?>">
                </div>
                <div class="campo">
                    <label for="cfg-video">Video de presentación (YouTube o Google Drive)</label>
                    <input id="cfg-video" name="video_presentacion_url" type="url" maxlength="500"
                           placeholder="https://youtu.be/..."
                           value="<?= h($config['video_presentacion_url'] ?? '') ?>">
                    <small>Si se deja vacío, la página de video muestra “Video próximamente”. En Drive, comparte el archivo como “Cualquier persona con el enlace”.</small>
                </div>
                <label class="check">
                    <input type="checkbox" name="pagina_abierta" value="1"
                        <?= strtolower($config['pagina_abierta'] ?? 'true') !== 'false' ? ' checked' : '' ?>>
                    Recibir pedidos web (si se desactiva, la tienda queda en modo consulta)
                </label>
                <button class="btn btn-primario" type="submit">Guardar configuración</button>
            </form>
        </section>

        <aside class="panel">
            <h2>Correo saliente</h2>
            <p class="texto-suave">
                Proveedor: <strong><?= h((string) env('SMTP_HOST', 'no configurado')) ?>:<?= h((string) env('SMTP_PORT', '')) ?></strong><br>
                Remitente: <strong><?= h((string) env('SMTP_FROM', 'no configurado')) ?></strong><br>
                Modo: <strong><?= h((string) env('MAIL_DRIVER', 'smtp')) ?></strong>
            </p>
            <p class="texto-suave">Las credenciales viven en <code>.env</code>, no en la base de datos.</p>
            <form method="post" action="<?= h(url('admin.php')) ?>" class="formulario">
                <?= campoCsrf() ?>
                <input type="hidden" name="accion" value="probar_correo">
                <input type="hidden" name="tab" value="configuracion">
                <div class="campo">
                    <label for="destino-correo">Enviar prueba a</label>
                    <input id="destino-correo" name="destino" type="email" value="<?= h($usuario['correo']) ?>" required>
                </div>
                <button class="btn btn-secundario btn-bloque" type="submit">Enviar correo de prueba</button>
            </form>
            <hr>
            <h3>Cuenta administradora</h3>
            <p class="texto-suave"><?= h($usuario['nombre']) ?><br><?= h($usuario['correo']) ?></p>
            <a class="btn btn-fantasma btn-bloque" href="<?= h(url('cambiar-password.php')) ?>">Cambiar mi contraseña</a>
        </aside>
    </div>

<?php elseif ($tab === 'auditoria'): ?>
    <section class="panel">
        <div class="panel-head">
            <h2>Auditoría (últimos 200 eventos)</h2>
            <p class="texto-suave">Incluye inicios de sesión, pagos, cancelaciones, cambios de stock y configuración.</p>
        </div>
        <?php if ($auditoria === []): ?>
            <div class="estado-vacio compacto"><p>No hay eventos registrados.</p></div>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla tabla-auditoria">
                    <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Tabla</th><th>Registro</th><th>Detalle</th><th>IP</th></tr></thead>
                    <tbody>
                        <?php foreach ($auditoria as $evento): ?>
                            <tr>
                                <td><?= h(fechaBonita($evento['creado_en'])) ?></td>
                                <td><?= h((string) ($evento['usuario'] ?? 'Sistema')) ?></td>
                                <td><span class="pastilla pastilla-ambar"><?= h((string) $evento['accion']) ?></span></td>
                                <td><?= h((string) $evento['tabla']) ?></td>
                                <td><?= $evento['registro_id'] !== null ? (int) $evento['registro_id'] : '—' ?></td>
                                <td><code class="detalle-json"><?= h((string) ($evento['detalles'] ?? '')) ?></code></td>
                                <td><?= h((string) ($evento['ip'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php pieHtml(); ?>
