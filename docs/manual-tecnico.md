# Manual técnico — CoffeeAndtea

Este manual cubre la **operación** y la **administración** del sistema. Está
dirigido a los roles **ENCARGADO** y **ADMIN**:

- El **encargado** opera la tienda: pedidos, cobro, caja, stock y reportes.
- El **administrador** hace todo lo anterior y además gestiona usuarios,
  productos, categorías, configuración, auditoría y respaldos, e instala y
  mantiene el sistema.

> Ninguno de los dos usa el carrito, el checkout ni “Mi cuenta”: esas funciones
> son exclusivas de visitantes y clientes. Si intentan abrir esas páginas, el
> sistema los devuelve a `gestion.php`.

---

## 1. Alcance y permisos

| Función | Visitante | CLIENTE | ENCARGADO | ADMIN |
|---|---|---|---|---|
| Ver la tienda | Sí | Sí | Solo consulta | Solo consulta |
| Hacer pedidos / usar carrito | Sí | Sí | No | No |
| Consultar pedido con código + token | Sí | Sí | Sí | Sí |
| Ver historial propio (Mi cuenta) | No | Sí | No | No |
| Gestionar pedidos y cobrar | No | No | Sí | Sí |
| Abrir/cerrar caja y ajustar stock | No | No | Sí | Sí |
| Ver reportes y exportar CSV | No | No | Sí | Sí |
| Crear/editar usuarios | No | No | No | Sí |
| Crear/editar productos y categorías | No | No | No | Sí |
| Cambiar la configuración de la tienda | No | No | No | Sí |
| Ver la auditoría completa | No | No | No | Sí |
| Ejecutar migración y respaldos | No | No | No | Sí |

El visitante no es un rol de base de datos: es una sesión anónima.

---

## 2. Arquitectura

Cinco capas, sin framework ni ORM: el SQL y las transacciones son explícitos.

![Diagrama de arquitectura: navegador, Apache, bootstrap, librerías y PostgreSQL](media/capturas/arquitectura.png)

*Figura 1. Arquitectura: cada página pasa por `bootstrap.php`, usa las librerías
del bloque correspondiente y consulta PostgreSQL con PDO.*

```text
CoffeeAndtea_Entrega/
├─ public/                  Document root
│  ├─ index.php             Tienda pública
│  ├─ carrito.php           Carrito
│  ├─ checkout.php          Checkout
│  ├─ pedido.php            Consulta pública por código + token
│  ├─ login.php             Ingreso
│  ├─ registro.php          Registro de clientes
│  ├─ recuperar.php         Solicitud de recuperación
│  ├─ restablecer.php       Cambio con token
│  ├─ cuenta.php            Historial del cliente
│  ├─ gestion.php           Pedidos, caja y stock
│  ├─ admin.php             Administración
│  ├─ reportes.php          Reportes y CSV
│  ├─ manual.php            Manual por rol
│  ├─ anexos.php            Documentación descargable
│  ├─ video.php             Video de presentación
│  ├─ css/, js/, media/     Recursos estáticos
│  └─ .htaccess             Endurecimiento de Apache
├─ anexos/                  PAC, SRS, MER, script de BD y log de errores
├─ app/php/
│  ├─ lib/                  config, conectarBD, bootstrap, excepciones, auth,
│  │                        pedidos, reportes, correo, password_reset, cuenta,
│  │                        auditoría, imágenes, video, anexos, manual_rol
│  └─ vistas/               plantilla.php y panel_pedido.php
├─ bin/crear-admin.php      Alta del administrador inicial
├─ db/                      schema.sql, seed-demo.sql y migraciones
├─ docs/                    Manuales y guías
├─ qa/                      Plan, casos, matriz RBAC e informe
├─ tests/                   Unitarias, integración y E2E
├─ scripts/iniciar.bat      Servidor local de respaldo
├─ storage/                 Logs (no se entrega)
└─ .env.example             Plantilla de configuración
```

### Flujo de una petición

1. El navegador entra por un archivo de `public/`.
2. El archivo carga `bootstrap.php`.
3. `bootstrap.php` lee `.env`, inicia la sesión, conecta a PostgreSQL y carga
   autenticación, auditoría y helpers.
4. La página valida rol y CSRF, ejecuta la lógica y renderiza la vista.

---

## 3. Instalación y configuración

### 3.1 Requisitos

