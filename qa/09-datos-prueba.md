# Datos de prueba — CoffeeAndtea

> Estos datos provienen de `db/seed-demo.sql` y son **solo para desarrollo/QA**.
> No deben cargarse en producción.

## Usuarios

| Rol | Nombre | Correo | Contraseña |
|---|---|---|---|
| ADMIN | Administrador Demo | `admin@coffeeandtea.local` | `Admin123!` |
| ENCARGADO | Encargado Turno | `encargado@coffeeandtea.local` | `Encargado123!` |
| CLIENTE | Cliente Demo | `cliente@coffeeandtea.local` | `Cliente123!` |

> En producción, el administrador se crea con `bin\crear-admin.php` y una
> contraseña temporal aleatoria; el sistema exige cambiarla al primer ingreso.

## Categorías (6)

1. Bebidas Calientes
2. Bebidas Frías
3. Panadería
4. Postres
5. Desayunos
6. Otros

## Clientes demo (3)

| Nombre | Correo | Teléfono |
|---|---|---|
| Juan Pérez | `juan@example.com` | 3001234567 |
| María Gómez | `maria@example.com` | 3007654321 |
| Cliente Demo | `cliente@coffeeandtea.local` | 3005551234 |

## Productos (10)

| Producto | Precio | Stock | Categoría |
|---|---:|---:|---|
| Café Express | 3.500 | 50 | Bebidas Calientes |
| Capuchino | 5.500 | 40 | Bebidas Calientes |
| Té Negro | 2.800 | 30 | Bebidas Calientes |
| Frappé de Café | 7.500 | 25 | Bebidas Frías |
| Jugo Natural | 4.500 | 20 | Bebidas Frías |
| Croissant | 4.500 | 20 | Panadería |
| Pan de Queso | 3.200 | 35 | Panadería |
| Galleta Integral | 2.200 | 100 | Postres |
| Torta de Chocolate | 6.000 | 12 | Postres |
| Combo Café y Croissant | 8.000 | 15 | Desayunos |

## Pedidos demo (6)

| Código | Estado | Total | Cliente |
|---|---|---:|---|
| CAF-0001 | PENDIENTE | 11.500 | Juan Pérez |
| CAF-0002 | PAGADO | 17.500 | Juan Pérez |
| CAF-0003 | EN_PREPARACION | 21.600 | María Gómez |
| CAF-0004 | LISTO | 21.000 | María Gómez |
| CAF-0005 | ENTREGADO | 9.900 | Cliente Demo |
| CAF-0006 | CANCELADO | 5.500 | Cliente Demo |

Los pedidos 2 a 5 tienen pago confirmado; el pedido 6 está cancelado y su pago
figura como reembolsado. La caja demo abre con base de $50.000.

## Cómo reiniciar los datos

```powershell
$env:PGPASSWORD='tu_clave'
powershell -ExecutionPolicy Bypass -File qa\scripts\preparar-bd.ps1
```

Esto elimina y recrea `coffeeandtea_qa`. **No ejecutar contra `CoffeeAndTea`.**

## Recomendaciones para pruebas manuales

- Usa correos con el dominio `example.com` para no enviar correos reales.
- Abre una ventana de incógnito para probar el flujo de visitante.
- Para probar el correo en desarrollo, usa Mailpit (`http://localhost:8025`)
  o `MAIL_DRIVER=log` y revisa `storage/mail.log`.
- Para probar la recuperación de contraseña con `MAIL_DRIVER=log`, copia el
  enlace completo desde `storage/mail.log`.
- No reutilices las contraseñas demo en producción.
