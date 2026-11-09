# Manual técnico de operación — Encargado

Este manual está dirigido al rol **ENCARGADO**. Explica las funciones diarias
de la tienda: pedidos, cobro, caja, stock y reportes.

> El encargado **no** administra usuarios, productos, categorías ni la
> configuración general. Esas tareas corresponden al rol `ADMIN`.

---

## 1. Alcance del rol

### Lo que puedes hacer

| Función | Dónde |
|---|---|
| Ver y filtrar pedidos | `gestion.php?tab=pedidos` |
| Abrir el detalle de un pedido | `gestion.php?tab=pedidos&ver=ID` |
| Confirmar pagos en efectivo o tarjeta | Detalle del pedido |
| Avanzar estados del pedido | Detalle del pedido |
| Cancelar pedidos y registrar reembolsos | Detalle del pedido |
| Abrir y cerrar caja | `gestion.php?tab=caja` |
| Registrar ingresos y egresos manuales | `gestion.php?tab=caja` |
| Ajustar stock | `gestion.php?tab=stock` |
| Consultar reportes y exportar CSV | `reportes.php` |
| Consultar el manual de operación | `manual.php` |

### Lo que no puedes hacer

- Usar el carrito, el checkout o la cuenta de cliente.
- Crear o editar usuarios.
- Cambiar roles o contraseñas de otras personas.
- Crear, editar o desactivar productos.
- Crear o editar categorías.
- Cambiar la configuración de la tienda.
- Consultar la auditoría completa.
- Eliminar pedidos, pagos o movimientos.

> Tu cuenta es de gestión: puedes consultar el menú, pero no comprar. Para
> probar el flujo de compra usa una cuenta `CLIENTE` o una ventana de incógnito.

Si necesitas alguna de esas acciones, solicítala a un administrador.

---

## 2. Acceso

1. Abre `login.php`.
2. Escribe tu correo y contraseña.
3. Al entrar serás enviado a `gestion.php`.

Si es tu primer ingreso con una contraseña temporal, el sistema te obligará a
cambiarla antes de usar cualquier otra página.

La sesión expira por inactividad. Si eso ocurre, vuelve a iniciar sesión.

---

## 3. Panel de gestión

La parte superior de `gestion.php` muestra indicadores:

- Pedidos pendientes de pago.
- Pedidos pagados.
- Pedidos en preparación.
- Pedidos listos.
- Productos con bajo stock.

Debajo están las pestañas:

- **Pedidos:** operación diaria.
- **Caja:** efectivo del turno.
- **Stock:** inventario.

Los indicadores son enlaces: al pulsarlos abren la lista filtrada.

---

## 4. Pedidos

### 4.1 Lista

Puedes filtrar por estado y buscar por código o cliente. Cada fila muestra:

- Código del pedido.
- Cliente.
- Fecha.
- Total.
- Estado actual.
- Botón **Abrir**.

### 4.2 Detalle

El detalle muestra:

- Datos del cliente.
- Productos, cantidades y precios congelados.
- Total.
- Estado y tiempo estimado.
- Historial de estados.
- Pagos registrados.
- Acciones disponibles según el estado.

El precio del detalle es el que tenía el producto cuando se creó el pedido.
Aunque el precio actual cambie, el pedido conserva su valor original.

---

## 5. Cobro en mostrador

Solo se pueden cobrar pedidos en estado `PENDIENTE`.

### 5.1 Pago en efectivo

1. Abre el pedido.
2. Pulsa **Cobrar**.
3. Selecciona **Efectivo**.
4. Escribe el efectivo recibido.
5. Confirma.

El sistema:

- Valida que haya una caja abierta.
- Calcula el cambio.
- Descuenta el stock.
- Registra el pago como `CONFIRMADO`.
- Crea un `INGRESO` en la caja.
- Cambia el pedido a `PAGADO`.

Si el efectivo recibido es menor al total, el cobro se rechaza.

### 5.2 Pago con tarjeta

1. Cobra con el datáfono externo.
2. En el sistema selecciona **Tarjeta**.
3. Escribe la referencia de la transacción (recomendado).
4. Confirma.

El sistema:

