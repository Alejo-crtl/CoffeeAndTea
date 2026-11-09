<?php
/**
 * Reglas de negocio de pedidos, pagos, stock y caja.
 *
 * Decisiones clave:
 *  - El stock se descuenta únicamente al confirmar el pago.
 *  - El detalle guarda el precio del momento de la venta.
 *  - La caja registra solo efectivo; la tarjeta se apoya en el datáfono externo.
 */
declare(strict_types=1);

/** Error de regla de negocio con mensaje apto para el usuario. */
class ReglaNegocioException extends RuntimeException
{
}

/** Porcentaje de reembolso cuando el pedido ya entró a preparación. */
const REEMBOLSO_PARCIAL_PORCENTAJE = 50;

/**
 * Porcentaje que se devuelve según el estado del pedido.
 * Antes de preparación se devuelve el 100 %; en preparación o listo, el 50 %.
 */
function porcentajeReembolsoCancelacion(string $estado): int
{
    return in_array($estado, ['EN_PREPARACION', 'LISTO'], true)
        ? REEMBOLSO_PARCIAL_PORCENTAJE
        : 100;
}

/** Calcula el monto a reembolsar según el estado del pedido. */
function montoReembolsoCancelacion(float $montoPagado, string $estado): float
{
    return round($montoPagado * porcentajeReembolsoCancelacion($estado) / 100, 2);
}

/** Lee un valor de configuración del sistema. */
function configSistema(PDO $conn, string $clave, ?string $porDefecto = null): ?string
{
    try {
        $stmt = $conn->prepare('SELECT valor FROM config_sistema WHERE clave = :clave LIMIT 1');
        $stmt->execute([':clave' => $clave]);
        $valor = $stmt->fetchColumn();
        return $valor === false ? $porDefecto : (string) $valor;
    } catch (Throwable $e) {
        error_log('CoffeeAndtea - configuración: ' . $e->getMessage());
        return $porDefecto;
    }
}

/** Guarda o actualiza un valor de configuración. */
function guardarConfigSistema(PDO $conn, string $clave, string $valor, ?string $descripcion = null): void
{
    $stmt = $conn->prepare(
        'INSERT INTO config_sistema (clave, valor, descripcion)
         VALUES (:clave, :valor, :descripcion)
         ON CONFLICT (clave) DO UPDATE SET valor = EXCLUDED.valor,
             descripcion = COALESCE(EXCLUDED.descripcion, config_sistema.descripcion)'
    );
    $stmt->execute([':clave' => $clave, ':valor' => $valor, ':descripcion' => $descripcion]);
}

/* ------------------------------------------------------------------ */
/* Carrito                                                             */
/* ------------------------------------------------------------------ */

/** Devuelve el carrito de la sesión como [id => cantidad]. */
function carritoCrudo(): array
{
    $carrito = $_SESSION['carrito'] ?? [];
    if (!is_array($carrito)) {
        return [];
    }
    $limpio = [];
    foreach ($carrito as $id => $cantidad) {
        $id = (int) $id;
        $cantidad = (int) $cantidad;
        if ($id > 0 && $cantidad > 0) {
            $limpio[$id] = min($cantidad, 99);
        }
    }
    return $limpio;
}

/** Guarda el carrito en sesión. */
function carritoGuardar(array $carrito): void
{
    $_SESSION['carrito'] = $carrito;
}

/** Agrega unidades de un producto validando que exista y esté activo. */
function carritoAgregar(PDO $conn, int $productoId, int $cantidad): void
{
    if ($productoId < 1 || $cantidad < 1) {
        throw new ReglaNegocioException('Cantidad no válida.');
    }
    $stmt = $conn->prepare('SELECT stock_actual FROM producto WHERE id = :id AND activo = TRUE LIMIT 1');
    $stmt->execute([':id' => $productoId]);
    $stock = $stmt->fetchColumn();
    if ($stock === false) {
        throw new ReglaNegocioException('El producto ya no está disponible.');
    }

    $carrito = carritoCrudo();
    $nueva = ($carrito[$productoId] ?? 0) + $cantidad;
    if ($nueva > (int) $stock) {
        throw new ReglaNegocioException('No hay suficientes unidades disponibles de ese producto.');
    }
    $carrito[$productoId] = min($nueva, 99);
    carritoGuardar($carrito);
}

/** Fija la cantidad de un producto; 0 lo elimina. */
function carritoFijar(PDO $conn, int $productoId, int $cantidad): void
{
    $carrito = carritoCrudo();
    if ($cantidad <= 0) {
        unset($carrito[$productoId]);
        carritoGuardar($carrito);
        return;
    }
    $stmt = $conn->prepare('SELECT stock_actual FROM producto WHERE id = :id AND activo = TRUE LIMIT 1');
    $stmt->execute([':id' => $productoId]);
    $stock = $stmt->fetchColumn();
    if ($stock === false) {
        throw new ReglaNegocioException('El producto ya no está disponible.');
    }
    if ($cantidad > (int) $stock) {
        throw new ReglaNegocioException('No hay suficientes unidades disponibles de ese producto.');
    }
    $carrito[$productoId] = min($cantidad, 99);
    carritoGuardar($carrito);
}

/** Elimina un producto del carrito. */
function carritoEliminar(int $productoId): void
{
    $carrito = carritoCrudo();
    unset($carrito[$productoId]);
    carritoGuardar($carrito);
}

/** Vacía el carrito. */
function carritoVaciar(): void
{
    unset($_SESSION['carrito']);
}

/**
 * Devuelve las líneas del carrito con datos actuales del producto.
 *
 * @return array{items:array<int,array<string,mixed>>,subtotal:float,unidades:int,ajustes:array<int,string>}
 */
