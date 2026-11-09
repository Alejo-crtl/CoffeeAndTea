# Plan de pruebas — CoffeeAndtea

## 1. Objetivo

Verificar que el sistema permite **pedir en línea para recoger en tienda** y que
la operación interna (cobro, caja, stock, cancelaciones, auditoría) es correcta,
segura y trazable.

## 2. Alcance

### Incluido

- Tienda pública: catálogo, carrito, checkout, estado del pedido.
- Autenticación, recuperación de contraseña y cambio forzado.
- Roles `CLIENTE`, `ENCARGADO`, `ADMIN` y visitante anónimo.
- Cobro en mostrador (efectivo/tarjeta), caja y cierre diario.
- Descuento y devolución de stock.
- Cancelaciones con reembolso y auditoría.
- Administración de usuarios, productos, categorías y configuración.
- Reportes y exportación CSV.
- Migración de datos desde la base original `CoffeeAndTea`.

### Excluido

- Pasarela de pago en línea (no existe; el datáfono es externo).
- Domicilios (solo recogida en tienda).
- Carga de imágenes (espacio reservado, sin uploads).
- Aplicación móvil nativa.
- Infraestructura de servidor definitiva y dominio (se define después).

## 3. Estrategia

| Nivel | Técnica | Herramienta |
|---|---|---|
| Unitario | Funciones puras y validaciones | `tests/Unit` + `tests/run.php` |
| Integración | Flujos con PostgreSQL real | `tests/Integration` + `tests/run.php` |
| Sistema | Navegación y formularios | Playwright (`tests/e2e`) |
| Seguridad | RBAC, CSRF, sesión, fuerza bruta | Casos manuales + matriz RBAC |
| Datos | Migración y conciliación de cifras | SQL de migración + consultas de control |

## 4. Entornos

| Entorno | Base de datos | Correo | URL |
|---|---|---|---|
| Desarrollo | `coffeeandtea_qa` | `MAIL_DRIVER=log` o Mailpit | `http://localhost:8000` |
| Producción | `CoffeeAndTea` | Gmail SMTP (App Password) | Dominio o túnel temporal |

## 5. Datos de prueba

Ver `09-datos-prueba.md`. Los usuarios demo tienen contraseñas conocidas solo
para QA y **no deben usarse en producción**.

## 6. Criterios de entrada

- Esquema y seed cargados sin errores.
- Todos los archivos PHP pasan `php -l`.
- Servidor local responde 200 en las páginas públicas.

## 7. Criterios de salida

- 100 % de casos críticos y altos ejecutados.
- 0 defectos abiertos de severidad crítica o alta.
- Conciliación exacta: total del pedido = suma de detalles; caja = base + ingresos − egresos.
- Stock consistente tras pago y cancelación.
- Migración con cifras iguales al origen (6 categorías, 11 clientes, 15 productos, 12 pedidos, 22 detalles).

## 8. Riesgos y supuestos

- El pago con tarjeta depende de un datáfono externo; el sistema solo guarda la referencia.
- El correo en producción depende de una contraseña de aplicación de Gmail válida.
- El acceso público temporal depende del túnel (Cloudflare/Tailscale).
- La zona horaria de operación es `America/Bogota`.

## 9. Cronograma sugerido

| Fase | Duración |
|---|---|
| Preparación de entorno | 0,5 día |
| Pruebas unitarias e integración | 1 día |
| Pruebas de sistema y RBAC | 1 día |
| Migración y conciliación | 1 día |
| Regresión y cierre | 0,5 día |
