-- ============================================================
-- BLOQUE B11 · BASE DE DATOS
-- CoffeeAndTea - Esquema completo
-- PostgreSQL 18
-- Orden: usuarios -> catálogo -> pedidos -> pagos/caja -> auditoría
-- ============================================================

-- ------------------------------------------------------------
-- 1. Usuarios y autenticación
-- ------------------------------------------------------------
CREATE TABLE usuario (
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

CREATE TABLE password_reset (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuario(id) ON DELETE CASCADE,
    token_hash VARCHAR(255) NOT NULL,
    expira_en TIMESTAMP WITH TIME ZONE NOT NULL,
    usado_en TIMESTAMP WITH TIME ZONE,
    creado_en TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(45)
);

-- ------------------------------------------------------------
-- 2. Catálogo
-- ------------------------------------------------------------
CREATE TABLE categoria (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    activo BOOLEAN NOT NULL DEFAULT true,
    creado_en TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE cliente (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL CHECK (nombre <> ''),
    correo VARCHAR(150) NOT NULL UNIQUE,
    telefono VARCHAR(30),
    consentimiento BOOLEAN NOT NULL DEFAULT false,
    activo BOOLEAN NOT NULL DEFAULT true,
    creado_en TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_id INTEGER UNIQUE REFERENCES usuario(id) ON DELETE SET NULL,
    reclamado_en TIMESTAMP WITH TIME ZONE
);

CREATE TABLE producto (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL CHECK (nombre <> ''),
    descripcion TEXT,
    precio NUMERIC(12,2) NOT NULL CHECK (precio > 0),
    stock_actual INTEGER NOT NULL DEFAULT 0 CHECK (stock_actual >= 0),
    stock_minimo INTEGER NOT NULL DEFAULT 5 CHECK (stock_minimo >= 0),
    categoria_id INTEGER NOT NULL REFERENCES categoria(id),
    activo BOOLEAN NOT NULL DEFAULT true,
    destacado BOOLEAN NOT NULL DEFAULT false,
    imagen_url VARCHAR(255),
    unidades_medida VARCHAR(50) NOT NULL DEFAULT 'unidades',
    tiempo_preparacion_min INTEGER CHECK (tiempo_preparacion_min IS NULL OR tiempo_preparacion_min > 0),
    creado_en TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 3. Pedidos
-- ------------------------------------------------------------
CREATE TABLE pedido (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(12) NOT NULL UNIQUE,
    token_publico VARCHAR(64) NOT NULL UNIQUE,
    cliente_id INTEGER NOT NULL REFERENCES cliente(id),
    estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
        CHECK (estado IN ('PENDIENTE', 'PAGADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO', 'CANCELADO')),
    total NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (total >= 0),
    observaciones TEXT,
    canal VARCHAR(20) NOT NULL DEFAULT 'WEB' CHECK (canal IN ('WEB', 'MOSTRADOR')),
    metodo_pago VARCHAR(20) CHECK (metodo_pago IN ('EFECTIVO', 'TARJETA')),
    tiempo_estimado_min INTEGER,
    fecha_creacion TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_pago TIMESTAMP WITH TIME ZONE,
    fecha_cierre TIMESTAMP WITH TIME ZONE,
    motivo_cancelacion TEXT,
    cancelado_por INTEGER REFERENCES usuario(id),
    fecha_cancelacion TIMESTAMP WITH TIME ZONE
);

CREATE TABLE detallepedido (
    id SERIAL PRIMARY KEY,
    id_pedido INTEGER NOT NULL REFERENCES pedido(id),
    id_producto INTEGER NOT NULL REFERENCES producto(id),
    cantidad INTEGER NOT NULL CHECK (cantidad > 0),
    precio_unitario NUMERIC(12,2) NOT NULL CHECK (precio_unitario > 0),
    UNIQUE (id_pedido, id_producto)
);

CREATE TABLE pedido_historial_estado (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL REFERENCES pedido(id) ON DELETE CASCADE,
    estado_anterior VARCHAR(20),
    estado_nuevo VARCHAR(20) NOT NULL,
    cambiado_por INTEGER REFERENCES usuario(id),
    cambiado_en TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo TEXT
);

-- ------------------------------------------------------------
-- 4. Pagos, caja y stock
-- ------------------------------------------------------------
CREATE TABLE pago (
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

CREATE TABLE caja_sesion (
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

CREATE TABLE movimiento_caja (
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

CREATE TABLE movimiento_stock (
    id SERIAL PRIMARY KEY,
    producto_id INTEGER NOT NULL REFERENCES producto(id),
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('VENTA', 'DEVOLUCION', 'AJUSTE')),
    cantidad INTEGER NOT NULL CHECK (cantidad > 0),
    referencia VARCHAR(100),
    creado_por INTEGER NOT NULL REFERENCES usuario(id),
    creado_en TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 5. Auditoría y configuración
-- ------------------------------------------------------------
CREATE TABLE auditoria (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER REFERENCES usuario(id) ON DELETE SET NULL,
    accion VARCHAR(50) NOT NULL,
    tabla VARCHAR(50) NOT NULL,
    registro_id INTEGER,
    detalles JSONB,
    ip VARCHAR(45),
    creado_en TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE config_sistema (
    id SERIAL PRIMARY KEY,
    clave VARCHAR(50) NOT NULL UNIQUE,
    valor TEXT NOT NULL,
    descripcion TEXT
);

-- ------------------------------------------------------------
-- 6. Funciones y triggers
-- ------------------------------------------------------------

-- Valida que el cliente del pedido esté activo.
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

CREATE TRIGGER trg_cliente_activo
BEFORE INSERT OR UPDATE ON pedido
FOR EACH ROW EXECUTE FUNCTION fn_cliente_activo();

-- fecha_cierre se completa al entregar o cancelar.
CREATE OR REPLACE FUNCTION fn_fecha_cierre()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.estado IN ('ENTREGADO', 'CANCELADO') AND NEW.fecha_cierre IS NULL THEN
        NEW.fecha_cierre := CURRENT_TIMESTAMP;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_fecha_cierre
BEFORE INSERT OR UPDATE ON pedido
FOR EACH ROW EXECUTE FUNCTION fn_fecha_cierre();

-- No se puede vender un producto inactivo.
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

CREATE TRIGGER trg_producto_activo
BEFORE INSERT OR UPDATE ON detallepedido
FOR EACH ROW EXECUTE FUNCTION fn_producto_activo();

-- Valida stock disponible. El descuento real se hace al confirmar el pago.
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

CREATE TRIGGER trg_stock_suficiente
BEFORE INSERT OR UPDATE ON detallepedido
FOR EACH ROW EXECUTE FUNCTION fn_stock_suficiente();

-- Recalcula el total usando el precio histórico del detalle.
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

CREATE TRIGGER trg_total_pedido
AFTER INSERT OR DELETE OR UPDATE ON detallepedido
FOR EACH ROW EXECUTE FUNCTION fn_total_pedido();

-- ------------------------------------------------------------
-- 7. Índices
-- ------------------------------------------------------------
CREATE INDEX idx_producto_categoria ON producto(categoria_id);
CREATE INDEX idx_producto_activo ON producto(activo);
CREATE INDEX idx_pedido_cliente ON pedido(cliente_id);
CREATE INDEX idx_pedido_estado ON pedido(estado);
CREATE INDEX idx_pedido_fecha_creacion ON pedido(fecha_creacion);
CREATE INDEX idx_pedido_fecha_pago ON pedido(fecha_pago);
CREATE INDEX idx_detalle_pedido ON detallepedido(id_pedido);
CREATE INDEX idx_detalle_producto ON detallepedido(id_producto);
CREATE INDEX idx_usuario_correo ON usuario(correo);
CREATE INDEX idx_usuario_rol ON usuario(rol);
CREATE INDEX idx_password_reset_lookup ON password_reset(token_hash) WHERE usado_en IS NULL;
CREATE INDEX idx_pago_pedido ON pago(pedido_id);
CREATE INDEX idx_pago_estado ON pago(estado);
CREATE INDEX idx_movimiento_caja_sesion ON movimiento_caja(caja_sesion_id);
CREATE INDEX idx_movimiento_caja_fecha ON movimiento_caja(creado_en);
-- Solo puede existir una caja abierta a la vez.
CREATE UNIQUE INDEX ux_caja_sesion_abierta ON caja_sesion (estado) WHERE estado = 'ABIERTA';
CREATE INDEX idx_movimiento_stock_producto ON movimiento_stock(producto_id);
CREATE INDEX idx_movimiento_stock_fecha ON movimiento_stock(creado_en);
CREATE INDEX idx_pedido_historial_pedido ON pedido_historial_estado(pedido_id);
CREATE INDEX idx_auditoria_usuario ON auditoria(usuario_id);
CREATE INDEX idx_auditoria_tabla ON auditoria(tabla);
CREATE INDEX idx_auditoria_fecha ON auditoria(creado_en);

-- ------------------------------------------------------------
-- 8. Configuración inicial
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
    ('horario_tienda', 'Lun a sáb, 8:00 a 20:00', 'Horario de atención');

-- ------------------------------------------------------------
-- 9. Reportes con refcursor
-- ------------------------------------------------------------
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

-- Fin del esquema
