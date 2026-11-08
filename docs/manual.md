Este manual explica cómo usar **CoffeeAndtea**, el sistema de gestión de una cafetería. La aplicación organiza en páginas separadas el CRUD de las cinco tablas, los reportes de PostgreSQL, este manual y el video institucional, que se muestra en Inicio.

> **Consejo:** usa la barra superior para abrir la sección que necesitas: Inicio, Gestión, Reportes o Manual. El índice de la izquierda te lleva a cada tema de este manual.

---

## 1. Qué es CoffeeAndtea

CoffeeAndtea es una aplicación web para administrar la información básica de una cafetería:

- **Categorías** para agrupar los productos.
- **Clientes** con sus datos de contacto y consentimiento.
- **Productos** con precio, existencias y categoría.
- **Pedidos** con cliente, estado, observaciones y total.
- **Detalles de pedido** que relacionan productos y pedidos.

El sistema se organiza en cuatro páginas: **Inicio**, **Gestión**, **Reportes** y **Manual**. La barra superior permite abrir cualquiera de ellas en la misma pestaña, sin ventanas nuevas.

## 2. Requisitos y puesta en marcha

### 2.1 Requisitos

| Componente | Requisito |
|---|---|
| PHP | Versión 8 o superior |
| Extensiones PHP | `pdo_pgsql`, `pgsql` y `mbstring` |
| Servidor web | Apache incluido en Laragon |
| Base de datos | PostgreSQL con la base `CoffeeAndTea` |
| Navegador | Chrome, Edge, Firefox u otro navegador moderno |
| Conexión | No se necesita internet para el CRUD ni el manual |

La conexión está configurada en `conectarBD.php`. Allí se indica el host, el puerto, el nombre de la base, el usuario y la contraseña. En este equipo ya está conectada a la base `CoffeeAndTea`.

### 2.2 Cómo iniciar

La forma más rápida es hacer doble clic en `abrir.bat`, dentro de la carpeta del proyecto:

1. Si Apache ya está activo, se abre `http://coffeeandtea.test/`.
2. Si Apache no está activo, se inicia un servidor PHP de respaldo en
   `http://localhost:8080/` y se abre el navegador.
3. Mantén abierta la ventana negra mientras uses el servidor de respaldo.

También puedes iniciar el sistema manualmente:

1. Abre Laragon.
2. Pulsa **Start All** si Apache o PostgreSQL no están iniciados.
3. Abre `http://localhost/CoffeeAndtea/` o `http://coffeeandtea.test/`.

> **Importante:** no abras `index.php` con doble clic. Los archivos PHP deben ejecutarse desde Apache o desde el servidor de respaldo; de lo contrario, el navegador no interpretará el código.

### 2.3 Cómo detener y reiniciar

Para detener el sitio, pulsa **Stop** en Laragon o cierra Laragon. Si usaste
`abrir.bat`, cierra la ventana negra o pulsa `Ctrl+C`. Los datos quedan
guardados en PostgreSQL; no se pierden al cerrar el servidor.

Como respaldo, la carpeta incluye `scripts/iniciar.bat`, que llama al mismo
`abrir.bat`. No es necesario usarlo cuando Laragon está funcionando.

## 3. Navegación entre páginas

La barra superior tiene estos enlaces:

| Enlace | Página | Qué muestra |
|---|---|---|
| Inicio | `index.php` | Presentación del sistema, contadores, accesos rápidos y video |
| Gestión | `gestion.php` | Formularios y listados de las cinco tablas |
| Reportes | `reportes.php` | Resultados de los procedimientos almacenados |
| Manual | `manual.php` | Este documento completo |

Los enlaces del menú navegan en la misma pestaña y el botón Atrás del navegador vuelve a la página anterior. Dentro de Gestión, la pestaña activa se conserva en la URL, por ejemplo `gestion.php?tabla=producto#gestion`, de modo que el enlace puede compartirse o marcarse.

