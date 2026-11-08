<?php
/**
 * Página de reportes: ejecuta los procedimientos almacenados de PostgreSQL.
 */
declare(strict_types=1);

require_once __DIR__ . '/php/lib/bootstrap.php';
require_once __DIR__ . '/php/lib/control_reportes.php';

cabeceraHtml('Reportes | CoffeeAndtea', 'reportes');

require __DIR__ . '/php/vistas/vista_reportes.php';

pieHtml();