- Descuenta el stock.
- Guarda el pago con la referencia.
- **No** registra movimiento de caja, porque el dinero entra por el datáfono.
- Cambia el pedido a `PAGADO`.

### 5.3 Reglas del cobro

- No se puede cobrar dos veces el mismo pedido.
- No se puede cobrar un pedido cancelado o entregado.
- El stock se descuenta **solo al confirmar el pago**.
- Si no hay stock suficiente, el cobro se rechaza y no se modifica nada.

---

## 6. Estados del pedido

Flujo normal:

```text
PENDIENTE → PAGADO → EN_PREPARACION → LISTO → ENTREGADO
```

| Estado | Significado | Acción típica |
|---|---|---|
| `PENDIENTE` | Creado en la web, sin pago | Cobrar |
| `PAGADO` | Cobrado, stock descontado | Pasar a preparación |
| `EN_PREPARACION` | En barra o cocina | Marcar como listo |
| `LISTO` | Disponible para recoger | Entregar |
| `ENTREGADO` | Entregado al cliente | Ninguna |
| `CANCELADO` | Anulado | Ninguna |

No se pueden saltar estados. Cada cambio queda en el historial y en la
auditoría.

---

## 7. Cancelaciones y reembolsos

Se puede cancelar un pedido en estado `PENDIENTE`, `PAGADO`,
`EN_PREPARACION` o `LISTO`. No se puede cancelar un pedido `ENTREGADO` ni uno
ya `CANCELADO`.

### Política de reembolso

| Estado al cancelar | Reembolso |
|---|---|
| `PENDIENTE` (sin pago) | No hay dinero que devolver. |
| `PAGADO` (aún sin preparar) | 100% de lo pagado. |
| `EN_PREPARACION` o `LISTO` | 50% de lo pagado. |
| `ENTREGADO` o `CANCELADO` | No se puede cancelar. |

El sistema calcula el monto automáticamente. No lo escribas a mano ni devuelvas
más de lo que muestra la pantalla de cancelación.

### 7.1 Pedido sin pago

1. Abre el pedido.
2. Pulsa **Cancelar**.
3. Escribe el motivo (mínimo 5 caracteres).
4. Selecciona reembolso **Ninguno**.
5. Confirma.

El pedido pasa a `CANCELADO`. No se mueve stock ni caja.

### 7.2 Pedido pagado en efectivo

1. Verifica que la caja esté abierta.
2. Cancela con motivo.
3. Selecciona reembolso **Efectivo**.
4. Confirma.

El sistema:

- Devuelve el stock.
- Marca el pago como `REEMBOLSADO` con el monto calculado.
- Registra un `EGRESO` en la caja por el monto reembolsado.
- Guarda responsable, fecha, motivo y auditoría.

### 7.3 Pedido pagado con tarjeta

1. Cancela con motivo.
2. Selecciona reembolso **Tarjeta**.
3. Escribe la referencia del reembolso del datáfono.
4. Confirma.

El sistema:

- Devuelve el stock.
- Marca el pago como `REEMBOLSADO` con el monto calculado.
- Guarda la referencia y el monto reembolsado.
- **No** mueve la caja.

> Si el reembolso con tarjeta no se puede procesar en el datáfono, no marques
> la cancelación como completada. Escala el caso al administrador.

---

## 8. Caja

### 8.1 Abrir caja

1. Ve a `gestion.php?tab=caja`.
2. Pulsa **Abrir caja**.
3. Escribe la base inicial: el efectivo con el que empieza el turno.
4. Confirma.

Solo puede haber **una caja abierta** a la vez. Si intentas abrir otra, el
sistema lo rechaza.

### 8.2 Movimientos

Durante el turno se registran:

- `INGRESO`: pagos en efectivo.
- `EGRESO`: reembolsos en efectivo.

También puedes registrar un movimiento manual con:

- Tipo: ingreso o egreso.
- Monto.
- Concepto.

Cada movimiento queda asociado a la caja y al usuario.

### 8.3 Cerrar caja

1. Cuenta el efectivo real.
2. Pulsa **Cerrar caja**.
3. Escribe el efectivo contado.
4. Confirma.

Fórmula:

```text
esperado = base inicial + ingresos − egresos
diferencia = contado − esperado
```