### 3.1 Video institucional

Al final de Inicio hay un marco en proporción 16:9 reservado para el video institucional. Por ahora muestra el texto **Espacio listo para insertar el video institucional** y no carga ningún recurso externo.

El marco es responsive: ocupa el ancho disponible hasta un máximo de 960 px y conserva la proporción en pantallas pequeñas. Para integrar el video, sustituye el contenido del bloque `.video-frame` en `php/vistas/vista_video.php` por un `iframe` del proveedor elegido o por una etiqueta `<video>` con un archivo local.

## 4. Gestión de datos

La sección **Gestión** tiene cinco pestañas: Categorías, Clientes, Productos, Pedidos y Detalles. Cada pestaña muestra dos partes:

- **Formulario:** sirve para crear un registro nuevo o editar uno existente.
- **Listado:** muestra los registros actuales y los botones **Editar** y **Eliminar**.

### 4.1 Orden recomendado de registro

Para respetar las relaciones entre tablas, registra los datos en este orden:

1. Categoría.
2. Cliente.
3. Producto, que necesita una categoría.
4. Pedido, que necesita un cliente.
5. Detalle de pedido, que necesita un pedido y un producto.

Si intentas crear un producto sin categorías, un pedido sin clientes o un detalle sin pedidos o productos, la aplicación muestra un aviso y no permite continuar.

### 4.2 Categorías

La categoría agrupa productos similares: bebidas calientes, repostería, sándwiches, etc.

| Campo | Obligatorio | Regla |
|---|---|---|
| Nombre | Sí | Máximo 100 caracteres y no puede repetirse |
| Descripción | No | Máximo 500 caracteres |
| Activa | No | Si se desactiva, no aparece para productos nuevos |

**Para crear una categoría:**

1. Abre la pestaña **Categorías**.
2. Escribe el nombre y, si quieres, una descripción.
3. Deja marcada la casilla **Categoría activa**.
4. Pulsa **Crear categoría**.

**Para editarla:** pulsa **Editar** en la fila correspondiente, cambia los datos y pulsa **Guardar cambios**.

**Para eliminarla:** pulsa **Eliminar**. Si la categoría tiene productos asociados, el sistema no la borra y recomienda desactivarla.

### 4.3 Clientes

El cliente es la persona que realiza los pedidos.

| Campo | Obligatorio | Regla |
|---|---|---|
| Nombre | Sí | Entre 3 y 150 caracteres |
| Correo | Sí | Formato válido, máximo 150 caracteres y único |
| Teléfono | No | Entre 7 y 20 caracteres: números, espacios, +, - o paréntesis |
| Consentimiento | Sí al crear | Autorización del tratamiento de datos |
| Activo | No | Si se desactiva, no aparece para pedidos nuevos |

El consentimiento se relaciona con la **Ley 1581 de 2012** de protección de datos personales. Al crear un cliente nuevo, la casilla debe estar marcada. Al editar un cliente existente se puede mantener o retirar el consentimiento.

**Para eliminarlo:** si el cliente tiene pedidos, el sistema bloquea la eliminación. En ese caso, desactívalo para conservar el historial.

### 4.4 Productos

Un producto es un artículo que vende la cafetería.

| Campo | Obligatorio | Regla |
|---|---|---|
| Nombre | Sí | Máximo 150 caracteres |
| Unidad de medida | Sí | Ejemplo: ml, unidades, gramos |
| Precio | Sí | Número mayor que cero, con dos decimales |
| Stock actual | Sí | Número entero mayor o igual que cero |
| Categoría | Sí | Debe existir y estar activa |
| Activo | No | Si se desactiva, no se puede agregar a pedidos nuevos |

El listado muestra una etiqueta de alerta cuando el stock es menor que 30. Esa etiqueta es solo visual; el reporte de bajo stock permite cambiar el límite.

