# CoffeeAndtea - Guía de uso local

## Abrir el sistema

La forma más rápida:

1. Haz doble clic en `C:\laragon\www\CoffeeAndtea\abrir.bat`.
2. Se abre el navegador en `http://coffeeandtea.test/` si Apache ya está activo.
3. Si Apache no está activo, el mismo archivo inicia un servidor PHP en
   `http://localhost:8080/`.

También puedes abrirlo manualmente:

1. Abre Laragon.
2. Pulsa **Start All** si Apache o PostgreSQL no están iniciados.
3. Entra a `http://coffeeandtea.test/` o `http://localhost/CoffeeAndtea/`.

---

## Estado actual

| Componente | Versión / Estado |
|---|---|
| Laragon | 8.8.0 instalado en `C:\laragon` |
| Apache | 2.4.68 |
| PHP | 8.3.33 con `pdo_pgsql`, `pgsql` y `mbstring` habilitados |
| PostgreSQL | 18 (servicio `postgresql-x64-18`, puerto 5432) |
| Base de datos | `CoffeeAndTea` con datos reales del proyecto |

El vhost `coffeeandtea.test` ya apunta a `C:/laragon/www/CoffeeAndtea`.

---

## Base de datos

La conexión está configurada en:

```
C:\laragon\www\CoffeeAndtea\php\lib\conectarBD.php
```

Valores actuales:

- Host: `localhost`
- Puerto: `5432`
- Base: `CoffeeAndTea`
- Usuario: `postgres`
- Contraseña: configurada en `conectarBD.php`

También se pueden usar las variables de entorno `DB_HOST`, `DB_PORT`,
`DB_NAME`, `DB_USER` y `DB_PASS`.

La base ya contiene las cinco tablas, el trigger que recalcula el total de los
pedidos y los cinco procedimientos de reportes.

---

## Cómo arrancar y detener

### Opción 1: abrir.bat (recomendada)

- Doble clic en `abrir.bat`.
- Si usa el servidor de respaldo, mantén la ventana negra abierta.
- Para detenerlo, cierra esa ventana o pulsa `Ctrl+C`.

### Opción 2: Laragon

1. Abre `C:\laragon\laragon.exe`.
2. Pulsa **Start All**.
3. Para detenerlo, pulsa **Stop All**.

PostgreSQL se ejecuta como servicio de Windows y queda disponible aunque
cierres la ventana de Laragon.

---

## Estructura del proyecto

```text
C:\laragon\www\CoffeeAndtea\
├── abrir.bat                    # Abre el sistema en el navegador
├── index.php                    # Inicio: hero, resumen y video
├── gestion.php                  # CRUD de las cinco tablas
├── reportes.php                 # Reportes con procedimientos de PostgreSQL
├── manual.php                   # Manual renderizado desde docs/manual.md
├── php\
│   ├── lib\                     # Infraestructura y lógica
│   │   ├── bootstrap.php        # Sesión, conexión, CSRF, flashes y helpers
│   │   ├── conectarBD.php       # Conexión PDO a PostgreSQL
│   │   ├── consultas.php        # Conteos compartidos
│   │   ├── control_gestion.php  # Validaciones y operaciones del CRUD
│   │   ├── control_reportes.php # Ejecución de reportes con refcursor
│   │   ├── manual_contenido.php # Renderizado del Markdown y su índice
│   │   ├── Parsedown.php        # Markdown -> HTML
│   │   └── Parsedown-LICENSE.txt
│   ├── vistas\                  # Presentación HTML
│   ├── formularios\             # Un formulario por tabla
│   └── tablas\                  # Un listado por tabla
├── css\app.css                  # Estilos
├── js\detalles.js               # Filas dinámicas del formulario de detalles
├── docs\
│   ├── manual.md                # Fuente del manual
│   ├── LEEME.txt
│   └── README_LARAGON.md
├── media\                       # Logo y favicon
└── scripts\
    ├── iniciar.bat              # Alias de abrir.bat
    └── router.php               # Enrutador del servidor PHP de respaldo
```