function carritoDetallado(PDO $conn): array
{
    $carrito = carritoCrudo();
    if (empty($carrito)) {
        return ['items' => [], 'subtotal' => 0.0, 'unidades' => 0, 'ajustes' => []];
    }

    $ids = array_keys($carrito);
    $marcadores = [];
    $parametros = [];
    foreach ($ids as $i => $id) {
        $marcadores[] = ':p' . $i;
        $parametros[':p' . $i] = $id;
    }

    $sql = 'SELECT p.id, p.nombre, p.descripcion, p.precio, p.stock_actual, p.tiempo_preparacion_min,
                   p.unidades_medida, c.nombre AS categoria
            FROM producto p
            INNER JOIN categoria c ON c.id = p.categoria_id
            WHERE p.id IN (' . implode(',', $marcadores) . ') AND p.activo = TRUE AND c.activo = TRUE
            ORDER BY c.nombre, p.nombre';
    $stmt = $conn->prepare($sql);
    $stmt->execute($parametros);
    $productos = $stmt->fetchAll();

    $items = [];
    $subtotal = 0.0;
    $unidades = 0;
    $ajustes = [];
    $encontrados = [];

    foreach ($productos as $producto) {
        $id = (int) $producto['id'];
        $encontrados[$id] = true;
        $cantidad = (int) ($carrito[$id] ?? 0);
        $stock = (int) $producto['stock_actual'];
        if ($stock < 1) {
            $ajustes[] = $producto['nombre'] . ' se agotó y se retiró del carrito.';
            unset($carrito[$id]);
            continue;
        }
        if ($cantidad > $stock) {
            $ajustes[] = 'Solo quedan ' . $stock . ' unidades de ' . $producto['nombre'] . '.';
            $cantidad = $stock;
            $carrito[$id] = $cantidad;
        }
        $precio = (float) $producto['precio'];
        $linea = [
            'producto_id'       => $id,
            'nombre'            => (string) $producto['nombre'],
            'descripcion'       => (string) ($producto['descripcion'] ?? ''),
            'categoria'         => (string) $producto['categoria'],
            'precio'            => $precio,
            'cantidad'          => $cantidad,
            'stock'             => $stock,
            'subtotal'          => round($precio * $cantidad, 2),
            'tiempo_preparacion' => $producto['tiempo_preparacion_min'] !== null
                ? (int) $producto['tiempo_preparacion_min']
                : null,
        ];
        $items[] = $linea;
        $subtotal += $linea['subtotal'];
        $unidades += $cantidad;
    }

    foreach ($carrito as $id => $cantidad) {
        if (!isset($encontrados[$id])) {
            unset($carrito[$id]);
            $ajustes[] = 'Un producto ya no está disponible y se retiró del carrito.';
        }
    }
    carritoGuardar($carrito);

    return [
        'items'    => $items,
        'subtotal' => round($subtotal, 2),
        'unidades' => $unidades,
        'ajustes'  => $ajustes,
    ];
}

/* ------------------------------------------------------------------ */
/* Pedidos                                                             */
/* ------------------------------------------------------------------ */

/** Genera un código público único, legible y sin caracteres ambiguos. */
function generarCodigoPedido(PDO $conn): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($intento = 0; $intento < 10; $intento++) {
        $codigo = 'CAF-';
        for ($i = 0; $i < 6; $i++) {
            $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        $stmt = $conn->prepare('SELECT 1 FROM pedido WHERE codigo = :codigo LIMIT 1');
        $stmt->execute([':codigo' => $codigo]);
        if ($stmt->fetchColumn() === false) {
            return $codigo;
        }
    }
    throw new ReglaNegocioException('No se pudo generar el código del pedido. Intenta de nuevo.');
}

/** Calcula el tiempo de preparación a partir del trabajo y la cola. */
function calcularTiempoPreparacion(
    array $items,
    int $trabajoCola,
    int $capacidad,
    int $porcentajeBuffer,
    int $tiempoRespaldo
): int {
    $capacidad = max(1, $capacidad);
    $porcentajeBuffer = max(0, min(100, $porcentajeBuffer));
    $tiempoRespaldo = max(1, $tiempoRespaldo);

    $trabajo = 0;
    foreach ($items as $item) {
        $cantidad = max(1, (int) ($item['cantidad'] ?? 1));
        $tiempo = (int) ($item['tiempo_preparacion'] ?? 0);
        if ($tiempo < 1) {
            $tiempo = $tiempoRespaldo;
        }
        $trabajo += $cantidad * $tiempo;
    }

    $base = (int) ceil($trabajo / $capacidad);
    $buffer = (int) ceil($base * $porcentajeBuffer / 100);
    $esperaCola = (int) ceil(max(0, $trabajoCola) / $capacidad);

    return max(5, $base + $buffer + $esperaCola);
}

/** Suma el trabajo pendiente de los pedidos que aún están en cocina. */
function trabajoPedidosActivos(PDO $conn, int $tiempoRespaldo): int
{
    $tiempoRespaldo = max(1, $tiempoRespaldo);
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(d.cantidad * COALESCE(p.tiempo_preparacion_min, :respaldo)), 0)
         FROM pedido o
         INNER JOIN detallepedido d ON d.id_pedido = o.id
         INNER JOIN producto p ON p.id = d.id_producto
         WHERE o.estado IN ('PAGADO', 'EN_PREPARACION')"
    );
    $stmt->execute([':respaldo' => $tiempoRespaldo]);
    return (int) $stmt->fetchColumn();
}

