-- ============================================================
-- CoffeeAndTea - Datos de demostración para QA
-- Contraseñas:
--   admin@coffeeandtea.local      -> Admin123!
--   encargado@coffeeandtea.local  -> Encargado123!
--   cliente@coffeeandtea.local    -> Cliente123!
-- Solo para CoffeeAndTea_qa. Nunca usar en producción.
-- ============================================================

INSERT INTO usuario (id, nombre, correo, password_hash, rol, activo) VALUES
    (1, 'Kevin Vega Perez', 'vegaperezkevinsantiago@gmail.com',
     '$2y$10$cRqerumdlhWkeeQLqpRtWurGrgv5sXr2CdSmLy30o2ywHSm0rhoqC', 'ADMIN', true),
    (2, 'Encargado Turno', 'encargado@coffeeandtea.local',
     '$2y$10$3knJh1/xOCNMp3Kc3LDl2.LowwD85x8QcJSNH6XtlIFlshX2M.oxe', 'ENCARGADO', true),
    (3, 'Cliente Demo', 'cliente@coffeeandtea.local',
     '$2y$10$tFD8QGBRuxP7FwwP/rkwNOjdn6MXq7hW4YwbU6cFjUJTfVMNeDyTi', 'CLIENTE', true);

INSERT INTO categoria (id, nombre, descripcion) VALUES
    (1, 'Bebidas Calientes', 'Café, té e infusiones'),
    (2, 'Bebidas Frías', 'Frappés, jugos y bebidas heladas'),
    (3, 'Panadería', 'Panes, croissants y hojaldres'),
    (4, 'Postres', 'Tortas, galletas y dulces'),
    (5, 'Desayunos', 'Combos de desayuno'),
    (6, 'Otros', 'Artículos varios');

INSERT INTO cliente (id, nombre, correo, telefono, consentimiento, activo, usuario_id, reclamado_en) VALUES
    (1, 'Juan Pérez', 'juan@example.com', '3001234567', true, true, NULL, NULL),
    (2, 'María Gómez', 'maria@example.com', '3007654321', true, true, NULL, NULL),
    (3, 'Cliente Demo', 'cliente@coffeeandtea.local', '3005551234', true, true, 3, CURRENT_TIMESTAMP);

INSERT INTO producto
    (id, nombre, descripcion, precio, stock_actual, stock_minimo, categoria_id, activo, destacado, unidades_medida, tiempo_preparacion_min)
VALUES
    (1, 'Café Express', 'Café negro intenso de 8 oz', 3500, 50, 10, 1, true, true, 'unidades', 5),
    (2, 'Capuchino', 'Café con leche espumada', 5500, 40, 10, 1, true, true, 'unidades', 8),
    (3, 'Té Negro', 'Té negro caliente', 2800, 30, 5, 1, true, false, 'unidades', 5),
    (4, 'Frappé de Café', 'Bebida fría con café y crema', 7500, 25, 5, 2, true, true, 'unidades', 10),
    (5, 'Jugo Natural', 'Jugo de naranja recién hecho', 4500, 20, 5, 2, true, false, 'unidades', 7),
    (6, 'Croissant', 'Croissant de mantequilla', 4500, 20, 5, 3, true, true, 'unidades', 3),
    (7, 'Pan de Queso', 'Pan de queso horneado', 3200, 35, 10, 3, true, false, 'unidades', 3),
    (8, 'Galleta Integral', 'Galleta de avena y pasas', 2200, 100, 20, 4, true, false, 'unidades', 2),
    (9, 'Torta de Chocolate', 'Porción de torta', 6000, 12, 3, 4, true, true, 'unidades', 4),
    (10, 'Combo Café y Croissant', 'Café + croissant', 8000, 15, 5, 5, true, true, 'unidades', 8);

INSERT INTO pedido
    (id, codigo, token_publico, cliente_id, estado, observaciones, canal, metodo_pago, tiempo_estimado_min,
     fecha_creacion, fecha_pago, fecha_cierre)