Interpretación:

- Diferencia `0`: caja cuadrada.
- Diferencia positiva: sobrante.
- Diferencia negativa: faltante.

El cierre guarda el efectivo contado, la diferencia, el usuario y la fecha.
No se puede cerrar una caja que ya está cerrada.

---

## 9. Stock

### 9.1 Descuento automático

El stock se descuenta al confirmar el pago. Se registra un movimiento tipo
`VENTA` por cada producto.

### 9.2 Devolución automática

Al cancelar un pedido pagado, el stock vuelve y se registra un movimiento tipo
`DEVOLUCION`.

### 9.3 Ajuste manual

1. Ve a `gestion.php?tab=stock`.
2. Busca el producto.
3. Escribe el nuevo stock actual.
4. Confirma.

El sistema:

- Valida que el stock no sea negativo.
- Registra un movimiento tipo `AJUSTE`.
- Guarda el usuario y la fecha.
- Actualiza la alerta de bajo stock.

Los productos por debajo del stock mínimo se resaltan en la lista.

> No uses el ajuste manual para “cuadrar” ventas. Si hay una diferencia,
> investiga primero con el administrador.

---

## 10. Reportes

`reportes.php` está disponible para encargados y administradores.

Incluye:

- Ventas del día.
- Productos más vendidos.
- Clientes con mayor consumo.
- Productos con bajo stock.
- Ingresos por categoría.

Puedes exportar los datos a CSV. El archivo se descarga con la fecha en el
nombre y se puede abrir en Excel o Google Sheets.

Los reportes son de consulta: no modifican pedidos, pagos ni stock.

---

## 11. Reglas de operación y seguridad

- No compartas tu contraseña.
- Cierra sesión al terminar el turno.
- No cobres un pedido sin verificar el código.
- No canceles sin motivo.
- No devuelvas efectivo sin registrar el egreso.
- No ajustes stock sin justificar el cambio.
- Verifica la referencia del datáfono antes de confirmar un pago con tarjeta.
- Si detectas un cobro duplicado, avisa de inmediato al administrador.
- No anotes contraseñas ni datos de tarjetas en papel.

---

## 12. Problemas frecuentes

**No aparece el botón Cobrar**  
El pedido no está en estado `PENDIENTE` o ya fue pagado.

**Dice que no hay caja abierta**  
Abre la caja antes de cobrar en efectivo. Con tarjeta no se exige caja.

**El efectivo recibido no alcanza**  
Revisa el total y el monto ingresado. El cambio se calcula automáticamente.

**No puedo cancelar**  
El pedido puede estar `ENTREGADO` o `CANCELADO`. Revisa el estado.

**El reembolso en efectivo falla**  
Verifica que la caja esté abierta y que el monto sea correcto.

**No puedo abrir caja**  
Ya existe una caja abierta. Ciérrala primero.

**El stock no coincide**  
Revisa los movimientos del producto. Si la diferencia es importante, escala
el caso al administrador.

**No veo Administración**  
Es correcto: esa sección es exclusiva del rol `ADMIN`.

---

## 13. Cuándo escalar al administrador

Escala el caso si:

- Hay un faltante o sobrante de caja que no puedes explicar.
- Un pago quedó duplicado.
- Un reembolso con tarjeta no se pudo procesar.
- El stock tiene diferencias grandes.
- Necesitas crear o desactivar un producto.
- Necesitas crear un usuario o cambiar un rol.
- Hay un problema de seguridad o un acceso sospechoso.
- La tienda debe cerrarse o abrirse para pedidos web.

---

## 14. Glosario rápido

| Término | Significado |
|---|---|
| Pedido | Solicitud de productos de un cliente. |
| Detalle | Línea del pedido: producto, cantidad y precio. |
| Caja | Turno de efectivo con base, movimientos y cierre. |
| Ingreso | Entrada de efectivo a la caja. |
| Egreso | Salida de efectivo de la caja. |
| Ajuste | Cambio manual del stock. |
| Reembolso | Devolución del dinero de un pedido pagado. |
| CSV | Archivo de texto con datos separados por comas. |

---

*Manual técnico de operación de CoffeeAndtea. Actualizado el 8 de noviembre de 2026.*
