-- ============================================================
-- CoffeeAndTea - Actualización incremental del esquema
-- Conserva todos los datos históricos. Solo agrega estructura
-- faltante y adapta los datos al modelo nuevo.
-- ============================================================

BEGIN;

-- ------------------------------------------------------------
-- 1. Tablas nuevas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuario (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(20) NOT NULL DEFAULT 'CLIENTE'
        CHECK (rol IN ('CLIENTE', 'ENCARGADO', 'ADMIN')),
    activo BOOLEAN NOT NULL DEFAULT true,
    debe_cambiar_password BOOLEAN NOT NULL DEFAULT false,
    session_version INTEGER NOT NULL DEFAULT 0,
    creado_en TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP WITH TIME ZONE
);

CREATE TABLE IF NOT EXISTS password_reset (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuario(id) ON DELETE CASCADE,
    token_hash VARCHAR(255) NOT NULL,
    expira_en TIMESTAMP WITH TIME ZONE NOT NULL,
    usado_en TIMESTAMP WITH TIME ZONE,
    creado_en TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(45)
);

CREATE TABLE IF NOT EXISTS pedido_historial_estado (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL REFERENCES pedido(id) ON DELETE CASCADE,
    estado_anterior VARCHAR(20),
    estado_nuevo VARCHAR(20) NOT NULL,
    cambiado_por INTEGER REFERENCES usuario(id),
    cambiado_en TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo TEXT
);

CREATE TABLE IF NOT EXISTS pago (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL REFERENCES pedido(id),
    monto NUMERIC(12,2) NOT NULL CHECK (monto > 0),
    metodo VARCHAR(20) NOT NULL CHECK (metodo IN ('EFECTIVO', 'TARJETA')),
    referencia VARCHAR(100),
    recibido NUMERIC(12,2),
    cambio NUMERIC(12,2),
    estado VARCHAR(20) NOT NULL DEFAULT 'CONFIRMADO'
        CHECK (estado IN ('CONFIRMADO', 'REEMBOLSADO')),
    creado_por INTEGER NOT NULL REFERENCES usuario(id),
    creado_en TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reembolsado_por INTEGER REFERENCES usuario(id),
    reembolsado_en TIMESTAMP WITH TIME ZONE,
    motivo_reembolso TEXT,
    referencia_reembolso VARCHAR(100),
    monto_reembolsado NUMERIC(12,2)
        CHECK (monto_reembolsado IS NULL OR monto_reembolsado >= 0)
);

CREATE TABLE IF NOT EXISTS caja_sesion (
    id SERIAL PRIMARY KEY,
    abierto_por INTEGER NOT NULL REFERENCES usuario(id),
    fecha_apertura TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    base_inicial NUMERIC(12,2) NOT NULL CHECK (base_inicial >= 0),
    efectivo_contado NUMERIC(12,2),
    diferencia NUMERIC(12,2),
    cerrado_por INTEGER REFERENCES usuario(id),
    fecha_cierre TIMESTAMP WITH TIME ZONE,
    estado VARCHAR(20) NOT NULL DEFAULT 'ABIERTA'
        CHECK (estado IN ('ABIERTA', 'CERRADA', 'CORREGIDA'))
);

