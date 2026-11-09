<?php
/**
 * Ejecución de los reportes almacenados en PostgreSQL.
 *
 * Cada procedimiento abre un refcursor; se ejecuta dentro de una transacción
 * y luego se recuperan todas las filas con FETCH ALL.
 */
declare(strict_types=1);

$reportesDefiniciones = [
    'ventas' => [
        'titulo'       => 'Ventas del día',
        'descripcion'  => 'Pedidos en estado PAGADO creados durante la fecha actual.',
        'procedimiento' => 'sp_reporte_ventas_diarias',
        'columnas'     => [
            'pedido_id'      => ['etiqueta' => 'Pedido', 'tipo' => 'pedido'],
            'cliente'        => ['etiqueta' => 'Cliente', 'tipo' => 'texto'],
            'total'          => ['etiqueta' => 'Total', 'tipo' => 'moneda'],
            'fecha_creacion' => ['etiqueta' => 'Fecha de creación', 'tipo' => 'fecha'],
        ],
        'columna_total' => 'total',
    ],
    'top' => [
        'titulo'       => 'Top 5 productos',
        'descripcion'  => 'Los cinco productos con más unidades vendidas en pedidos pagados.',
        'procedimiento' => 'sp_reporte_top_productos',
        'columnas'     => [
            'producto'           => ['etiqueta' => 'Producto', 'tipo' => 'texto'],
            'total_vendido'      => ['etiqueta' => 'Unidades vendidas', 'tipo' => 'entero'],
            'ingresos_generados' => ['etiqueta' => 'Ingresos generados', 'tipo' => 'moneda'],
        ],
        'columna_total' => 'ingresos_generados',
    ],
    'vip' => [
        'titulo'       => 'Clientes VIP',
        'descripcion'  => 'Clientes cuyos pedidos pagados suman más de $20.00.',
        'procedimiento' => 'sp_reporte_clientes_vip',
        'columnas'     => [
            'cliente'          => ['etiqueta' => 'Cliente', 'tipo' => 'texto'],
            'correo'           => ['etiqueta' => 'Correo', 'tipo' => 'texto'],
            'telefono'         => ['etiqueta' => 'Teléfono', 'tipo' => 'texto'],
            'cantidad_pedidos' => ['etiqueta' => 'Pedidos pagados', 'tipo' => 'entero'],
            'total_gastado'    => ['etiqueta' => 'Total gastado', 'tipo' => 'moneda'],
        ],
        'columna_total' => 'total_gastado',
    ],
    'stock' => [
        'titulo'       => 'Bajo stock',
        'descripcion'  => 'Productos activos cuya existencia está por debajo del límite indicado.',
        'procedimiento' => 'sp_reporte_bajo_stock',
        'columnas'     => [
            'producto'     => ['etiqueta' => 'Producto', 'tipo' => 'texto'],
            'stock_actual' => ['etiqueta' => 'Stock actual', 'tipo' => 'entero'],
            'categoria'    => ['etiqueta' => 'Categoría', 'tipo' => 'texto'],
            'precio'       => ['etiqueta' => 'Precio', 'tipo' => 'moneda'],
        ],
        'columna_total' => null,
    ],
    'categoria' => [
        'titulo'       => 'Ingresos por categoría',
        'descripcion'  => 'Ventas pagadas agrupadas por categoría, con unidades e ingresos.',
        'procedimiento' => 'sp_reporte_ingresos_categoria',
        'columnas'     => [
            'categoria'                  => ['etiqueta' => 'Categoría', 'tipo' => 'texto'],
            'pedidos_con_esta_categoria' => ['etiqueta' => 'Pedidos', 'tipo' => 'entero'],
            'unidades_vendidas'          => ['etiqueta' => 'Unidades', 'tipo' => 'entero'],
            'ingresos_totales'           => ['etiqueta' => 'Ingresos', 'tipo' => 'moneda'],
        ],
        'columna_total' => 'ingresos_totales',
    ],
];

$reportesPermitidos = array_keys($reportesDefiniciones);
$reporteActivo = (string) ($_GET['reporte'] ?? 'ventas');
if (!in_array($reporteActivo, $reportesPermitidos, true)) {
    $reporteActivo = 'ventas';
}

$limiteStock = 30;
if (isset($_GET['limite'])) {
    $limiteValidado = filter_var(
        $_GET['limite'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 9999]]
    );
    if ($limiteValidado !== false) {
        $limiteStock = (int) $limiteValidado;
    }
}

/**
 * Ejecuta un procedimiento con refcursor y devuelve las filas.
 *
 * @throws Throwable Si el procedimiento no existe o PostgreSQL devuelve error.
 */
function ejecutarReporteRefcursor(PDO $conn, string $reporte, int $limite): array
{
    $cursor = 'cur_' . bin2hex(random_bytes(8));
    $cursorLiteral = "'" . $cursor . "'";

    $llamada = match ($reporte) {
        'ventas'    => "CALL sp_reporte_ventas_diarias({$cursorLiteral})",
        'top'       => "CALL sp_reporte_top_productos({$cursorLiteral})",
        'vip'       => "CALL sp_reporte_clientes_vip({$cursorLiteral})",
        'stock'     => 'CALL sp_reporte_bajo_stock(' . $limite . ", {$cursorLiteral})",
        'categoria' => "CALL sp_reporte_ingresos_categoria({$cursorLiteral})",
        default     => throw new RuntimeException('Reporte no válido.'),
    };

    $conn->beginTransaction();
    try {
        // El CALL devuelve el nombre del cursor como INOUT; hay que consumirlo
        // antes de ejecutar FETCH en la misma conexión.
        $stmt = $conn->query($llamada);
        if ($stmt !== false) {
            $stmt->fetchAll();
            $stmt->closeCursor();
        }

        $fetch = $conn->query('FETCH ALL FROM "' . $cursor . '"');
        $filas = $fetch->fetchAll();

        $conn->commit();
        return $filas;
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        throw $e;
    }
}

$errorReporte = null;
$filasReporte = [];

try {
    $filasReporte = ejecutarReporteRefcursor($conn, $reporteActivo, $limiteStock);
} catch (Throwable $e) {
    error_log('CoffeeAndtea - reporte ' . $reporteActivo . ': ' . $e->getMessage());
    $errorReporte = 'No fue posible ejecutar el reporte. Verifica que el procedimiento exista en la base de datos y vuelve a intentarlo.';
}

$reporteInfo = $reportesDefiniciones[$reporteActivo];
$totalColumna = null;
if ($reporteInfo['columna_total'] !== null) {
    $totalColumna = 0.0;
    foreach ($filasReporte as $fila) {
        $totalColumna += (float) ($fila[$reporteInfo['columna_total']] ?? 0);
    }
}
