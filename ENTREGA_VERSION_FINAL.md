# Entrega final — CoffeeAndtea

Rama: `feature/version-final-php`  
Base: `main`  
Repositorio: `Alejo-crtl/CoffeeAndTea`

Esta rama contiene la versión final en PHP 8.3 y PostgreSQL 18, con
documentación, anexos y pruebas.

## Contenido

- `public/`: tienda, gestión, administración, reportes, manuales y anexos.
- `app/php/`: lógica de autenticación, pedidos, pagos, caja, stock y correo.
- `anexos/`: PAC, SRS, MER, diagrama, script de base de datos y log de errores.
- `bin/crear-admin.php`: alta del administrador inicial.
- `db/`: esquema, datos demo y migraciones.
- `docs/`: manuales por rol.
- `qa/`: plan, casos, riesgos, RBAC e informe.
- `tests/`: pruebas unitarias, integración y E2E.

## Resultados

- Casos QA: **53/53**.
- Pruebas PHP: **14/14**.
- Pruebas E2E: **14/14**.
- Migración: 6 categorías, 11 clientes, 15 productos, 12 pedidos y 22 detalles.

## Instalación rápida

```powershell
Copy-Item .env.example .env
# Editar .env con la base PostgreSQL

psql -U postgres -h 127.0.0.1 -d postgres -c "CREATE DATABASE CoffeeAndTea;"
psql -U postgres -h 127.0.0.1 -d CoffeeAndTea -v ON_ERROR_STOP=1 -f db/schema.sql
php bin/crear-admin.php --nombre="Administrador" --correo="admin@tudominio.com"
php -S localhost:8000 -t public
```

## Pruebas

```powershell
$env:DB_NAME='coffeeandtea_qa'
$env:DB_USER='postgres'
$env:DB_PASS='tu_clave'
php tests/run.php

npm install
npx playwright install chromium
npx playwright test
```

## Nota para el administrador

El merge debe hacerse desde `feature/version-final-php` hacia `main`.
La rama reemplaza la aplicación anterior y deja la versión final en la raíz.