/** Calcula el tiempo estimado de preparación en minutos. */
function calcularTiempoEstimado(PDO $conn, array $items): int
{
    $tiempoRespaldo = max(1, (int) (configSistema(
        $conn,
        'tiempo_estimado_pedidos_min',
        (string) env('APP_TIEMPO_ESTIMADO_MIN', '20')
    ) ?? 20));
    $capacidad = max(1, (int) (configSistema($conn, 'capacidad_preparacion_simultanea', '2') ?? 2));
    $buffer = max(0, min(100, (int) (configSistema($conn, 'buffer_preparacion_porcentaje', '15') ?? 15)));
    $trabajoCola = trabajoPedidosActivos($conn, $tiempoRespaldo);

    return calcularTiempoPreparacion($items, $trabajoCola, $capacidad, $buffer, $tiempoRespaldo);
}

/** Devuelve el rango de tiempos configurados en los productos activos. */
function rangoTiempoPreparacion(PDO $conn): array
{
    $tiempoRespaldo = max(1, (int) (configSistema(
        $conn,
        'tiempo_estimado_pedidos_min',
        (string) env('APP_TIEMPO_ESTIMADO_MIN', '20')
    ) ?? 20));

    $stmt = $conn->query(
        'SELECT MIN(tiempo_preparacion_min) AS minimo, MAX(tiempo_preparacion_min) AS maximo
         FROM producto
         WHERE activo = TRUE AND tiempo_preparacion_min IS NOT NULL'
    );
    $fila = $stmt->fetch();

    if ($fila === false || $fila['minimo'] === null || $fila['maximo'] === null) {
        return ['min' => $tiempoRespaldo, 'max' => $tiempoRespaldo];
    }

    return [
        'min' => max(5, (int) $fila['minimo']),
        'max' => max(5, (int) $fila['maximo']),
    ];
}

/**
 * Crea un pedido web con precio histórico.
 *
 * @param array{nombre:string,correo:string,telefono:string,observaciones:string,consentimiento:bool} $datos
 * @return array{id:int,codigo:string,token:string,tiempo_estimado:int,total:float}
 */
