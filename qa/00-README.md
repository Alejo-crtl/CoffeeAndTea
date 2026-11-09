# QA — CoffeeAndtea

Paquete de aseguramiento de calidad del sistema de tienda y gestión **CoffeeAndtea**.

## Contenido

| Archivo | Propósito |
|---|---|
| `00-README.md` | Este documento: alcance y cómo ejecutar la QA. |
| `01-plan-pruebas.md` | Estrategia, alcance, entornos y criterios de salida. |
| `02-matriz-riesgos.md` | Riesgos técnicos y de negocio con mitigación. |
| `03-casos-prueba.csv` | Casos de prueba ejecutables (importable a hoja de cálculo). |
| `04-matriz-trazabilidad.md` | Requisito → caso de prueba → resultado. |
| `05-plantilla-incidencias.md` | Plantilla para reportar defectos. |
| `06-checklist-release.md` | Verificación previa a cada entrega. |
| `07-informe-resultados.md` | Resultados de la ejecución actual. |
| `08-matriz-rbac.md` | Permisos por rol y por página/acción. |
| `09-datos-prueba.md` | Usuarios, productos y datos demo. |

## Resultados actuales

- **46/46 casos aprobados.**
- **12/12 pruebas PHP** (`php tests\run.php`).
- **10/10 pruebas E2E** (`npx playwright test`).
- Informe completo: `qa/07-informe-resultados.md`.

## Requisitos previos

- PHP 8.3 con `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`.
- PostgreSQL 18 en `127.0.0.1:5432`.
- PowerShell 5.1 o superior.
- (Opcional) Node.js 20+ para las pruebas E2E con Playwright.
- (Opcional) Composer para PHPUnit/PHPStan/PHPCS.

## Preparar la base de QA

```powershell
$env:PGPASSWORD='tu_clave_de_postgres'
powershell -ExecutionPolicy Bypass -File qa\scripts\preparar-bd.ps1
```

Esto **recrea** `coffeeandtea_qa` desde `db/schema.sql` + `db/seed-demo.sql`.
Nunca toca la base original `CoffeeAndTea`.

## Ejecutar la aplicación para pruebas

```powershell
.\scripts\iniciar.bat
# http://localhost:8000/
```

Para que use la base de QA, crea un `.env` local (no se entrega) o define las
variables de entorno antes de arrancar:

```powershell
$env:DB_NAME='coffeeandtea_qa'
$env:DB_USER='postgres'
$env:DB_PASS='tu_clave'
$env:MAIL_DRIVER='log'
$env:APP_ENV='development'
php -S localhost:8000 -t public
```

## Ejecutar pruebas automatizadas

```powershell
# Pruebas unitarias + integración sin dependencias externas
php tests\run.php

# Con Composer instalado (define DB_NAME, DB_USER y DB_PASS en el entorno)
composer install
composer test
composer phpstan
composer phpcs

# E2E (requiere Node y Playwright)
npm install
npx playwright install chromium
npx playwright test
```

## Reglas de la QA

1. Toda prueba se ejecuta sobre `coffeeandtea_qa`, nunca sobre `CoffeeAndTea`.
2. Cada defecto se registra con la plantilla `05-plantilla-incidencias.md`.
3. Un caso solo pasa si se verificó el efecto en base de datos, no solo la pantalla.
4. No se adjuntan contraseñas reales, tokens ni datos personales en las evidencias.
5. La entrega final no incluye `.env`, `vendor/`, `node_modules/` ni logs.
