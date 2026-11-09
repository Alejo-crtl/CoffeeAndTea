# Checklist de release — CoffeeAndtea

Marcar cada punto antes de publicar o entregar una versión.

## Código y configuración

- [ ] `php -l` sin errores en todos los archivos PHP.
- [ ] `composer test` (o `php tests/run.php`) en verde.
- [ ] Sin credenciales en el código; revisar `grep` de contraseñas.
- [ ] `.env` creado fuera de la entrega y con permisos restrictivos.
- [ ] `APP_ENV=production` y `APP_DEBUG=false` en producción.
- [ ] `APP_COOKIE_SECURE=true` con HTTPS.
- [ ] `APP_BASE_URL` apunta a la URL real.
- [ ] Zona horaria `America/Bogota`.

## Base de datos

- [ ] `db/schema.sql` ejecuta sin errores en base vacía.
- [ ] `db/seed-demo.sql` es solo para demo/QA y no se carga en producción.
- [ ] Migración probada en copia y conciliada con el origen.
- [ ] Respaldo de la base original antes de migrar.
- [ ] Índices y restricciones presentes (caja única, stock ≥ 0, códigos únicos).
- [ ] Secuencias ajustadas tras migrar (`setval`).

## Seguridad

- [ ] RBAC verificado para visitante, CLIENTE, ENCARGADO y ADMIN.
- [ ] CSRF activo en todos los POST.
- [ ] Sesiones expiran por inactividad y por cambio de contraseña.
- [ ] Recuperación de contraseña con token de un solo uso.
- [ ] Sin listado de directorios ni acceso a `app/`, `db/` o `storage/` desde el navegador.
- [ ] HTTPS y cabeceras de seguridad activas.
- [ ] Datos personales y logs no incluidos en la entrega.

## Operación

- [ ] Caja abre, cobra, registra egresos y cierra con diferencia calculada.
- [ ] Pago con tarjeta registra referencia sin tocar caja.
- [ ] Stock consistente después de pagar y cancelar.
- [ ] Correo de prueba recibido (Mailpit en desarrollo o Gmail en producción).
- [ ] Reportes y CSV muestran cifras correctas.
- [ ] Manual y ayuda actualizados.

## Entrega

- [ ] Sin `.env`, `vendor/`, `node_modules/`, logs ni evidencias con datos reales.
- [ ] `README.md` raíz con instalación y credenciales de demo.
- [ ] `qa/07-informe-resultados.md` actualizado.
- [ ] Contraseña temporal del administrador entregada por canal seguro.
- [ ] Copia de respaldo de la base y del proyecto.