CREATE TABLE IF NOT EXISTS movimiento_caja (
    id SERIAL PRIMARY KEY,
    caja_sesion_id INTEGER NOT NULL REFERENCES caja_sesion(id),
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('INGRESO', 'EGRESO')),
    monto NUMERIC(12,2) NOT NULL CHECK (monto > 0),
    concepto VARCHAR(255) NOT NULL,
    related_type VARCHAR(50),
    related_id INTEGER,
    creado_por INTEGER NOT NULL REFERENCES usuario(id),
    creado_en TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS movimiento_stock (
    id SERIAL PRIMARY KEY,
    producto_id INTEGER NOT NULL REFERENCES producto(id),
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('VENTA', 'DEVOLUCION', 'AJUSTE')),
    cantidad INTEGER NOT NULL CHECK (cantidad > 0),
    referencia VARCHAR(100),
    creado_por INTEGER NOT NULL REFERENCES usuario(id),
    creado_en TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS auditoria (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER REFERENCES usuario(id) ON DELETE SET NULL,
    accion VARCHAR(50) NOT NULL,
    tabla VARCHAR(50) NOT NULL,
    registro_id INTEGER,
    detalles JSONB,
    ip VARCHAR(45),
    creado_en TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS config_sistema (
    id SERIAL PRIMARY KEY,
    clave VARCHAR(50) NOT NULL UNIQUE,
    valor TEXT NOT NULL,
    descripcion TEXT
);

-- ------------------------------------------------------------
-- 2. Columnas nuevas en tablas existentes
-- ------------------------------------------------------------
ALTER TABLE cliente
    ADD COLUMN IF NOT EXISTS usuario_id INTEGER UNIQUE REFERENCES usuario(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS reclamado_en TIMESTAMP WITH TIME ZONE;

ALTER TABLE cliente ALTER COLUMN telefono TYPE VARCHAR(30);

ALTER TABLE producto
    ADD COLUMN IF NOT EXISTS descripcion TEXT,
    ADD COLUMN IF NOT EXISTS stock_minimo INTEGER NOT NULL DEFAULT 5,
    ADD COLUMN IF NOT EXISTS destacado BOOLEAN NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS imagen_url VARCHAR(255),
    ADD COLUMN IF NOT EXISTS tiempo_preparacion_min INTEGER;

ALTER TABLE producto ALTER COLUMN stock_actual SET DEFAULT 0;
ALTER TABLE producto ALTER COLUMN unidades_medida SET DEFAULT 'unidades';

ALTER TABLE producto
    ADD CONSTRAINT chk_producto_stock_minimo CHECK (stock_minimo >= 0);

ALTER TABLE producto
    ADD CONSTRAINT chk_producto_tiempo_preparacion
    CHECK (tiempo_preparacion_min IS NULL OR tiempo_preparacion_min > 0);

ALTER TABLE pedido
    ADD COLUMN IF NOT EXISTS codigo VARCHAR(12),
    ADD COLUMN IF NOT EXISTS token_publico VARCHAR(64),
    ADD COLUMN IF NOT EXISTS canal VARCHAR(20) NOT NULL DEFAULT 'WEB',
    ADD COLUMN IF NOT EXISTS metodo_pago VARCHAR(20),
    ADD COLUMN IF NOT EXISTS tiempo_estimado_min INTEGER,
    ADD COLUMN IF NOT EXISTS fecha_pago TIMESTAMP WITH TIME ZONE,
    ADD COLUMN IF NOT EXISTS motivo_cancelacion TEXT,
    ADD COLUMN IF NOT EXISTS cancelado_por INTEGER REFERENCES usuario(id),
    ADD COLUMN IF NOT EXISTS fecha_cancelacion TIMESTAMP WITH TIME ZONE;

ALTER TABLE pedido ALTER COLUMN total SET DEFAULT 0;
ALTER TABLE pedido ALTER COLUMN estado SET DEFAULT 'PENDIENTE';

ALTER TABLE detallepedido
    ADD COLUMN IF NOT EXISTS precio_unitario NUMERIC(12,2);

-- ------------------------------------------------------------
-- 3. Adaptación de datos históricos (sin borrar nada)
-- ------------------------------------------------------------
-- Estados: ABIERTO -> PENDIENTE (modelo nuevo)
ALTER TABLE pedido DROP CONSTRAINT IF EXISTS chk_pedido_estado;
UPDATE pedido SET estado = 'PENDIENTE' WHERE estado = 'ABIERTO';
ALTER TABLE pedido
    ADD CONSTRAINT chk_pedido_estado
    CHECK (estado IN ('PENDIENTE', 'PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO', 'CANCELADO'));

-- Los pedidos históricos se registran como ventas de mostrador
UPDATE pedido
SET canal = 'MOSTRADOR'
WHERE codigo IS NULL AND token_publico IS NULL;

-- Código público y token de consulta
UPDATE pedido
SET codigo = 'CAF-' || lpad(id::text, 6, '0')
WHERE codigo IS NULL;

UPDATE pedido
SET token_publico = md5(random()::text || clock_timestamp()::text)
                 || md5(random()::text || clock_timestamp()::text)
WHERE token_publico IS NULL;

-- Fecha de pago de los pedidos ya pagados
UPDATE pedido
SET fecha_pago = fecha_cierre
WHERE estado = 'PAGADO' AND fecha_pago IS NULL AND fecha_cierre IS NOT NULL;

-- Datos de cancelación de pedidos históricos cancelados
UPDATE pedido
SET fecha_cancelacion = COALESCE(fecha_cierre, fecha_creacion)
WHERE estado = 'CANCELADO' AND fecha_cancelacion IS NULL;

UPDATE pedido
SET motivo_cancelacion = 'Pedido cancelado antes de la actualización del sistema'
WHERE estado = 'CANCELADO' AND motivo_cancelacion IS NULL;

ALTER TABLE pedido ALTER COLUMN codigo SET NOT NULL;
ALTER TABLE pedido ALTER COLUMN token_publico SET NOT NULL;

ALTER TABLE pedido
    ADD CONSTRAINT pedido_codigo_key UNIQUE (codigo);
ALTER TABLE pedido
    ADD CONSTRAINT pedido_token_publico_key UNIQUE (token_publico);
ALTER TABLE pedido
    ADD CONSTRAINT chk_pedido_canal CHECK (canal IN ('WEB', 'MOSTRADOR'));
ALTER TABLE pedido
    ADD CONSTRAINT chk_pedido_metodo_pago
    CHECK (metodo_pago IS NULL OR metodo_pago IN ('EFECTIVO', 'TARJETA'));

-- Precio histórico de cada detalle (precio actual del producto)
UPDATE detallepedido d
SET precio_unitario = pr.precio
FROM producto pr
WHERE pr.id = d.id_producto AND d.precio_unitario IS NULL;

ALTER TABLE detallepedido ALTER COLUMN precio_unitario SET NOT NULL;

ALTER TABLE detallepedido
    ADD CONSTRAINT chk_detalle_precio CHECK (precio_unitario > 0);
ALTER TABLE detallepedido
    ADD CONSTRAINT uq_detalle_pedido_producto UNIQUE (id_pedido, id_producto);

-- ------------------------------------------------------------
-- 4. Funciones y triggers del modelo nuevo
-- ------------------------------------------------------------
-- Se retira el trigger viejo que calculaba con el precio actual.
DROP TRIGGER IF EXISTS trg_actualizar_total ON detallepedido;

CREATE OR REPLACE FUNCTION fn_cliente_activo()
RETURNS TRIGGER AS $$
DECLARE
    v_activo BOOLEAN;
BEGIN
    IF TG_OP = 'INSERT' OR NEW.cliente_id IS DISTINCT FROM OLD.cliente_id THEN
        SELECT activo INTO v_activo FROM cliente WHERE id = NEW.cliente_id;
        IF v_activo = FALSE THEN
            RAISE EXCEPTION 'El cliente % esta inactivo y no puede hacer pedidos', NEW.cliente_id;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_cliente_activo ON pedido;
CREATE TRIGGER trg_cliente_activo
BEFORE INSERT OR UPDATE ON pedido
FOR EACH ROW EXECUTE FUNCTION fn_cliente_activo();

CREATE OR REPLACE FUNCTION fn_fecha_cierre()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.estado IN ('ENTREGADO', 'CANCELADO') AND NEW.fecha_cierre IS NULL THEN
        NEW.fecha_cierre := CURRENT_TIMESTAMP;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_fecha_cierre ON pedido;
CREATE TRIGGER trg_fecha_cierre
BEFORE INSERT OR UPDATE ON pedido
FOR EACH ROW EXECUTE FUNCTION fn_fecha_cierre();

CREATE OR REPLACE FUNCTION fn_producto_activo()
RETURNS TRIGGER AS $$
DECLARE
    v_activo BOOLEAN;
BEGIN
    IF TG_OP = 'INSERT' OR NEW.id_producto IS DISTINCT FROM OLD.id_producto THEN
        SELECT activo INTO v_activo FROM producto WHERE id = NEW.id_producto;
        IF v_activo = FALSE THEN
            RAISE EXCEPTION 'El producto % esta inactivo y no se puede vender', NEW.id_producto;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_producto_activo ON detallepedido;
CREATE TRIGGER trg_producto_activo
BEFORE INSERT OR UPDATE ON detallepedido
FOR EACH ROW EXECUTE FUNCTION fn_producto_activo();

CREATE OR REPLACE FUNCTION fn_stock_suficiente()
RETURNS TRIGGER AS $$
DECLARE
    v_stock INTEGER;
BEGIN
    SELECT stock_actual INTO v_stock FROM producto WHERE id = NEW.id_producto;
    IF NEW.cantidad > v_stock THEN
        RAISE EXCEPTION 'Stock insuficiente del producto %: hay % y se pidieron %',
            NEW.id_producto, v_stock, NEW.cantidad;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_stock_suficiente ON detallepedido;
CREATE TRIGGER trg_stock_suficiente
BEFORE INSERT OR UPDATE ON detallepedido
FOR EACH ROW EXECUTE FUNCTION fn_stock_suficiente();

CREATE OR REPLACE FUNCTION fn_total_pedido()
RETURNS TRIGGER AS $$
DECLARE
    v_pedido INTEGER := COALESCE(NEW.id_pedido, OLD.id_pedido);
BEGIN
    UPDATE pedido SET total = COALESCE((
        SELECT SUM(d.cantidad * d.precio_unitario)
        FROM detallepedido d
        WHERE d.id_pedido = v_pedido
    ), 0)
    WHERE id = v_pedido;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_total_pedido ON detallepedido;
CREATE TRIGGER trg_total_pedido
AFTER INSERT OR DELETE OR UPDATE ON detallepedido
FOR EACH ROW EXECUTE FUNCTION fn_total_pedido();

-- ------------------------------------------------------------
-- 5. Índices
-- ------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_producto_categoria ON producto(categoria_id);
CREATE INDEX IF NOT EXISTS idx_producto_activo ON producto(activo);
CREATE INDEX IF NOT EXISTS idx_pedido_cliente ON pedido(cliente_id);
CREATE INDEX IF NOT EXISTS idx_pedido_estado ON pedido(estado);
CREATE INDEX IF NOT EXISTS idx_pedido_fecha_creacion ON pedido(fecha_creacion);
CREATE INDEX IF NOT EXISTS idx_pedido_fecha_pago ON pedido(fecha_pago);
CREATE INDEX IF NOT EXISTS idx_detalle_pedido ON detallepedido(id_pedido);
CREATE INDEX IF NOT EXISTS idx_detalle_producto ON detallepedido(id_producto);
CREATE INDEX IF NOT EXISTS idx_usuario_correo ON usuario(correo);
CREATE INDEX IF NOT EXISTS idx_usuario_rol ON usuario(rol);
CREATE INDEX IF NOT EXISTS idx_password_reset_lookup ON password_reset(token_hash) WHERE usado_en IS NULL;
CREATE INDEX IF NOT EXISTS idx_pago_pedido ON pago(pedido_id);
CREATE INDEX IF NOT EXISTS idx_pago_estado ON pago(estado);
CREATE INDEX IF NOT EXISTS idx_movimiento_caja_sesion ON movimiento_caja(caja_sesion_id);
CREATE INDEX IF NOT EXISTS idx_movimiento_caja_fecha ON movimiento_caja(creado_en);
CREATE UNIQUE INDEX IF NOT EXISTS ux_caja_sesion_abierta ON caja_sesion (estado) WHERE estado = 'ABIERTA';
CREATE INDEX IF NOT EXISTS idx_movimiento_stock_producto ON movimiento_stock(producto_id);
CREATE INDEX IF NOT EXISTS idx_movimiento_stock_fecha ON movimiento_stock(creado_en);
CREATE INDEX IF NOT EXISTS idx_pedido_historial_pedido ON pedido_historial_estado(pedido_id);
CREATE INDEX IF NOT EXISTS idx_auditoria_usuario ON auditoria(usuario_id);
CREATE INDEX IF NOT EXISTS idx_auditoria_tabla ON auditoria(tabla);
CREATE INDEX IF NOT EXISTS idx_auditoria_fecha ON auditoria(creado_en);

-- ------------------------------------------------------------
-- 6. Configuración inicial
-- ------------------------------------------------------------
INSERT INTO config_sistema (clave, valor, descripcion) VALUES
    ('nombre_tienda', 'CoffeeAndtea', 'Nombre visible de la tienda'),
    ('tiempo_estimado_pedidos_min', '20', 'Tiempo de respaldo si un producto no tiene tiempo configurado'),
    ('capacidad_preparacion_simultanea', '2', 'Productos que la cocina puede preparar al mismo tiempo'),
    ('buffer_preparacion_porcentaje', '15', 'Margen de seguridad sobre el tiempo de preparación'),
    ('stock_minimo_alerta', '5', 'Stock mínimo por defecto para alertas'),
    ('moneda', 'COP', 'Moneda usada en la tienda'),
    ('pagina_abierta', 'true', 'Permite recibir pedidos web'),
    ('direccion_tienda', 'Dirección pendiente de configurar', 'Dirección física para recogida'),
    ('telefono_tienda', '', 'Teléfono de contacto de la tienda'),
    ('correo_contacto', 'vegaperezkevinsantiago@gmail.com', 'Correo público de contacto'),
    ('horario_tienda', 'Lun a sáb, 8:00 a 20:00', 'Horario de atención')
ON CONFLICT (clave) DO NOTHING;

-- ------------------------------------------------------------
-- 7. Procedimientos de reportes
-- ------------------------------------------------------------
-- Se eliminan solo las versiones con refcursor (código, no datos)
-- para poder cambiar sus parámetros de salida.
DROP PROCEDURE IF EXISTS sp_reporte_ventas_diarias(refcursor);
DROP PROCEDURE IF EXISTS sp_reporte_top_productos(refcursor);
DROP PROCEDURE IF EXISTS sp_reporte_clientes_vip(refcursor);
DROP PROCEDURE IF EXISTS sp_reporte_bajo_stock(integer, refcursor);
DROP PROCEDURE IF EXISTS sp_reporte_ingresos_categoria(refcursor);

CREATE OR REPLACE PROCEDURE sp_reporte_ventas_diarias(cursor_name refcursor)
LANGUAGE plpgsql AS $$
BEGIN
    OPEN cursor_name FOR
        SELECT
            p.id AS pedido_id,
            c.nombre AS cliente,
            p.total,
            p.fecha_creacion
        FROM pedido p
        JOIN cliente c ON c.id = p.cliente_id
        WHERE p.estado IN ('PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
          AND COALESCE(p.fecha_pago, p.fecha_creacion)::date = CURRENT_DATE
        ORDER BY p.id DESC;
END;
$$;

CREATE OR REPLACE PROCEDURE sp_reporte_top_productos(cursor_name refcursor)
LANGUAGE plpgsql AS $$
BEGIN
    OPEN cursor_name FOR
        SELECT
            pr.nombre AS producto,
            SUM(d.cantidad) AS total_vendido,
            SUM(d.cantidad * d.precio_unitario) AS ingresos_generados
        FROM detallepedido d
        JOIN producto pr ON pr.id = d.id_producto
        JOIN pedido p ON p.id = d.id_pedido
        WHERE p.estado IN ('PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
        GROUP BY pr.id, pr.nombre
        ORDER BY total_vendido DESC, ingresos_generados DESC
        LIMIT 5;
END;
$$;

CREATE OR REPLACE PROCEDURE sp_reporte_clientes_vip(cursor_name refcursor)
LANGUAGE plpgsql AS $$
BEGIN
    OPEN cursor_name FOR
        SELECT
            c.nombre AS cliente,
            c.correo,
            c.telefono,
            COUNT(p.id) AS cantidad_pedidos,
            SUM(p.total) AS total_gastado
        FROM cliente c
        JOIN pedido p ON p.cliente_id = c.id
        WHERE p.estado IN ('PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
        GROUP BY c.id, c.nombre, c.correo, c.telefono
        HAVING SUM(p.total) > 20
        ORDER BY total_gastado DESC;
END;
$$;

CREATE OR REPLACE PROCEDURE sp_reporte_bajo_stock(limite INTEGER, cursor_name refcursor)
LANGUAGE plpgsql AS $$
BEGIN
    OPEN cursor_name FOR
        SELECT
            pr.nombre AS producto,
            pr.stock_actual,
            c.nombre AS categoria,
            pr.precio
        FROM producto pr
        JOIN categoria c ON c.id = pr.categoria_id
        WHERE pr.activo = TRUE
          AND pr.stock_actual < limite
        ORDER BY pr.stock_actual ASC, pr.nombre ASC;
END;
$$;

CREATE OR REPLACE PROCEDURE sp_reporte_ingresos_categoria(cursor_name refcursor)
LANGUAGE plpgsql AS $$
BEGIN
    OPEN cursor_name FOR
        SELECT
            c.nombre AS categoria,
            COUNT(DISTINCT p.id) AS pedidos_con_esta_categoria,
            SUM(d.cantidad) AS unidades_vendidas,
            SUM(d.cantidad * d.precio_unitario) AS ingresos_totales
        FROM detallepedido d
        JOIN producto pr ON pr.id = d.id_producto
        JOIN categoria c ON c.id = pr.categoria_id
        JOIN pedido p ON p.id = d.id_pedido
        WHERE p.estado IN ('PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
        GROUP BY c.id, c.nombre
        ORDER BY ingresos_totales DESC;
END;
$$;

COMMIT;
