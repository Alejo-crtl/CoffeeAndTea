# Informe de resultados — CoffeeAndtea

**Fecha de ejecución:** 2026-11-10  
**Revisión de documentos:** 2026-11-11  
**Entorno:** Desarrollo, PostgreSQL 18, base `coffeeandtea_qa`, PHP 8.3.36  
**Servidor:** Apache/Laragon — `http://coffeeandtea.test/`  
**Correo:** `MAIL_DRIVER=log` (sin envío real)

## 1. Resumen

| Métrica | Valor |
|---|---|
| Casos planificados | 55 |
| Ejecutados | 55 |
| Aprobados | 55 |
| Fallidos | 0 |
| Pendientes | 0 |
| Defectos críticos abiertos | 0 |
| Defectos altos abiertos | 0 |

## 2. Resultados por área

| Área | Casos | Resultado |
|---|---|---|
| Autenticación y sesión | CP-001 a CP-008 | Aprobado. Login, registro, recuperación (log), bloqueo por fuerza bruta y cambio forzado verificados. |
| RBAC | CP-009 a CP-011, CP-043 | Aprobado. Visitante → login; CLIENTE y ENCARGADO no acceden a administración; el personal interno no usa carrito ni cuenta. |
| Tienda y carrito | CP-012 a CP-020, CP-044, CP-046, CP-047 | Aprobado. Catálogo, stock, checkout, token público, precio histórico, pedidos recientes, tiempo estimado y copiar enlace. |
| Anexos y navegación | CP-048, CP-049 | Aprobado. Descarga de anexos y botones de regreso en las páginas. |
| Video de presentación | CP-054 | Aprobado. Enlace en el pie, página propia y aviso “Video próximamente”. |
| Manuales por rol | Revisión manual | Aprobado. Se abrió `manual.php` con visitante/CLIENTE, ENCARGADO y ADMIN; cada rol mostró el documento correcto. |
| Pagos y caja | CP-021 a CP-024, CP-031 a CP-034 | Aprobado. Efectivo con cambio, tarjeta con referencia, caja obligatoria, cierre cuadrado y faltante de $2.000. |
| Cancelaciones y estados | CP-025 a CP-030, CP-045 | Aprobado. Reembolso tarjeta/efectivo, devolución de stock, motivo obligatorio, transiciones válidas y reembolso del 50% en preparación. |
| Administración | CP-035 a CP-040, CP-050 a CP-053, CP-055 | Aprobado. Stock, usuarios, contraseña temporal, CRUD de producto, imágenes de producto sin recortes, protección del último admin y configuración. |
| Reportes | CP-041 | Aprobado. Reportes 200 y CSV con `Content-Type: text/csv`. |
| Migración | CP-042 | Aprobado. Migración ejecutada en base copia y conciliada con el origen. |

## 3. Evidencia de las pruebas clave

### Pedido web y stock
- Pedido `CAF-TG8VGX`: total $7.000, stock del producto 1 pasó de 50 a **50** al crear (no se descuenta).
- Pago en efectivo: stock 50 → **48**, pago `CONFIRMADO`, recibido $10.000, cambio $3.000, `INGRESO` de $7.000 en caja.
- Cancelación: stock 48 → **50**, pago `REEMBOLSADO`, `EGRESO` de $7.000.

### Pago con tarjeta
- Pedido `CAF-UMQW2P`: stock 40 → **39**, pago `CONFIRMADO` con referencia `TRX-QA-1`, sin movimiento de caja.
- Cancelación con reembolso tarjeta: stock 39 → **40**, pago `REEMBOLSADO` con referencia `REF-QA-1`, sin egreso de caja.

### Caja
- Cierre 1: base $50.000 + ingresos $45.500 − egresos $7.000 = **$88.500**; contado $88.500; diferencia **$0**.
- Cierre 2: base $30.000, contado $28.000; diferencia **−$2.000**, registrada en auditoría.
- Caja 3 abierta con base $50.000 para continuar operando.

### Estados
- Pedido `CAF-0002`: `PAGADO → EN_PREPARACION → LISTO → ENTREGADO`, con historial y auditoría por cada paso.
- Intento de cancelar un pedido `ENTREGADO`: rechazado, estado sin cambios.

### Reembolso parcial
- Pedido de prueba de $2.000 en `EN_PREPARACION`, cancelado con tarjeta: pago `REEMBOLSADO` con `monto_reembolsado` de **$1.000 (50%)** y stock devuelto.
- Pedido cancelado antes de preparación: reembolso del **100%**.

### Tiempo estimado
- Pedido de prueba: 2 Café Express (5 min) + 1 Croissant (3 min).
- Cola activa al momento: 42 min de trabajo; capacidad 2; buffer 15%.
- Cálculo: `ceil(13/2)=7`, buffer `ceil(7×0.15)=2`, cola `ceil(42/2)=21`; total **30 min**.
- El pedido guardó **30** y coincidió con el cálculo esperado.

### Anexos y navegación
- `anexos.php` muestra los 6 documentos y el footer enlaza a **Anexos**.
- Las 6 descargas responden 200 con `Content-Disposition: attachment`.
- El botón **Copiar enlace** genera un enlace sin `nuevo=1` y muestra confirmación o alternativa manual.
- Las páginas de acceso, privacidad, manual, reportes y detalle de gestión tienen botón de regreso.

