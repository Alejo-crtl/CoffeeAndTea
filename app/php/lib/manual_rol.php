<?php
/**
 * BLOQUE B10 · DOCUMENTACIÓN, ANEXOS Y VIDEO
 * Qué hace: decide qué manual corresponde al rol de la sesión.
 * Funciones: manualActual().
 * Páginas: manual.php, descargar-manual.php.
 */
declare(strict_types=1);

/**
 * @return array{rol:string,archivo:string,titulo:string,eyebrow:string,descripcion:string,descarga:string,etiqueta:string}
 */
function manualActual(): array
{
    $usuario = function_exists('usuarioActual') ? usuarioActual() : null;
    $rol = $usuario['rol'] ?? 'VISITANTE';

    if ($rol === 'ADMIN') {
        return [
            'rol'         => 'ADMIN',
            'archivo'     => 'manual-admin.md',
            'titulo'      => 'Manual técnico de administración',
            'eyebrow'     => 'Guía para administradores',
            'descripcion' => 'Instalación, usuarios, productos, configuración, auditoría, seguridad y mantenimiento del sistema.',
            'descarga'    => 'manual-administracion-coffeeandtea.md',
            'etiqueta'    => 'Manual técnico',
        ];
    }

    if ($rol === 'ENCARGADO') {
        return [
            'rol'         => 'ENCARGADO',
            'archivo'     => 'manual-encargado.md',
            'titulo'      => 'Manual técnico de operación',
            'eyebrow'     => 'Guía técnica para encargados',
            'descripcion' => 'Pedidos, cobro en mostrador, caja, stock y reportes: todo lo que necesitas para operar la tienda.',
            'descarga'    => 'manual-operacion-coffeeandtea.md',
            'etiqueta'    => 'Manual operación',
        ];
    }

    return [
        'rol'         => $usuario !== null ? 'CLIENTE' : 'VISITANTE',
        'archivo'     => 'manual.md',
        'titulo'      => 'Manual de usuario',
        'eyebrow'     => 'Guía para clientes',
        'descripcion' => 'Aprende a pedir, pagar en el mostrador, recoger tu pedido y consultar su estado.',
        'descarga'    => 'manual-usuario-coffeeandtea.md',
        'etiqueta'    => 'Manual',
    ];
}
