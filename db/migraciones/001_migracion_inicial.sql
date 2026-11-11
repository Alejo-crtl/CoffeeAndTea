-- ============================================================
-- BLOQUE B11 · BASE DE DATOS
-- CoffeeAndtea - Migracion inicial de datos
-- Origen: base anterior CoffeeAndTea (5 tablas, sin usuarios)
-- Destino: base nueva con db/schema.sql ya cargado
--
-- Requisitos:
--   1. La base destino debe estar vacia (sin categorias, clientes,
--      productos, pedidos ni detalles). config_sistema puede existir.
--   2. El origen debe estar accesible y el usuario debe poder crear
--      la extension dblink en la base destino.
--
-- Uso (PowerShell):
--   $env:PGPASSWORD='clave'
--   $env:ORIGEN_DSN='host=127.0.0.1 port=5432 dbname=CoffeeAndTea user=postgres password=clave'
--   psql -U postgres -h 127.0.0.1 -p 5432 -d coffeeandtea_migracion `
--        -v ON_ERROR_STOP=1 -f db/migraciones/001_migracion_inicial.sql
--
-- La migracion se ejecuta en una sola transaccion: si algo falla,
-- no deja datos a medias.
-- ============================================================

\getenv origen_dsn ORIGEN_DSN
\if :{?origen_dsn}
\else
    \echo 'ERROR: define la variable de entorno ORIGEN_DSN con la cadena de conexion al origen.'
    \quit 1
\endif

BEGIN;

CREATE EXTENSION IF NOT EXISTS dblink;

-- ------------------------------------------------------------
-- 0. Validaciones previas
-- ------------------------------------------------------------
DO $$
BEGIN
    IF (SELECT count(*) FROM categoria) > 0
       OR (SELECT count(*) FROM cliente) > 0
       OR (SELECT count(*) FROM producto) > 0
       OR (SELECT count(*) FROM pedido) > 0
       OR (SELECT count(*) FROM detallepedido) > 0 THEN
        RAISE EXCEPTION 'La base destino ya contiene datos. Use una base vacia para migrar.';
    END IF;
END;
$$;

SELECT dblink_connect('origen', :'origen_dsn');

-- ------------------------------------------------------------
-- 1. Categorias (6)
-- ------------------------------------------------------------
INSERT INTO categoria (id, nombre, descripcion, activo, creado_en)
SELECT id, nombre, descripcion, activo, creado_en
FROM dblink('origen', $q$
    SELECT id, nombre, descripcion, activo, creado_en
    FROM categoria
    ORDER BY id
$q$) AS t(
    id integer,
    nombre varchar(100),
    descripcion text,
    activo boolean,
    creado_en timestamptz
);

-- ------------------------------------------------------------
-- 2. Clientes (11)
--    Los clientes historicos no tienen cuenta de usuario; se
--    conservan sin usuario_id y podran reclamarla despues.
-- ------------------------------------------------------------
INSERT INTO cliente (id, nombre, correo, telefono, consentimiento, activo, creado_en)
SELECT id, nombre, correo, telefono, consentimiento, activo, creado_en
FROM dblink('origen', $q$
    SELECT id, nombre, correo, telefono, consentimiento, activo, creado_en
    FROM cliente
    ORDER BY id
$q$) AS t(
    id integer,
    nombre varchar(150),
    correo varchar(150),
    telefono varchar(30),
    consentimiento boolean,
    activo boolean,
    creado_en timestamptz
);

-- ------------------------------------------------------------
-- 3. Productos (15)
--    El origen no tenia descripcion, stock minimo ni tiempo de
--    preparacion; se usan los valores por defecto del esquema.
-- ------------------------------------------------------------
INSERT INTO producto (
    id, nombre, descripcion, precio, stock_actual, stock_minimo,
    categoria_id, activo, destacado, imagen_url, unidades_medida,
    tiempo_preparacion_min, creado_en
)
SELECT id, nombre, NULL, precio, stock_actual, 5,
       categoria_id, activo, false, NULL, unidades_medida,
       NULL, creado_en
FROM dblink('origen', $q$
    SELECT id, nombre, precio, stock_actual, categoria_id,
           activo, unidades_medida, creado_en
    FROM producto
    ORDER BY id
$q$) AS t(
    id integer,
    nombre varchar(150),
    precio numeric(12,2),
    stock_actual integer,
    categoria_id integer,
    activo boolean,
    unidades_medida varchar(50),
    creado_en timestamptz
);

-- Fotos para los productos migrados que tienen una imagen disponible.
-- Los productos sin foto conservan imagen_url = NULL.
UPDATE producto
SET imagen_url = CASE lower(trim(nombre))
    WHEN 'espresso'           THEN 'media/productos/espresso-sencillo.jpg'
    WHEN 'americano'          THEN 'media/productos/cafe-express.jpg'
    WHEN 'cappuccino'         THEN 'media/productos/capuchino.jpg'
    WHEN 'latte'              THEN 'media/productos/capuchino.jpg'
    WHEN 'té negro'           THEN 'media/productos/te-negro.jpg'
    WHEN 'té verde'           THEN 'media/productos/te-verde.jpg'
    WHEN 'frappé de moca'     THEN 'media/productos/frappe-cafe.jpg'
    WHEN 'torta de chocolate' THEN 'media/productos/torta-chocolate.jpg'
    WHEN 'croissant'          THEN 'media/productos/croissant.jpg'
    WHEN 'sándwich de pollo'  THEN 'media/productos/sandwich-pollo.jpg'
    WHEN 'ensalada césar'     THEN 'media/productos/ensalada-cesar.jpg'
    ELSE imagen_url
END
WHERE imagen_url IS NULL
  AND lower(trim(nombre)) IN (
      'espresso', 'americano', 'cappuccino', 'latte', 'té negro', 'té verde',
      'frappé de moca', 'torta de chocolate', 'croissant', 'sándwich de pollo',
      'ensalada césar'
  );

-- ------------------------------------------------------------
-- 4. Pedidos (12)
--    ABIERTO -> PENDIENTE; PAGADO -> PAGADO; CANCELADO -> CANCELADO.
--    El codigo y el token publico se generan aqui porque el
--    origen no los tenia.
-- ------------------------------------------------------------
INSERT INTO pedido (
    id, codigo, token_publico, cliente_id, estado, total,
    observaciones, canal, metodo_pago, tiempo_estimado_min,
    fecha_creacion, fecha_pago, fecha_cierre,
    motivo_cancelacion, cancelado_por, fecha_cancelacion
)
SELECT
    o.id,
    'CAF-' || lpad(o.id::text, 6, '0'),
    md5('migrado-' || o.id::text || '-' || random()::text || clock_timestamp()::text),
    o.cliente_id,
    CASE o.estado
        WHEN 'ABIERTO' THEN 'PENDIENTE'
        ELSE o.estado
    END,
    o.total,
    o.observaciones,
    'MOSTRADOR',
    NULL,
    NULL,
    o.fecha_creacion,
    CASE WHEN o.estado = 'PAGADO' THEN COALESCE(o.fecha_cierre, o.fecha_creacion) END,
    CASE WHEN o.estado = 'CANCELADO' THEN o.fecha_cierre END,
    CASE WHEN o.estado = 'CANCELADO' THEN 'Pedido cancelado en el sistema anterior' END,
    NULL,
    CASE WHEN o.estado = 'CANCELADO' THEN o.fecha_cierre END
FROM dblink('origen', $q$
    SELECT id, cliente_id, estado, total, observaciones,
           fecha_creacion, fecha_cierre
    FROM pedido
    ORDER BY id
$q$) AS o(
    id integer,
    cliente_id integer,
    estado varchar(20),
    total numeric(12,2),
    observaciones text,
    fecha_creacion timestamptz,
    fecha_cierre timestamptz
);

-- ------------------------------------------------------------
-- 5. Detalles (22)
--    El origen no guardaba el precio unitario; se usa el precio
--    actual del producto. El trigger recalcula pedido.total y la
--    verificacion final exige que coincida con el total original.
-- ------------------------------------------------------------
INSERT INTO detallepedido (id, id_pedido, id_producto, cantidad, precio_unitario)
SELECT d.id, d.id_pedido, d.id_producto, d.cantidad, pr.precio
FROM dblink('origen', $q$
    SELECT d.id, d.id_pedido, d.id_producto, d.cantidad, p.precio
    FROM detallepedido d
    JOIN producto p ON p.id = d.id_producto
    ORDER BY d.id
$q$) AS d(
    id integer,
    id_pedido integer,
    id_producto integer,
    cantidad integer,
    precio numeric(12,2)
)
JOIN producto pr ON pr.id = d.id_producto;

-- ------------------------------------------------------------
-- 6. Historial inicial
--    Un registro por pedido migrado, sin usuario responsable
--    (no existe en el origen).
-- ------------------------------------------------------------
INSERT INTO pedido_historial_estado (
    pedido_id, estado_anterior, estado_nuevo, cambiado_por, cambiado_en, motivo
)
SELECT id, NULL, estado, NULL, fecha_creacion, 'Pedido migrado del sistema anterior'
FROM pedido
ORDER BY id;

-- ------------------------------------------------------------
-- 7. Conciliacion: totales y cantidades
-- ------------------------------------------------------------
DO $$
DECLARE
    v_origen RECORD;
    v_total_destino NUMERIC(12,2);
BEGIN
    FOR v_origen IN
        SELECT * FROM dblink('origen', $q$
            SELECT p.id, p.total AS total_origen,
                   COALESCE(SUM(d.cantidad * pr.precio), 0) AS total_calculado,
                   COUNT(d.id) AS lineas
            FROM pedido p
            LEFT JOIN detallepedido d ON d.id_pedido = p.id
            LEFT JOIN producto pr ON pr.id = d.id_producto
            GROUP BY p.id, p.total
            ORDER BY p.id
        $q$) AS t(
            id integer,
            total_origen numeric(12,2),
            total_calculado numeric(12,2),
            lineas integer
        )
    LOOP
        SELECT total INTO v_total_destino FROM pedido WHERE id = v_origen.id;
        IF v_total_destino IS DISTINCT FROM v_origen.total_calculado THEN
            RAISE EXCEPTION 'Pedido %: total destino % no coincide con el calculado %',
                v_origen.id, v_total_destino, v_origen.total_calculado;
        END IF;
        IF v_total_destino IS DISTINCT FROM v_origen.total_origen THEN
            RAISE EXCEPTION 'Pedido %: total origen % no coincide con el destino %',
                v_origen.id, v_origen.total_origen, v_total_destino;
        END IF;
    END LOOP;

    IF (SELECT count(*) FROM detallepedido) <> 22 THEN
        RAISE EXCEPTION 'Se esperaban 22 detalles y se migraron %',
            (SELECT count(*) FROM detallepedido);
    END IF;
END;
$$;

-- ------------------------------------------------------------
-- 8. Secuencias
-- ------------------------------------------------------------
SELECT setval('categoria_id_seq', (SELECT MAX(id) FROM categoria));
SELECT setval('cliente_id_seq', (SELECT MAX(id) FROM cliente));
SELECT setval('producto_id_seq', (SELECT MAX(id) FROM producto));
SELECT setval('pedido_id_seq', (SELECT MAX(id) FROM pedido));
SELECT setval('detallepedido_id_seq', (SELECT MAX(id) FROM detallepedido));
SELECT setval('pedido_historial_estado_id_seq', (SELECT MAX(id) FROM pedido_historial_estado));

-- ------------------------------------------------------------
-- 9. Auditoria de la migracion
-- ------------------------------------------------------------
INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, detalles)
SELECT NULL, 'MIGRACION_DATOS', 'sistema', NULL, jsonb_build_object(
    'origen', 'CoffeeAndTea',
    'categorias', (SELECT count(*) FROM categoria),
    'clientes', (SELECT count(*) FROM cliente),
    'productos', (SELECT count(*) FROM producto),
    'pedidos', (SELECT count(*) FROM pedido),
    'detalles', (SELECT count(*) FROM detallepedido)
);

SELECT dblink_disconnect('origen');

COMMIT;

\echo 'Migracion completada: 6 categorias, 11 clientes, 15 productos, 12 pedidos y 22 detalles.'
