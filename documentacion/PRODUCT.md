# CoffeeAndTea — Contexto del producto

**Versión:** 1.1 (sistema final, 08/11/2026)  
**Implementación de referencia:** `CoffeeAndtea_Entrega` (PHP 8.3 + PostgreSQL 18).

Sistema de tienda y gestión para una cafetería de barrio. El cliente pide en
línea, **recoge en tienda** y paga en el mostrador. El personal cobra, controla
caja, stock, pedidos, usuarios, reportes y auditoría.

La versión 1.0 describía un prototipo de cinco tablas y un front-end React sin
backend. La versión 1.1 documenta el sistema final con roles, autenticación,
pagos, caja, stock, cancelaciones, manuales por rol y migración de datos.

---

## Problema real

Una cafetería lleva sus pedidos en cuaderno o en chats: se anota a mano, se
suman los precios de memoria y al final del día nadie sabe con precisión qué se
vendió más ni cuánto se facturó. Los errores de cobro aparecen tarde y no hay
trazabilidad del efectivo, del stock ni de las cancelaciones.

CoffeeAndTea reemplaza ese proceso con un flujo claro:

1. El cliente pide desde el navegador.
2. Recibe un código y un enlace de consulta.
3. Paga en el mostrador, en efectivo o con tarjeta.
4. El personal descuenta stock, controla caja y cambia el estado.
5. Todo queda auditado.

---

## Alcance

### Incluido

- Tienda pública: catálogo, carrito, checkout y consulta por código + token.
- Roles `CLIENTE`, `ENCARGADO` y `ADMIN`, además del visitante anónimo.
- Registro, login, recuperación de contraseña y cambio forzado.
- Cobro en efectivo o tarjeta; el datáfono es externo.
- Caja: apertura, ingresos, egresos y cierre con diferencia.
- Stock: descuento al pagar, devolución al cancelar y ajuste manual.
- Cancelaciones con motivo, reembolso y auditoría.
- Pedidos recientes de invitado en el navegador.
- Manuales diferenciados por rol.
- Reportes, CSV, migración y pruebas automatizadas.

### Fuera de alcance

- Facturación electrónica.
- Domicilios.
- Pasarela de pago en línea.
- Carga de imágenes de productos.
- Aplicación móvil nativa.
- Integración con proveedores.

---

## Módulos funcionales

| Código | Módulo | Función |
|---|---|---|
| RF-AUT | Autenticación | Registro, login, bloqueo, recuperación y cambio de contraseña. |
| RF-RBAC | Control de acceso | Permisos por rol y restricciones del personal interno. |
| RF-TIE | Tienda | Catálogo, carrito, checkout, consulta y pedidos recientes de invitado. |
| RF-CAJ | Caja y pagos | Efectivo, tarjeta, apertura, movimientos y cierre. |
| RF-CAN | Cancelaciones | Motivo, reembolso del 100 % o 50 %, stock y auditoría. |
| RF-PED | Pedidos | Estados, historial y transiciones controladas. |
| RF-STO | Stock | Venta, devolución y ajuste manual. |
| RF-ADM | Administración | Usuarios, productos, categorías, configuración y último admin. |
| RF-REP | Reportes | Ventas, productos, clientes, stock e ingresos por categoría. |
| RF-MIG | Migración | Traslado conciliado desde la base anterior CoffeeAndTea. |
| RF-AUD | Auditoría | Registro de acciones sensibles. |
| RF-SEG | Seguridad | CSRF, sesión, cookies y RBAC. |
| RF-MAN | Manuales | Manual de usuario, operación y administración. |
| RF-UI | Experiencia | Avisos, línea de tiempo de estados y política de cancelación. |

---

## Reglas de negocio

