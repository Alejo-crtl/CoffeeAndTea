<?php
/**
 * Lógica del CRUD de las cinco tablas.
 *
 * Procesa las acciones POST (crear, actualizar, eliminar), carga los datos
 * para las pestañas y deja las variables que usa vista_gestion.php.
 */
/*
 * declare(strict_types=1) activa los TIPOS ESTRICTOS de PHP:
 * si una función pide int y recibe el texto "5", PHP lanza TypeError en
 * lugar de convertirlo en silencio. Los errores aparecen antes y más claros.
 */
declare(strict_types=1);

require_once __DIR__ . '/consultas.php';

/*
 * Excepción propia para las REGLAS DE NEGOCIO (por ejemplo: "no se puede
 * borrar una categoría que todavía tiene productos"). Se distingue de
 * PDOException, que representa fallos técnicos de la base de datos.
 * El if evita el error "class already declared" si el archivo se incluyera
 * dos veces en la misma petición.
 */
if (!class_exists('GestionReglaException')) {
    class GestionReglaException extends RuntimeException
    {
    }
}

/** Devuelve una tabla válida o la tabla de categorías por defecto. */
/*
 * LISTA BLANCA de tablas. El nombre de la tabla llega por GET/POST, así que
 * nunca se interpola directamente en el SQL: se compara contra estas cinco
 * opciones y, si no coincide, se usa 'categoria'. Así se evita la inyección
 * SQL por el nombre de la tabla; los VALORES siempre van por marcadores
 * (:nombre) en consultas preparadas.
 */
function gestionTablaValida(string $tabla): string
{
    return in_array($tabla, ['categoria', 'cliente', 'producto', 'pedido', 'detalle'], true)
        ? $tabla
        : 'categoria';
}

/** Valores iniciales del formulario de cada tabla. */
/*
 * Valores con los que se pinta el formulario cuando está vacío (modo crear).
 * Usa "match", la expresión de comparación ESTRICTA de PHP 8: devuelve el
 * arreglo de la tabla pedida y, si no existe, un arreglo vacío.
 */
function gestionValoresDefecto(string $tabla): array
{
    return match ($tabla) {
        'categoria' => [
            'nombre'      => '',
            'descripcion' => '',
            'activo'      => true,
        ],
        'cliente' => [
            'nombre'         => '',
            'correo'         => '',
            'telefono'       => '',
            'consentimiento' => false,
            'activo'         => true,
        ],
        'producto' => [
            'unidades_medida' => '',
            'nombre'          => '',
            'precio'          => '',
            'stock_actual'    => '',
            'categoria_id'    => '',
            'activo'          => true,
        ],
        'pedido' => [
            'cliente_id'    => '',
            'estado'        => 'ABIERTO',
            'observaciones' => '',
            'fecha_cierre'  => '',
        ],
        'detalle' => [
            'id_pedido'   => '',
            'id_producto' => '',
            'cantidad'    => '1',
            // El formulario de creación trabaja con varias filas; $id_producto
            // y $cantidad se conservan para la edición de un solo detalle.
            'lineas'      => [['id_producto' => '', 'cantidad' => '1']],
        ],
        default => [],
    };
}

/** Convierte un registro de la base en valores para el formulario. */
/*
 * Camino inverso: toma una fila de PostgreSQL y la convierte en los valores
 * que espera el formulario (modo editar). Se usa (string) para que null se
 * vuelva "" y number_format() para que el precio salga con dos decimales y
 * punto, que es el formato que acepta <input type="number">.
 */
function gestionValoresDesdeRegistro(string $tabla, array $registro): array
{
    return match ($tabla) {
        'categoria' => [
            'nombre'      => (string) ($registro['nombre'] ?? ''),
            'descripcion' => (string) ($registro['descripcion'] ?? ''),
            'activo'      => esVerdadero($registro['activo'] ?? false),
        ],
        'cliente' => [
            'nombre'         => (string) ($registro['nombre'] ?? ''),
            'correo'         => (string) ($registro['correo'] ?? ''),
            'telefono'       => (string) ($registro['telefono'] ?? ''),
            'consentimiento' => esVerdadero($registro['consentimiento'] ?? false),
            'activo'         => esVerdadero($registro['activo'] ?? false),
        ],
        'producto' => [
            'unidades_medida' => (string) ($registro['unidades_medida'] ?? ''),
            'nombre'          => (string) ($registro['nombre'] ?? ''),
            'precio'          => number_format((float) ($registro['precio'] ?? 0), 2, '.', ''),
            'stock_actual'    => (string) ($registro['stock_actual'] ?? ''),
            'categoria_id'    => (string) ($registro['categoria_id'] ?? ''),
            'activo'          => esVerdadero($registro['activo'] ?? false),
        ],
        'pedido' => [
            'cliente_id'    => (string) ($registro['cliente_id'] ?? ''),
            'estado'        => (string) ($registro['estado'] ?? 'ABIERTO'),
            'observaciones' => (string) ($registro['observaciones'] ?? ''),
            'fecha_cierre'  => fechaInput($registro['fecha_cierre'] ?? null),
        ],
        'detalle' => [
            'id_pedido'   => (string) ($registro['id_pedido'] ?? ''),
            'id_producto' => (string) ($registro['id_producto'] ?? ''),
            'cantidad'    => (string) ($registro['cantidad'] ?? '1'),
        ],
        default => [],
    };
}

/** Carga un registro concreto para el formulario de edición. */
/*
 * Recupera UNA fila para el formulario de edición. El arreglo $sql traduce
 * el nombre lógico de la tabla al nombre real en PostgreSQL (ojo: la tabla
 * de detalles se llama "detallepedido", sin guion bajo). Si la tabla no
 * está en el arreglo, devuelve null. El "?array" del final significa que
 * puede devolver un arreglo o null.
 */
