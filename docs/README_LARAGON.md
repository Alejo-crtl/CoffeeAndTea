# CoffeeAndtea — Guía de instalación con Laragon

Esta guía deja el sistema funcionando en Windows con Laragon y PostgreSQL.
El proyecto puede vivir en cualquier carpeta; lo importante es que el
**document root** apunte a `public/`, no a la raíz del proyecto.

---

## 1. Requisitos

| Componente | Versión usada |
|---|---|
| Laragon | 8.x |
| Apache | 2.4.x |
| PHP | 8.3 con `pdo_pgsql`, `pgsql`, `mbstring`, `openssl` |
| PostgreSQL | 18 en `127.0.0.1:5432` |
| Navegador | Chrome, Edge o Firefox actualizado |

Comprueba las extensiones de PHP:

```powershell
php -m | Select-String 'pgsql|pdo|mbstring|openssl'
```

---

## 2. Ubicación del proyecto

Copia la carpeta de la entrega, por ejemplo:

```text
C:\laragon\www\CoffeeAndtea_Entrega
```

La raíz contiene `public/`, `app/`, `db/`, `docs/`, `tests/`, etc.
**No publiques la raíz completa**: el docroot debe ser `public/`.

---

## 3. Configurar el document root

### Opción A: vhost de Laragon

1. Abre Laragon → **Menu → Apache → sites-enabled**.
2. Crea o edita el archivo `coffeeandtea.test.conf`:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/laragon/www/CoffeeAndtea_Entrega/public"
    ServerName coffeeandtea.test
    <Directory "C:/laragon/www/CoffeeAndtea_Entrega/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

3. Reinicia Apache.
4. Abre `http://coffeeandtea.test/`.

### Opción B: servidor de PHP incluido

```powershell
cd C:\laragon\www\CoffeeAndtea_Entrega
php -S localhost:8000 -t public
```

Abre `http://localhost:8000/`.

También puedes usar el acceso directo:

```text
scripts\iniciar.bat
```

---

## 4. Crear el archivo `.env`

Copia la plantilla y edita los valores:

```powershell
Copy-Item .env.example .env
notepad .env
```

Ejemplo para desarrollo local:

```ini
APP_ENV=development
APP_DEBUG=false
APP_TIMEZONE=America/Bogota
APP_BASE_URL=http://coffeeandtea.test/
APP_SESSION_NAME=coffeeandtea
APP_COOKIE_SECURE=false
APP_COOKIE_SAMESITE=Lax

DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=coffeeandtea_qa
DB_USER=postgres
DB_PASS=tu_clave

MAIL_DRIVER=log
```

Para producción: `APP_ENV=production`, `APP_DEBUG=false`,
`APP_COOKIE_SECURE=true` y `APP_BASE_URL` con `https://`.

---

## 5. Preparar la base de datos

### Instalación nueva

```powershell
$env:PGPASSWORD='tu_clave'
$psql='C:\Program Files\PostgreSQL\18\bin\psql.exe'

& $psql -U postgres -h 127.0.0.1 -d postgres -c "CREATE DATABASE coffeeandtea_qa;"
& $psql -U postgres -h 127.0.0.1 -d coffeeandtea_qa -v ON_ERROR_STOP=1 -f db\schema.sql
```

### Con datos de demostración

```powershell
& $psql -U postgres -h 127.0.0.1 -d coffeeandtea_qa -v ON_ERROR_STOP=1 -f db\seed-demo.sql
```

### Desde la base anterior

Sigue [`db/migraciones/README.md`](../db/migraciones/README.md). La migración
se prueba primero en una copia y nunca modifica `CoffeeAndTea`.

### Script de QA

```powershell
$env:PGPASSWORD='tu_clave'
powershell -ExecutionPolicy Bypass -File qa\scripts\preparar-bd.ps1
```

Este script **elimina y recrea** `coffeeandtea_qa`.

---

## 6. Crear el administrador

Con datos demo ya existe un administrador. En una instalación limpia:

```powershell
php bin\crear-admin.php --nombre="Kevin Vega" --correo="admin@tudominio.com"
```

Guarda la contraseña temporal que muestra el script. Al entrar, el sistema
obligará a cambiarla.

---

## 7. Verificar la instalación

1. Abre `http://coffeeandtea.test/` o `http://localhost:8000/`.
2. Comprueba que se ven categorías, productos, precios y el tiempo estimado.
3. Entra a `login.php` con el administrador.
4. Abre `gestion.php` y verifica las pestañas **Pedidos**, **Caja** y **Stock**.
5. Abre `admin.php` y revisa usuarios, productos y configuración.
6. Abre `reportes.php` y exporta un CSV.
7. Abre `manual.php` para ver este manual dentro del sistema.

---

## 8. Correo en desarrollo

### Mailpit

Si tienes Mailpit en `127.0.0.1:1025`:

```ini
MAIL_DRIVER=smtp
SMTP_HOST=127.0.0.1
SMTP_PORT=1025
SMTP_ENCRYPTION=none
SMTP_FROM=noreply@coffeeandtea.local
```

Abre `http://localhost:8025` para ver los correos.

### Modo log

```ini
MAIL_DRIVER=log
```

Los correos se escriben en `storage/mail.log`. Es útil para copiar el enlace de
recuperación sin configurar SMTP.

---

## 9. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| 404 en todas las páginas | El docroot apunta a la raíz | Cámbialo a `public/`. |
| “No fue posible conectar con la base” | `.env` incorrecto o PostgreSQL detenido | Revisa credenciales y el servicio. |
| Página en blanco | `APP_DEBUG=false` oculta el error | Revisa `storage/php-error.log`. |
| No llega el correo | `MAIL_DRIVER=log` o SMTP mal configurado | Revisa `storage/mail.log` o Mailpit. |
| El administrador vuelve a `cuenta.php` | Rol o sesión desactualizada | Cierra sesión y vuelve a entrar. |
| La tienda no recibe pedidos | `pagina_abierta=false` | Actívala en `admin.php?tab=configuracion`. |
| Caja bloqueada | Ya hay una caja abierta | Ciérrala antes de abrir otra. |

---

## 10. Respaldos

Antes de migrar o actualizar:

```powershell
& $psql -U postgres -h 127.0.0.1 -d CoffeeAndTea -Fc -f respaldo_coffeeandtea.dump
```

Restauración:

```powershell
& 'C:\Program Files\PostgreSQL\18\bin\pg_restore.exe' -U postgres -h 127.0.0.1 `
  -d CoffeeAndTea --clean --if-exists respaldo_coffeeandtea.dump
```

Los respaldos no se incluyen en la entrega.

---

*Actualizado: 8 de noviembre de 2026.*