VALUES
    (1, 'CAF-0001', 'demo0000000000000000000000000001', 1, 'PENDIENTE', 'Sin azúcar', 'WEB', 'EFECTIVO', 20,
     CURRENT_TIMESTAMP - INTERVAL '10 minutes', NULL, NULL),
    (2, 'CAF-0002', 'demo0000000000000000000000000002', 1, 'PAGADO', NULL, 'WEB', 'EFECTIVO', 20,
     CURRENT_TIMESTAMP - INTERVAL '2 hours', CURRENT_TIMESTAMP - INTERVAL '90 minutes', NULL),
    (3, 'CAF-0003', 'demo0000000000000000000000000003', 2, 'EN_PREPARACION', 'Para llevar', 'WEB', 'TARJETA', 25,
     CURRENT_TIMESTAMP - INTERVAL '40 minutes', CURRENT_TIMESTAMP - INTERVAL '30 minutes', NULL),
    (4, 'CAF-0004', 'demo0000000000000000000000000004', 2, 'LISTO', NULL, 'WEB', 'EFECTIVO', 20,
     CURRENT_TIMESTAMP - INTERVAL '3 hours', CURRENT_TIMESTAMP - INTERVAL '150 minutes', NULL),
    (5, 'CAF-0005', 'demo0000000000000000000000000005', 3, 'ENTREGADO', NULL, 'WEB', 'TARJETA', 15,
     CURRENT_TIMESTAMP - INTERVAL '1 day', CURRENT_TIMESTAMP - INTERVAL '23 hours', CURRENT_TIMESTAMP - INTERVAL '22 hours'),
    (6, 'CAF-0006', 'demo0000000000000000000000000006', 3, 'CANCELADO', 'Cliente no pudo recoger', 'WEB', 'EFECTIVO', 20,
     CURRENT_TIMESTAMP - INTERVAL '2 days', CURRENT_TIMESTAMP - INTERVAL '47 hours', CURRENT_TIMESTAMP - INTERVAL '46 hours');

INSERT INTO detallepedido (id, id_pedido, id_producto, cantidad, precio_unitario) VALUES
    (1, 1, 1, 2, 3500),
    (2, 1, 6, 1, 4500),
    (3, 2, 2, 1, 5500),
    (4, 2, 9, 2, 6000),
    (5, 3, 4, 2, 7500),
    (6, 3, 8, 3, 2200),
    (7, 4, 10, 1, 12000),
    (8, 4, 5, 2, 4500),
    (9, 5, 1, 1, 3500),
    (10, 5, 7, 2, 3200),
    (11, 6, 2, 1, 5500);

INSERT INTO pedido_historial_estado
    (pedido_id, estado_anterior, estado_nuevo, cambiado_por, motivo)
VALUES
    (2, 'PENDIENTE', 'PAGADO', 2, 'Pago confirmado en caja'),
    (3, 'PENDIENTE', 'PAGADO', 2, 'Pago confirmado en caja'),
    (3, 'PAGADO', 'EN_PREPARACION', 2, 'Pedido en preparación'),
    (4, 'PENDIENTE', 'PAGADO', 2, 'Pago confirmado en caja'),
    (4, 'PAGADO', 'EN_PREPARACION', 2, 'Pedido en preparación'),
    (4, 'EN_PREPARACION', 'LISTO', 2, 'Pedido listo para recoger'),
    (5, 'PENDIENTE', 'PAGADO', 2, 'Pago confirmado en caja'),
    (5, 'PAGADO', 'EN_PREPARACION', 2, 'Pedido en preparación'),
    (5, 'EN_PREPARACION', 'LISTO', 2, 'Pedido listo'),
    (5, 'LISTO', 'ENTREGADO', 2, 'Entregado al cliente'),
    (6, 'PENDIENTE', 'CANCELADO', 2, 'Cliente no pudo recoger');

INSERT INTO caja_sesion (id, abierto_por, base_inicial, estado) VALUES
    (1, 2, 50000, 'ABIERTA');

INSERT INTO pago
    (pedido_id, monto, metodo, referencia, recibido, cambio, estado, creado_por, creado_en)
VALUES
    (2, 17500, 'EFECTIVO', NULL, 20000, 2500, 'CONFIRMADO', 2, CURRENT_TIMESTAMP - INTERVAL '90 minutes'),
    (3, 21600, 'TARJETA', 'TRX-001', NULL, NULL, 'CONFIRMADO', 2, CURRENT_TIMESTAMP - INTERVAL '30 minutes'),
    (4, 21000, 'EFECTIVO', NULL, 25000, 4000, 'CONFIRMADO', 2, CURRENT_TIMESTAMP - INTERVAL '150 minutes'),
    (5, 9900, 'TARJETA', 'TRX-002', NULL, NULL, 'CONFIRMADO', 2, CURRENT_TIMESTAMP - INTERVAL '23 hours');

INSERT INTO movimiento_caja
    (caja_sesion_id, tipo, monto, concepto, related_type, related_id, creado_por, creado_en)
VALUES
    (1, 'INGRESO', 17500, 'Pago pedido #2', 'pedido', 2, 2, CURRENT_TIMESTAMP - INTERVAL '90 minutes'),
    (1, 'INGRESO', 21000, 'Pago pedido #4', 'pedido', 4, 2, CURRENT_TIMESTAMP - INTERVAL '150 minutes');

SELECT setval('usuario_id_seq', 3, true);
SELECT setval('categoria_id_seq', 6, true);
SELECT setval('cliente_id_seq', 3, true);
SELECT setval('producto_id_seq', 10, true);
SELECT setval('pedido_id_seq', 6, true);
SELECT setval('detallepedido_id_seq', 11, true);
SELECT setval('pago_id_seq', 4, true);
SELECT setval('caja_sesion_id_seq', 1, true);
SELECT setval('movimiento_caja_id_seq', 2, true);
SELECT setval('pedido_historial_estado_id_seq', 11, true);