**Para eliminarlo:** si el producto aparece en algún detalle de pedido, el sistema bloquea la eliminación. Desactívalo para conservar las ventas históricas.

### 4.5 Pedidos

El pedido es el encabezado de una compra.

| Campo | Obligatorio | Regla |
|---|---|---|
| Cliente | Sí | Debe existir y estar activo, salvo que se esté editando el pedido actual |
| Estado | Sí | ABIERTO, PAGADO o CANCELADO |
| Observaciones | No | Máximo 1000 caracteres |
| Fecha de cierre | No | Fecha y hora; puede quedar vacía |
| Total | No se edita | Se calcula con los detalles |

El total empieza en `$0.00` y se actualiza automáticamente cuando se agregan, editan o eliminan detalles. No escribas el total manualmente: el sistema lo calcula a partir de la cantidad y el precio de cada producto.

**Estados del pedido:**

- **ABIERTO:** el pedido está en curso.
- **PAGADO:** la venta se completó; los reportes de ventas lo tienen en cuenta.
- **CANCELADO:** el pedido no se cobró.

**Para eliminar un pedido:** el sistema pide confirmación y elimina también todos sus detalles en una sola transacción. Esta acción no se puede deshacer.

### 4.6 Detalles de pedido

El detalle relaciona un pedido con un producto y una cantidad. Un pedido puede tener **varios productos distintos**, pero cada producto aparece **una sola vez** por pedido.

| Campo | Obligatorio | Regla |
|---|---|---|
| Pedido | Sí | Debe existir |
| Producto | Sí | Debe existir y estar activo, salvo que sea el producto actual del detalle |
| Cantidad | Sí | Número entero mayor que cero y no puede superar el stock disponible |

**Para agregar productos:** elige el pedido y completa una fila por cada producto. Usa **+ Agregar otro producto** para añadir filas y el botón **×** para quitar una. Al guardar se insertan todas las filas en una sola transacción: si una falla, no se guarda ninguna.

**Productos repetidos:** el sistema bloquea el mismo producto dos veces en el mismo pedido, tanto si se repite en el formulario como si ya estaba guardado. En ese caso, edita el detalle existente para cambiar la cantidad. Las cantidades no se suman automáticamente. Los productos disponibles se deshabilitan en las demás filas para evitar el error.

**Después de guardar:** el pedido queda seleccionado para que puedas seguir agregando productos sin volver a buscarlo.

El subtotal se calcula como **cantidad × precio del producto**. El total del pedido se recalcula automáticamente.

**Al editar un detalle:** se trabaja con una sola fila. Si cambias el pedido, el sistema recalcula el total del pedido anterior y el del nuevo. Si cambias la cantidad, recalcula el pedido correspondiente.

**Al eliminar un detalle:** el total del pedido se recalcula de inmediato.

### 4.7 Editar y eliminar

- **Editar:** abre el formulario con los datos actuales. El botón **Cancelar edición** vuelve al formulario de creación.
- **Eliminar:** siempre pide confirmación. Las reglas de integridad evitan borrar datos que dejarían pedidos o productos huérfanos.
- **Desactivar:** es la alternativa recomendada cuando un registro tiene historial. Un registro inactivo se conserva, pero no se ofrece para operaciones nuevas.

Los mensajes de éxito aparecen como avisos en la esquina superior derecha. Los errores de validación aparecen dentro de la sección de gestión.

## 5. Reportes

Los reportes se ejecutan con los procedimientos almacenados de PostgreSQL. Cada pestaña muestra una descripción, una tabla de resultados y el nombre del procedimiento utilizado.

### 5.1 Ventas del día

Muestra los pedidos **PAGADOS** cuya fecha de creación es la fecha actual.

| Columna | Significado |
|---|---|
| Pedido | Número del pedido |
| Cliente | Nombre del cliente |
| Total | Total del pedido |
| Fecha de creación | Fecha y hora del pedido |

Si no hay ventas hoy, aparece un aviso. Es normal si los pedidos de ejemplo son de días anteriores.

