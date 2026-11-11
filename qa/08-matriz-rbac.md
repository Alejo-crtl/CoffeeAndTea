# Matriz RBAC — CoffeeAndtea

Roles de base de datos: `CLIENTE`, `ENCARGADO`, `ADMIN`.
El visitante es anónimo y **no** existe como rol en la base.

## Leyenda

- ✅ Permitido
- ❌ Denegado (redirige o 403)
- 🔒 Permitido solo si es el propio registro o su propio pedido

## Páginas

| Página | Visitante | CLIENTE | ENCARGADO | ADMIN |
|---|---|---|---|---|
| `index.php` (tienda, solo consulta para staff) | ✅ | ✅ | ✅ | ✅ |
| `carrito.php` | ✅ | ✅ | ❌ | ❌ |
| `checkout.php` | ✅ | ✅ | ❌ | ❌ |
| `pedidos-invitado.php` | ✅ | ✅ | ❌ | ❌ |
| `pedido.php` (código + token) | ✅ | ✅ | ✅ | ✅ |
| `login.php`, `registro.php`, `recuperar.php`, `restablecer.php` | ✅ | ❌ (redirige) | ❌ | ❌ |
| `privacidad.php`, `politica-cancelacion.php`, `manual.php`, `video.php` | ✅ | ✅ | ✅ | ✅ |
| `anexos.php`, `descargar-anexo.php`, `descargar-manual.php` | ✅ | ✅ | ✅ | ✅ |
| `cuenta.php` | ❌ | ✅ | ❌ | ❌ |
| `cambiar-password.php` | ❌ | ✅ | ✅ | ✅ |
| `gestion.php` | ❌ | ❌ | ✅ | ✅ |
| `reportes.php` | ❌ | ❌ | ✅ | ✅ |
| `admin.php` | ❌ | ❌ | ❌ | ✅ |

> Las cuentas `ENCARGADO` y `ADMIN` no tienen carrito, checkout ni “Mi cuenta”.
> Si abren esas URLs directamente, el sistema los redirige a `gestion.php`.
> En `index.php` ven el menú en modo consulta, sin botones de agregar.

## Acciones sensibles

| Acción | CLIENTE | ENCARGADO | ADMIN | Verificación |
|---|---|---|---|---|
| Crear pedido web | ✅ | ❌ | ❌ | `crearPedido()` + bloqueo en `carrito.php`/`checkout.php` |
| Ver historial propio | ✅ | ❌ | ❌ | `cuenta.php` bloqueada para gestión |
| Confirmar pago | ❌ | ✅ | ✅ | `requerirRol(ENCARGADO, ADMIN)` |
| Avanzar estado | ❌ | ✅ | ✅ | `avanzarEstadoPedido()` |
| Cancelar pedido y reembolsar | ❌ | ✅ | ✅ | `cancelarPedido()` |
| Abrir/cerrar caja | ❌ | ✅ | ✅ | `gestion.php?tab=caja` |
| Ajustar stock | ❌ | ✅ | ✅ | `gestion.php?tab=stock` |
| Crear/editar usuarios | ❌ | ❌ | ✅ | `admin.php?tab=usuarios` |
| Resetear contraseña de otro usuario | ❌ | ❌ | ✅ | `admin.php` (`usuario_reset`) |
| Crear/editar productos y categorías | ❌ | ❌ | ✅ | `admin.php?tab=productos/categorias` |
| Cambiar configuración de tienda | ❌ | ❌ | ✅ | `admin.php?tab=configuracion` |
| Ver auditoría | ❌ | ❌ | ✅ | `admin.php?tab=auditoria` |
| Ver reportes y exportar CSV | ❌ | ✅ | ✅ | `reportes.php` |

> El visitante anónimo puede crear pedidos web, pero no tiene historial ni
> cuenta. La tabla de acciones muestra solo roles autenticados.

## Reglas adicionales

1. `exigirCambioPassword()` bloquea toda página salvo `cambiar-password.php` y `logout.php` mientras `debe_cambiar_password` sea verdadero.
2. Cambiar rol, desactivar un usuario o resetear su contraseña incrementa `session_version`, lo que invalida sus sesiones abiertas.
3. El sistema impide desactivar o degradar al último `ADMIN` activo.
4. `admin.php` no es accesible ni siquiera para `ENCARGADO`; solo `ADMIN`.
5. La consulta pública de un pedido exige **código + token**; el token no se muestra en listados públicos.
6. Los correos de recuperación no revelan si la cuenta existe.
7. `puedeGestionar()` identifica a `ENCARGADO` y `ADMIN`; esas cuentas no usan carrito, checkout ni cuenta de cliente.
8. La restricción se aplica en el servidor, no solo ocultando enlaces del menú.
9. Reembolso: 100% antes de preparación; 50% en `EN_PREPARACION` o `LISTO`; un pedido `ENTREGADO` no se cancela.