function crearPedido(PDO $conn, array $datos, ?int $usuarioId = null): array
{
    $nombre = trim($datos['nombre'] ?? '');
    $correo = mb_strtolower(trim($datos['correo'] ?? ''), 'UTF-8');
    $telefono = trim($datos['telefono'] ?? '');
    $observaciones = trim($datos['observaciones'] ?? '');
    $consentimiento = !empty($datos['consentimiento']);

    $errores = [];
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 150) {
        $errores[] = 'Escribe tu nombre completo (entre 3 y 150 caracteres).';
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150) {
        $errores[] = 'Escribe un correo electrónico válido.';
    }
    if ($telefono !== '' && mb_strlen($telefono) > 30) {
        $errores[] = 'El teléfono no puede superar 30 caracteres.';
    }
    if (mb_strlen($observaciones) > 500) {
        $errores[] = 'Las observaciones no pueden superar 500 caracteres.';
    }
    if (!$consentimiento) {
        $errores[] = 'Debes autorizar el tratamiento de tus datos para continuar.';
    }
    if (empty($datos['acepta_cancelacion'])) {
        $errores[] = 'Debes aceptar la política de cancelación para continuar.';
    }
    if ($errores !== []) {
        throw new ReglaNegocioException(implode(' ', $errores));
    }

    $carrito = carritoDetallado($conn);
    if ($carrito['items'] === []) {
        throw new ReglaNegocioException('Tu carrito está vacío.');
    }

    $tiempo = calcularTiempoEstimado($conn, $carrito['items']);
    $token = bin2hex(random_bytes(24));
    $codigo = generarCodigoPedido($conn);

    $conn->beginTransaction();
    try {
        // Cliente: se reutiliza por correo; nunca se duplica.
        $stmt = $conn->prepare('SELECT id, activo, usuario_id FROM cliente WHERE lower(correo) = :correo FOR UPDATE');
        $stmt->execute([':correo' => $correo]);
        $cliente = $stmt->fetch();

        if ($cliente === false) {
            $stmt = $conn->prepare(
                'INSERT INTO cliente (nombre, correo, telefono, consentimiento, activo)
                 VALUES (:nombre, :correo, :telefono, :consentimiento, TRUE)
                 RETURNING id'
            );
            $stmt->execute([
                ':nombre'         => $nombre,
                ':correo'         => $correo,
                ':telefono'       => $telefono !== '' ? $telefono : null,
                ':consentimiento' => boolSql($consentimiento),
            ]);
            $clienteId = (int) $stmt->fetchColumn();
        } else {
            if (!esVerdadero($cliente['activo'])) {
                throw new ReglaNegocioException('No podemos tomar el pedido con este correo. Contacta a la tienda.');
            }
            $clienteId = (int) $cliente['id'];
            $stmt = $conn->prepare(
                'UPDATE cliente
                 SET nombre = :nombre, telefono = :telefono, consentimiento = :consentimiento
                 WHERE id = :id'
            );
            $stmt->execute([
                ':nombre'         => $nombre,
                ':telefono'       => $telefono !== '' ? $telefono : null,
                ':consentimiento' => boolSql($consentimiento),
                ':id'             => $clienteId,
            ]);

            if ($cliente['usuario_id'] === null && $usuarioId !== null) {
                $stmt = $conn->prepare(
                    'UPDATE cliente SET usuario_id = :usuario_id, reclamado_en = CURRENT_TIMESTAMP
                     WHERE id = :id AND usuario_id IS NULL'
                );
                $stmt->execute([':usuario_id' => $usuarioId, ':id' => $clienteId]);
            }
        }

        $stmt = $conn->prepare(
            'INSERT INTO pedido (codigo, token_publico, cliente_id, estado, total, observaciones, canal, tiempo_estimado_min)
             VALUES (:codigo, :token, :cliente_id, \'PENDIENTE\', 0, :observaciones, \'WEB\', :tiempo)
             RETURNING id'
        );
        $stmt->execute([
            ':codigo'        => $codigo,
            ':token'         => $token,
            ':cliente_id'    => $clienteId,
            ':observaciones' => $observaciones !== '' ? $observaciones : null,
            ':tiempo'        => $tiempo,
        ]);
        $pedidoId = (int) $stmt->fetchColumn();

        $stmtDetalle = $conn->prepare(
            'INSERT INTO detallepedido (id_pedido, id_producto, cantidad, precio_unitario)
             VALUES (:id_pedido, :id_producto, :cantidad, :precio_unitario)'
        );
        foreach ($carrito['items'] as $item) {
            $stmtDetalle->execute([
                ':id_pedido'      => $pedidoId,
                ':id_producto'    => (int) $item['producto_id'],
                ':cantidad'       => (int) $item['cantidad'],
                ':precio_unitario' => number_format((float) $item['precio'], 2, '.', ''),
            ]);
        }

        $stmt = $conn->prepare(
            'INSERT INTO pedido_historial_estado (pedido_id, estado_anterior, estado_nuevo, cambiado_por, motivo)
             VALUES (:pedido_id, NULL, \'PENDIENTE\', :usuario_id, :motivo)'
        );
        $stmt->execute([
            ':pedido_id'  => $pedidoId,
            ':usuario_id' => $usuarioId,
            ':motivo'     => 'Pedido creado desde la web',
        ]);

        $stmt = $conn->prepare('SELECT total FROM pedido WHERE id = :id');
        $stmt->execute([':id' => $pedidoId]);
        $total = (float) $stmt->fetchColumn();

        registrarAuditoria($conn, $usuarioId, 'PEDIDO_CREADO', 'pedido', $pedidoId, [
            'codigo'   => $codigo,
            'total'    => $total,
            'unidades' => $carrito['unidades'],
        ]);

        $conn->commit();
        carritoVaciar();

        return [
            'id'               => $pedidoId,
            'codigo'           => $codigo,
            'token'            => $token,
            'tiempo_estimado'  => $tiempo,
            'total'            => $total,
        ];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if ($e instanceof ReglaNegocioException) {
            throw $e;
        }
        error_log('CoffeeAndtea - crear pedido: ' . $e->getMessage());
        throw new ReglaNegocioException('No se pudo crear el pedido. Inténtalo de nuevo.');
    }
}

/** Busca un pedido por código y token público. */
function obtenerPedidoPublico(PDO $conn, string $codigo, string $token): ?array
{
    $stmt = $conn->prepare(
        'SELECT p.*, c.nombre AS cliente_nombre, c.correo AS cliente_correo, c.telefono AS cliente_telefono
         FROM pedido p
         INNER JOIN cliente c ON c.id = p.cliente_id
         WHERE p.codigo = :codigo AND p.token_publico = :token
         LIMIT 1'
    );
    $stmt->execute([':codigo' => $codigo, ':token' => $token]);
    $pedido = $stmt->fetch();
    return $pedido === false ? null : $pedido;
}

/** Devuelve un pedido con sus detalles, pago e historial. */
function obtenerPedidoCompleto(PDO $conn, int $pedidoId): ?array
{
    $stmt = $conn->prepare(
        'SELECT p.*, c.nombre AS cliente_nombre, c.correo AS cliente_correo, c.telefono AS cliente_telefono
         FROM pedido p
         INNER JOIN cliente c ON c.id = p.cliente_id
         WHERE p.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $pedidoId]);
    $pedido = $stmt->fetch();
    if ($pedido === false) {
        return null;
    }

    $stmt = $conn->prepare(
        'SELECT d.*, pr.nombre AS producto, pr.unidades_medida
         FROM detallepedido d
         INNER JOIN producto pr ON pr.id = d.id_producto
         WHERE d.id_pedido = :id
         ORDER BY d.id'
    );
    $stmt->execute([':id' => $pedidoId]);
    $pedido['detalles'] = $stmt->fetchAll();

    $stmt = $conn->prepare('SELECT * FROM pago WHERE pedido_id = :id ORDER BY id DESC');
    $stmt->execute([':id' => $pedidoId]);
    $pedido['pagos'] = $stmt->fetchAll();

    $stmt = $conn->prepare(
        'SELECT h.*, u.nombre AS usuario
         FROM pedido_historial_estado h
         LEFT JOIN usuario u ON u.id = h.cambiado_por
         WHERE h.pedido_id = :id
         ORDER BY h.id'
    );
    $stmt->execute([':id' => $pedidoId]);
    $pedido['historial'] = $stmt->fetchAll();

    return $pedido;
}

/** Pedidos de un cliente vinculado a una cuenta. */
function pedidosDeUsuario(PDO $conn, int $usuarioId): array
{
    $stmt = $conn->prepare(
        'SELECT p.*, c.nombre AS cliente_nombre
         FROM pedido p
         INNER JOIN cliente c ON c.id = p.cliente_id
         WHERE c.usuario_id = :usuario_id
         ORDER BY p.id DESC'
    );
    $stmt->execute([':usuario_id' => $usuarioId]);
    return $stmt->fetchAll();
}