### 5.2 Top 5 productos

Muestra los cinco productos con más unidades vendidas en pedidos pagados.

| Columna | Significado |
|---|---|
| Producto | Nombre del producto |
| Unidades vendidas | Suma de cantidades |
| Ingresos generados | Suma de cantidad × precio |

### 5.3 Clientes VIP

Muestra los clientes cuyos pedidos pagados suman más de `$20.00`.

| Columna | Significado |
|---|---|
| Cliente | Nombre del cliente |
| Correo | Correo electrónico |
| Teléfono | Teléfono registrado |
| Pedidos pagados | Cantidad de pedidos pagados |
| Total gastado | Suma de los totales pagados |

### 5.4 Bajo stock

Muestra los productos activos con existencias menores que el límite indicado. El valor inicial es **30**.

1. Escribe el límite en el campo.
2. Pulsa **Actualizar reporte**.
3. Revisa la tabla de productos y su categoría.

### 5.5 Ingresos por categoría

Agrupa las ventas pagadas por categoría.

| Columna | Significado |
|---|---|
| Categoría | Nombre de la categoría |
| Pedidos | Pedidos distintos que incluyen productos de la categoría |
| Unidades | Unidades vendidas |
| Ingresos | Suma de cantidad × precio |

### 5.6 Cómo se ejecutan los reportes

Cada procedimiento abre un cursor de PostgreSQL. La aplicación ejecuta la llamada, recupera las filas y cierra la transacción. Si un procedimiento no existe o la base no responde, la sección muestra un mensaje de error en lugar de una tabla vacía.

## 6. Reglas de negocio y base de datos

### 6.1 Relaciones entre tablas

```text
categoria 1 ─── N producto 1 ─── N detallepedido N ─── 1 pedido N ─── 1 cliente
```

- Una categoría puede tener muchos productos.
- Un producto puede aparecer en muchos detalles.
- Un pedido puede tener muchos detalles.
- Un cliente puede tener muchos pedidos.
- Un detalle pertenece a un solo pedido y a un solo producto.

### 6.2 Tablas principales

| Tabla | Función |
|---|---|
| `categoria` | Agrupa productos |
| `cliente` | Datos de contacto y consentimiento |
| `producto` | Artículos, precio y existencias |
| `pedido` | Encabezado de la compra y total |
| `detallepedido` | Productos y cantidades de cada pedido |

### 6.3 Reglas de integridad

- No se elimina una categoría con productos.
- No se elimina un cliente con pedidos.
- No se elimina un producto con detalles.
- Al eliminar un pedido se eliminan sus detalles.
- El total del pedido se calcula con los detalles.
- El correo del cliente y el nombre de la categoría son únicos.
- El precio debe ser mayor que cero y el stock no puede ser negativo.

## 7. Solución de problemas

| Problema | Causa probable | Solución |
|---|---|---|
| No conecta con la base | PostgreSQL detenido o datos incorrectos | Inicia PostgreSQL y revisa `conectarBD.php` |
| Aparece “could not find driver” | Falta la extensión de PostgreSQL | Habilita `pdo_pgsql` y `pgsql` en `php.ini` |
| No abre `http://localhost/CoffeeAndtea/` | Apache está detenido o el proyecto no está en `www` | Inicia Apache en Laragon y verifica la carpeta `C:\laragon\www\CoffeeAndtea` |
| El puerto 80 está ocupado | Hay otro servidor web activo | Detén el otro servidor o cambia el puerto de Apache |
| No se puede eliminar un registro | Tiene datos relacionados | Desactívalo o elimina primero sus dependencias |
| El total del pedido es cero | El pedido no tiene detalles | Agrega al menos un detalle |
| Ventas del día no muestra filas | No hay pedidos pagados de hoy | Crea un pedido, márcalo como PAGADO y agrega detalles |
| El reporte de bajo stock está vacío | Ningún producto está por debajo del límite | Sube el límite y actualiza |
| El manual no aparece | Falta `manual.md` | Restaura el archivo en la carpeta del proyecto |
| El video no aparece | Todavía no se ha integrado un reproductor | Sustituye el marcado de `php/vistas/vista_video.php` por el `iframe` o la etiqueta `<video>` definitiva |
| La página se ve sin estilos | Se abrió con `file://` | Usa `http://localhost/CoffeeAndtea/` |