### Imágenes de producto
- Se creó una categoría y un producto desde Administración; ambos aparecieron en la tienda y la categoría en los filtros del menú.
- La imagen subida se guardó en `public/media/productos` y se mostró **completa, sin recortes**, dentro de una tarjeta 3:2 con fondo crema.
- Se revisó el catálogo en 1440, 768 y 390 px; la tarjeta 3:2 mantiene la imagen completa y no la corta por arriba ni por abajo.
- Al reemplazar la imagen cambió la ruta y se eliminó el archivo anterior.
- Al marcar **Quitar la imagen actual**, `producto.imagen_url` quedó vacío y la tienda mostró el espacio reservado.
- Un archivo falso con extensión `.jpg` fue rechazado con el mensaje **Formato no permitido. Usa JPG, PNG o WebP.**
- Al desactivar el producto, desapareció de la tienda.
- Los productos, categorías y archivos temporales de la prueba se eliminaron al finalizar.

### Video de presentación
- El pie de página muestra el enlace **Video** junto a **Anexos**.
- `video.php` abre con el aviso **“Video próximamente”** cuando no hay URL configurada.
- Desde **Administración → Configuración** se validó un enlace inválido de Vimeo: el panel lo rechazó.
- Se guardó un enlace de YouTube y la página mostró el iframe de `youtube-nocookie.com`.
- Luego se vació el campo y la página volvió al aviso **“Video próximamente”**.
- La página no desborda horizontalmente en 390 px.

### Manuales por rol
- Se abrió `manual.php` como visitante/CLIENTE y se mostró `docs/manual.md`.
- Se abrió como ENCARGADO y se mostró `docs/manual-encargado.md`.
- Se abrió como ADMIN y se mostró `docs/manual-admin.md`.
- La descarga de cada manual correspondió al rol de la sesión.
- Resultado: revisión manual aprobada para RF-MAN-01, RF-MAN-02 y RF-MAN-03.

### Seguridad
- 8 intentos fallidos de login → bloqueo de 10 minutos.
- Cliente y encargado reciben redirección al intentar abrir `admin.php`.
- Token de restablecimiento inválido → HTTP 410.
- El último administrador activo no puede quitarse el rol ni desactivarse.

### Migración (CP-042)
Ejecución sobre la base copia `coffeeandtea_migracion`, creada desde `db/schema.sql`:

| Tabla | Origen | Destino |
|---|---:|---:|
| Categorías | 6 | 6 |
| Clientes | 11 | 11 |
| Productos | 15 | 15 |
| Pedidos | 12 | 12 |
| Detalles | 22 | 22 |

- Totales de los 12 pedidos conciliados: **0 diferencias** frente a la suma de detalles.
- Estados migrados: 2 `PENDIENTE`, 9 `PAGADO`, 1 `CANCELADO`.
- Códigos generados `CAF-000001` a `CAF-000012`; tokens públicos únicos.
- La base original `CoffeeAndTea` **no se modificó**.

## 4. Pruebas automatizadas

| Suite | Comando | Resultado |
|---|---|---|
| PHP unitarias + integración | `php tests/run.php` | **15/15 aprobadas** |
| Playwright E2E | `npx playwright test` | **16/16 aprobadas** |

Las pruebas de integración crean y eliminan sus propios datos en `coffeeandtea_qa`.
Las E2E cubren catálogo, carrito, checkout, confirmación, login por rol, RBAC,
restricciones del personal interno, pedidos recientes de invitado, validación
de consentimiento, gestión de imágenes de producto y página de video.

## 5. Defectos encontrados y corregidos

| ID | Descripción | Estado |
|---|---|---|
| INC-001 | `usuarioActual()` cacheaba el rol anterior al login y enviaba al administrador a `cuenta.php`. | Corregido: caché por ID de sesión. |
| INC-002 | El total del pedido se recalculaba con el precio actual del producto. | Corregido: se usa `detallepedido.precio_unitario`. |
| INC-003 | `privacidad.php` no cargaba `pedidos.php` y devolvía 500. | Corregido. |
| INC-004 | `scripts/iniciar.bat` servía la raíz en lugar de `public/`. | Corregido. |
| INC-005 | La migración requería escribir la contraseña de origen dentro del SQL. | Corregido: se lee de `ORIGEN_DSN` y no se persiste. |

## 6. Observaciones

- El correo se probó con `MAIL_DRIVER=log`; el envío real por Gmail queda pendiente de generar la contraseña de aplicación.
- La migración se ejecutó en `coffeeandtea_migracion`. Para producción: respaldar, migrar y conciliar antes de abrir la tienda.
- El tiempo estimado mostrado puede ser mayor al base por el recargo de cola (máximo +20 min).
- Las contraseñas demo de `qa/09-datos-prueba.md` son solo para desarrollo y deben cambiarse en producción.

## 7. Conclusión

El sistema cumple los flujos críticos de tienda, cobro, caja, stock, cancelación,
seguridad, imágenes de producto, video de presentación y migración de datos. Los
**55 casos de prueba están aprobados** y las suites automatizadas pasan completas.
La única dependencia externa pendiente es la contraseña de aplicación de Gmail
para el envío real de correos.