| Componente | Requisito |
|---|---|
| PHP | 8.3 o superior |
| Extensiones | `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `session`, `json`, `fileinfo`, `gd` |
| PostgreSQL | 14 o superior (probado con 18) |
| Servidor web | Apache o Nginx con docroot en `public/` |
| Composer | Opcional, para PHPUnit, PHPStan y PHPCS |
| Node.js | Opcional, 20 o superior para Playwright |

### 3.2 Configuración

```powershell
Copy-Item .env.example .env
notepad .env
```

Variables principales:

```ini
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=America/Bogota
APP_BASE_URL=https://tu-dominio.com/
APP_SESSION_NAME=coffeeandtea
APP_COOKIE_SECURE=true
APP_COOKIE_SAMESITE=Lax

DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=CoffeeAndTea
DB_USER=postgres
DB_PASS=tu_clave

MAIL_DRIVER=smtp
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=tu_correo@gmail.com
SMTP_PASS=contrasena_de_aplicacion
SMTP_ENCRYPTION=tls
SMTP_FROM=tu_correo@gmail.com
SMTP_FROM_NAME=CoffeeAndtea
```

En desarrollo:

```ini
APP_ENV=development
APP_COOKIE_SECURE=false
MAIL_DRIVER=log
```

### 3.3 Base de datos

```powershell
$env:PGPASSWORD='tu_clave'
$psql='C:\Program Files\PostgreSQL\18\bin\psql.exe'

& $psql -U postgres -h 127.0.0.1 -d postgres -c "CREATE DATABASE coffeeandtea_qa;"
& $psql -U postgres -h 127.0.0.1 -d coffeeandtea_qa -v ON_ERROR_STOP=1 -f db\schema.sql
```

Para demo o QA:

```powershell
& $psql -U postgres -h 127.0.0.1 -d coffeeandtea_qa -v ON_ERROR_STOP=1 -f db\seed-demo.sql
```

**No cargues `seed-demo.sql` en producción.**

### 3.4 Administrador inicial

```powershell
php bin\crear-admin.php --nombre="Administrador" --correo="admin@tudominio.com"
```

El script genera una contraseña temporal si no se pasa `--password`, la muestra
una sola vez, marca `debe_cambiar_password = true` y registra la acción en
auditoría.

### 3.5 Servidor

```powershell
php -S localhost:8000 -t public
```

Con Laragon, el document root debe apuntar a `public/` y el host virtual a
`coffeeandtea.test`. Con Apache o Nginx, aplica lo mismo.

---

## 4. Base de datos

### 4.1 Las 14 tablas

| Tabla | Propósito |
|---|---|
| `usuario` | Cuentas, roles y estado. |
| `cliente` | Datos de clientes, vinculables a un usuario. |
| `categoria` | Categorías del menú. |
| `producto` | Catálogo, precio y stock. |
| `pedido` | Cabecera del pedido. |
| `detallepedido` | Líneas con precio histórico congelado. |
| `pago` | Pagos y reembolsos. |
| `caja_sesion` | Turnos de caja. |
| `movimiento_caja` | Ingresos y egresos. |
| `movimiento_stock` | Ventas, devoluciones y ajustes. |
| `pedido_historial_estado` | Historial de estados. |
| `password_reset` | Tokens de recuperación. |
| `auditoria` | Eventos sensibles. |
| `config_sistema` | Configuración de la tienda. |

### 4.2 Reglas del esquema

- Solo puede existir una caja `ABIERTA` (índice único parcial).
- El stock no puede ser negativo.
- El precio del detalle debe ser mayor que cero.
- El total del pedido se recalcula desde los detalles.
- Los estados están restringidos por `CHECK`.
- Los triggers validan cliente activo, producto activo y stock suficiente.

### 4.3 Migración desde CoffeeAndTea

```powershell
$env:ORIGEN_DSN='host=127.0.0.1 port=5432 dbname=CoffeeAndTea user=postgres password=tu_clave'
& $psql -U postgres -h 127.0.0.1 -d coffeeandtea_migracion `
  -v ON_ERROR_STOP=1 -f db\migraciones\001_migracion_inicial.sql
```

Cifras esperadas: **6 categorías, 11 clientes, 15 productos, 12 pedidos y 22
detalles**. La migración corre en una transacción y falla completa si los totales
no cuadran.

---

## 5. Operación diaria

### 5.1 Panel de gestión

`gestion.php` muestra cinco indicadores enlazados: por cobrar, pagados, en
preparación, listos y bajo stock. Debajo están las pestañas **Pedidos**, **Caja**
y **Stock**.

