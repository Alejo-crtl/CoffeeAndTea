# Entrega final — CoffeeAndtea

Rama: `feature/version-final-php`  
Base: `main`  
Repositorio: `Alejo-crtl/CoffeeAndTea`

Esta rama reemplaza la aplicación anterior por la versión final en PHP 8.3 y
PostgreSQL 18, con documentación y pruebas incluidas.

## Contenido

- `public/`: tienda, gestión, administración, reportes y manuales.
- `app/php/`: lógica de autenticación, pedidos, pagos, caja, stock y correo.
- `bin/crear-admin.php`: alta del administrador inicial.
- `db/`: esquema, datos demo y migraciones.
- `docs/`: manuales por rol.
- `documentacion/`: SRS, MER, diagrama, presentación y plan de QA en Word.
- `qa/`: plan, casos, riesgos, RBAC e informe de resultados.
- `tests/`: pruebas unitarias, integración y E2E.

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

## Resultados

- Casos QA: **46/46**.
- Pruebas PHP: **12/12**.
- Pruebas E2E: **10/10**.
- Migración: 6 categorías, 11 clientes, 15 productos, 12 pedidos y 22 detalles.

## Nota para el administrador del repositorio

El merge debe hacerse desde `feature/version-final-php` hacia `main`. La rama
elimina la app anterior y deja la versión final en la raíz del repositorio.
