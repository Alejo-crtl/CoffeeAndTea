<?php
/**
 * Página del manual: renderiza docs/manual.md con su índice.
 */
declare(strict_types=1);

require_once __DIR__ . '/php/lib/bootstrap.php';
require_once __DIR__ . '/php/lib/manual_contenido.php';

$manual = prepararManual(__DIR__ . '/docs/manual.md');
$manualToc = renderizarTocManual($manual['toc']);

cabeceraHtml('Manual | CoffeeAndtea', 'manual');
?>

<section id="manual" class="section section-manual" aria-labelledby="manual-titulo">
    <div class="section-head">
        <div>
            <p class="eyebrow">Documentación integrada</p>
            <h2 id="manual-titulo">Manual de uso</h2>
            <p>
                Guía completa del sistema, escrita en Markdown y renderizada aquí mismo.
                El archivo <code>manual.md</code> es la fuente editable de este contenido.
            </p>
        </div>
        <div class="section-actions">
            <button type="button" class="btn btn-secundario" onclick="window.print()">Imprimir manual</button>
            <a class="btn btn-secundario" href="<?= h(baseUrl() . 'docs/manual.md') ?>" download>Descargar .md</a>
        </div>
    </div>

    <?php if ($manual['error'] !== null): ?>
        <div class="alerta-error" role="alert">
            <strong>No se pudo cargar el manual.</strong>
            <p><?= h($manual['error']) ?></p>
        </div>
    <?php else: ?>
        <div class="manual-layout">
            <aside class="manual-toc" aria-label="Índice del manual">
                <h3>Contenido</h3>
                <?= $manualToc ?>
                <p class="hint">El índice se genera a partir de los títulos del archivo Markdown.</p>
            </aside>

            <article class="manual-contenido">
                <?= $manual['html'] ?>
            </article>
        </div>
    <?php endif; ?>
</section>

<?php pieHtml(); ?>