1. **Solo recogida:** no hay domicilios.
2. **Pago en mostrador:** efectivo o tarjeta; no se cobra en línea.
3. **Stock al pagar:** el stock se descuenta únicamente al confirmar el pago.
4. **Precio histórico:** `detallepedido.precio_unitario` congela el precio.
5. **Caja obligatoria para efectivo:** solo puede existir una caja abierta.
6. **Cierre de caja:** `base + ingresos − egresos`; la diferencia se audita.
7. **Estados:** `PENDIENTE → PAGADO → EN_PREPARACION → LISTO → ENTREGADO`.
8. **Cancelación:** exige motivo; no aplica a `ENTREGADO` ni `CANCELADO`.
9. **Reembolso:** 100 % antes de preparación; 50 % en preparación o listo. El
   checkout exige aceptar esta política.
10. **Personal interno:** `ENCARGADO` y `ADMIN` no usan carrito, checkout ni
    “Mi cuenta”. La restricción se aplica en el servidor.
11. **Último administrador:** no se puede desactivar ni degradar.
12. **Pedidos de invitado:** se guardan hasta 5 códigos/tokens en una cookie
    HttpOnly durante 90 días.
13. **Manuales:** cada rol ve su documento correspondiente.
14. **Auditoría:** login, pagos, cancelaciones, caja, stock y cambios sensibles.
15. **Tiempo estimado:** se calcula con los tiempos de cada producto, la
    capacidad simultánea de la cocina, un buffer del 15 % y la carga de pedidos
    activos. El admin puede variar el tiempo de cada producto.

---

## Arquitectura

- **Base de datos:** PostgreSQL 18, base `CoffeeAndTea`, 14 tablas en tercera
  forma normal. El esquema final está en `db/schema.sql`.
- **Backend:** PHP 8.3 con PDO, sesiones, CSRF, RBAC y SMTP.
- **Docroot:** `public/`; la lógica vive en `app/php/lib/` y las vistas en
  `app/php/vistas/`.
- **Objetos de base:** triggers de stock, total, cliente activo, producto activo
  y fecha de cierre; procedimientos `sp_reporte_*`; índice único parcial de caja
  abierta.
- **Pruebas:** runner PHP propio, PHPUnit opcional y Playwright E2E.
- **Documentación:** SRS v1.1, MER v1.1, manuales por rol y QA.

La carpeta `_historico/Prototipo_React_v1.0` corresponde al prototipo React de la versión
1.0 y se conserva como referencia histórica. La implementación final es la
aplicación PHP de `CoffeeAndtea_Entrega`.

---

## Modo de la interfaz

La tienda pública prioriza claridad y confianza; la gestión prioriza velocidad
y escaneabilidad.

- Totales y acciones principales visibles.
- Números tabulares y alineados a la derecha.
- Estados con color **y** etiqueta.
- Línea de tiempo con los 5 estados y check verde en los alcanzados.
- Diseño responsive para celular, tablet y escritorio.
- Mensajes en español que explican qué pasó y qué hacer.

---

## Dirección visual

Cafetería: marfil cálido, espresso y un acento de matcha. Sin gradientes
decorativos ni sombras difusas; el color se gana con contraste. Tipografía sans
del sistema, con cifras tabulares en importes.

---

## Restricciones de datos

- `NUMERIC(12,2)` para dinero; nunca punto flotante.
- `pago.monto_reembolsado` distingue el reembolso del 100 % del 50 %.
- Los pedidos históricos no cambian cuando cambia el precio del producto.
- No se guardan contraseñas en texto plano.
- No se incluyen `.env`, secretos, `vendor/`, `node_modules/` ni logs en la
  entrega.

---

## Voz

Colombiana, directa y sin floreos. “Café”, no “bebida de café”. Al usuario se le
dice qué pasó y qué hacer, no qué componente falló.

---

## Documentos relacionados

- `01_Documentacion/SRS_CoffeeAndTea_v1.1.docx`
- `03_Diagramas/Documentacion_MER_CoffeeAndTea_v1.1.docx`
- `03_Diagramas/Diagrama_MER_CoffeeAndTea_v1.1.drawio`
- `docs/manual.md`
- `docs/manual-encargado.md`
- `docs/manual-admin.md`
- `qa/`
