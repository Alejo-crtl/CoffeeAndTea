<?php
/**
 * BLOQUE B10 · DOCUMENTACIÓN, ANEXOS Y VIDEO
 * Qué hace: decide qué manual corresponde al rol de la sesión.
 * Funciones: manualActual().
 * Páginas: manual.php, descargar-manual.php.
 */
declare(strict_types=1);

/**
 * @return array{rol:string,archivo:string,titulo:string,eyebrow:string,descripcion:string,descarga:string,descarga_docx:string,etiqueta:string}
 */
function manualActual(): array
{
    $usuario = function_exists('usuarioActual') ? usuarioActual() : null;
    $rol = $usuario['rol'] ?? 'VISITANTE';

    if ($rol === 'ADMIN' || $rol === 'ENCARGADO') {
        return [
            'rol'          => $rol,
            'archivo'      => 'manual-tecnico.md',
            'titulo'       => 'Manual técnico',
            'eyebrow'      => 'Guía técnica del sistema',
            'descripcion'  => 'Operación diaria, administración, instalación, base de datos, seguridad y despliegue.',
            'descarga'     => 'manual-tecnico-coffeeandtea.md',
            'descarga_docx' => 'Manual_Tecnico_CoffeeAndTea_v1.0.docx',
            'etiqueta'     => 'Manual técnico',
        ];
    }

    return [
        'rol'          => $usuario !== null ? 'CLIENTE' : 'VISITANTE',
        'archivo'      => 'manual.md',
        'titulo'       => 'Manual de usuario',
        'eyebrow'      => 'Guía para clientes',
        'descripcion'  => 'Aprende a pedir, pagar en el mostrador, recoger tu pedido y consultar su estado.',
        'descarga'     => 'manual-usuario-coffeeandtea.md',
        'descarga_docx' => 'Manual_Usuario_CoffeeAndTea_v1.0.docx',
        'etiqueta'     => 'Manual',
    ];
}
