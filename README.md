# CoffeeAndtea

Sistema web de tienda y gestión para una cafetería. Permite **pedir en línea
para recoger en tienda**, cobrar en el mostrador y administrar pedidos, caja,
stock, usuarios y reportes.

> **Solo recogida.** No hay domicilios. El pago se realiza en el mostrador en
> efectivo o con tarjeta; el datáfono es externo y el sistema solo guarda la
> referencia de la transacción.

---

## 1. Funcionalidades

### Tienda pública
- Catálogo por categorías con imagen, precio, stock y tiempo estimado de preparación.
- Carrito con validación de existencias.
- Checkout como visitante o cliente registrado.
- Confirmación con código de pedido, token público, estado y tiempo estimado.
- Consulta posterior del pedido con **código + token**.
- Historial de pedidos recientes para invitados, guardado en el navegador.
- Registro, inicio de sesión y recuperación de contraseña por correo.

### Gestión interna (`ENCARGADO` y `ADMIN`)
- Lista de pedidos con filtros y detalle.
- Cobro en efectivo (con cambio y movimiento de caja) o tarjeta (con referencia).
- Flujo de estados: `PENDIENTE → PAGADO → EN_PREPARACION → LISTO → ENTREGADO`.
- Cancelaciones con motivo, responsable, reembolso y devolución de stock.
- Apertura y cierre de caja con efectivo contado y diferencia auditada.
- Ajuste manual de stock con movimiento y auditoría.

### Administración (`ADMIN`)
- Usuarios y roles (`CLIENTE`, `ENCARGADO`, `ADMIN`).
- Contraseñas temporales con cambio obligatorio y cierre de sesiones.
- Productos, categorías, imágenes de producto y configuración de la tienda.
- Reportes de ventas, productos, clientes, stock e ingresos por categoría.
- Exportación CSV y consulta de auditoría.

---

## 2. Estructura del proyecto

```text
CoffeeAndtea_Entrega/
├─ public/                  Document root (solo esto se publica)
│  ├─ index.php             Tienda pública
│  ├─ carrito.php           Carrito
│  ├─ checkout.php          Confirmación del pedido
│  ├─ pedido.php            Estado público por código + token
│  ├─ login.php, registro.php, recuperar.php, restablecer.php
│  ├─ cuenta.php            Historial del cliente
│  ├─ gestion.php           Pedidos, caja y stock
│  ├─ admin.php             Usuarios, productos, configuración y auditoría
│  ├─ reportes.php          Reportes y CSV
│  ├─ manual.php            Manual renderizado desde docs/manual.md
│  ├─ css/, js/, media/     Estilos, scripts e imágenes
│  └─ ...
├─ anexos/               PAC, SRS, MER, script BD y log de errores
├─ app/php/
│  ├─ lib/                  Configuración, conexión, auth, pedidos, correo, etc.
│  ├─ vistas/               Plantillas y componentes
├─ bin/crear-admin.php      Alta del administrador inicial por CLI
├─ db/
│  ├─ schema.sql            Esquema completo + triggers + procedimientos
│  ├─ seed-demo.sql         Datos demo/QA (no usar en producción)
│  └─ migraciones/          Migración desde la base anterior
├─ docs/                    Manual, guía de Laragon y LEEME
├─ qa/                      Plan, casos, matriz RBAC e informe
├─ tests/                   Unitarias, integración y E2E
├─ scripts/iniciar.bat      Servidor local de respaldo
├─ storage/                 Logs (se crea en ejecución; no se entrega)
├─ .env.example             Plantilla de configuración
├─ composer.json
├─ phpunit.xml
├─ package.json
└─ playwright.config.js
```

---

## 3. Requisitos

| Componente | Versión / detalle |
|---|---|
| PHP | 8.3 o superior |
| Extensiones PHP | `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `session`, `json`, `fileinfo`, `gd` |
| PostgreSQL | 18 (funciona desde 14) |
| Servidor web | Apache/Nginx con docroot en `public/`, o `php -S` |
| Composer | Opcional, para PHPUnit/PHPStan/PHPCS |
| Node.js | Opcional, 20+ para Playwright |

---

## 4. Instalación rápida

### 4.1 Configuración

```powershell
Copy-Item .env.example .env
```

Edita `.env` con los datos reales:

```ini
APP_ENV=development
APP_DEBUG=false
APP_BASE_URL=http://localhost:8000/
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=coffeeandtea_qa
DB_USER=postgres
DB_PASS=tu_clave
MAIL_DRIVER=log
```

> `.env` **nunca** se incluye en la entrega ni se sube a Git.

### 4.2 Base de datos

Para una instalación nueva:

```powershell
$env:PGPASSWORD='tu_clave'
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -U postgres -h 127.0.0.1 `
  -d postgres -c "CREATE DATABASE coffeeandtea_qa;"
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -U postgres -h 127.0.0.1 `
  -d coffeeandtea_qa -v ON_ERROR_STOP=1 -f db\schema.sql
