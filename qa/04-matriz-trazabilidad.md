# Matriz de trazabilidad — CoffeeAndtea

Requisito → implementación → prueba. Estados: **Aprobado**, **Pendiente**, **N/A**.

| ID | Requisito | Implementación | Casos | Estado |
|---|---|---|---|---|
| RF-AUT-01 | Registro de clientes con consentimiento | `public/registro.php`, `app/php/lib/cuenta.php` | CP-001, CP-002 | Aprobado |
| RF-AUT-02 | Inicio de sesión seguro | `app/php/lib/auth.php`, `public/login.php` | CP-003, CP-004 | Aprobado |
| RF-AUT-03 | Límite de intentos | `autenticar()` en `auth.php` | CP-005 | Aprobado |
| RF-AUT-04 | Recuperación por correo | `password_reset.php`, `correo.php` | CP-006, CP-007 | Aprobado (driver log) |
| RF-AUT-05 | Cambio forzado de contraseña | `exigirCambioPassword()`, `cambiar-password.php` | CP-008 | Aprobado |
| RF-RBAC-01 | Visitante sin acceso a gestión | `requerirRol()` | CP-009 | Aprobado |
| RF-RBAC-02 | Cliente sin acceso a administración | `requerirRol(ROL_ADMIN)` | CP-010 | Aprobado |
| RF-RBAC-03 | Encargado sin acceso a administración | `requerirRol(ROL_ADMIN)` | CP-011 | Aprobado |
| RF-RBAC-04 | Personal interno sin carrito ni cuenta | `puedeGestionar()`, guardas en `carrito.php`, `checkout.php` y `cuenta.php` | CP-043 | Aprobado |
| RF-TIE-01 | Catálogo público con stock y tiempo | `public/index.php` | CP-012 | Aprobado |
| RF-TIE-02 | Precio histórico inmutable | `detallepedido.precio_unitario`, `recalcularTotalPedido()` | CP-013 | Aprobado |
| RF-TIE-03 | Carrito con validación de stock | `carritoAgregar()`, `carritoFijar()` | CP-014, CP-015 | Aprobado |
| RF-TIE-04 | Checkout de invitado o cliente | `crearPedido()` | CP-016, CP-017 | Aprobado |
| RF-TIE-05 | Stock se descuenta solo al pagar | `confirmarPago()` | CP-018, CP-021 | Aprobado |
| RF-TIE-06 | Consulta pública por código y token | `obtenerPedidoPublico()`, `pedido.php` | CP-019, CP-020 | Aprobado |
| RF-TIE-07 | Historial de invitado en el navegador | `pedidos_invitado.php`, `pedidos-invitado.php` | CP-044 | Aprobado |
| RF-TIE-10 | Tiempo estimado por producto, capacidad y cola | `calcularTiempoEstimado()`, `calcularTiempoPreparacion()` | CP-046 | Aprobado |
| RF-TIE-11 | Copiar enlace limpio del pedido | `pedido.php`, botón Copiar enlace | CP-047 | Aprobado |
| RF-DOC-01 | Página de anexos y descargas controladas | `anexos.php`, `descargar-anexo.php` | CP-048 | Aprobado |
| RF-UI-02 | Navegación con botones en todas las páginas | `pieHtml()`, botones de regreso | CP-049 | Aprobado |
| RF-CAJ-01 | Cobro en efectivo con cambio | `confirmarPago()` | CP-021, CP-024 | Aprobado |
| RF-CAJ-02 | Cobro con tarjeta y referencia externa | `confirmarPago()` | CP-022 | Aprobado |
| RF-CAJ-03 | Caja obligatoria para efectivo | `cajaAbierta()` | CP-023 | Aprobado |
| RF-CAJ-04 | Apertura única de caja | `abrirCaja()`, índice `ux_caja_sesion_abierta` | CP-031, CP-032 | Aprobado |
| RF-CAJ-05 | Cierre con diferencia auditada | `cerrarCaja()` | CP-033, CP-034 | Aprobado |
| RF-CAN-01 | Cancelación con reembolso tarjeta | `cancelarPedido()` | CP-025 | Aprobado |
| RF-CAN-02 | Cancelación con reembolso efectivo | `cancelarPedido()` | CP-026 | Aprobado |
| RF-CAN-03 | Motivo obligatorio | `cancelarPedido()` | CP-027 | Aprobado |
| RF-CAN-04 | No cancelar pedidos cerrados | `cancelarPedido()` | CP-028 | Aprobado |
| RF-CAN-05 | Reembolso parcial en preparación o listo | `porcentajeReembolsoCancelacion()`, `montoReembolsoCancelacion()` | CP-045 | Aprobado |
| RF-PED-01 | Flujo de estados controlado | `avanzarEstadoPedido()` | CP-029, CP-030 | Aprobado |
| RF-STO-01 | Ajuste manual auditado | `public/gestion.php` (stock) | CP-035 | Aprobado |
| RF-ADM-01 | Crear usuarios con contraseña temporal | `public/admin.php` | CP-036 | Aprobado |
| RF-ADM-02 | Reset administrativo | `public/admin.php` | CP-037 | Aprobado |
| RF-ADM-03 | CRUD de productos y categorías | `public/admin.php` | CP-038 | Aprobado |
| RF-ADM-04 | Proteger el último administrador | `administradoresActivos()` | CP-039 | Aprobado |
| RF-ADM-05 | Configuración de tienda | `config_sistema`, `admin.php` | CP-040 | Aprobado |
| RF-ADM-06 | Gestión de imágenes de producto | `public/admin.php`, `app/php/lib/imagenes.php` | CP-050, CP-051, CP-052, CP-053 | Aprobado |
| RF-REP-01 | Reportes y exportación CSV | `public/reportes.php` | CP-041 | Aprobado |
| RF-MIG-01 | Migración fiel de datos | `db/migraciones/001_migracion_inicial.sql` | CP-042 | Aprobado |
| RF-AUD-01 | Auditoría de acciones sensibles | `auditoria.php` | Transversal | Aprobado |
| RF-SEG-01 | CSRF en formularios | `campoCsrf()`, `exigirCsrf()` | Transversal | Aprobado |
| RF-SEG-02 | Cookies y sesión endurecidas | `bootstrap.php` | Transversal | Aprobado |
