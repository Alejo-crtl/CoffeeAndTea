<?php
/**
 * Página pública de anexos y descargas.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';
require_once __DIR__ . '/../app/php/lib/anexos.php';

$anexos = listaAnexos();

cabeceraHtml('Anexos | CoffeeAndtea', 'manual');
?>

<section class="pagina-cabecera">
    <div>
        <p class="eyebrow">Documentación del proyecto</p>
        <h1>Anexos</h1>
        <p>Documentos técnicos, académicos y de calidad de CoffeeAndtea.</p>
    </div>
    <a class="btn btn-secundario" href="<?= h(url('index.php')) ?>">Volver a la tienda</a>
</section>

<div class="anexos-grid">
    <?php foreach ($anexos as $id => $anexo): ?>
        <?php
        $ruta = APP_ROOT . '/anexos/' . $anexo['archivo'];
        $disponible = is_file($ruta) && is_readable($ruta);
        $tamanio = $disponible ? filesize($ruta) : 0;
        ?>
        <article class="anexo-card">
            <span class="anexo-tipo"><?= h($anexo['tipo']) ?></span>
            <h2><?= h($anexo['titulo']) ?></h2>
            <p><?= h($anexo['descripcion']) ?></p>
            <p class="texto-suave">
                <?= $disponible
                    ? h($anexo['archivo']) . ' · ' . h((string) max(1, (int) round($tamanio / 1024))) . ' KB'
                    : 'Archivo no disponible temporalmente.' ?>
            </p>
            <?php if ($disponible): ?>
                <a class="btn btn-primario" href="<?= h(url('descargar-anexo.php?id=' . urlencode($id))) ?>">
                    Descargar
                </a>
            <?php else: ?>
                <button class="btn btn-secundario" type="button" disabled>No disponible</button>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>

<?php pieHtml(); ?>
