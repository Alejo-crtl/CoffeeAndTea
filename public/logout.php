<?php
/**
 * BLOQUE B02 · AUTENTICACIÓN Y CUENTAS
 * Qué hace: cierra la sesión y redirige a la tienda.
 * Relacionados: auth.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';

cerrarSesionUsuario();

// Nueva sesión limpia solo para mostrar el aviso de salida.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
session_regenerate_id(true);
flash('info', 'Cerraste sesión correctamente.');

redirigir(url('index.php'));
