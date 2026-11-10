<?php
/**
 * Video de presentación del proyecto.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/php/lib/bootstrap.php';

$videoUrl = urlVideoPresentacion($conn);

cabeceraHtml('Video | CoffeeAndtea', 'video');
?>

<section class="pagina-cabecera">
    <div>
        <p class="eyebrow">Presentación del proyecto</p>
        <h1>Video de presentación</h1>
        <p>Resumen audiovisual de CoffeeAndtea.</p>
    </div>
    <a class="btn btn-secundario" href="<?= h(url('index.php')) ?>">Volver a la tienda</a>
</section>

<section class="video-presentacion" aria-labelledby="video-titulo">
    <h2 id="video-titulo" class="sr-only">Reproductor del video de presentación</h2>

    <div class="video-marco<?= $videoUrl === null ? ' sin-video' : '' ?>">
        <?php if ($videoUrl !== null): ?>
            <iframe
                src="<?= h($videoUrl) ?>"
                title="Video de presentación de CoffeeAndtea"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen
                loading="lazy"
                referrerpolicy="strict-origin-when-cross-origin"></iframe>
        <?php else: ?>
            <div class="video-placeholder">
                <span class="video-placeholder-icono" aria-hidden="true">▶</span>
                <h3>Video próximamente</h3>
                <p>Aquí se publicará la presentación del proyecto. El administrador podrá pegar el enlace de YouTube o Google Drive desde Configuración.</p>
            </div>
        <?php endif; ?>
    </div>

    <p class="video-nota">
        El video se reproduce desde YouTube o Google Drive. No se descarga ni se guarda en el servidor.
    </p>
</section>

<?php pieHtml(); ?>