/** Lista de pedidos para gestión, con filtros simples. */
function listarPedidosGestion(PDO $conn, ?string $estado = null, string $buscar = ''): array
{
    $sql = 'SELECT p.*, c.nombre AS cliente_nombre, c.correo AS cliente_correo, c.telefono AS cliente_telefono,
                   (SELECT COUNT(*) FROM detallepedido d WHERE d.id_pedido = p.id) AS lineas
            FROM pedido p
            INNER JOIN cliente c ON c.id = p.cliente_id
            WHERE 1 = 1';
    $params = [];
    if ($estado !== null && $estado !== '' && $estado !== 'TODOS') {
        $sql .= ' AND p.estado = :estado';
        $params[':estado'] = $estado;
    }
    if ($buscar !== '') {
        $sql .= ' AND (p.codigo ILIKE :buscar OR c.nombre ILIKE :buscar OR c.correo ILIKE :buscar)';
        $params[':buscar'] = '%' . $buscar . '%';
    }
    $sql .= ' ORDER BY CASE p.estado
                WHEN \'PENDIENTE\' THEN 1 WHEN \'PAGADO\' THEN 2 WHEN \'EN_PREPARACION\' THEN 3
                WHEN \'LISTO\' THEN 4 ELSE 5 END, p.id DESC LIMIT 200';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Transiciones permitidas sin pasar por caja. */
function transicionesPermitidas(string $actual): array
{
    return [
        'PAGADO'         => ['EN_PREPARACION'],
        'EN_PREPARACION' => ['LISTO'],
        'LISTO'          => ['ENTREGADO'],
    ][$actual] ?? [];
}

/** Avanza el estado de un pedido ya pagado. */
function avanzarEstadoPedido(PDO $conn, int $pedidoId, string $nuevoEstado, int $usuarioId, ?string $motivo = null): void
{
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare('SELECT estado, codigo FROM pedido WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $pedidoId]);
        $pedido = $stmt->fetch();
        if ($pedido === false) {
            throw new ReglaNegocioException('El pedido no existe.');
        }
        $actual = (string) $pedido['estado'];
        if ($nuevoEstado === 'PAGADO' || $nuevoEstado === 'CANCELADO') {
            throw new ReglaNegocioException('Ese cambio debe hacerse desde caja o cancelación.');
        }
        if (!in_array($nuevoEstado, transicionesPermitidas($actual), true)) {
            throw new ReglaNegocioException('No se puede pasar de ' . etiquetaEstado($actual) . ' a ' . etiquetaEstado($nuevoEstado) . '.');
        }

        $stmt = $conn->prepare('UPDATE pedido SET estado = :estado WHERE id = :id');
        $stmt->execute([':estado' => $nuevoEstado, ':id' => $pedidoId]);

        $stmt = $conn->prepare(
            'INSERT INTO pedido_historial_estado (pedido_id, estado_anterior, estado_nuevo, cambiado_por, motivo)
             VALUES (:pedido_id, :anterior, :nuevo, :usuario_id, :motivo)'
        );
        $stmt->execute([
            ':pedido_id'  => $pedidoId,
            ':anterior'   => $actual,
            ':nuevo'      => $nuevoEstado,
            ':usuario_id' => $usuarioId,
            ':motivo'     => $motivo,
        ]);

        registrarAuditoria($conn, $usuarioId, 'PEDIDO_ESTADO', 'pedido', $pedidoId, [
            'codigo' => $pedido['codigo'],
            'de'     => $actual,
            'a'      => $nuevoEstado,
            'motivo' => $motivo,
        ]);
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if ($e instanceof ReglaNegocioException) {
            throw $e;
        }
        error_log('CoffeeAndtea - avanzar estado: ' . $e->getMessage());
        throw new ReglaNegocioException('No se pudo actualizar el estado del pedido.');
    }
}

/* ------------------------------------------------------------------ */
/* Caja                                                                */
/* ------------------------------------------------------------------ */

/** Devuelve la sesión de caja abierta, si existe. */
function cajaAbierta(PDO $conn): ?array
{
    $stmt = $conn->query("SELECT cs.*, u.nombre AS abierto_por_nombre
                          FROM caja_sesion cs
                          INNER JOIN usuario u ON u.id = cs.abierto_por
                          WHERE cs.estado = 'ABIERTA'
                          ORDER BY cs.id DESC LIMIT 1");
    $fila = $stmt->fetch();
    return $fila === false ? null : $fila;
}

/** Abre una caja con base inicial. */
function abrirCaja(PDO $conn, int $usuarioId, float $baseInicial): int
{
    if ($baseInicial < 0) {
        throw new ReglaNegocioException('La base inicial no puede ser negativa.');
    }
    if (cajaAbierta($conn) !== null) {
        throw new ReglaNegocioException('Ya hay una caja abierta. Ciérrala antes de abrir otra.');
    }

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare(
            'INSERT INTO caja_sesion (abierto_por, base_inicial, estado)
             VALUES (:usuario_id, :base, \'ABIERTA\') RETURNING id'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':base'       => number_format($baseInicial, 2, '.', ''),
        ]);
        $id = (int) $stmt->fetchColumn();
        registrarAuditoria($conn, $usuarioId, 'CAJA_ABIERTA', 'caja_sesion', $id, ['base_inicial' => $baseInicial]);
        $conn->commit();
        return $id;
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if ($e instanceof ReglaNegocioException) {
            throw $e;
        }
        error_log('CoffeeAndtea - abrir caja: ' . $e->getMessage());
        throw new ReglaNegocioException('No se pudo abrir la caja. Verifica que no haya otra abierta.');
    }
}

/** Totales de una sesión de caja. */
function resumenCaja(PDO $conn, int $sesionId): array
{
    $stmt = $conn->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN tipo = 'INGRESO' THEN monto ELSE 0 END), 0) AS ingresos,
            COALESCE(SUM(CASE WHEN tipo = 'EGRESO' THEN monto ELSE 0 END), 0) AS egresos,
            COUNT(*) AS movimientos
         FROM movimiento_caja WHERE caja_sesion_id = :id"
    );
    $stmt->execute([':id' => $sesionId]);
    $fila = $stmt->fetch() ?: ['ingresos' => 0, 'egresos' => 0, 'movimientos' => 0];

    $stmt = $conn->prepare('SELECT base_inicial, estado, efectivo_contado, diferencia FROM caja_sesion WHERE id = :id');
    $stmt->execute([':id' => $sesionId]);
    $sesion = $stmt->fetch() ?: ['base_inicial' => 0, 'estado' => 'CERRADA', 'efectivo_contado' => null, 'diferencia' => null];

    $esperado = (float) $sesion['base_inicial'] + (float) $fila['ingresos'] - (float) $fila['egresos'];

    return [
        'base'        => (float) $sesion['base_inicial'],
        'ingresos'    => (float) $fila['ingresos'],
        'egresos'     => (float) $fila['egresos'],
        'esperado'    => round($esperado, 2),
        'contado'     => $sesion['efectivo_contado'] !== null ? (float) $sesion['efectivo_contado'] : null,
        'diferencia'  => $sesion['diferencia'] !== null ? (float) $sesion['diferencia'] : null,
        'movimientos' => (int) $fila['movimientos'],
        'estado'      => (string) $sesion['estado'],
    ];
}

