<?php
/**
 * BLOQUE B01 · NÚCLEO
 * Qué hace: excepciones compartidas por las librerías de negocio.
 * Páginas: todas (se carga desde bootstrap.php).
 */
declare(strict_types=1);

/** Error de regla de negocio con un mensaje apto para mostrar al usuario. */
class ReglaNegocioException extends RuntimeException
{
}

/** Error de validación con el detalle por campo del formulario. */
class ValidacionException extends ReglaNegocioException
{
    /** @var array<string,string> */
    public array $campos;

    /** @param array<string,string> $campos */
    public function __construct(string $mensaje, array $campos = [])
    {
        parent::__construct($mensaje);
        $this->campos = $campos;
    }
}