![Panel de gestión con indicadores, pestañas y lista de pedidos](media/capturas/gestion-pedidos.jpg)

*Figura 2. Panel de gestión: indicadores del día, pestañas y lista de pedidos con
filtros por estado.*

### 5.2 Detalle del pedido

![Detalle de un pedido pendiente con datos del cliente y del pago](media/capturas/gestion-detalle.jpg)

*Figura 3. Detalle: datos del cliente, productos con precio histórico, total,
observaciones y acciones según el estado.*

El detalle muestra datos del cliente, productos y cantidades, precio congelado,
total, estado, tiempo estimado, historial y pagos. El precio es el que tenía el
producto al crear el pedido: aunque cambie el precio actual, el pedido conserva
su valor original.

### 5.3 Cobro en mostrador

Solo se pueden cobrar pedidos en estado `PENDIENTE`.

![Formulario de cobro en efectivo con el cambio estimado](media/capturas/cobro-efectivo.jpg)

*Figura 4. Cobro: método de pago, efectivo recibido con cambio estimado y
referencia del datáfono.*

**Efectivo**

1. Abre el pedido y elige **Efectivo**.
2. Escribe el efectivo recibido (debe cubrir el total).
3. Confirma.

El sistema valida que haya una caja abierta, calcula el cambio, descuenta el
stock, registra el pago como `CONFIRMADO`, crea un `INGRESO` en la caja y pasa el
pedido a `PAGADO`.

**Tarjeta**

1. Cobra con el datáfono externo.
2. Elige **Tarjeta** y escribe la referencia.
3. Confirma.

El sistema descuenta el stock y guarda el pago con la referencia, pero **no**
mueve la caja porque el dinero entra por el datáfono.

**Reglas del cobro**

- No se puede cobrar dos veces el mismo pedido.
- No se puede cobrar un pedido cancelado o entregado.
- El stock se descuenta **solo al confirmar el pago**.
- Si no hay stock suficiente, el cobro se rechaza y no se modifica nada.

### 5.4 Estados del pedido

![Flujo del pedido desde la creación hasta la entrega](media/capturas/flujo-pedido.png)

*Figura 5. Flujo normal y política de cancelación.*

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

No se pueden saltar estados. Cada cambio queda en el historial y en la auditoría.

### 5.5 Cancelaciones y reembolsos

Se puede cancelar en `PENDIENTE`, `PAGADO`, `EN_PREPARACION` o `LISTO`. Nunca en
`ENTREGADO` ni en `CANCELADO`.

| Estado al cancelar | Reembolso |
|---|---|
| `PENDIENTE` (sin pago) | No hay dinero que devolver. |
| `PAGADO` (aún sin preparar) | 100 % de lo pagado. |
| `EN_PREPARACION` o `LISTO` | 50 % de lo pagado. |
| `ENTREGADO` o `CANCELADO` | No se puede cancelar. |

El monto se calcula automáticamente; no lo escribas a mano. Al confirmar:

- Se devuelve el stock y se registra un movimiento `DEVOLUCION`.
- El pago queda como `REEMBOLSADO` con el monto en `pago.monto_reembolsado`.
- Si fue en efectivo, se registra un `EGRESO` en la caja.
- Si fue con tarjeta, se guarda la referencia del reembolso y no se mueve la caja.

### 5.6 Caja

![Caja abierta con base, ingresos, egresos y movimiento manual](media/capturas/caja.jpg)

*Figura 6. Caja abierta: base inicial, ingresos, egresos, efectivo esperado y
registro de movimientos manuales.*

**Abrir caja:** escribe la base inicial (el efectivo con el que empieza el turno).
Solo puede haber **una caja abierta** a la vez.

**Movimientos:** durante el turno se registran `INGRESO` por pagos en efectivo y
`EGRESO` por reembolsos. También puedes registrar un movimiento manual indicando
tipo, monto y concepto.

**Cerrar caja**

![Cierre de caja con el efectivo esperado y el contado](media/capturas/cierre-caja.jpg)

*Figura 7. Cierre de caja: se escribe el efectivo contado y el sistema calcula la
diferencia.*

```text
esperado   = base inicial + ingresos − egresos
diferencia = contado − esperado
```

Diferencia `0` es caja cuadrada; positiva, sobrante; negativa, faltante. El cierre
guarda el efectivo contado, la diferencia, el usuario y la fecha.

### 5.7 Stock

