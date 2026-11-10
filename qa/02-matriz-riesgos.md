# Matriz de riesgos — CoffeeAndtea

Probabilidad (P) e impacto (I): 1 = bajo, 2 = medio, 3 = alto.
Nivel = P × I. Prioridad: ≥6 alta, 3–5 media, 1–2 baja.

| ID | Riesgo | P | I | Nivel | Mitigación | Responsable |
|---|---|---|---|---|---|---|
| R-01 | Cobrar un pedido sin stock suficiente | 2 | 3 | 6 | Bloqueo con `SELECT ... FOR UPDATE` y actualización condicional de stock | Desarrollo |
| R-02 | Descontar stock al crear el pedido en lugar de al pagar | 1 | 3 | 3 | Regla implementada en `confirmarPago`; prueba CP-020 | Desarrollo |
| R-03 | Duplicar el descuento de stock al reintentar un pago | 2 | 3 | 6 | Transacción + validación de estado `PENDIENTE` | Desarrollo |
| R-04 | Cancelar un pedido pagado sin devolver stock | 1 | 3 | 3 | Devolución automática y movimiento `DEVOLUCION`; CP-024 | Desarrollo |
| R-05 | Reembolso en efectivo sin caja abierta | 2 | 3 | 6 | Se exige caja abierta y se registra `EGRESO`; CP-025 | Operación |
| R-06 | Diferencias de caja por movimientos no registrados | 2 | 2 | 4 | Cierre con efectivo contado y diferencia auditada | Operación |
| R-07 | Cambiar el precio de un producto altera pedidos históricos | 2 | 3 | 6 | `detallepedido.precio_unitario` congelado; CP-013 | Desarrollo |
| R-08 | Acceso de un cliente a gestión o administración | 2 | 3 | 6 | `requerirRol()` en cada página; matriz RBAC | Desarrollo |
| R-09 | Fuerza bruta contra el login | 2 | 2 | 4 | Límite de 8 intentos/10 min y auditoría | Desarrollo |
| R-10 | Secuestro de sesión | 1 | 3 | 3 | Cookies HttpOnly/SameSite, regeneración de ID, timeout | Desarrollo |
| R-11 | CSRF en formularios | 2 | 2 | 4 | Token por sesión y `exigirCsrf()` | Desarrollo |
| R-12 | Enumeración de correos en recuperación | 2 | 1 | 2 | Respuesta genérica siempre | Desarrollo |
| R-13 | Enlace de recuperación reutilizable | 1 | 3 | 3 | Token hasheado, un solo uso, vence en 60 min | Desarrollo |
| R-14 | Correo de producción no configurado | 2 | 2 | 4 | Prueba SMTP desde administración y registro de errores | Operación |
| R-15 | Pérdida de datos en la migración | 1 | 3 | 3 | Copia de respaldo + conciliación de conteos | Datos |
| R-16 | Exposición de secretos en la entrega | 2 | 3 | 6 | `.gitignore`, revisión final, sin `.env` | Entrega |
| R-17 | Caída del túnel público | 3 | 1 | 3 | URL fija con Cloudflare Tunnel nombrado o dominio propio | Operación |
| R-18 | Zona horaria incorrecta en fechas y cierres | 1 | 2 | 2 | `America/Bogota` en PHP y PostgreSQL | Desarrollo |
| R-19 | Pedidos duplicados por doble clic | 2 | 2 | 4 | Token CSRF + redirección PRG tras confirmar | Desarrollo |
| R-20 | Stock negativo por ajustes manuales | 1 | 3 | 3 | `CHECK (stock_actual >= 0)` y validación de entrada | Desarrollo |
| R-21 | Subir un archivo malicioso como imagen de producto | 2 | 3 | 6 | Validación MIME real, límite de 2 MB, recorte con GD, nombre aleatorio y bloqueo de scripts en `media/productos`; CP-053 | Desarrollo |
| R-22 | Archivos de imagen huérfanos al reemplazar o quitar la foto | 1 | 1 | 1 | Borrado posterior al commit y verificación de que otro producto no use la ruta; CP-051 y CP-052 | Desarrollo |