```

Para cargar datos de demostración (solo desarrollo/QA):

```powershell
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -U postgres -h 127.0.0.1 `
  -d coffeeandtea_qa -v ON_ERROR_STOP=1 -f db\seed-demo.sql
```

### 4.3 Administrador inicial

En producción **no** cargues `seed-demo.sql`. Crea el administrador por CLI:

```powershell
php bin\crear-admin.php --nombre="Administrador" --correo="admin@tudominio.com"
```

El script genera una contraseña temporal, la muestra una sola vez y obliga a
cambiarla al primer inicio de sesión. También registra la acción en auditoría.

### 4.4 Arrancar

```powershell
scripts\iniciar.bat
# http://localhost:8000/
```

O manualmente:

```powershell
php -S localhost:8000 -t public
```

---

## 5. Migración desde la base anterior

La migración conserva las cifras históricas: **6 categorías, 11 clientes,
15 productos, 12 pedidos y 22 detalles**.

```powershell
# 1. Crear la base destino y cargar el esquema
# 2. Definir la conexión al origen (no se guarda en archivos)
$env:ORIGEN_DSN='host=127.0.0.1 port=5432 dbname=CoffeeAndTea user=postgres password=tu_clave'

# 3. Ejecutar la migración
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -U postgres -h 127.0.0.1 `
  -d coffeeandtea_migracion -v ON_ERROR_STOP=1 `
  -f db\migraciones\001_migracion_inicial.sql
```

La migración se ejecuta en una transacción y falla completa si los totales no
cuadran. Instrucciones y consultas de verificación en
[`db/migraciones/README.md`](db/migraciones/README.md).

> **Antes de migrar en producción:** haz un respaldo de `CoffeeAndTea` y prueba
> primero sobre una copia (`coffeeandtea_migracion`).
>
> Si actualizas una base creada antes de la política de reembolso parcial,
> ejecuta también `db/migraciones/002_reembolso_parcial.sql`.

---

## 6. Roles y permisos

| Rol | Acceso |
|---|---|
| Visitante (anónimo) | Tienda, carrito, checkout, consulta por código + token, manual y privacidad. No es un rol de base de datos. |
| `CLIENTE` | Lo anterior + su cuenta e historial. No accede a gestión ni administración. |
| `ENCARGADO` | Gestión de pedidos, cobro, caja, stock y reportes. No accede a administración ni usa carrito o cuenta de cliente. |
| `ADMIN` | Todas las funciones de gestión y administración: usuarios, productos, categorías, configuración y auditoría. No usa carrito ni cuenta de cliente. |

> Las cuentas internas (`ENCARGADO` y `ADMIN`) pueden consultar el menú, pero no
> comprar. El carrito, el checkout y “Mi cuenta” son exclusivos de visitantes y
> clientes.

Matriz completa en [`qa/08-matriz-rbac.md`](qa/08-matriz-rbac.md).

---

## 7. Reglas de negocio

1. **Stock:** se descuenta únicamente al confirmar el pago, nunca al crear el pedido.
2. **Precio histórico:** `detallepedido.precio_unitario` congela el precio; cambiar el precio actual no altera pedidos anteriores.
3. **Pago:** el método se elige al cobrar en el mostrador. El pedido web nace `PENDIENTE` sin método.
4. **Caja:** el efectivo exige una caja abierta. El cierre calcula `base + ingresos − egresos` y guarda la diferencia.
5. **Cancelación:** exige motivo; si el pedido estaba pagado, exige método de reembolso. Se devuelve el 100% antes de preparación y el 50% si ya está en preparación o listo; el stock vuelve al inventario.
6. **Estados:** no se puede cancelar un pedido `ENTREGADO` ni saltar transiciones.
7. **Último administrador:** no se puede desactivar ni degradar al último `ADMIN` activo.
8. **Tiempo estimado:** se calcula con los tiempos de cada producto, la capacidad de la cocina, un buffer del 15% y la carga de pedidos activos.

---

## 8. Correo