![Pestaña de stock con existencias, mínimos y ajuste manual](media/capturas/stock.jpg)

*Figura 8. Stock: existencias, mínimo y formulario de ajuste con motivo.*

- **Descuento automático:** al confirmar el pago se registra un movimiento `VENTA`.
- **Devolución automática:** al cancelar un pedido pagado se registra `DEVOLUCION`.
- **Ajuste manual:** escribes el nuevo stock actual; el sistema valida que no sea
  negativo, registra un movimiento `AJUSTE` y guarda usuario y fecha.

Los productos por debajo del stock mínimo se resaltan en la lista y alimentan la
alerta de gestión.

> No uses el ajuste manual para “cuadrar” ventas. Si hay una diferencia,
> investígala primero con la auditoría.

### 5.8 Reportes

![Reportes de ventas del periodo, clientes y productos más vendidos](media/capturas/reportes.jpg)

*Figura 9. Reportes: ventas del periodo, ticket promedio, medios de pago, top
de productos y clientes con mayor consumo.*

`reportes.php` incluye ventas del periodo seleccionado, productos más vendidos,
clientes con mayor consumo, productos con bajo stock e ingresos por categoría.
La exportación CSV descarga las ventas del rango elegido y se abre en Excel o
Google Sheets.

Los reportes son de consulta: no modifican pedidos, pagos ni stock.

---

## 6. Administración

### 6.1 Usuarios y contraseñas

![Pestaña de usuarios con roles, estado y acciones](media/capturas/admin-usuarios.jpg)

*Figura 10. Usuarios: rol, estado, último acceso y creación de cuentas.*

- Ver todos los usuarios, su rol, estado y último acceso.
- Crear usuarios con rol `CLIENTE`, `ENCARGADO` o `ADMIN`.
- Editar nombre, rol y estado.
- Resetear la contraseña de un usuario.

Reglas:

- La contraseña temporal se muestra **una sola vez**.
- El usuario debe cambiarla al entrar.
- Resetear una contraseña incrementa `session_version` y cierra sus sesiones.
- No se puede desactivar ni degradar al **último administrador activo**.
- Los usuarios no se eliminan: se desactivan para conservar el histórico.

### 6.2 Productos y categorías

![Pestaña de productos con la tabla del catálogo](media/capturas/admin-productos.jpg)

*Figura 11. Productos: catálogo, categoría, precio, stock, estado y edición.*

Campos del producto: nombre, descripción, precio en COP, categoría, stock actual,
stock mínimo, tiempo de preparación, activo, destacado e imagen.

Reglas:

- Desactivar un producto lo oculta de la tienda.
- Cambiar el stock genera un movimiento `AJUSTE` y auditoría.
- El stock mínimo alimenta las alertas de gestión.
- El tiempo de preparación influye en el tiempo estimado del pedido.
- Los pedidos históricos conservan el precio congelado.

En **Categorías** puedes crear, editar y activar o desactivar. Una categoría
inactiva oculta sus productos de la tienda.

### 6.3 Imágenes de producto

![Formulario de edición con la vista previa de la imagen](media/capturas/admin-producto-imagen.jpg)

*Figura 12. Edición de producto: la imagen se ajusta sin recortar y se ve la
vista previa antes de guardar.*

- Formatos: JPG, PNG o WebP, máximo 2 MB.
- Al subirla se ajusta **sin recortar**, con un máximo de 1400 px por lado.
- La tarjeta muestra la foto completa en una proporción de 3:2.
- Para reemplazarla, selecciona otro archivo y guarda; el anterior se elimina si
  ningún otro producto lo usa.
- Para dejar el producto sin foto, marca **Quitar la imagen actual** y guarda.
- Se rechazan archivos que no sean imagen válida, que superen 2 MB o que no
  contengan una imagen.
- Si al cambiar de equipo las fotos no aparecen, verifica que existan en
  `public/media/productos` y ejecuta `db/asignar-imagenes.sql`.

### 6.4 Configuración de la tienda

![Configuración de la tienda con tiempos, contacto y video](media/capturas/admin-config.jpg)

*Figura 13. Configuración: nombre, tiempos de preparación, capacidad, contacto,
horario y video.*

