# Migraciones — CoffeeAndtea

## 001_migracion_inicial.sql

Migra los datos históricos de la base anterior `CoffeeAndTea` a la estructura
nueva. No crea usuarios ni contraseñas: los clientes históricos quedan sin
cuenta y el administrador se crea con `bin/crear-admin.php`.

### Qué migra

| Origen | Destino | Notas |
|---|---:|---|
| `categoria` | 6 | Se conservan IDs, nombre, descripción y estado. |
| `cliente` | 11 | Quedan sin `usuario_id` hasta que reclamen su cuenta. |
| `producto` | 15 | Se conservan precio, stock y categoría; se completan valores por defecto. |
| `pedido` | 12 | `ABIERTO → PENDIENTE`, `PAGADO → PAGADO`, `CANCELADO → CANCELADO`. |
| `detallepedido` | 22 | El precio unitario se congela con el precio actual del producto. |

- Los pedidos migrados llevan `canal = 'MOSTRADOR'`, código `CAF-000001` en
  adelante y un token público aleatorio.
- Los pedidos `PAGADO` usan la antigua `fecha_cierre` como `fecha_pago`.
- Los pedidos `CANCELADO` conservan la fecha y un motivo genérico.
- El origen no guardaba pagos, caja ni método de pago; por eso no se inventan
  registros en `pago`, `movimiento_caja` ni `movimiento_stock`.
- Se registra un evento `MIGRACION_DATOS` en `auditoria`.

### Requisitos

1. La base destino debe estar vacía y tener cargado `db/schema.sql`
   (la tabla `config_sistema` puede tener sus valores iniciales).
2. El usuario de PostgreSQL debe poder crear la extensión `dblink`.
3. La base original **no se modifica**: la migración solo lee de ella.

### Ejecución

```powershell
$env:PGPASSWORD='tu_clave'

# 1. Crear la base destino desde cero
createdb -U postgres -h 127.0.0.1 coffeeandtea_migracion
psql -U postgres -h 127.0.0.1 -d coffeeandtea_migracion -v ON_ERROR_STOP=1 -f db\schema.sql

# 2. Cadena de conexión al origen (no se guarda en archivos)
$env:ORIGEN_DSN='host=127.0.0.1 port=5432 dbname=CoffeeAndTea user=postgres password=tu_clave'

# 3. Ejecutar la migración
psql -U postgres -h 127.0.0.1 -d coffeeandtea_migracion -v ON_ERROR_STOP=1 `
     -f db\migraciones\001_migracion_inicial.sql
```

Si cualquier validación falla, la transacción se revierte por completo.

### Verificación

```sql
SELECT 'categorias' AS tabla, count(*) FROM categoria
UNION ALL SELECT 'clientes', count(*) FROM cliente
UNION ALL SELECT 'productos', count(*) FROM producto
UNION ALL SELECT 'pedidos', count(*) FROM pedido
UNION ALL SELECT 'detalles', count(*) FROM detallepedido;

-- Debe devolver 0 filas: ningún total quedó descuadrado.
SELECT p.id, p.total, SUM(d.cantidad * d.precio_unitario) AS calculado
FROM pedido p
JOIN detallepedido d ON d.id_pedido = p.id
GROUP BY p.id, p.total
HAVING p.total <> SUM(d.cantidad * d.precio_unitario);

-- Estados migrados
SELECT estado, count(*) FROM pedido GROUP BY estado ORDER BY estado;
```

Resultado esperado: 6 / 11 / 15 / 12 / 22, cero descuadres y estados
`PENDIENTE` (2), `PAGADO` (9) y `CANCELADO` (1).

### Después de migrar

1. Ejecutar `php bin/crear-admin.php` para crear el administrador.
2. Configurar la tienda en `admin.php?tab=configuracion`.
3. Abrir la caja con la base inicial real.
4. Verificar el correo con `admin.php?tab=configuracion` antes de publicar.

---

## 002_reembolso_parcial.sql

Actualización para bases existentes creadas antes de la política de reembolso
parcial. Agrega `pago.monto_reembolsado` y completa los reembolsos históricos
con el monto total del pago.

```powershell
psql -U postgres -h 127.0.0.1 -d CoffeeAndTea `
  -v ON_ERROR_STOP=1 -f db\migraciones\002_reembolso_parcial.sql
```

Las instalaciones nuevas ya incluyen la columna en `db/schema.sql` y no
necesitan ejecutar este archivo.

---

## 003_tiempo_estimado.sql

Actualización para bases existentes que aún no tienen los parámetros del tiempo
estimado por producto. Agrega:

- `capacidad_preparacion_simultanea` (por defecto `2`).
- `buffer_preparacion_porcentaje` (por defecto `15`).

```powershell
psql -U postgres -h 127.0.0.1 -d CoffeeAndTea `
  -v ON_ERROR_STOP=1 -f db\migraciones\003_tiempo_estimado.sql
```

Las instalaciones nuevas ya incluyen estas claves en `db/schema.sql`.


