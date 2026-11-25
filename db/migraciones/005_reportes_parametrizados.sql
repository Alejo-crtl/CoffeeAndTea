-- 005 · Procedimientos de reportes con rango de fechas
--
-- Los cinco procedimientos existían pero no se usaban. Ahora aceptan el rango
-- de fechas (y un límite donde aplica) para que la página de reportes los llame.

DROP PROCEDURE IF EXISTS sp_reporte_ventas_diarias(refcursor);
DROP PROCEDURE IF EXISTS sp_reporte_top_productos(refcursor);
DROP PROCEDURE IF EXISTS sp_reporte_clientes_vip(refcursor);
DROP PROCEDURE IF EXISTS sp_reporte_bajo_stock(integer, refcursor);
DROP PROCEDURE IF EXISTS sp_reporte_ingresos_categoria(refcursor);

CREATE OR REPLACE PROCEDURE sp_reporte_ventas_diarias(
    p_desde DATE,
    p_hasta DATE,
    cursor_name refcursor
)
LANGUAGE plpgsql AS $$
BEGIN
    OPEN cursor_name FOR
        SELECT
            p.id AS pedido_id,
            p.codigo,
            c.nombre AS cliente,
            p.total,
            p.metodo_pago,
            COALESCE(p.fecha_pago, p.fecha_creacion) AS fecha
        FROM pedido p
        JOIN cliente c ON c.id = p.cliente_id
        WHERE p.estado IN ('PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
          AND COALESCE(p.fecha_pago, p.fecha_creacion)::date BETWEEN p_desde AND p_hasta
        ORDER BY COALESCE(p.fecha_pago, p.fecha_creacion) DESC;
END;
$$;

CREATE OR REPLACE PROCEDURE sp_reporte_top_productos(
    p_desde DATE,
    p_hasta DATE,
    p_limite INTEGER,
    cursor_name refcursor
)
LANGUAGE plpgsql AS $$
BEGIN
    OPEN cursor_name FOR
        SELECT
            pr.nombre AS producto,
            c.nombre AS categoria,
            SUM(d.cantidad) AS unidades,
            SUM(d.cantidad * d.precio_unitario) AS ingresos
        FROM detallepedido d
        JOIN producto pr ON pr.id = d.id_producto
        JOIN categoria c ON c.id = pr.categoria_id
        JOIN pedido p ON p.id = d.id_pedido
        WHERE p.estado IN ('PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
          AND COALESCE(p.fecha_pago, p.fecha_creacion)::date BETWEEN p_desde AND p_hasta
        GROUP BY pr.id, pr.nombre, c.nombre
        ORDER BY unidades DESC, ingresos DESC
        LIMIT GREATEST(p_limite, 1);
END;
$$;

CREATE OR REPLACE PROCEDURE sp_reporte_clientes_vip(
    p_desde DATE,
    p_hasta DATE,
    p_limite INTEGER,
    cursor_name refcursor
)
LANGUAGE plpgsql AS $$
BEGIN
    OPEN cursor_name FOR
        SELECT
            c.nombre AS cliente,
            c.correo,
            COUNT(p.id) AS pedidos,
            SUM(p.total) AS total
        FROM cliente c
        JOIN pedido p ON p.cliente_id = c.id
        WHERE p.estado IN ('PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
          AND COALESCE(p.fecha_pago, p.fecha_creacion)::date BETWEEN p_desde AND p_hasta
        GROUP BY c.id, c.nombre, c.correo
        ORDER BY total DESC, pedidos DESC
        LIMIT GREATEST(p_limite, 1);
END;
$$;

CREATE OR REPLACE PROCEDURE sp_reporte_bajo_stock(
    p_solo_bajo BOOLEAN,
    cursor_name refcursor
)
LANGUAGE plpgsql AS $$
BEGIN
    OPEN cursor_name FOR
        SELECT
            pr.nombre,
            pr.stock_actual,
            pr.stock_minimo,
            c.nombre AS categoria
        FROM producto pr
        JOIN categoria c ON c.id = pr.categoria_id
        WHERE pr.activo = TRUE
          AND (NOT p_solo_bajo OR pr.stock_actual <= pr.stock_minimo)
        ORDER BY pr.stock_actual ASC, pr.nombre ASC;
END;
$$;

CREATE OR REPLACE PROCEDURE sp_reporte_ingresos_categoria(
    p_desde DATE,
    p_hasta DATE,
    cursor_name refcursor
)
LANGUAGE plpgsql AS $$
BEGIN
    OPEN cursor_name FOR
        SELECT
            c.nombre AS categoria,
            SUM(d.cantidad) AS unidades,
            SUM(d.cantidad * d.precio_unitario) AS ingresos
        FROM detallepedido d
        JOIN producto pr ON pr.id = d.id_producto
        JOIN categoria c ON c.id = pr.categoria_id
        JOIN pedido p ON p.id = d.id_pedido
        WHERE p.estado IN ('PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
          AND COALESCE(p.fecha_pago, p.fecha_creacion)::date BETWEEN p_desde AND p_hasta
        GROUP BY c.id, c.nombre
        ORDER BY ingresos DESC;
END;
$$;