| Campo | Efecto |
|---|---|
| Nombre de la tienda | Se muestra en la tienda y el manual. |
| Tiempo de respaldo | Se usa solo si un producto no tiene tiempo configurado. |
| Capacidad simultánea | Productos que la cocina puede preparar al mismo tiempo. |
| Buffer de seguridad | Porcentaje que se suma al tiempo calculado. |
| Dirección, teléfono, correo y horario | Información pública de la tienda. |
| Video de presentación | Enlace de YouTube o Google Drive. |
| Recibir pedidos web | Si se desactiva, el checkout queda cerrado. |

El tiempo estimado se calcula así:

```text
trabajo = suma(cantidad × tiempo del producto)
base    = ceil(trabajo / capacidad)
buffer  = ceil(base × porcentaje / 100)
cola    = ceil(trabajo de pedidos activos / capacidad)
estimado = max(5, base + buffer + cola)
```

La configuración se guarda en `config_sistema` y se aplica de inmediato.

**Correo saliente:** las credenciales viven en `.env`, no en la base de datos.
`MAIL_DRIVER=log` escribe en `storage/mail.log`; `MAIL_DRIVER=smtp` envía por
SMTP. En Gmail se requiere una contraseña de aplicación.

### 6.5 Auditoría

![Auditoría con los últimos eventos, usuario, acción e IP](media/capturas/auditoria.jpg)

*Figura 14. Auditoría: usuario, acción, tabla, registro, detalle JSON e IP.*

`admin.php?tab=auditoria` muestra los últimos 200 eventos: inicios de sesión y
fallos, pagos, cancelaciones y reembolsos, aperturas y cierres de caja, ajustes de
stock, cambios de usuarios, productos y configuración, y migraciones.

Cada evento guarda usuario, acción, tabla, registro, detalle JSON, IP y fecha.
Úsala para investigar diferencias de caja o stock, verificar quién canceló o
reembolsó y revisar accesos sospechosos.

---

## 7. Documentación, anexos y video

![Página de anexos con los documentos descargables](media/capturas/anexos.jpg)

*Figura 15. Anexos: SRS, PAC, MER, diagrama editable, script de base de datos y
log de errores.*

La página **Anexos** publica la documentación del proyecto con descarga directa.
Los manuales se descargan desde **Manual** en Word o Markdown.

![Página del video de presentación](media/capturas/video.jpg)

*Figura 16. Video: si no hay enlace configurado, la página muestra “Video
próximamente”.*

El video se configura en **Administración → Configuración**. Acepta enlaces de
YouTube (`youtu.be` o `watch?v=`) y de Google Drive con permiso “Cualquier
persona con el enlace”. Como respaldo se puede definir `APP_VIDEO_URL` en `.env`;
la configuración de la base de datos tiene prioridad.

---

## 8. Seguridad

Controles implementados:

- Contraseñas con `password_hash()` (bcrypt).
- Cookies `HttpOnly`, `SameSite` y `Secure` en producción.
- Regeneración del ID de sesión al iniciar y al cambiar contraseña.
- Expiración por inactividad y duración máxima de la sesión.
- Token CSRF en todos los formularios POST.
- Límite de 8 intentos fallidos por cuenta, con bloqueo de 10 minutos persistente en la base.
- RBAC por página y por acción, validado en el servidor.
- Validación de stock y de transiciones de estado en el servidor.
- Auditoría de acciones sensibles.

Recomendaciones:

- Usa HTTPS y `APP_COOKIE_SECURE=true`.
- Mantén `APP_DEBUG=false` en producción.
- No publiques `.env`, `storage/`, `db/` ni `tests/`.
- Restringe el acceso al servidor y a PostgreSQL: la base nunca debe quedar
  expuesta a internet.
- Actualiza PHP, PostgreSQL y las dependencias.
- Revisa la auditoría periódicamente.
- No uses las contraseñas demo en producción.

---

## 9. Respaldos

```powershell
& 'C:\Program Files\PostgreSQL\18\bin\pg_dump.exe' -U postgres -h 127.0.0.1 `
  -Fc -f respaldo_coffeeandtea.dump CoffeeAndTea
```

Restauración:

```powershell
& 'C:\Program Files\PostgreSQL\18\bin\pg_restore.exe' -U postgres -h 127.0.0.1 `
  -d CoffeeAndTea --clean --if-exists respaldo_coffeeandtea.dump
```

Buenas prácticas: respalda antes de migrar o actualizar, guarda los respaldos
fuera del servidor, prueba la restauración al menos una vez y no incluyas
respaldos en el repositorio.

---

## 10. Pruebas y calidad

```powershell
$env:DB_NAME='coffeeandtea_qa'
$env:DB_USER='postgres'
$env:DB_PASS='tu_clave'
php tests\run.php
```

