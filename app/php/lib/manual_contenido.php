<?php
/**
 * BLOQUE B10 · DOCUMENTACIÓN, ANEXOS Y VIDEO
 * Qué hace: carga el manual Markdown, lo convierte con Parsedown y genera el índice.
 * Funciones: prepararManual(), renderizarTocManual().
 * Páginas: manual.php, descargar-manual.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/Parsedown.php';

/**
 * @return array{html:string,toc:array<int,array{nivel:int,texto:string,slug:string}>,error:?string}
 */
function prepararManual(string $ruta): array
{
    if (!is_file($ruta) || !is_readable($ruta)) {
        return [
            'html'  => '',
            'toc'   => [],
            'error' => 'No se encontró el archivo del manual. Verifica que esté en la carpeta docs.',
        ];
    }

    $markdown = file_get_contents($ruta);
    if ($markdown === false) {
        return [
            'html'  => '',
            'toc'   => [],
            'error' => 'No fue posible leer el archivo del manual. Revisa los permisos.',
        ];
    }

    $parsedown = new Parsedown();
    $parsedown->setSafeMode(true);
    $html = $parsedown->text($markdown);

    $toc = [];
    $usados = [];

    $html = preg_replace_callback(
        '/<h([23])>(.*?)<\/h\1>/s',
        static function (array $coincidencia) use (&$toc, &$usados): string {
            $nivel = (int) $coincidencia[1];
            $texto = trim(html_entity_decode(strip_tags($coincidencia[2]), ENT_QUOTES, 'UTF-8'));
            $base = slugificar($texto);
            $slug = $base;
            $contador = 2;

            while (in_array($slug, $usados, true)) {
                $slug = $base . '-' . $contador;
                $contador++;
            }
            $usados[] = $slug;

            $toc[] = [
                'nivel' => $nivel,
                'texto' => $texto,
                'slug'  => $slug,
            ];

            return '<h' . $nivel . ' id="' . h($slug) . '">' . $coincidencia[2] . '</h' . $nivel . '>';
        },
        $html
    );

    $html = $html ?? '';

    // Envolver las tablas para que puedan desplazarse en pantallas pequeñas.
    $html = str_replace(
        '<table>',
        '<div class="tabla-scroll manual-tabla-scroll"><table class="manual-tabla">',
        $html
    );
    $html = str_replace('</table>', '</table></div>', $html);

    return [
        'html'  => $html,
        'toc'   => $toc,
        'error' => null,
    ];
}

/** Construye el índice lateral del manual. */
function renderizarTocManual(array $toc): string
{
    if (empty($toc)) {
        return '';
    }

    $html = '<ul class="toc-lista">';
    foreach ($toc as $entrada) {
        $clase = $entrada['nivel'] === 3 ? 'toc-nivel-3' : 'toc-nivel-2';
        $html .= '<li class="' . $clase . '"><a href="#' . h($entrada['slug']) . '">'
            . h($entrada['texto']) . '</a></li>';
    }
    $html .= '</ul>';

    return $html;
}
