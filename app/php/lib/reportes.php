<?php
/**
 * BLOQUE B09 · REPORTES
 * Qué hace: ejecuta los procedimientos de reportes que abren un refcursor.
 * Funciones: consultarProcedimiento().
 * Páginas: reportes.php.
 */
declare(strict_types=1);

/**
 * Ejecuta un procedimiento que abre un refcursor y devuelve sus filas.
 *
 * @param array<int, int|string|bool> $parametros
 * @return array<int, array<string, mixed>>
 */
function consultarProcedimiento(PDO $conn, string $procedimiento, array $parametros = []): array
{
    $cursor = 'cur_' . bin2hex(random_bytes(6));
    $marcas = implode(', ', array_fill(0, count($parametros), '?'));
    $sql = 'CALL ' . $procedimiento . '('
        . ($parametros === [] ? '' : $marcas . ', ')
        . "'" . $cursor . "')";

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare($sql);
        $indice = 1;
        foreach ($parametros as $valor) {
            $tipo = is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($indice++, is_bool($valor) ? ($valor ? 'true' : 'false') : $valor, $tipo);
        }
        $stmt->execute();
        $stmt->closeCursor();

        $filas = $conn->query('FETCH ALL IN "' . $cursor . '"')->fetchAll();
        $conn->commit();
        return $filas;
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}
