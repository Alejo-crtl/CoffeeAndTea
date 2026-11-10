# Manual técnico de administración — CoffeeAndtea

Este manual está dirigido al rol **ADMIN**. Cubre la instalación, la
configuración, la seguridad, la operación y el mantenimiento del sistema.

> El administrador puede usar **todas las funciones de gestión y
> administración**, pero no el carrito, el checkout ni la cuenta de cliente:
> esas funciones son exclusivas de visitantes y clientes. Además de la
> operación diaria, es responsable de usuarios, productos, configuración,
> auditoría y respaldos.

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
| Cambiar configuración de la tienda | No | No | No | Sí |
| Ver auditoría completa | No | No | No | Sí |
| Ejecutar migración y respaldos | No | No | No | Sí |

El visitante no es un rol de base de datos: es una sesión anónima.

> Las cuentas `ENCARGADO` y `ADMIN` son de gestión. Pueden consultar el menú,
> pero no tienen carrito, checkout ni “Mi cuenta”. Si intentan abrir esas
> páginas, el sistema los devuelve a `gestion.php`.

---

## 2. Arquitectura del proyecto

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
│  ├─ descargar-manual.php  Descarga del manual en Markdown
│  ├─ css/, js/, media/     Recursos estáticos
│  └─ .htaccess             Endurecimiento de Apache
├─ anexos/               PAC, SRS, MER, script BD y log de errores
├─ app/php/
│  ├─ lib/
│  │  ├─ config.php         Carga de .env
│  │  ├─ conectarBD.php     Conexión PDO
│  │  ├─ bootstrap.php      Sesión, CSRF, helpers y arranque
│  │  ├─ auth.php           Login, roles y contraseñas
│  │  ├─ pedidos.php        Carrito, pedidos, pagos, caja y stock
│  │  ├─ correo.php         Envío SMTP o log
│  │  ├─ password_reset.php Recuperación de contraseña
│  │  ├─ cuenta.php         Registro y cuenta de cliente
│  │  ├─ auditoria.php      Registro de eventos
│  │  └─ manual_rol.php     Selección del manual por rol
│  ├─ vistas/               Plantillas y componentes
├─ bin/crear-admin.php      Alta del administrador inicial
├─ db/
│  ├─ schema.sql            Esquema, triggers y procedimientos
│  ├─ seed-demo.sql         Datos demo/QA
│  └─ migraciones/          Migración desde CoffeeAndTea
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

## 3. Requisitos e instalación

### 3.1 Requisitos