/** Registra un movimiento de caja. */
function registrarMovimientoCaja(
    PDO $conn,
    int $sesionId,
    string $tipo,
    float $monto,
    string $concepto,
    int $usuarioId,
    ?string $relatedType = null,
    ?int $relatedId = null
): int {
    if (!in_array($tipo, ['INGRESO', 'EGRESO'], true)) {
        throw new ReglaNegocioException('Tipo de movimiento de caja no válido.');
    }
    if ($monto <= 0) {
        throw new ReglaNegocioException('El monto debe ser mayor que cero.');
    }
    $stmt = $conn->prepare(
        'INSERT INTO movimiento_caja (caja_sesion_id, tipo, monto, concepto, related_type, related_id, creado_por)
         VALUES (:sesion, :tipo, :monto, :concepto, :related_type, :related_id, :usuario_id)
         RETURNING id'
    );
    $stmt->execute([
        ':sesion'       => $sesionId,
        ':tipo'         => $tipo,
        ':monto'        => number_format($monto, 2, '.', ''),
        ':concepto'     => mb_substr($concepto, 0, 255),
        ':related_type' => $relatedType,
        ':related_id'   => $relatedId,
        ':usuario_id'   => $usuarioId,
    ]);
    return (int) $stmt->fetchColumn();
}

/** Cierra la caja y calcula la diferencia contra el efectivo contado. */
function cerrarCaja(PDO $conn, int $sesionId, int $usuarioId, float $contado): array
{
    if ($contado < 0) {
        throw new ReglaNegocioException('El efectivo contado no puede ser negativo.');
    }
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM caja_sesion WHERE id = :id AND estado = 'ABIERTA' FOR UPDATE");
        $stmt->execute([':id' => $sesionId]);
        $sesion = $stmt->fetch();
        if ($sesion === false) {
            throw new ReglaNegocioException('La caja no está abierta o ya fue cerrada.');
        }

        $resumen = resumenCaja($conn, $sesionId);
        $diferencia = round($contado - $resumen['esperado'], 2);

        $stmt = $conn->prepare(
            "UPDATE caja_sesion
             SET efectivo_contado = :contado, diferencia = :diferencia,
                 cerrado_por = :usuario_id, fecha_cierre = CURRENT_TIMESTAMP, estado = 'CERRADA'
             WHERE id = :id"
        );
        $stmt->execute([
            ':contado'    => number_format($contado, 2, '.', ''),
            ':diferencia' => number_format($diferencia, 2, '.', ''),
            ':usuario_id' => $usuarioId,
            ':id'         => $sesionId,
        ]);

        registrarAuditoria($conn, $usuarioId, 'CAJA_CERRADA', 'caja_sesion', $sesionId, [
            'esperado'   => $resumen['esperado'],
            'contado'    => $contado,
            'diferencia' => $diferencia,
        ]);
        $conn->commit();

        return ['esperado' => $resumen['esperado'], 'contado' => $contado, 'diferencia' => $diferencia];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if ($e instanceof ReglaNegocioException) {
            throw $e;
        }
        error_log('CoffeeAndtea - cerrar caja: ' . $e->getMessage());
        throw new ReglaNegocioException('No se pudo cerrar la caja.');
    }
}

/* ------------------------------------------------------------------ */
/* Pago y cancelación                                                  */
/* ------------------------------------------------------------------ */

/**
 * Confirma el pago en mostrador: descuenta stock, registra pago y caja.
 *
 * @return array{total:float,cambio:float,codigo:string}
 */