---

## Navegación

| Sección | Página |
|---|---|
| Inicio | `index.php` |
| Gestión | `gestion.php` |
| Reportes | `reportes.php` |
| Manual | `manual.php` |

Los enlaces del menú navegan en la misma pestaña. El botón atrás y adelante del
navegador funciona por página.

---

## Gestión de datos

Cada pestaña de `gestion.php` tiene un formulario y un listado con acciones
**Editar** y **Eliminar**.

Orden recomendado para registrar:

1. Categoría
2. Cliente
3. Producto (requiere una categoría)
4. Pedido (requiere un cliente)
5. Detalle (requiere un pedido y un producto)

Reglas principales:

- No se puede eliminar una categoría que tenga productos.
- No se puede eliminar un cliente que tenga pedidos.
- No se puede eliminar un producto que aparezca en detalles de pedido.
- Al eliminar un pedido se eliminan también sus detalles.
- El total del pedido se calcula automáticamente con los detalles.
- Si un detalle cambia de pedido, se recalculan el pedido anterior y el nuevo.
- En Detalles se pueden agregar varios productos distintos en un solo envío.
  Un producto no puede repetirse en el mismo pedido.

Los avisos de éxito y error se muestran como notificaciones flotantes
(toasts) en la esquina superior derecha.

---

## Reportes

Se usan los procedimientos almacenados con `refcursor`:

- `sp_reporte_ventas_diarias`
- `sp_reporte_top_productos`
- `sp_reporte_clientes_vip`
- `sp_reporte_bajo_stock`
- `sp_reporte_ingresos_categoria`

El reporte de bajo stock permite indicar el límite; por defecto usa 30.
“Ventas del día” solo muestra pedidos con estado `PAGADO` creados hoy.

---

## Manual

El contenido está en `docs/manual.md`. Para cambiarlo, edita ese archivo y
recarga `manual.php`. El índice se genera automáticamente a partir de los
títulos. El botón **Imprimir manual** aplica estilos de impresión y el botón
**Descargar .md** entrega el archivo fuente.

Parsedown se usa en modo seguro.

---

## Requisitos

- Laragon con Apache y PHP 8.3; extensiones `pdo_pgsql`, `pgsql` y `mbstring`.
- PostgreSQL en `localhost:5432` con la base `CoffeeAndTea`.
- El proyecto en `C:\laragon\www\CoffeeAndtea`.
- No abras los `.php` con doble clic: necesitan Apache o el servidor de
  respaldo.

---

## Verificación técnica

### Sintaxis PHP

```powershell
Get-ChildItem -Recurse -Filter *.php 'C:\laragon\www\CoffeeAndtea' |
  ForEach-Object { & 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe' -l $_.FullName }
```

### Extensiones activas

```powershell
& 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe' -m |
  Select-String 'pgsql|pdo|mbstring|openssl'
```

### Base de datos

```powershell
$env:PGPASSWORD='MySQLcontra26'
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -U postgres -h 127.0.0.1 -p 5432 -d CoffeeAndTea `
  -c "SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name;"
```

---

## Problemas conocidos

- **`coffeeandtea.test` no responde**: inicia Apache en Laragon o usa
  `http://localhost/CoffeeAndtea/`. También puedes ejecutar `abrir.bat`.
- **Error de conexión a la base**: verifica que el servicio
  `postgresql-x64-18` esté iniciado y que la contraseña de
  `php/lib/conectarBD.php` sea la correcta.
- **Backdrop-filter en Chromium**: el desenfoque de la topbar puede no
  renderizarse sin aceleración por hardware. No afecta la funcionalidad.
- **Páginas en la raíz**: `index.php`, `gestion.php`, `reportes.php` y
  `manual.php` son los puntos de entrada públicos y deben quedarse en la raíz
  para conservar las URLs simples.
