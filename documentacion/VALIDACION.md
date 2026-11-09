# Validación — CoffeeAndtea v1.1

## Resultados vigentes

| Suite | Resultado |
|---|---|
| Casos de prueba QA | **49/49 aprobados** |
| Pruebas PHP (unitarias + integración) | **12/12 aprobadas** |
| Pruebas E2E Playwright | **12/12 aprobadas** |

## Evidencia

- Plan, casos y matriz RBAC: `CoffeeAndtea_Entrega/qa/`
- Informe detallado: `CoffeeAndtea_Entrega/qa/07-informe-resultados.md`
- Script de preparación de base QA: `CoffeeAndtea_Entrega/qa/scripts/preparar-bd.ps1`
- Pruebas PHP: `CoffeeAndtea_Entrega/tests/run.php`
- Pruebas E2E: `CoffeeAndtea_Entrega/tests/e2e/`

## Cobertura de los cambios v1.1

- Roles y restricciones de `ENCARGADO`/`ADMIN` (sin carrito ni cuenta).
- Manuales diferenciados por rol.
- Pedidos recientes de invitado en cookie.
- Línea de tiempo de 5 estados con check verde.
- Política de cancelación con reembolso del 100 % o 50 %.
- Columna `pago.monto_reembolsado` y migración 002.
- Tiempo estimado por producto, capacidad simultánea, buffer y cola.
- Migración de datos conciliada: 6/11/15/12/22.

## Cómo reproducir

```powershell
$env:DB_NAME='coffeeandtea_qa'
$env:DB_USER='postgres'
$env:DB_PASS='tu_clave'
php tests\run.php

npm install
npx playwright install chromium
$env:E2E_BASE_URL='http://127.0.0.1:8000'
npx playwright test
```

> Las pruebas se ejecutan sobre `coffeeandtea_qa`, nunca sobre `CoffeeAndTea`.