Las pruebas se niegan a ejecutarse si la base no termina en `_qa` o `_test`.

```powershell
npm install
npx playwright install chromium
npx playwright test
```

El paquete de QA está en `qa/`:

- `qa/03-casos-prueba.csv`: 55 casos.
- `qa/04-matriz-trazabilidad.md`: 50 requisitos funcionales trazados.
- `qa/07-informe-resultados.md`: resultados.
- `qa/08-matriz-rbac.md`: permisos por rol.
- `qa/scripts/preparar-bd.ps1`: recrea la base QA.

---

## 11. Despliegue

1. Publica solo `public/` como document root.
2. Coloca `.env` fuera de la carpeta pública.
3. Configura HTTPS y `APP_COOKIE_SECURE=true`.
4. Usa `APP_ENV=production` y `APP_DEBUG=false`.
5. Crea la base y carga `db/schema.sql`.
6. Ejecuta la migración si vienes de CoffeeAndTea.
7. Crea el administrador con `bin/crear-admin.php`.
8. Configura el correo y prueba la recuperación.
9. Abre la caja con la base inicial real.
10. Programa respaldos automáticos.

**Acceso público temporal:** para probar sin dominio puedes usar Cloudflare
Tunnel o Tailscale Funnel. Si la URL cambia, actualiza `APP_BASE_URL`.

---

## 12. Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| 404 en todas las páginas | Docroot en la raíz | Apunta a `public/`. |
| Error de conexión | `.env` incorrecto o PostgreSQL detenido | Revisa credenciales y servicio. |
| Página en blanco | `APP_DEBUG=false` | Revisa `storage/php-error.log`. |
| No llega el correo | SMTP mal configurado o modo log | Revisa `storage/mail.log` o Mailpit. |
| La tienda no recibe pedidos | `pagina_abierta=false` | Actívala en configuración. |
| No aparece el botón Cobrar | El pedido no está `PENDIENTE` | Revisa el estado. |
| Dice que no hay caja abierta | No hay turno abierto | Abre la caja antes de cobrar en efectivo. |
| El efectivo recibido no alcanza | Monto menor al total | Revisa el total y el monto. |
| No puedo cancelar | Pedido `ENTREGADO` o `CANCELADO` | Revisa el estado. |
| El reembolso en efectivo falla | Caja cerrada o monto incorrecto | Verifica la caja y el monto. |
| No puedo abrir caja | Ya hay una caja abierta | Ciérrala primero. |
| El stock no coincide | Movimiento no registrado | Revisa auditoría y movimientos. |
| No veo Administración | La cuenta es `ENCARGADO` | Es correcto: es exclusiva del `ADMIN`. |
| No se puede desactivar un admin | Es el último ADMIN activo | Crea o activa otro ADMIN. |

---

## 13. Glosario

| Término | Significado |
|---|---|
| Pedido | Solicitud de productos de un cliente. |
| Detalle | Línea del pedido: producto, cantidad y precio. |
| Precio congelado | Precio guardado al crear el pedido; no cambia después. |
| Caja | Turno de efectivo con base, movimientos y cierre. |
| Ingreso / Egreso | Entrada o salida de efectivo de la caja. |
| Ajuste | Cambio manual del stock, con movimiento y auditoría. |
| Reembolso | Devolución del dinero de un pedido pagado. |
| RBAC | Control de acceso basado en roles. |
| CSRF | Token que impide enviar formularios desde otro sitio. |
| PDO | Capa de PHP para consultar PostgreSQL con sentencias preparadas. |
| CSV | Archivo de texto con datos separados por punto y coma. |

---

## 14. Checklist de puesta en marcha

- [ ] `php -l` sin errores en todos los archivos.
- [ ] `php tests/run.php` en verde.
- [ ] E2E de Playwright en verde.
- [ ] `.env` configurado y fuera de la entrega.
- [ ] `APP_ENV=production` y `APP_DEBUG=false`.
- [ ] `APP_COOKIE_SECURE=true` con HTTPS.
- [ ] Base creada y, si aplica, migrada y conciliada.
- [ ] Administrador creado y contraseña temporal cambiada.
- [ ] Correo de prueba recibido.
- [ ] Caja abierta con la base real.
- [ ] Respaldos programados.
- [ ] Sin secretos en el repositorio.

---

*Manual técnico de CoffeeAndtea. Actualizado el 25 de noviembre de 2026.*