## 8. Preguntas frecuentes

**¿Puedo editar el total de un pedido?**
No. El total se calcula automáticamente a partir de los detalles. Así se evita que el total no coincida con los productos vendidos.

**¿Qué pasa si elimino un pedido que tiene detalles?**
Se eliminan el pedido y todos sus detalles en una sola transacción, después de confirmar la acción.

**¿Por qué no puedo eliminar una categoría?**
Porque tiene productos asociados. Desactívala si ya no la usas, así conservas el historial de ventas.

**¿Por qué el cliente nuevo exige consentimiento?**
Porque la aplicación maneja datos personales y aplica el criterio de autorización de la Ley 1581 de 2012.

**¿El stock se descuenta al vender?**
No automáticamente. El sistema registra el stock actual y lo usa en el reporte de bajo stock; el descuento por venta no forma parte de este CRUD.

**¿Puedo agregar varios productos a la vez?**
Sí. En Detalles elige el pedido, agrega una fila por producto y guarda una sola vez. Todos se guardan juntos o no se guarda ninguno. No puedes repetir el mismo producto en el pedido.

**¿Puedo mover un detalle a otro pedido?**
Sí. Al hacerlo, el sistema recalcula el total del pedido anterior y el del nuevo.

**¿Los cambios se guardan al cerrar el servidor?**
Sí. La información se guarda en PostgreSQL, no en el navegador.

**¿Dónde edito el manual?**
En el archivo `manual.md`. La sección Manual se actualiza al refrescar la página.

## 9. Glosario

| Término | Significado |
|---|---|
| CRUD | Crear, leer, actualizar y eliminar |
| Tabla | Conjunto de datos organizado en filas y columnas |
| Llave primaria | Campo que identifica de forma única un registro |
| Llave foránea | Campo que relaciona una tabla con otra |
| Trigger | Función que se ejecuta automáticamente ante un cambio |
| Procedimiento almacenado | Rutina guardada y ejecutada dentro de PostgreSQL |
| Refcursor | Cursor de PostgreSQL que permite devolver varias filas desde un procedimiento |
| Markdown | Formato de texto con títulos, listas, tablas y enlaces |
| Transacción | Conjunto de operaciones que se confirman o se deshacen juntas |

## 10. Lista rápida de comprobación

Usa esta lista para verificar que todo funciona:

- Crear una categoría de prueba y verla en el listado.
- Editar la categoría y guardar los cambios.
- Intentar eliminar una categoría con productos y leer el aviso.
- Crear un cliente con consentimiento.
- Crear un producto con precio y stock válidos.
- Crear un pedido y agregarle tres productos distintos en un solo envío.
- Comprobar que el total del pedido coincide con los subtotales.
- Editar un detalle y verificar el nuevo total.
- Eliminar un detalle y comprobar el recálculo.
- Eliminar un pedido y confirmar que también se eliminaron sus detalles.
- Abrir los cinco reportes y revisar sus tablas.
- Cambiar el límite del reporte de bajo stock.
- Revisar que el espacio del video se adapte al ancho de la pantalla.
- Editar `manual.md` y refrescar la página para ver el cambio.

> **Buenas prácticas:** antes de eliminar datos reales, verifica las relaciones. Para conservar el historial, desactiva categorías, clientes y productos en lugar de borrarlos.

---

**CoffeeAndtea** · Manual de uso · La aplicación y su documentación están organizadas por páginas para facilitar la consulta durante la operación de la cafetería.