function confirmarPago(
    PDO $conn,
    int $pedidoId,
    int $usuarioId,
    string $metodo,
    ?float $recibido = null,
    ?string $referencia = null
): array {
    $metodo = strtoupper(trim($metodo));
    if (!in_array($metodo, ['EFECTIVO', 'TARJETA'], true)) {
        throw new ReglaNegocioException('Selecciona un método de pago válido.');
    }

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare('SELECT * FROM pedido WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $pedidoId]);
        $pedido = $stmt->fetch();
        if ($pedido === false) {
            throw new ReglaNegocioException('El pedido no existe.');
        }
        if ((string) $pedido['estado'] !== 'PENDIENTE') {
            throw new ReglaNegocioException('Solo se pueden cobrar pedidos pendientes de pago.');
        }

        $stmt = $conn->prepare(
            'SELECT d.id_producto, d.cantidad, d.precio_unitario, p.nombre, p.stock_actual
             FROM detallepedido d
             INNER JOIN producto p ON p.id = d.id_producto
             WHERE d.id_pedido = :id
             ORDER BY d.id
             FOR UPDATE OF p'
        );
        $stmt->execute([':id' => $pedidoId]);
        $detalles = $stmt->fetchAll();
        if ($detalles === []) {
            throw new ReglaNegocioException('El pedido no tiene productos.');
        }

        $total = 0.0;
        foreach ($detalles as $detalle) {
            $total += (float) $detalle['cantidad'] * (float) $detalle['precio_unitario'];
            if ((int) $detalle['cantidad'] > (int) $detalle['stock_actual']) {
                throw new ReglaNegocioException('Stock insuficiente de ' . $detalle['nombre'] . '.');
            }
        }
        $total = round($total, 2);
        if (abs($total - (float) $pedido['total']) > 0.005) {
            $stmt = $conn->prepare('UPDATE pedido SET total = :total WHERE id = :id');
            $stmt->execute([':total' => number_format($total, 2, '.', ''), ':id' => $pedidoId]);
        }

        $sesion = null;
        if ($metodo === 'EFECTIVO') {
            $sesion = cajaAbierta($conn);
            if ($sesion === null) {
                throw new ReglaNegocioException('Abre la caja antes de cobrar en efectivo.');
            }
            if ($recibido === null || $recibido <= 0) {
                throw new ReglaNegocioException('Escribe el efectivo recibido.');
            }
            if ($recibido + 0.001 < $total) {
                throw new ReglaNegocioException('El efectivo recibido no alcanza para cubrir el total.');
            }
            $cambio = round($recibido - $total, 2);
        } else {
            $cambio = 0.0;
            $recibido = null;
            if ($referencia !== null && mb_strlen(trim($referencia)) > 100) {
                throw new ReglaNegocioException('La referencia de la tarjeta es demasiado larga.');
            }
        }

        $stmtProducto = $conn->prepare(
            'UPDATE producto SET stock_actual = stock_actual - :cantidad
             WHERE id = :id AND stock_actual >= :cantidad'
        );
        $stmtMovimiento = $conn->prepare(
            'INSERT INTO movimiento_stock (producto_id, tipo, cantidad, referencia, creado_por)
             VALUES (:producto_id, \'VENTA\', :cantidad, :referencia, :usuario_id)'
        );
        foreach ($detalles as $detalle) {
            $cantidad = (int) $detalle['cantidad'];
            $stmtProducto->execute([':cantidad' => $cantidad, ':id' => (int) $detalle['id_producto']]);
            if ($stmtProducto->rowCount() !== 1) {
                throw new ReglaNegocioException('Stock insuficiente de ' . $detalle['nombre'] . '.');
            }
            $stmtMovimiento->execute([
                ':producto_id' => (int) $detalle['id_producto'],
                ':cantidad'    => $cantidad,
                ':referencia'  => (string) $pedido['codigo'],
                ':usuario_id'  => $usuarioId,
            ]);
        }

        $stmt = $conn->prepare(
            'INSERT INTO pago (pedido_id, monto, metodo, referencia, recibido, cambio, estado, creado_por)
             VALUES (:pedido_id, :monto, :metodo, :referencia, :recibido, :cambio, \'CONFIRMADO\', :usuario_id)
             RETURNING id'
        );
        $stmt->execute([
            ':pedido_id'  => $pedidoId,
            ':monto'      => number_format($total, 2, '.', ''),
            ':metodo'     => $metodo,
            ':referencia' => $referencia !== null && trim($referencia) !== '' ? trim($referencia) : null,
            ':recibido'   => $recibido !== null ? number_format($recibido, 2, '.', '') : null,
            ':cambio'     => number_format($cambio, 2, '.', ''),
            ':usuario_id' => $usuarioId,
        ]);
        $pagoId = (int) $stmt->fetchColumn();

        if ($metodo === 'EFECTIVO' && $sesion !== null) {
            registrarMovimientoCaja(
                $conn,
                (int) $sesion['id'],
                'INGRESO',
                $total,
                'Pago pedido ' . $pedido['codigo'],
                $usuarioId,
                'pago',
                $pagoId
            );
        }

        $stmt = $conn->prepare(
            "UPDATE pedido
             SET estado = 'PAGADO', metodo_pago = :metodo, fecha_pago = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute([':metodo' => $metodo, ':id' => $pedidoId]);

        $stmt = $conn->prepare(
            'INSERT INTO pedido_historial_estado (pedido_id, estado_anterior, estado_nuevo, cambiado_por, motivo)
             VALUES (:pedido_id, \'PENDIENTE\', \'PAGADO\', :usuario_id, :motivo)'
        );
        $stmt->execute([
            ':pedido_id'  => $pedidoId,
            ':usuario_id' => $usuarioId,
            ':motivo'     => 'Pago confirmado en caja (' . $metodo . ')',
        ]);

        registrarAuditoria($conn, $usuarioId, 'PAGO_CONFIRMADO', 'pedido', $pedidoId, [
            'codigo'     => $pedido['codigo'],
            'total'      => $total,
            'metodo'     => $metodo,
            'recibido'   => $recibido,
            'cambio'     => $cambio,
            'referencia' => $referencia,
        ]);
        $conn->commit();

        return ['total' => $total, 'cambio' => $cambio, 'codigo' => (string) $pedido['codigo']];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if ($e instanceof ReglaNegocioException) {
            throw $e;
        }
        error_log('CoffeeAndtea - confirmar pago: ' . $e->getMessage());
        throw new ReglaNegocioException('No se pudo confirmar el pago.');
    }
}

/**
 * Cancela un pedido y revierte stock, pago y caja cuando corresponde.
 */
function cancelarPedido(
    PDO $conn,
    int $pedidoId,
    int $usuarioId,
    string $motivo,
    string $metodoReembolso = 'NINGUNO',
    ?string $referenciaReembolso = null
): array {
    $motivo = trim($motivo);
    if (mb_strlen($motivo) < 5) {
        throw new ReglaNegocioException('Escribe el motivo de la cancelación (mínimo 5 caracteres).');
    }
    $metodoReembolso = strtoupper(trim($metodoReembolso));
    if (!in_array($metodoReembolso, ['NINGUNO', 'EFECTIVO', 'TARJETA'], true)) {
        throw new ReglaNegocioException('Método de reembolso no válido.');
    }

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare('SELECT * FROM pedido WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $pedidoId]);
        $pedido = $stmt->fetch();
        if ($pedido === false) {
            throw new ReglaNegocioException('El pedido no existe.');
        }
        $estado = (string) $pedido['estado'];
        if (!in_array($estado, ['PENDIENTE', 'PAGADO', 'EN_PREPARACION', 'LISTO'], true)) {
            throw new ReglaNegocioException('Este pedido ya no se puede cancelar.');
        }

        $stmt = $conn->prepare(
            "SELECT * FROM pago WHERE pedido_id = :id AND estado = 'CONFIRMADO' ORDER BY id DESC LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([':id' => $pedidoId]);
        $pago = $stmt->fetch();
        $estabaPagado = $pago !== false;

        $porcentajeReembolso = $estabaPagado ? porcentajeReembolsoCancelacion($estado) : 0;
        $montoReembolso = $estabaPagado
            ? montoReembolsoCancelacion((float) $pago['monto'], $estado)
            : 0.0;

        if ($estabaPagado && $metodoReembolso === 'NINGUNO') {
            throw new ReglaNegocioException('El pedido está pagado: indica cómo se reembolsará el dinero.');
        }
        if (!$estabaPagado && $metodoReembolso !== 'NINGUNO') {
            throw new ReglaNegocioException('El pedido no tiene pago confirmado; no hay dinero que reembolsar.');
        }

        // Devolver stock solo si el pago lo había descontado.
        if ($estabaPagado) {
            $stmt = $conn->prepare(
                'SELECT id_producto, cantidad FROM detallepedido WHERE id_pedido = :id ORDER BY id FOR UPDATE'
            );
            $stmt->execute([':id' => $pedidoId]);
            $detalles = $stmt->fetchAll();
            $stmtStock = $conn->prepare('UPDATE producto SET stock_actual = stock_actual + :cantidad WHERE id = :id');
            $stmtMov = $conn->prepare(
                'INSERT INTO movimiento_stock (producto_id, tipo, cantidad, referencia, creado_por)
                 VALUES (:producto_id, \'DEVOLUCION\', :cantidad, :referencia, :usuario_id)'
            );
            foreach ($detalles as $detalle) {
                $stmtStock->execute([':cantidad' => (int) $detalle['cantidad'], ':id' => (int) $detalle['id_producto']]);
                $stmtMov->execute([
                    ':producto_id' => (int) $detalle['id_producto'],
                    ':cantidad'    => (int) $detalle['cantidad'],
                    ':referencia'  => (string) $pedido['codigo'],
                    ':usuario_id'  => $usuarioId,
                ]);
            }

            if ($metodoReembolso === 'EFECTIVO') {
                $sesion = cajaAbierta($conn);
                if ($sesion === null) {
                    throw new ReglaNegocioException('Abre la caja para registrar el reembolso en efectivo.');
                }
                registrarMovimientoCaja(
                    $conn,
                    (int) $sesion['id'],
                    'EGRESO',
                    $montoReembolso,
                    'Reembolso pedido ' . $pedido['codigo']
                        . ($porcentajeReembolso < 100 ? ' (' . $porcentajeReembolso . '%)' : ''),
                    $usuarioId,
                    'pedido',
                    $pedidoId
                );
            }

            $stmt = $conn->prepare(
                "UPDATE pago
                 SET estado = 'REEMBOLSADO', reembolsado_por = :usuario_id,
                     reembolsado_en = CURRENT_TIMESTAMP, motivo_reembolso = :motivo,
                     referencia_reembolso = :referencia, monto_reembolsado = :monto_reembolsado
                 WHERE id = :id"
            );
            $stmt->execute([
                ':usuario_id'        => $usuarioId,
                ':motivo'            => $motivo,
                ':referencia'        => $referenciaReembolso !== null && trim($referenciaReembolso) !== ''
                    ? trim($referenciaReembolso)
                    : null,
                ':monto_reembolsado' => number_format($montoReembolso, 2, '.', ''),
                ':id'                => (int) $pago['id'],
            ]);
        }

        $stmt = $conn->prepare(
            "UPDATE pedido
             SET estado = 'CANCELADO', motivo_cancelacion = :motivo,
                 cancelado_por = :usuario_id, fecha_cancelacion = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute([':motivo' => $motivo, ':usuario_id' => $usuarioId, ':id' => $pedidoId]);

        $stmt = $conn->prepare(
            'INSERT INTO pedido_historial_estado (pedido_id, estado_anterior, estado_nuevo, cambiado_por, motivo)
             VALUES (:pedido_id, :anterior, \'CANCELADO\', :usuario_id, :motivo)'
        );
        $stmt->execute([
            ':pedido_id'  => $pedidoId,
            ':anterior'   => $estado,
            ':usuario_id' => $usuarioId,
            ':motivo'     => $motivo,
        ]);

        registrarAuditoria($conn, $usuarioId, 'PEDIDO_CANCELADO', 'pedido', $pedidoId, [
            'codigo'             => $pedido['codigo'],
            'estado_anterior'    => $estado,
            'motivo'             => $motivo,
            'estaba_pagado'      => $estabaPagado,
            'metodo_reembolso'   => $metodoReembolso,
            'porcentaje_reembolso' => $porcentajeReembolso,
            'monto_reembolsado'  => $montoReembolso,
            'referencia'         => $referenciaReembolso,
        ]);
        $conn->commit();

        return [
            'estaba_pagado'        => $estabaPagado,
            'metodo_reembolso'     => $metodoReembolso,
            'porcentaje_reembolso' => $porcentajeReembolso,
            'monto_reembolsado'    => $montoReembolso,
            'estado_anterior'      => $estado,
        ];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if ($e instanceof ReglaNegocioException) {
            throw $e;
        }
        error_log('CoffeeAndtea - cancelar pedido: ' . $e->getMessage());
        throw new ReglaNegocioException('No se pudo cancelar el pedido.');
    }
}