| Componente | Requisito |
|---|---|
| PHP | 8.3 o superior |
| Extensiones | `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `session`, `json` |
| PostgreSQL | 14 o superior (probado con 18) |
| Servidor web | Apache/Nginx con docroot en `public/` |
| Composer | Opcional, para PHPUnit/PHPStan/PHPCS |
| Node.js | Opcional, 20+ para Playwright |

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

En desarrollo puedes usar:

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

El script:

- Genera una contraseña temporal si no se pasa `--password`.
- La muestra una sola vez.
- Marca `debe_cambiar_password = true`.
- Registra la acción en auditoría.

### 3.5 Servidor

```powershell
php -S localhost:8000 -t public
```

O con Apache/Nginx, apuntando el document root a `public/`.

---

## 4. Base de datos y migración

### 4.1 Tablas principales

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

Cifras esperadas: **6 categorías, 11 clientes, 15 productos, 12 pedidos y
22 detalles**. La migración se ejecuta en una transacción y falla completa si
los totales no cuadran. Ver `db/migraciones/README.md`.

---

## 5. Usuarios y contraseñas

Desde `admin.php?tab=usuarios` puedes:

- Ver todos los usuarios, su rol, estado y último acceso.
- Crear usuarios con rol `CLIENTE`, `ENCARGADO` o `ADMIN`.
- Editar nombre, rol y estado.
- Resetear la contraseña de un usuario.

### Reglas

- La contraseña temporal se muestra **una sola vez**.
- El usuario debe cambiarla al entrar.
- Resetear una contraseña incrementa `session_version` y cierra sus sesiones.
- No se puede desactivar ni degradar al **último administrador activo**.
- Los usuarios no se eliminan: se desactivan.

### Buenas prácticas

- Usa una cuenta personal por persona; no compartas el ADMIN.
- Revisa periódicamente los usuarios inactivos.
- Ante una sospecha de acceso, resetea la contraseña y revisa auditoría.
- El administrador inicial debe cambiar su contraseña temporal de inmediato.

---

## 6. Productos y categorías

### 6.1 Categorías

`admin.php?tab=categorias`

- Crear, editar y activar/desactivar.
- Una categoría inactiva oculta sus productos de la tienda.
- No se eliminan: se desactivan para conservar el histórico.

### 6.2 Productos

`admin.php?tab=productos`

Campos:

- Nombre y descripción.
- Precio en COP.
- Categoría.
- Stock actual y stock mínimo.
- Tiempo de preparación.
- Activo.
- Destacado.
- Imagen del producto (JPG, PNG o WebP, máximo 2 MB).

Reglas:

- Desactivar un producto lo oculta de la tienda.
- Cambiar el stock genera un movimiento `AJUSTE` y auditoría.
- El stock mínimo alimenta las alertas de gestión.
- El tiempo de preparación influye en el tiempo estimado del pedido.
- Los pedidos históricos conservan el precio congelado.
- Al subir una imagen se recorta y escala automáticamente a 960 × 540 px.
- Para reemplazar la foto, selecciona otro archivo y guarda. El archivo anterior se elimina si ningún otro producto lo usa.
- Para dejar el producto sin foto, marca **Quitar la imagen actual** y guarda. La tienda mostrará el espacio reservado.
- Se rechazan archivos que no sean JPG, PNG o WebP, que superen los 2 MB o que no contengan una imagen válida.

---

## 7. Configuración de la tienda

`admin.php?tab=configuracion`

| Campo | Efecto |
|---|---|
| Nombre de la tienda | Se muestra en la tienda y el manual. |
| Tiempo de respaldo | Se usa solo si un producto no tiene tiempo configurado. |
| Capacidad simultánea | Productos que la cocina puede preparar al mismo tiempo. |
| Buffer de seguridad | Porcentaje que se suma al tiempo calculado. |
| Dirección | Información para recogida. |
| Teléfono | Contacto público. |
| Correo de contacto | Se muestra en la tienda. |
| Horario | Información de atención. |
| Recibir pedidos web | Si se desactiva, el checkout queda cerrado. |

El tiempo estimado del pedido se calcula con los tiempos de preparación de cada
producto, la capacidad simultánea, el buffer y la carga de pedidos activos:

```text
trabajo = suma(cantidad × tiempo del producto)
base = ceil(trabajo / capacidad)
buffer = ceil(base × porcentaje / 100)
cola = ceil(trabajo de pedidos activos / capacidad)
estimado = max(5, base + buffer + cola)
```

El admin puede variar el tiempo de cada producto y los parámetros generales en
`admin.php?tab=configuracion`.

La configuración se guarda en `config_sistema`. Los cambios se aplican de
inmediato.

### Correo saliente

La pestaña muestra el proveedor SMTP configurado y permite enviar una prueba.
Las credenciales viven en `.env`, no en la base de datos.

- `MAIL_DRIVER=log`: escribe en `storage/mail.log`.
- `MAIL_DRIVER=smtp`: envía por SMTP (Mailpit o Gmail).
- En Gmail se requiere una **contraseña de aplicación**, no la contraseña normal.

### Cuenta administradora

Desde el mismo panel puedes cambiar tu propia contraseña.

---

## 8. Operación diaria

El administrador también puede operar la tienda:

- Ver pedidos y cobrar en efectivo o tarjeta.
- Avanzar estados.
- Cancelar con reembolso.
- Abrir y cerrar caja.
- Ajustar stock.
- Consultar reportes.

El detalle operativo está en el **Manual técnico de operación - Encargado**,
visible al iniciar sesión con un usuario `ENCARGADO`.

### Política de cancelación

- Pedido pagado antes de preparación: reembolso del **100%**.
- Pedido en preparación o listo: reembolso del **50%**.
- Pedido entregado: no se puede cancelar.
- Pedido sin pago: se anula sin reembolso.

El monto se calcula automáticamente y queda guardado en `pago.monto_reembolsado`.
El checkout exige aceptar esta política antes de registrar el pedido.

---

## 9. Reportes

`reportes.php` genera:

- Ventas del día.
- Productos más vendidos.
- Clientes con mayor consumo.
- Productos con bajo stock.
- Ingresos por categoría.

La exportación CSV usa `Content-Type: text/csv` y un nombre con la fecha. Los
reportes son de solo lectura.

---

## 10. Auditoría

`admin.php?tab=auditoria` muestra los últimos 200 eventos:

- Inicios de sesión y fallos.
- Pagos confirmados.
- Cancelaciones y reembolsos.
- Aperturas y cierres de caja.
- Ajustes de stock.
- Cambios de usuarios, productos y configuración.
- Migración de datos.

Cada evento guarda usuario, acción, tabla, registro, detalle JSON, IP y fecha.

### Uso recomendado

- Investigar diferencias de caja o stock.
- Verificar quién canceló o reembolsó un pedido.
- Revisar accesos fallidos o sospechosos.
- Demostrar trazabilidad ante una auditoría.

---

## 11. Seguridad

### Controles implementados

- Contraseñas con `password_hash()` (bcrypt).
- Cookies `HttpOnly`, `SameSite` y `Secure` en producción.
- Regeneración del ID de sesión al iniciar y cambiar contraseña.
- Expiración por inactividad y duración máxima.
- Token CSRF en todos los formularios POST.
- Límite de 8 intentos fallidos de login por 10 minutos.
- RBAC por página y por acción.
- Validación de stock y estados en el servidor.
- Auditoría de acciones sensibles.

### Recomendaciones

- Usa HTTPS y `APP_COOKIE_SECURE=true`.
- Mantén `APP_DEBUG=false` en producción.
- No publiques `.env`, `storage/`, `db/` ni `tests/`.
- Restringe el acceso al servidor y a PostgreSQL.
- Actualiza PHP, PostgreSQL y dependencias.
- Revisa la auditoría periódicamente.
- No uses las contraseñas demo en producción.

---

## 12. Respaldos

Respaldo lógico:

```powershell
& 'C:\Program Files\PostgreSQL\18\bin\pg_dump.exe' -U postgres -h 127.0.0.1 `
  -Fc -f respaldo_coffeeandtea.dump CoffeeAndTea
```