function gestionCargarRegistro(PDO $conn, string $tabla, int $id): ?array
{
    $sql = [
        'categoria' => 'SELECT * FROM categoria WHERE id = :id',
        'cliente'   => 'SELECT * FROM cliente WHERE id = :id',
        'producto'  => 'SELECT * FROM producto WHERE id = :id',
        'pedido'    => 'SELECT * FROM pedido WHERE id = :id',
        'detalle'   => 'SELECT * FROM detallepedido WHERE id = :id',
    ][$tabla] ?? null;

    if ($sql === null) {
        return null;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    $registro = $stmt->fetch();
    return $registro === false ? null : $registro;
}

/** Carga conteos, catálogos para selects y la lista de la tabla activa. */
/*
 * Carga TODO lo que la pantalla de gestión necesita en una sola llamada:
 *   - conteos: los números de las tarjetas superiores;
 *   - categorias / clientes / productos / pedidos: opciones de los <select>;
 *   - registros: las filas de la tabla que se está viendo.
 * Son consultas de solo lectura, por eso se usa query() y no prepare().
 */
function gestionCargarDatos(PDO $conn, string $tabla): array
{
    $conteos = obtenerConteos($conn);

    $categorias = $conn->query(
        'SELECT id, nombre, activo FROM categoria ORDER BY nombre ASC'
    )->fetchAll();

    $clientes = $conn->query(
        'SELECT id, nombre, correo, activo FROM cliente ORDER BY nombre ASC'
    )->fetchAll();

    $productos = $conn->query(
        'SELECT p.id, p.nombre, p.precio, p.stock_actual, p.activo, p.categoria_id,
                c.nombre AS categoria
         FROM producto p
         INNER JOIN categoria c ON c.id = p.categoria_id
         ORDER BY p.nombre ASC'
    )->fetchAll();

    $pedidos = $conn->query(
        'SELECT p.id, p.cliente_id, p.estado, p.total, p.fecha_creacion,
                c.nombre AS cliente
         FROM pedido p
         INNER JOIN cliente c ON c.id = p.cliente_id
         ORDER BY p.id DESC'
    )->fetchAll();

    /*
     * Productos que ya tiene cada pedido. Solo se consulta en la pestaña de
     * detalles: el formulario usa este mapa para deshabilitar los productos
     * repetidos antes de enviar y evitar el error de UNIQUE (id_pedido,
     * id_producto). Son datos de solo lectura y el volumen es pequeño.
     */
    $detallesPorPedido = [];
    if ($tabla === 'detalle') {
        $filasDetalle = $conn->query('SELECT id_pedido, id_producto FROM detallepedido')->fetchAll();
        foreach ($filasDetalle as $filaDetalle) {
            $detallesPorPedido[(int) $filaDetalle['id_pedido']][] = (int) $filaDetalle['id_producto'];
        }
    }

    $registros = match ($tabla) {
        'categoria' => $conn->query(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM producto p WHERE p.categoria_id = c.id) AS productos_count
             FROM categoria c
             ORDER BY c.id ASC'
        )->fetchAll(),
        'cliente' => $conn->query(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM pedido p WHERE p.cliente_id = c.id) AS pedidos_count
             FROM cliente c
             ORDER BY c.id ASC'
        )->fetchAll(),
        'producto' => $conn->query(
            'SELECT p.*, c.nombre AS categoria,
                    (SELECT COUNT(*) FROM detallepedido d WHERE d.id_producto = p.id) AS detalles_count
             FROM producto p
             INNER JOIN categoria c ON c.id = p.categoria_id
             ORDER BY p.id ASC'
        )->fetchAll(),
        'pedido' => $conn->query(
            'SELECT p.*, c.nombre AS cliente,
                    (SELECT COUNT(*) FROM detallepedido d WHERE d.id_pedido = p.id) AS detalles_count
             FROM pedido p
             INNER JOIN cliente c ON c.id = p.cliente_id
             ORDER BY p.id ASC'
        )->fetchAll(),
        'detalle' => $conn->query(
            'SELECT d.*, p.id AS pedido_numero, p.estado, c.nombre AS cliente,
                    pr.nombre AS producto, d.precio_unitario,
                    (d.cantidad * d.precio_unitario) AS subtotal
             FROM detallepedido d
             INNER JOIN pedido p ON p.id = d.id_pedido
             INNER JOIN cliente c ON c.id = p.cliente_id
             INNER JOIN producto pr ON pr.id = d.id_producto
             ORDER BY d.id ASC'
        )->fetchAll(),
        default => [],
    };

    return [
        'conteos'    => $conteos,
        'categorias' => $categorias,
        'clientes'   => $clientes,
        'productos'  => $productos,
        'pedidos'    => $pedidos,
        'registros'  => $registros,
        'detalles_por_pedido' => $detallesPorPedido,
    ];
}

/** Valida el formulario de categorías. */
/* -------------------------------------------------------------------------
   VALIDACIONES
   Cada función devuelve siempre dos cosas:
     'valores' => lo que el usuario escribió (para volver a pintarlo)
     'errores' => lista de mensajes; si está vacía, todo está correcto.
   $id es null al crear y el id del registro al editar: sirve para que la
   comprobación de duplicados no choque contra el propio registro.
   ------------------------------------------------------------------------- */

/*
 * VALIDACIÓN de categorías: nombre obligatorio (máximo 100) y descripción
 * de hasta 500 caracteres. Además comprueba que no exista otra categoría
 * con el mismo nombre ignorando mayúsculas (lower()).
 */
function gestionValidarCategoria(PDO $conn, array $post, ?int $id): array
{
    $valores = [
        'nombre'      => trim((string) ($post['nombre'] ?? '')),
        'descripcion' => trim((string) ($post['descripcion'] ?? '')),
        'activo'      => isset($post['activo']),
    ];
    $errores = [];

    if ($valores['nombre'] === '') {
        $errores[] = 'El nombre de la categoría es obligatorio.';
    } elseif (mb_strlen($valores['nombre']) > 100) {
        $errores[] = 'El nombre no puede superar los 100 caracteres.';
    }

    if (mb_strlen($valores['descripcion']) > 500) {
        $errores[] = 'La descripción no puede superar los 500 caracteres.';
    }

    if (empty($errores)) {
        $sql = 'SELECT id FROM categoria WHERE lower(nombre) = lower(:nombre)';
        $params = [':nombre' => $valores['nombre']];
        if ($id !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $id;
        }
        $sql .= ' LIMIT 1';

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn() !== false) {
            $errores[] = "La categoría '{$valores['nombre']}' ya está registrada.";
        }
    }

    return ['errores' => $errores, 'valores' => $valores];
}

/** Valida el formulario de clientes. */
/*
 * VALIDACIÓN de clientes. Además del formato, aplica una regla legal:
 * la Ley 1581 de 2012 exige autorización para tratar datos personales,
 * por eso al CREAR se obliga a marcar el consentimiento. El correo se
 * guarda en minúsculas para que no haya duplicados por mayúsculas.
 */
function gestionValidarCliente(PDO $conn, array $post, ?int $id): array
{
    $valores = [
        'nombre'         => trim((string) ($post['nombre'] ?? '')),
        'correo'         => mb_strtolower(trim((string) ($post['correo'] ?? ''))),
        'telefono'       => trim((string) ($post['telefono'] ?? '')),
        'consentimiento' => isset($post['consentimiento']),
        'activo'         => isset($post['activo']),
    ];
    $errores = [];

    if ($valores['nombre'] === '') {
        $errores[] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($valores['nombre']) < 3 || mb_strlen($valores['nombre']) > 150) {
        $errores[] = 'El nombre debe tener entre 3 y 150 caracteres.';
    }

    if ($valores['correo'] === '') {
        $errores[] = 'El correo electrónico es obligatorio.';
    } elseif (!filter_var($valores['correo'], FILTER_VALIDATE_EMAIL)
        || !preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $valores['correo'])) {
        $errores[] = 'El formato del correo electrónico no es válido.';
    } elseif (mb_strlen($valores['correo']) > 150) {
        $errores[] = 'El correo no puede superar los 150 caracteres.';
    }

    if ($valores['telefono'] !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $valores['telefono'])) {
        $errores[] = 'El teléfono debe tener entre 7 y 20 caracteres (números, espacios, +, - o paréntesis).';
    }

    if ($id === null && !$valores['consentimiento']) {
        $errores[] = 'Para crear el cliente debes autorizar el tratamiento de datos (Ley 1581 de 2012).';
    }

    if (empty($errores)) {
        $sql = 'SELECT id FROM cliente WHERE lower(correo) = :correo';
        $params = [':correo' => $valores['correo']];
        if ($id !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $id;
        }
        $sql .= ' LIMIT 1';

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn() !== false) {
            $errores[] = "El correo electrónico '{$valores['correo']}' ya está registrado.";
        }
    }

    return ['errores' => $errores, 'valores' => $valores];
}

