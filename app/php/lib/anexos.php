<?php
/**
 * Catálogo de documentos de la carpeta anexos/.
 */
declare(strict_types=1);

/**
 * @return array<string,array{titulo:string,descripcion:string,archivo:string,tipo:string,mime:string}>
 */
function listaAnexos(): array
{
    return [
        'pac' => [
            'titulo'      => 'PAC — Plan de aseguramiento de calidad',
            'descripcion' => 'Plan, riesgos, casos de prueba, trazabilidad, RBAC y resultados de calidad v1.1.',
            'archivo'     => 'PAC_Plan_Aseguramiento_Calidad_Coffeeandtea_v1.1.docx',
            'tipo'        => 'Word',
            'mime'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'srs' => [
            'titulo'      => 'SRS — Especificación de requisitos',
            'descripcion' => 'Requisitos funcionales y no funcionales del sistema final v1.2.',
            'archivo'     => 'SRS_CoffeeAndTea_v1.2.docx',
            'tipo'        => 'Word',
            'mime'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'mer' => [
            'titulo'      => 'MER — Modelo entidad-relación',
            'descripcion' => 'Entidades, relaciones, diccionario de datos, índices y triggers.',
            'archivo'     => 'Documentacion_MER_CoffeeAndTea_v1.1.docx',
            'tipo'        => 'Word',
            'mime'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'diagrama' => [
            'titulo'      => 'Diagrama MER editable',
            'descripcion' => 'Diagrama de las 14 tablas para abrir en diagrams.net / draw.io.',
            'archivo'     => 'Diagrama_MER_CoffeeAndTea_v1.1.drawio',
            'tipo'        => 'Draw.io',
            'mime'        => 'application/xml',
        ],
        'script_bd' => [
            'titulo'      => 'Script de base de datos',
            'descripcion' => 'Esquema completo de PostgreSQL: tablas, triggers, índices y procedimientos.',
            'archivo'     => 'script_bd.sql',
            'tipo'        => 'SQL',
            'mime'        => 'application/sql; charset=UTF-8',
        ],
        'log' => [
            'titulo'      => 'Log de errores y sugerencias',
            'descripcion' => 'Errores corregidos, limitaciones actuales y propuestas de mejora.',
            'archivo'     => 'Log_Errores_Sugerencias_Coffeeandtea_v1.0.docx',
            'tipo'        => 'Word',
            'mime'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ];
}