Restauración:

```powershell
& 'C:\Program Files\PostgreSQL\18\bin\pg_restore.exe' -U postgres -h 127.0.0.1 `
  -d CoffeeAndTea --clean --if-exists respaldo_coffeeandtea.dump
```

Buenas prácticas:

- Respalda antes de migrar o actualizar.
- Guarda los respaldos fuera del servidor.
- Prueba la restauración al menos una vez.
- Conserva los comprobantes de caja y pagos.
- No incluyas respaldos en la entrega del código.

---

## 13. Pruebas y QA

### PHP

```powershell
$env:DB_NAME='coffeeandtea_qa'
$env:DB_USER='postgres'
$env:DB_PASS='tu_clave'
php tests\run.php
```

Las pruebas se niegan a ejecutarse si la base no termina en `_qa` o `_test`.

### Playwright

```powershell
npm install
npx playwright install chromium
npx playwright test
```

### Composer (opcional)

```powershell
composer install
composer test
composer phpstan
composer phpcs
```

El pack de QA está en `qa/`:

- `qa/03-casos-prueba.csv`: 53 casos.
- `qa/07-informe-resultados.md`: resultados.
- `qa/08-matriz-rbac.md`: permisos por rol.
- `qa/scripts/preparar-bd.ps1`: recrea la base QA.

---

## 14. Despliegue

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

### Acceso público temporal

Para probar sin dominio puedes usar Cloudflare Tunnel o Tailscale Funnel. La URL
puede cambiar si el túnel es temporal; actualiza `APP_BASE_URL` si es necesario.

---

## 15. Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| 404 en todas las páginas | Docroot en la raíz | Apunta a `public/`. |
| Error de conexión | `.env` incorrecto o PostgreSQL detenido | Revisa credenciales y servicio. |
| Página en blanco | `APP_DEBUG=false` | Revisa `storage/php-error.log`. |
| No llega el correo | SMTP mal configurado o modo log | Revisa `storage/mail.log` o Mailpit. |
| La tienda no recibe pedidos | `pagina_abierta=false` | Actívala en configuración. |
| No se puede abrir caja | Ya hay una caja abierta | Ciérrala primero. |
| No se puede desactivar un admin | Es el último ADMIN activo | Crea o activa otro ADMIN. |
| El administrador vuelve a `cuenta.php` | Sesión antigua | Cierra sesión y vuelve a entrar. |
| El stock no coincide | Movimiento no registrado | Revisa auditoría y movimientos. |

---

## 16. Checklist de release

- [ ] `php -l` sin errores en todos los archivos.
- [ ] `php tests/run.php` en verde.
- [ ] E2E de Playwright en verde.
- [ ] `.env` configurado y fuera de la entrega.
- [ ] `APP_ENV=production` y `APP_DEBUG=false`.
- [ ] `APP_COOKIE_SECURE=true` con HTTPS.
- [ ] Base migrada y conciliada.
- [ ] Administrador creado y contraseña temporal cambiada.
- [ ] Correo de prueba recibido.
- [ ] Caja abierta con la base real.
- [ ] Respaldos programados.
- [ ] Sin secretos en el repositorio.

---

*Manual técnico de administración de CoffeeAndtea. Actualizado el 8 de noviembre de 2026.*
