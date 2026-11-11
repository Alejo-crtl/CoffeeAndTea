-- ============================================================
-- BLOQUE B11 · BASE DE DATOS
-- CoffeeAndtea - Actualización: tiempo estimado por producto
--
-- Agrega la capacidad de preparación simultánea y el buffer de
-- seguridad usados por el cálculo del tiempo estimado.
--
-- Uso:
--   psql -U postgres -h 127.0.0.1 -d CoffeeAndTea `
--        -v ON_ERROR_STOP=1 -f db/migraciones/003_tiempo_estimado.sql
-- ============================================================

BEGIN;

INSERT INTO config_sistema (clave, valor, descripcion) VALUES
    ('capacidad_preparacion_simultanea', '2', 'Productos que la cocina puede preparar al mismo tiempo'),
    ('buffer_preparacion_porcentaje', '15', 'Margen de seguridad sobre el tiempo de preparación')
ON CONFLICT (clave) DO NOTHING;

UPDATE config_sistema
SET descripcion = 'Tiempo de respaldo si un producto no tiene tiempo configurado'
WHERE clave = 'tiempo_estimado_pedidos_min';

COMMIT;

\echo 'Actualización aplicada: configuración de tiempo estimado disponible.'
