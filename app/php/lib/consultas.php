<?php
/**
 * Consultas compartidas de solo lectura.
 */
declare(strict_types=1);

/**
 * Devuelve los conteos de las cinco tablas.
 *
 * Se usa en la página de inicio (resumen del hero) y en la carga de gestión,
 * para no repetir el mismo SQL en dos lugares.
 *
 * @return array{categorias:int,clientes:int,productos:int,pedidos:int,detalles:int}
 */
function obtenerConteos(PDO $conn): array
{
    $conteos = $conn->query(
        'SELECT
            (SELECT COUNT(*) FROM categoria) AS categorias,
            (SELECT COUNT(*) FROM cliente) AS clientes,
            (SELECT COUNT(*) FROM producto) AS productos,
            (SELECT COUNT(*) FROM pedido) AS pedidos,
            (SELECT COUNT(*) FROM detallepedido) AS detalles'
    )->fetch();

    return [
        'categorias' => (int) ($conteos['categorias'] ?? 0),
        'clientes'   => (int) ($conteos['clientes'] ?? 0),
        'productos'  => (int) ($conteos['productos'] ?? 0),
        'pedidos'    => (int) ($conteos['pedidos'] ?? 0),
        'detalles'   => (int) ($conteos['detalles'] ?? 0),
    ];
}
