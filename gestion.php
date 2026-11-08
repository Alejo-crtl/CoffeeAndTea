<?php
/**
 * Página de gestión: CRUD de categorías, clientes, productos, pedidos y detalles.
 */
declare(strict_types=1);

require_once __DIR__ . '/php/lib/bootstrap.php';
require_once __DIR__ . '/php/lib/control_gestion.php';

cabeceraHtml('Gestión | CoffeeAndtea', 'gestion');

require __DIR__ . '/php/vistas/vista_gestion.php';

pieHtml();
