<?php
/**
 * Página del manual: renderiza el documento que corresponde al rol actual.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/manual_contenido.php';

$manualActual = manualActual();
$rutaManual = APP_ROOT . '/docs/' . $manualActual['archivo'];
$manual = prepararManual($rutaManual);
$manualToc = renderizarTocManual($manual['toc']);

cabeceraHtml($manualActual['titulo'] . ' | CoffeeAndtea', 'manual');
?>

<section id="manual" class="section section-manual" aria-labelledby="manual-titulo">
    <div class="section-head">
        <div>
            <p class="eyebrow"><?= h($manualActual['eyebrow']) ?></p>
            <h2 id="manual-titulo"><?= h($manualActual['titulo']) ?></h2>
            <p><?= h($manualActual['descripcion']) ?></p>
            <p class="texto-suave">
                Vista: <strong><?= h($manualActual['rol']) ?></strong>
                &middot; Fuente: <code><?= h($manualActual['archivo']) ?></code>
            </p>
        </div>
        <div class="section-actions">
            <a class="btn btn-secundario" href="<?= h(url('index.php')) ?>">Volver a la tienda</a>
            <button type="button" class="btn btn-secundario" onclick="window.print()">Imprimir manual</button>
            <a class="btn btn-secundario" href="<?= h(baseUrl() . 'descargar-manual.php') ?>" download>Descargar .md</a>
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
                <p class="hint">El índice se genera a partir de los títulos del documento.</p>
            </aside>

            <article class="manual-contenido">
                <?= $manual['html'] ?>
            </article>
        </div>
    <?php endif; ?>
</section>

<?php pieHtml(); ?>
