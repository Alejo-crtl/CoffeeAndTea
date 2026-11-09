# Documentación — CoffeeAndtea

## Versión vigente

- **SRS v1.1:** `SRS_CoffeeAndTea_v1.1.docx`
- **MER v1.1:** `../03_Diagramas/Documentacion_MER_CoffeeAndTea_v1.1.docx`
- **Diagrama MER v1.1:** `../03_Diagramas/Diagrama_MER_CoffeeAndTea_v1.1.drawio`
- **Presentación v1.1:** `Sistema-de-Gestion-de-Pedidos-de-Cafeteria_v1.1.pptx`

## Versiones históricas

- `../_historico/documentacion_v1.0/SRS_CoffeeAndTea_v1.0.docx`: prototipo de 5 tablas y 5 módulos (octubre 2026).
- `Instrumento_Calidad_Software_v1.0.docx`: instrumento genérico de calidad.
- `../_historico/documentacion_v1.0/Sistema-de-Gestion-de-Pedidos-de-Cafeteria.pptx`: presentación del prototipo.

La versión 1.0 se conserva como evidencia de la evolución. La versión 1.1 es la
fuente de verdad para la implementación final en PHP/PostgreSQL de
`CoffeeAndtea_Entrega`.

## Cambios principales v1.0 → v1.1

- 14 tablas y roles `CLIENTE`, `ENCARGADO`, `ADMIN`.
- Autenticación, recuperación de contraseña y cambio forzado.
- Tienda pública con carrito, checkout y consulta por código + token.
- Cobro en mostrador, caja, stock y reportes.
- Cancelaciones con reembolso del 100 % o 50 %.
- Pedidos recientes de invitado en el navegador.
- Manuales diferenciados por rol.
- Auditoría, migración y pruebas automatizadas.

## Documentos relacionados

- `../PRODUCT.md`
- `../02_Base_Datos/00_LEEME_Base_Datos.md`
- `../../Coffeeandtea_Entrega/README.md` (implementación)
- `../../Coffeeandtea_Entrega/qa/07-informe-resultados.md`