| Entorno | Configuración |
|---|---|
| Desarrollo con Mailpit | `MAIL_DRIVER=smtp`, `SMTP_HOST=127.0.0.1`, `SMTP_PORT=1025`, `SMTP_ENCRYPTION=none` |
| Desarrollo sin servidor | `MAIL_DRIVER=log` → escribe en `storage/mail.log` |
| Producción con Gmail | `MAIL_DRIVER=smtp`, `SMTP_HOST=smtp.gmail.com`, `SMTP_PORT=587`, `SMTP_ENCRYPTION=tls`, usuario y **contraseña de aplicación** |

La recuperación de contraseña usa un token hasheado, de un solo uso y con
vencimiento de 60 minutos. La respuesta es siempre genérica para no revelar si
el correo existe.

---

## 9. Seguridad

- Contraseñas con `password_hash()` (bcrypt).
- Sesiones con `HttpOnly`, `SameSite=Lax`, regeneración de ID y expiración.
- Token CSRF en todos los formularios POST.
- Límite de 8 intentos fallidos de login por 10 minutos.
- RBAC en cada página y acción sensible.
- Auditoría de login, pagos, cancelaciones, caja, stock y cambios de usuario.
- `APP_DEBUG=false` y `APP_ENV=production` en producción.
- HTTPS obligatorio con `APP_COOKIE_SECURE=true`.

---

## 10. Pruebas

### PHP (sin dependencias)

```powershell
$env:DB_NAME='coffeeandtea_qa'
$env:DB_USER='postgres'
$env:DB_PASS='tu_clave'
php tests\run.php
```

Cubre funciones puras y el flujo de pedido, pago con tarjeta y cancelación
sobre PostgreSQL real. Las pruebas se niegan a ejecutarse si la base no termina
en `_qa` o `_test`.

### Playwright (E2E)

```powershell
npm install
npx playwright install chromium
# Con el servidor en 127.0.0.1:8001
npx playwright test
```

Si usas otra URL:

```powershell
$env:E2E_BASE_URL='http://localhost:8000'
npx playwright test
```

### Composer (opcional)

```powershell
composer install
composer test
composer phpstan
composer phpcs
```

El pack completo de QA está en [`qa/`](qa/): plan, 53 casos, matriz de riesgos,
matriz RBAC, checklist de release e informe de resultados.

---

## 11. Despliegue

1. Publica **solo** `public/` como document root.
2. Coloca `.env` fuera de la carpeta pública y con permisos restringidos.
3. Configura HTTPS y `APP_COOKIE_SECURE=true`.
4. Usa `APP_ENV=production` y `APP_DEBUG=false`.
5. Crea la base, carga `db/schema.sql` y migra los datos.
6. Crea el administrador con `bin/crear-admin.php`.
7. Configura el correo y prueba la recuperación de contraseña.
8. Abre la caja con la base inicial real.
9. Programa respaldos de la base y de los comprobantes.

Para acceso público temporal sin dominio se puede usar Cloudflare Tunnel o
Tailscale Funnel; la URL cambia si el túnel es temporal.

---

## 12. Contenido de la entrega

Incluye código, esquema, migración, documentación, QA y pruebas.

**No incluye** (por seguridad y limpieza):

- `.env` ni credenciales reales.
- `vendor/` ni `node_modules/`.
- Logs de `storage/`.
- Evidencias con datos personales.
- Respaldos `.sql`, `.dump` o `.bak`.

---

## 13. Credenciales de demostración

Solo existen si se cargó `db/seed-demo.sql`. **No usarlas en producción.**

| Rol | Correo | Contraseña |
|---|---|---|
| `ADMIN` | `vegaperezkevinsantiago@gmail.com` | `Admin123!` |
| `ENCARGADO` | `encargado@coffeeandtea.local` | `Encargado123!` |
| `CLIENTE` | `cliente@coffeeandtea.local` | `Cliente123!` |

---

## 14. Documentación

- [`docs/manual.md`](docs/manual.md) — manual de usuario para visitantes y clientes.
- [`docs/manual-encargado.md`](docs/manual-encargado.md) — manual técnico de operación (caja, pedidos y stock).
- [`docs/manual-admin.md`](docs/manual-admin.md) — manual técnico de administración.
- [`docs/README_LARAGON.md`](docs/README_LARAGON.md) — instalación con Laragon.
- [`docs/LEEME.txt`](docs/LEEME.txt) — inicio rápido en texto plano.
- [`db/migraciones/README.md`](db/migraciones/README.md) — migración de datos.
- [`qa/07-informe-resultados.md`](qa/07-informe-resultados.md) — resultados de QA.

---

**Autor:** Kevin Vega Pérez  
**Año:** 2026
