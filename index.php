<?php
/**
 * Página de inicio de CoffeeAndtea.
 *
 * Solo muestra el hero, el resumen de la base y el video. La gestión, los
 * reportes y el manual viven en sus propias páginas.
 */
declare(strict_types=1);

require_once __DIR__ . '/php/lib/bootstrap.php';

$seccionActiva = 'inicio';
$conteos = obtenerConteos($conn);

$totalCategorias = (int) ($conteos['categorias'] ?? 0);
$totalClientes   = (int) ($conteos['clientes'] ?? 0);
$totalProductos  = (int) ($conteos['productos'] ?? 0);
$totalPedidos    = (int) ($conteos['pedidos'] ?? 0);
$totalDetalles   = (int) ($conteos['detalles'] ?? 0);

cabeceraHtml('CoffeeAndtea | Gestión, reportes y manual', $seccionActiva);

require __DIR__ . '/php/vistas/vista_inicio.php';

pieHtml();
