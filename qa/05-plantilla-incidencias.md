# Plantilla de incidencias — CoffeeAndtea

Copia este bloque por cada defecto encontrado.

```
ID:            INC-____
Título:        [Resumen corto y específico]
Fecha:         AAAA-MM-DD
Reportado por:
Caso de prueba: CP-____
Severidad:     Crítica | Alta | Media | Baja
Prioridad:     Urgente | Alta | Media | Baja
Estado:        Nuevo | En análisis | En corrección | En verificación | Cerrado | Rechazado
Componente:    Tienda | Carrito | Checkout | Gestión | Caja | Administración | Reportes | Migración | Infraestructura
Versión:       (commit o fecha de la entrega)
Entorno:       Desarrollo (coffeeandtea_qa) | Producción

Descripción:
[Qué ocurre y por qué es un problema]

Precondiciones:
- Usuario / rol:
- Pedido / producto:
- Caja abierta: sí/no
- Datos relevantes:

Pasos para reproducir:
1.
2.
3.

Resultado actual:
[Qué se observó]

Resultado esperado:
[Qué debería ocurrir]

Evidencia:
- Captura o ruta de archivo (sin datos personales ni contraseñas)
- Consulta SQL de verificación:
- Log relevante (storage/php-error.log, storage/mail-error.log)

Impacto:
[Económico, de datos, de seguridad o de experiencia]

Causa raíz (llenar al cerrar):
[Explicación técnica]

Corrección aplicada:
[Archivo y cambio]

Verificación:
- Caso re-ejecutado:
- Fecha de cierre:
- Verificado por:
```

## Reglas

1. Un defecto por incidencia; no agrupar problemas distintos.
2. La severidad mide el impacto; la prioridad mide la urgencia de corrección.
3. Toda incidencia de dinero, stock o permisos se clasifica como mínima **Alta**.
4. Una incidencia solo se cierra con la prueba de regresión ejecutada.
