-- ============================================================
-- BLOQUE B11 · BASE DE DATOS
-- CoffeeAndtea - Asignar imágenes a los productos existentes
--
-- Uso:
--   psql -U postgres -h 127.0.0.1 -d coffeeandtea_qa \
--        -v ON_ERROR_STOP=1 -f db/asignar-imagenes.sql
--
-- Es idempotente: solo cambia productos cuyo nombre coincide.
-- Las imágenes deben existir en public/media/productos/.
-- ============================================================

BEGIN;

UPDATE producto
SET imagen_url = CASE lower(trim(nombre))
    WHEN 'café express'            THEN 'media/productos/cafe-express.jpg'
    WHEN 'espresso'                THEN 'media/productos/espresso-sencillo.jpg'
    WHEN 'americano'               THEN 'media/productos/cafe-express.jpg'
    WHEN 'capuchino'               THEN 'media/productos/capuchino.jpg'
    WHEN 'cappuccino'              THEN 'media/productos/capuchino.jpg'
    WHEN 'latte'                   THEN 'media/productos/capuchino.jpg'
    WHEN 'té negro'                THEN 'media/productos/te-negro.jpg'
    WHEN 'té verde'                THEN 'media/productos/te-verde.jpg'
    WHEN 'frappé de café'          THEN 'media/productos/frappe-cafe.jpg'
    WHEN 'frappé de moca'          THEN 'media/productos/frappe-cafe.jpg'
    WHEN 'jugo natural'            THEN 'media/productos/jugo-natural.jpg'
    WHEN 'croissant'               THEN 'media/productos/croissant.jpg'
    WHEN 'pan de queso'            THEN 'media/productos/pan-de-queso.jpg'
    WHEN 'galleta integral'        THEN 'media/productos/galleta-integral.jpg'
    WHEN 'galletas de avena'       THEN 'media/productos/galletas-avena.jpg'
    WHEN 'torta de chocolate'      THEN 'media/productos/torta-chocolate.jpg'
    WHEN 'combo café y croissant'  THEN 'media/productos/combo-cafe-croissant.jpg'
    WHEN 'sándwich de pollo'       THEN 'media/productos/sandwich-pollo.jpg'
    WHEN 'ensalada césar'          THEN 'media/productos/ensalada-cesar.jpg'
    WHEN 'café en grano colombia'  THEN 'media/productos/cafe-en-grano.jpg'
    ELSE imagen_url
END
WHERE lower(trim(nombre)) IN (
    'café express', 'espresso', 'americano', 'capuchino', 'cappuccino', 'latte',
    'té negro', 'té verde', 'frappé de café', 'frappé de moca', 'jugo natural',
    'croissant', 'pan de queso', 'galleta integral', 'galletas de avena',
    'torta de chocolate', 'combo café y croissant', 'sándwich de pollo',
    'ensalada césar', 'café en grano colombia'
);

COMMIT;

-- Verificación:
-- SELECT id, nombre, imagen_url FROM producto ORDER BY id;