/** Valida el formulario de productos. */
/*
 * VALIDACIÓN de productos. Cubre las tres columnas críticas:
 *   precio    -> número mayor que 0 y con un máximo razonable;
 *   stock     -> entero no negativo;
 *   categoría -> debe existir y estar activa.
 * La categoría "actual" se consulta ANTES de validar para permitir editar
 * un producto antiguo aunque su categoría ya esté desactivada.
 */
function gestionValidarProducto(PDO $conn, array $post, ?int $id): array
{
    $precioTexto = str_replace(',', '.', trim((string) ($post['precio'] ?? '')));
    $valores = [
        'unidades_medida' => trim((string) ($post['unidades_medida'] ?? '')),
        'nombre'          => trim((string) ($post['nombre'] ?? '')),
        'precio'          => $precioTexto,
        'stock_actual'    => trim((string) ($post['stock_actual'] ?? '')),
        'categoria_id'    => trim((string) ($post['categoria_id'] ?? '')),
        'activo'          => isset($post['activo']),
    ];
    $errores = [];

    $categoriaActual = null;
    if ($id !== null) {
        $stmt = $conn->prepare('SELECT categoria_id FROM producto WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $categoriaActual = $stmt->fetchColumn();
        if ($categoriaActual === false) {
            $errores[] = 'El producto que intentas editar ya no existe.';
            $categoriaActual = null;
        } else {
            $categoriaActual = (int) $categoriaActual;
        }
    }

    if ($valores['unidades_medida'] === '') {
        $errores[] = 'La unidad de medida es obligatoria (ml, unidades, gramos...).';
    } elseif (mb_strlen($valores['unidades_medida']) > 50) {
        $errores[] = 'La unidad de medida no puede superar los 50 caracteres.';
    }

    if ($valores['nombre'] === '') {
        $errores[] = 'El nombre del producto es obligatorio.';
    } elseif (mb_strlen($valores['nombre']) > 150) {
        $errores[] = 'El nombre no puede superar los 150 caracteres.';
    }

    $precio = filter_var($valores['precio'], FILTER_VALIDATE_FLOAT);
    if ($precio === false) {
        $errores[] = 'El precio debe ser un número (usa punto o coma para los decimales).';
    } elseif ($precio <= 0) {
        $errores[] = 'El precio debe ser mayor que cero.';
    } elseif ($precio > 9999999999.99) {
        $errores[] = 'El precio excede el máximo permitido (9.999.999.999,99).';
    } else {
        $valores['precio_valor'] = number_format((float) $precio, 2, '.', '');
    }

    $stock = filter_var($valores['stock_actual'], FILTER_VALIDATE_INT);
    if ($stock === false) {
        $errores[] = 'El stock debe ser un número entero.';
    } elseif ($stock < 0) {
        $errores[] = 'El stock no puede ser negativo.';
    } else {
        $valores['stock_int'] = (int) $stock;
    }

    $categoriaId = filter_var($valores['categoria_id'], FILTER_VALIDATE_INT);
    if ($categoriaId === false || $categoriaId < 1) {
        $errores[] = 'Selecciona una categoría válida.';
    } else {
        $valores['categoria_id_int'] = (int) $categoriaId;

        $stmt = $conn->prepare('SELECT activo FROM categoria WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $categoriaId]);
        $categoria = $stmt->fetch();

        if ($categoria === false) {
            $errores[] = 'La categoría seleccionada no existe.';
        } elseif (!esVerdadero($categoria['activo']) && (int) $categoriaId !== $categoriaActual) {
            $errores[] = 'La categoría seleccionada está inactiva. Elige una activa.';
        }
    }

    return ['errores' => $errores, 'valores' => $valores];
}

/** Valida el formulario de pedidos. */
/*
 * VALIDACIÓN de pedidos. El estado solo puede ser ABIERTO, PAGADO o
 * CANCELADO, y el cliente debe existir y estar activo. La fecha de cierre
 * llega del <input type="datetime-local"> con formato "Y-m-d\TH:i"; aquí se
 * convierte al formato de PostgreSQL "Y-m-d H:i:s".
 */
function gestionValidarPedido(PDO $conn, array $post, ?int $id): array
{
    $estados = ['ABIERTO', 'PAGADO', 'CANCELADO'];
    $valores = [
        'cliente_id'    => trim((string) ($post['cliente_id'] ?? '')),
        'estado'        => strtoupper(trim((string) ($post['estado'] ?? ''))),
        'observaciones' => trim((string) ($post['observaciones'] ?? '')),
        'fecha_cierre'  => trim((string) ($post['fecha_cierre'] ?? '')),
    ];
    $errores = [];

    $clienteActual = null;
    if ($id !== null) {
        $stmt = $conn->prepare('SELECT cliente_id FROM pedido WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $clienteActual = $stmt->fetchColumn();
        if ($clienteActual === false) {
            $errores[] = 'El pedido que intentas editar ya no existe.';
            $clienteActual = null;
        } else {
            $clienteActual = (int) $clienteActual;
        }
    }

    $clienteId = filter_var($valores['cliente_id'], FILTER_VALIDATE_INT);
    if ($clienteId === false || $clienteId < 1) {
        $errores[] = 'Selecciona un cliente válido.';
    } else {
        $valores['cliente_id_int'] = (int) $clienteId;

        $stmt = $conn->prepare('SELECT activo FROM cliente WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $clienteId]);
        $cliente = $stmt->fetch();

        if ($cliente === false) {
            $errores[] = 'El cliente seleccionado no existe.';
        } elseif (!esVerdadero($cliente['activo']) && (int) $clienteId !== $clienteActual) {
            $errores[] = 'El cliente seleccionado está inactivo. Elige uno activo.';
        }
    }

    if (!in_array($valores['estado'], $estados, true)) {
        $errores[] = 'El estado debe ser ABIERTO, PAGADO o CANCELADO.';
    }

    if (mb_strlen($valores['observaciones']) > 1000) {
        $errores[] = 'Las observaciones no pueden superar los 1000 caracteres.';
    }

    $valores['fecha_cierre_sql'] = null;
    if ($valores['fecha_cierre'] !== '') {
        $fecha = DateTime::createFromFormat('Y-m-d\TH:i', $valores['fecha_cierre']);
        $erroresFecha = DateTime::getLastErrors();

        if ($fecha === false || (is_array($erroresFecha)
            && ($erroresFecha['warning_count'] > 0 || $erroresFecha['error_count'] > 0))) {
            $errores[] = 'La fecha de cierre no tiene un formato válido.';
        } else {
            $valores['fecha_cierre_sql'] = $fecha->format('Y-m-d H:i:s');
        }
    }

    return ['errores' => $errores, 'valores' => $valores];
}

/** Valida el formulario de detalles de pedido. */
/*
 * VALIDACIÓN de líneas de pedido. Comprueba que el pedido exista, que el
 * producto exista y esté activo y que la cantidad sea un entero >= 1.
 * El stock y el total NO se tocan aquí: los controlan los triggers de la
 * base de datos, para que la regla viva en un solo sitio.
 */
function gestionValidarDetalle(PDO $conn, array $post, ?int $id): array
{
    $valores = [
        'id_pedido'   => trim((string) ($post['id_pedido'] ?? '')),
        'id_producto' => trim((string) ($post['id_producto'] ?? '')),
        'cantidad'    => trim((string) ($post['cantidad'] ?? '')),
    ];
    $errores = [];

    $productoActual = null;
    $precioActual = null;
    if ($id !== null) {
        $stmt = $conn->prepare('SELECT id_producto, precio_unitario FROM detallepedido WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $detalleActual = $stmt->fetch();
        if ($detalleActual === false) {
            $errores[] = 'El detalle que intentas editar ya no existe.';
        } else {
            $productoActual = (int) $detalleActual['id_producto'];
            $precioActual = (string) $detalleActual['precio_unitario'];
        }
    }

    $pedidoId = filter_var($valores['id_pedido'], FILTER_VALIDATE_INT);
    if ($pedidoId === false || $pedidoId < 1) {
        $errores[] = 'Selecciona un pedido válido.';
    } else {
        $valores['id_pedido_int'] = (int) $pedidoId;
        $stmt = $conn->prepare('SELECT id FROM pedido WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $pedidoId]);
        if ($stmt->fetchColumn() === false) {
            $errores[] = 'El pedido seleccionado no existe.';
        }
    }

    $productoId = filter_var($valores['id_producto'], FILTER_VALIDATE_INT);
    if ($productoId === false || $productoId < 1) {
        $errores[] = 'Selecciona un producto válido.';
    } else {
        $valores['id_producto_int'] = (int) $productoId;
        $stmt = $conn->prepare('SELECT activo, stock_actual, precio FROM producto WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $productoId]);
        $producto = $stmt->fetch();
        if ($producto === false) {
            $errores[] = 'El producto seleccionado no existe.';
        } else {
            // El detalle conserva su precio histórico; solo se toma el precio
            // actual del producto cuando la línea es nueva o cambia de producto.
            if ($productoActual === null || $productoId !== $productoActual) {
                $valores['precio_unitario'] = number_format((float) $producto['precio'], 2, '.', '');
            } elseif ($precioActual !== null) {
                $valores['precio_unitario'] = number_format((float) $precioActual, 2, '.', '');
            }
            // Si es un nuevo detalle o se cambia de producto, validar stock
            if ($productoActual === null || $productoId !== $productoActual) {
                $stockDisponible = (int) $producto['stock_actual'];
                $cantidad = filter_var($valores['cantidad'], FILTER_VALIDATE_INT);
                if ($cantidad === false || $cantidad < 1) {
                    $errores[] = 'La cantidad debe ser un número entero mayor que 0.';
                } else {
                    if ($cantidad > $stockDisponible) {
                        $errores[] = sprintf('La cantidad (%d) supera el stock disponible (%d) del producto.', $cantidad, $stockDisponible);
                    } else {
                        $valores['cantidad_int'] = $cantidad;
                    }
                }
            } else {
                // Mismo producto, solo validar cantidad
                $cantidad = filter_var($valores['cantidad'], FILTER_VALIDATE_INT);
                if ($cantidad === false || $cantidad < 1) {
                    $errores[] = 'La cantidad debe ser un número entero mayor que 0.';
                } else {
                    $valores['cantidad_int'] = $cantidad;
                }
            }
            // Permitir producto inactivo si es el actual
            if (!esVerdadero($producto['activo']) && (int) $productoId !== $productoActual) {
                $errores[] = 'El producto seleccionado está inactivo. Elige uno activo.';
            }
        }
    }

    return ['errores' => $errores, 'valores' => $valores];
}

/** Normaliza las filas del formulario multi-producto. */
/*
 * El formulario de creación envía detalles[0][id_producto],
 * detalles[0][cantidad], detalles[1]... Esta función convierte esa estructura
 * en una lista limpia y descarta cualquier valor que no sea un arreglo.
 */
function gestionLineasDetalleDesdePost(array $post): array
{
    $entrada = $post['detalles'] ?? null;
    if (!is_array($entrada)) {
        return [];
    }

    $lineas = [];
    foreach ($entrada as $fila) {
        if (!is_array($fila)) {
            continue;
        }
        $lineas[] = [
            'id_producto' => trim((string) ($fila['id_producto'] ?? '')),
            'cantidad'    => trim((string) ($fila['cantidad'] ?? '')),
        ];
    }

    return $lineas;
}

/**
 * Valida el lote completo de productos de un pedido nuevo.
 *
 * Reglas:
 *   - el pedido existe;
 *   - queda al menos una fila con datos;
 *   - cada producto existe, está activo y tiene stock suficiente;
 *   - la cantidad es un entero mayor que 0;
 *   - no se repite un producto dentro del formulario;
 *   - no se repite un producto que ya esté guardado en ese pedido.
 *
 * Devuelve los errores con el número de fila y las líneas tal como las
 * escribió el usuario, para poder repintar el formulario sin perder datos.
 */
function gestionValidarDetallesLote(PDO $conn, array $post): array
{
    $lineas = gestionLineasDetalleDesdePost($post);
    $errores = [];
    $pedidoId = null;

    $idPedido = filter_var(trim((string) ($post['id_pedido'] ?? '')), FILTER_VALIDATE_INT);
    if ($idPedido === false || $idPedido < 1) {
        $errores[] = 'Selecciona un pedido válido.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM pedido WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $idPedido]);
        if ($stmt->fetchColumn() === false) {
            $errores[] = 'El pedido seleccionado no existe.';
        } else {
            $pedidoId = (int) $idPedido;
        }
    }

    // Las filas completamente vacías se ignoran; debe quedar al menos una.
    // La numeración es continua para que "Fila N" coincida con lo que se
    // vuelve a pintar después de un error.
    $filas = [];
    $numeroFila = 0;
    foreach ($lineas as $linea) {
        if ($linea['id_producto'] === '' && $linea['cantidad'] === '') {
            continue;
        }
        $numeroFila++;
        $filas[] = [$numeroFila, $linea];
    }
    if (empty($filas)) {
        $errores[] = 'Agrega al menos un producto al pedido.';
    }

    // Productos que ya están guardados en el pedido elegido.
    $yaEnPedido = [];
    if ($pedidoId !== null) {
        $stmt = $conn->prepare('SELECT id_producto FROM detallepedido WHERE id_pedido = :id');
        $stmt->execute([':id' => $pedidoId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $productoExistente) {
            $yaEnPedido[(int) $productoExistente] = true;
        }
    }

    $vistos = [];
    $lineasValidadas = [];

    foreach ($filas as [$numeroFila, $linea]) {
        $linea['numero_fila'] = $numeroFila;
        $linea['error'] = false;

        $productoId = filter_var($linea['id_producto'], FILTER_VALIDATE_INT);
        if ($productoId === false || $productoId < 1) {
            $errores[] = "Fila {$numeroFila}: selecciona un producto válido.";
            $linea['error'] = true;
            $lineasValidadas[] = $linea;
            continue;
        }
        $productoId = (int) $productoId;

        $stmt = $conn->prepare('SELECT nombre, activo, stock_actual, precio FROM producto WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $productoId]);
        $producto = $stmt->fetch();
        if ($producto === false) {
            $errores[] = "Fila {$numeroFila}: el producto seleccionado no existe.";
            $linea['error'] = true;
            $lineasValidadas[] = $linea;
            continue;
        }

        $nombre = (string) $producto['nombre'];
        $linea['id_producto_int'] = $productoId;
        $linea['nombre_producto'] = $nombre;
        $linea['precio_unitario'] = (float) $producto['precio'];

        if (!esVerdadero($producto['activo'])) {
            $errores[] = "Fila {$numeroFila} ({$nombre}): el producto está inactivo.";
            $linea['error'] = true;
        }

        $cantidad = filter_var($linea['cantidad'], FILTER_VALIDATE_INT);
        if ($cantidad === false || $cantidad < 1) {
            $errores[] = "Fila {$numeroFila} ({$nombre}): la cantidad debe ser un número entero mayor que 0.";
            $linea['error'] = true;
        } else {
            $linea['cantidad_int'] = (int) $cantidad;
            $stock = (int) $producto['stock_actual'];
            if ($cantidad > $stock) {
                $errores[] = sprintf(
                    'Fila %d (%s): la cantidad (%d) supera el stock disponible (%d).',
                    $numeroFila,
                    $nombre,
                    $cantidad,
                    $stock
                );
                $linea['error'] = true;
            }
        }

        if (isset($yaEnPedido[$productoId])) {
            $errores[] = "Fila {$numeroFila} ({$nombre}): ya está en el pedido #{$pedidoId}. Edita ese detalle para cambiar la cantidad.";
            $linea['error'] = true;
        } elseif (isset($vistos[$productoId])) {
            $errores[] = "Fila {$numeroFila} ({$nombre}): está repetido; ya lo elegiste en la fila {$vistos[$productoId]}.";
            $linea['error'] = true;
        } else {
            $vistos[$productoId] = $numeroFila;
        }

        $lineasValidadas[] = $linea;
    }

    return [
        'errores' => $errores,
        'valores' => [
            'id_pedido'     => $idPedido === false ? '' : (string) $idPedido,
            'id_pedido_int' => $pedidoId,
            'lineas'        => $lineasValidadas,
        ],
    ];
}

/** Dispatcher de validación por tabla. */
/*
 * Dispatcher: elige la función de validación según la tabla activa.
 * Así el resto del controlador no necesita un if por cada formulario.
 */
function gestionValidarFormulario(PDO $conn, string $tabla, array $post, ?int $id): array
{
    if ($tabla === 'detalle') {
        // Al crear se envía un lote de filas; al editar, un solo detalle.
        if ($id === null) {
            return gestionValidarDetallesLote($conn, $post);
        }

        $detalleValidado = gestionValidarDetalle($conn, $post, $id);
        $errores = $detalleValidado['errores'];
        $valores = $detalleValidado['valores'];

        if (empty($errores)) {
            $stmt = $conn->prepare('SELECT id FROM detallepedido WHERE id_pedido = :pedido AND id_producto = :producto AND id != :id LIMIT 1');
            $stmt->execute([':pedido' => $valores['id_pedido_int'], ':producto' => $valores['id_producto_int'], ':id' => (int)($id ?? 0)]);
            if ($stmt->fetchColumn()) {
                $errores[] = 'Ese producto ya está en el pedido. Edita el detalle existente para cambiar la cantidad.';
            }
        }
        return ['errores' => $errores, 'valores' => $valores];
    }

    return match ($tabla) {
        'categoria' => gestionValidarCategoria($conn, $post, $id),
        'cliente'   => gestionValidarCliente($conn, $post, $id),
        'producto'  => gestionValidarProducto($conn, $post, $id),
        'pedido'    => gestionValidarPedido($conn, $post, $id),
        default     => ['errores' => ['Tabla no válida.'], 'valores' => []],
    };
}

/** Inserta un registro y devuelve su id y un mensaje. */
/* -------------------------------------------------------------------------
   ESCRITURA EN LA BASE DE DATOS
   ------------------------------------------------------------------------- */

/*
 * INSERCIÓN. Un switch por tabla; todos los INSERT usan:
 *   - marcadores :nombre en el SQL (consultas preparadas, sin concatenar);
 *   - bindValue() con el tipo correcto (PARAM_INT, PARAM_STR, PARAM_NULL);
 *   - RETURNING id, la forma que tiene PostgreSQL de devolver el id que
 *     acaba de generar la columna SERIAL.
 */
function gestionInsertar(PDO $conn, string $tabla, array $v): array
{
    switch ($tabla) {
        case 'categoria':
            $stmt = $conn->prepare(
                'INSERT INTO categoria (nombre, descripcion, activo)
                 VALUES (:nombre, :descripcion, :activo)
                 RETURNING id'
            );
            $stmt->bindValue(':nombre', $v['nombre'], PDO::PARAM_STR);
            $stmt->bindValue(':descripcion', $v['descripcion'] === '' ? null : $v['descripcion'], $v['descripcion'] === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':activo', boolSql((bool) $v['activo']), PDO::PARAM_STR);
            $stmt->execute();
            $id = (int) $stmt->fetchColumn();
            return ['id' => $id, 'mensaje' => "Categoría #{$id} creada correctamente."];

        case 'cliente':
            $stmt = $conn->prepare(
                'INSERT INTO cliente (nombre, correo, telefono, consentimiento, activo)
                 VALUES (:nombre, :correo, :telefono, :consentimiento, :activo)
                 RETURNING id'
            );
            $stmt->bindValue(':nombre', $v['nombre'], PDO::PARAM_STR);
            $stmt->bindValue(':correo', $v['correo'], PDO::PARAM_STR);
            $stmt->bindValue(':telefono', $v['telefono'] === '' ? null : $v['telefono'], $v['telefono'] === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':consentimiento', boolSql((bool) $v['consentimiento']), PDO::PARAM_STR);
            $stmt->bindValue(':activo', boolSql((bool) $v['activo']), PDO::PARAM_STR);
            $stmt->execute();
            $id = (int) $stmt->fetchColumn();
            return ['id' => $id, 'mensaje' => "Cliente #{$id} creado correctamente."];

        case 'producto':
            $stmt = $conn->prepare(
                'INSERT INTO producto (unidades_medida, nombre, precio, stock_actual, categoria_id, activo)
                 VALUES (:unidades, :nombre, :precio, :stock, :categoria_id, :activo)
                 RETURNING id'
            );
            $stmt->bindValue(':unidades', $v['unidades_medida'], PDO::PARAM_STR);
            $stmt->bindValue(':nombre', $v['nombre'], PDO::PARAM_STR);
            $stmt->bindValue(':precio', $v['precio_valor'], PDO::PARAM_STR);
            $stmt->bindValue(':stock', (int) $v['stock_int'], PDO::PARAM_INT);
            $stmt->bindValue(':categoria_id', (int) $v['categoria_id_int'], PDO::PARAM_INT);
            $stmt->bindValue(':activo', boolSql((bool) $v['activo']), PDO::PARAM_STR);
            $stmt->execute();
            $id = (int) $stmt->fetchColumn();
            return ['id' => $id, 'mensaje' => "Producto #{$id} creado correctamente."];

        case 'pedido':
            $stmt = $conn->prepare(
                'INSERT INTO pedido (cliente_id, estado, total, observaciones, fecha_cierre)
                 VALUES (:cliente_id, :estado, 0.00, :observaciones, :fecha_cierre)
                 RETURNING id'
            );
            $stmt->bindValue(':cliente_id', (int) $v['cliente_id_int'], PDO::PARAM_INT);
            $stmt->bindValue(':estado', $v['estado'], PDO::PARAM_STR);
            $stmt->bindValue(':observaciones', $v['observaciones'] === '' ? null : $v['observaciones'], $v['observaciones'] === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':fecha_cierre', $v['fecha_cierre_sql'], $v['fecha_cierre_sql'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();
            $id = (int) $stmt->fetchColumn();
            return ['id' => $id, 'mensaje' => "Pedido #{$id} creado. Agrega sus detalles para calcular el total."];

        /*
         * El detalle es el único INSERT con TRANSACCIÓN. Se insertan todas
         * las filas del formulario y, si una sola falla (stock, duplicado o
         * trigger), el rollback deja el pedido como estaba: todo o nada.
         * El trigger recalcula el total después de cada fila; se lee al final.
         */
        case 'detalle':
            $lineas = is_array($v['lineas'] ?? null) ? $v['lineas'] : [];
            if (empty($lineas)) {
                throw new GestionReglaException('Agrega al menos un producto al pedido.');
            }

            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare(
                    'INSERT INTO detallepedido (id_pedido, id_producto, cantidad, precio_unitario)
                     VALUES (:id_pedido, :id_producto, :cantidad, :precio_unitario)
                     RETURNING id'
                );

                $ids = [];
                foreach ($lineas as $linea) {
                    $stmt->bindValue(':id_pedido', (int) $v['id_pedido_int'], PDO::PARAM_INT);
                    $stmt->bindValue(':id_producto', (int) $linea['id_producto_int'], PDO::PARAM_INT);
                    $stmt->bindValue(':cantidad', (int) $linea['cantidad_int'], PDO::PARAM_INT);
                    $stmt->bindValue(':precio_unitario', (float) $linea['precio_unitario'], PDO::PARAM_STR);
                    $stmt->execute();
                    $ids[] = (int) $stmt->fetchColumn();
                    $stmt->closeCursor();
                }

                $totalStmt = $conn->prepare('SELECT total FROM pedido WHERE id = :id');
                $totalStmt->execute([':id' => $v['id_pedido_int']]);
                $total = (float) $totalStmt->fetchColumn();

                $conn->commit();

                $agregados = count($ids);
                $mensaje = $agregados === 1
                    ? "1 producto agregado al pedido #{$v['id_pedido_int']}. Total del pedido: " . moneda($total) . '.'
                    : "{$agregados} productos agregados al pedido #{$v['id_pedido_int']}. Total del pedido: " . moneda($total) . '.';

                return [
                    'id'      => $ids[0] ?? 0,
                    'mensaje' => $mensaje,
                ];
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollback();
                }
                throw $e;
            }
    }

    throw new GestionReglaException('No se pudo crear el registro.');
}

/** Actualiza un registro y devuelve un mensaje. */
/*
 * ACTUALIZACIÓN. Mismo patrón que el INSERT, pero con WHERE id = :id y
 * RETURNING id: si no vuelve ninguna fila, el registro ya no existía y se
 * lanza GestionReglaException para avisar al usuario.
 */
function gestionActualizar(PDO $conn, string $tabla, int $id, array $v): array
{
    switch ($tabla) {
        case 'categoria':
            $stmt = $conn->prepare(
                'UPDATE categoria
                 SET nombre = :nombre, descripcion = :descripcion, activo = :activo
                 WHERE id = :id
                 RETURNING id'
            );
            $stmt->bindValue(':nombre', $v['nombre'], PDO::PARAM_STR);
            $stmt->bindValue(':descripcion', $v['descripcion'] === '' ? null : $v['descripcion'], $v['descripcion'] === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':activo', boolSql((bool) $v['activo']), PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() === false) {
                throw new GestionReglaException('La categoría ya no existe.');
            }
            return ['mensaje' => "Categoría #{$id} actualizada correctamente."];

        case 'cliente':
            $stmt = $conn->prepare(
                'UPDATE cliente
                 SET nombre = :nombre, correo = :correo, telefono = :telefono,
                     consentimiento = :consentimiento, activo = :activo
                 WHERE id = :id
                 RETURNING id'
            );
            $stmt->bindValue(':nombre', $v['nombre'], PDO::PARAM_STR);
            $stmt->bindValue(':correo', $v['correo'], PDO::PARAM_STR);
            $stmt->bindValue(':telefono', $v['telefono'] === '' ? null : $v['telefono'], $v['telefono'] === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':consentimiento', boolSql((bool) $v['consentimiento']), PDO::PARAM_STR);
            $stmt->bindValue(':activo', boolSql((bool) $v['activo']), PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() === false) {
                throw new GestionReglaException('El cliente ya no existe.');
            }
            return ['mensaje' => "Cliente #{$id} actualizado correctamente."];

        case 'producto':
            $stmt = $conn->prepare(
                'UPDATE producto
                 SET unidades_medida = :unidades, nombre = :nombre, precio = :precio,
                     stock_actual = :stock, categoria_id = :categoria_id, activo = :activo
                 WHERE id = :id
                 RETURNING id'
            );
            $stmt->bindValue(':unidades', $v['unidades_medida'], PDO::PARAM_STR);
            $stmt->bindValue(':nombre', $v['nombre'], PDO::PARAM_STR);
            $stmt->bindValue(':precio', $v['precio_valor'], PDO::PARAM_STR);
            $stmt->bindValue(':stock', (int) $v['stock_int'], PDO::PARAM_INT);
            $stmt->bindValue(':categoria_id', (int) $v['categoria_id_int'], PDO::PARAM_INT);
            $stmt->bindValue(':activo', boolSql((bool) $v['activo']), PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() === false) {
                throw new GestionReglaException('El producto ya no existe.');
            }
            // Los detalles guardan el precio del momento de la venta: cambiar el
            // precio del producto no altera pedidos históricos.
            return ['mensaje' => "Producto #{$id} actualizado correctamente."];

        case 'pedido':
            $stmt = $conn->prepare(
                'UPDATE pedido
                 SET cliente_id = :cliente_id, estado = :estado,
                     observaciones = :observaciones, fecha_cierre = :fecha_cierre
                 WHERE id = :id
                 RETURNING id'
            );
            $stmt->bindValue(':cliente_id', (int) $v['cliente_id_int'], PDO::PARAM_INT);
            $stmt->bindValue(':estado', $v['estado'], PDO::PARAM_STR);
            $stmt->bindValue(':observaciones', $v['observaciones'] === '' ? null : $v['observaciones'], $v['observaciones'] === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':fecha_cierre', $v['fecha_cierre_sql'], $v['fecha_cierre_sql'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() === false) {
                throw new GestionReglaException('El pedido ya no existe.');
            }
            return ['mensaje' => "Pedido #{$id} actualizado correctamente."];

        case 'detalle':
            $conn->beginTransaction();
            try {
                $oldStmt = $conn->prepare('SELECT id_pedido FROM detallepedido WHERE id = :id FOR UPDATE');
                $oldStmt->execute([':id' => $id]);
                $pedidoAnterior = $oldStmt->fetchColumn();

                if ($pedidoAnterior === false) {
                    throw new GestionReglaException('El detalle ya no existe.');
                }
                $pedidoAnterior = (int) $pedidoAnterior;

                $stmt = $conn->prepare(
                    'UPDATE detallepedido
                     SET id_pedido = :id_pedido, id_producto = :id_producto,
                         cantidad = :cantidad, precio_unitario = :precio_unitario
                     WHERE id = :id
                     RETURNING id'
                );
                $stmt->bindValue(':id_pedido', (int) $v['id_pedido_int'], PDO::PARAM_INT);
                $stmt->bindValue(':id_producto', (int) $v['id_producto_int'], PDO::PARAM_INT);
                $stmt->bindValue(':cantidad', (int) $v['cantidad_int'], PDO::PARAM_INT);
                $stmt->bindValue(':precio_unitario', (float) $v['precio_unitario'], PDO::PARAM_STR);
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                if ($stmt->fetchColumn() === false) {
                    throw new GestionReglaException('El detalle ya no existe.');
                }

                $pedidoNuevo = (int) $v['id_pedido_int'];
                recalcularTotalPedido($conn, $pedidoAnterior);
                if ($pedidoNuevo !== $pedidoAnterior) {
                    recalcularTotalPedido($conn, $pedidoNuevo);
                }

                $totalStmt = $conn->prepare('SELECT total FROM pedido WHERE id = :id');
                $totalStmt->execute([':id' => $pedidoNuevo]);
                $total = (float) $totalStmt->fetchColumn();

                $conn->commit();

                $mensaje = "Detalle #{$id} actualizado. Total del pedido #{$pedidoNuevo}: " . moneda($total) . '.';
                if ($pedidoNuevo !== $pedidoAnterior) {
                    $mensaje .= " También se recalculó el pedido #{$pedidoAnterior}.";
                }
                return ['mensaje' => $mensaje];
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollback();
                }
                throw $e;
            }
    }

    throw new GestionReglaException('No se pudo actualizar el registro.');
}

/** Elimina un registro aplicando las reglas de integridad del negocio. */
/*
 * ELIMINACIÓN con reglas de integridad. Antes de borrar se cuenta cuántos
 * registros dependen de esta fila:
 *   categoría -> productos | cliente -> pedidos | producto -> detalles
 * Si hay dependencias se lanza una excepción y se sugiere DESACTIVAR en
 * lugar de borrar, para no perder el historial de ventas. El pedido sí borra
 * primero sus detalles, dentro de una transacción.
 */
function gestionEliminar(PDO $conn, string $tabla, int $id): string
{
    switch ($tabla) {
        case 'categoria':
            $stmt = $conn->prepare('SELECT COUNT(*) FROM producto WHERE categoria_id = :id');
            $stmt->execute([':id' => $id]);
            $productos = (int) $stmt->fetchColumn();
            if ($productos > 0) {
                throw new GestionReglaException(
                    "No se puede eliminar la categoría #{$id}: tiene {$productos} producto(s) asociado(s). "
                    . 'Puedes desactivarla para conservar el historial.'
                );
            }

            $stmt = $conn->prepare('DELETE FROM categoria WHERE id = :id RETURNING id');
            $stmt->execute([':id' => $id]);
            if ($stmt->fetchColumn() === false) {
                throw new GestionReglaException('La categoría ya no existe.');
            }
            return "Categoría #{$id} eliminada correctamente.";

        case 'cliente':
            $stmt = $conn->prepare('SELECT COUNT(*) FROM pedido WHERE cliente_id = :id');
            $stmt->execute([':id' => $id]);
            $pedidos = (int) $stmt->fetchColumn();
            if ($pedidos > 0) {
                throw new GestionReglaException(
                    "No se puede eliminar el cliente #{$id}: tiene {$pedidos} pedido(s) asociado(s). "
                    . 'Puedes desactivarlo para conservar el historial.'
                );
            }

            $stmt = $conn->prepare('DELETE FROM cliente WHERE id = :id RETURNING id');
            $stmt->execute([':id' => $id]);
            if ($stmt->fetchColumn() === false) {
                throw new GestionReglaException('El cliente ya no existe.');
            }
            return "Cliente #{$id} eliminado correctamente.";

        case 'producto':
            $stmt = $conn->prepare('SELECT COUNT(*) FROM detallepedido WHERE id_producto = :id');
            $stmt->execute([':id' => $id]);
            $detalles = (int) $stmt->fetchColumn();
            if ($detalles > 0) {
                throw new GestionReglaException(
                    "No se puede eliminar el producto #{$id}: aparece en {$detalles} detalle(s) de pedido. "
                    . 'Puedes desactivarlo para conservar el historial.'
                );
            }

            $stmt = $conn->prepare('DELETE FROM producto WHERE id = :id RETURNING id');
            $stmt->execute([':id' => $id]);
            if ($stmt->fetchColumn() === false) {
                throw new GestionReglaException('El producto ya no existe.');
            }
            return "Producto #{$id} eliminado correctamente.";

        case 'pedido':
            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare('SELECT COUNT(*) FROM detallepedido WHERE id_pedido = :id');
                $stmt->execute([':id' => $id]);
                $detalles = (int) $stmt->fetchColumn();

                $stmt = $conn->prepare('DELETE FROM detallepedido WHERE id_pedido = :id');
                $stmt->execute([':id' => $id]);

                $stmt = $conn->prepare('DELETE FROM pedido WHERE id = :id RETURNING id');
                $stmt->execute([':id' => $id]);
                if ($stmt->fetchColumn() === false) {
                    throw new GestionReglaException('El pedido ya no existe.');
                }

                $conn->commit();
                return $detalles > 0
                    ? "Pedido #{$id} eliminado junto con {$detalles} detalle(s)."
                    : "Pedido #{$id} eliminado correctamente.";
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollback();
                }
                throw $e;
            }

        case 'detalle':
            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare('SELECT id_pedido FROM detallepedido WHERE id = :id FOR UPDATE');
                $stmt->execute([':id' => $id]);
                $pedidoId = $stmt->fetchColumn();
                if ($pedidoId === false) {
                    throw new GestionReglaException('El detalle ya no existe.');
                }
                $pedidoId = (int) $pedidoId;

                $stmt = $conn->prepare('DELETE FROM detallepedido WHERE id = :id RETURNING id');
                $stmt->execute([':id' => $id]);
                if ($stmt->fetchColumn() === false) {
                    throw new GestionReglaException('El detalle ya no existe.');
                }

                recalcularTotalPedido($conn, $pedidoId);

                $stmt = $conn->prepare('SELECT total FROM pedido WHERE id = :id');
                $stmt->execute([':id' => $pedidoId]);
                $total = (float) $stmt->fetchColumn();

                $conn->commit();
                return "Detalle #{$id} eliminado. Total del pedido #{$pedidoId}: " . moneda($total) . '.';
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollback();
                }
                throw $e;
            }
    }

    throw new GestionReglaException('No se pudo eliminar el registro.');
}

/** Traduce los errores técnicos de PostgreSQL a mensajes para el usuario. */
/*
 * Traduce los códigos SQLSTATE de PostgreSQL a mensajes entendibles:
 *   23505 = unique_violation      (valor duplicado)
 *   23503 = foreign_key_violation (registro relacionado)
 *   23514 = check_violation       (regla de la tabla)
 * El detalle técnico se guarda en el log, no se muestra al usuario.
 */
function gestionMensajePdo(PDOException $e): string
{
    // Para errores de stock o cliente/producto inactivo (P0001), mostrar el mensaje original
    if ($e->getCode() === 'P0001') {
        return $e->getMessage();
    }
    return match ((string) $e->getCode()) {
        '23505' => 'Ya existe un registro con ese valor único.',
        '23503' => 'No se puede completar la operación porque hay registros relacionados.',
        '23514' => 'Algún dato no cumple las reglas de la base de datos.',
        default => 'No fue posible guardar o eliminar el registro. Revisa los datos e inténtalo de nuevo.',
    };
}

/* -------------------------------------------------------------------------
   Ejecución del controlador
   ------------------------------------------------------------------------- */

/*
 * A partir de aquí empieza el trabajo real de cada petición. Este archivo se
 * incluye desde index.php ANTES de imprimir HTML, por eso puede usar
 * redirigir() (que hace header('Location: ...')) sin errores de
 * "headers already sent".
 */
$tablaActiva = gestionTablaValida((string) ($_POST['tabla'] ?? $_GET['tabla'] ?? 'categoria'));
$erroresGestion = [];
$modoEdicion = false;
$idEdicion = null;
$valores = gestionValoresDefecto($tablaActiva);
/*
 * Variables de estado que consumirá vista_gestion.php:
 *   $tablaActiva    -> pestaña actual (categoría por defecto)
 *   $erroresGestion -> mensajes de validación
 *   $modoEdicion    -> true si se está editando
 *   $idEdicion      -> id del registro editado
 *   $valores        -> datos para pintar el formulario
 *   $huboPost       -> indica si la petición viene de un formulario
 */
$huboPost = $_SERVER['REQUEST_METHOD'] === 'POST';

/*
 * Rama POST: aquí se crea, actualiza o elimina. Toda acción termina en
 * redirigir() (patrón POST-Redirect-GET) para que refrescar el navegador
 * no repita la operación y para evitar el aviso de reenvío de formulario.
 */
if ($huboPost) {
    $accion = (string) ($_POST['accion'] ?? '');

    /*
     * CSRF: el formulario envía un token guardado en la sesión y validarCsrf()
     * lo compara. Si no coincide, la petición se rechaza antes de tocar la
     * base de datos. Protege contra formularios falsos de otros sitios.
     */
    if (!validarCsrf()) {
        flash('error', 'La sesión expiró o el formulario no es válido. Inténtalo de nuevo.');
        redirigir(urlGestion($tablaActiva));
    }

    if (in_array($accion, ['crear', 'actualizar'], true)) {
        $id = null;
        if ($accion === 'actualizar') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                flash('error', 'No se indicó un registro válido para actualizar.');
                redirigir(urlGestion($tablaActiva));
            }
            $id = (int) $id;
            $modoEdicion = true;
            $idEdicion = $id;
        }

        $resultado = gestionValidarFormulario($conn, $tablaActiva, $_POST, $id);
        $erroresGestion = $resultado['errores'];
        $valores = $resultado['valores'];

        if (empty($erroresGestion)) {
            try {
                if ($accion === 'crear') {
                    $resultadoGuardado = gestionInsertar($conn, $tablaActiva, $valores);
                } else {
                    $resultadoGuardado = gestionActualizar($conn, $tablaActiva, (int) $id, $valores);
                }

                flash('exito', $resultadoGuardado['mensaje']);

                /*
                 * Al crear detalles se conserva el pedido en la URL para que
                 * el formulario lo deje seleccionado y se puedan agregar más
                 * productos sin volver a buscarlo.
                 */
                if ($tablaActiva === 'detalle' && $accion === 'crear' && !empty($valores['id_pedido_int'])) {
                    redirigir(baseUrl() . 'gestion.php?tabla=detalle&pedido=' . (int) $valores['id_pedido_int'] . '#gestion');
                }

                redirigir(urlGestion($tablaActiva));
            } catch (GestionReglaException $e) {
                $erroresGestion[] = $e->getMessage();
            } catch (PDOException $e) {
                error_log('CoffeeAndtea - CRUD: ' . $e->getMessage());
                $erroresGestion[] = gestionMensajePdo($e);
            } catch (Throwable $e) {
                error_log('CoffeeAndtea - CRUD inesperado: ' . $e->getMessage());
                $erroresGestion[] = 'Ocurrió un error inesperado. Revisa los datos e inténtalo de nuevo.';
            }
        }
    /*
     * Rama de borrado. No pasa por la validación del formulario porque solo
     * necesita el id; la comprobación de dependencias vive en gestionEliminar().
     */
    } elseif ($accion === 'eliminar') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            flash('error', 'No se indicó un registro válido para eliminar.');
            redirigir(urlGestion($tablaActiva));
        }
        $id = (int) $id;

        try {
            $mensaje = gestionEliminar($conn, $tablaActiva, $id);
            flash('exito', $mensaje);
        } catch (GestionReglaException $e) {
            flash('error', $e->getMessage());
        } catch (PDOException $e) {
            error_log('CoffeeAndtea - eliminar: ' . $e->getMessage());
            flash('error', gestionMensajePdo($e));
        } catch (Throwable $e) {
            error_log('CoffeeAndtea - eliminar inesperado: ' . $e->getMessage());
            flash('error', 'Ocurrió un error inesperado al eliminar. Inténtalo de nuevo.');
        }

        redirigir(urlGestion($tablaActiva));
    } else {
        flash('error', 'La acción solicitada no es válida.');
        redirigir(urlGestion($tablaActiva));
    }
/*
 * Rama GET de edición: cuando la URL trae ?editar=5 se carga ese registro y
 * el formulario se pinta relleno. Si el id no es válido o ya no existe, se
 * vuelve a la lista con un mensaje flash.
 */
} elseif (isset($_GET['editar'])) {
    $id = filter_var($_GET['editar'], FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        flash('error', 'El registro solicitado no es válido.');
        redirigir(urlGestion($tablaActiva));
    }

    $registro = gestionCargarRegistro($conn, $tablaActiva, (int) $id);
    if ($registro === null) {
        flash('error', 'El registro solicitado no existe.');
        redirigir(urlGestion($tablaActiva));
    }

    $modoEdicion = true;
    $idEdicion = (int) $id;
    $valores = gestionValoresDesdeRegistro($tablaActiva, $registro);
}

/*
 * Después de guardar un lote se vuelve con ?pedido=N. Se preselecciona ese
 * pedido para poder seguir agregando productos sin buscarlo otra vez.
 */
if (!$modoEdicion && !$huboPost && isset($_GET['pedido'])) {
    $pedidoRecordado = filter_var($_GET['pedido'], FILTER_VALIDATE_INT);
    if ($pedidoRecordado !== false && $pedidoRecordado > 0) {
        $valores['id_pedido'] = (string) $pedidoRecordado;
    }
}

/*
 * Al final se cargan los catálogos y la lista de la tabla activa, y se
 * reparten en variables sueltas ($conteos, $categoriasTodas, ...) porque así
 * las esperan vista_gestion.php y los formularios incluidos.
 */
$datosGestion = gestionCargarDatos($conn, $tablaActiva);
$conteos = $datosGestion['conteos'];
$categoriasTodas = $datosGestion['categorias'];
$clientesTodos = $datosGestion['clientes'];
$productosTodos = $datosGestion['productos'];
$pedidosTodos = $datosGestion['pedidos'];
$registros = $datosGestion['registros'];
$detallesPorPedido = $datosGestion['detalles_por_pedido'];
