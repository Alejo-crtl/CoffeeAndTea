<?php
/**
 * Vista de la página de inicio: hero, resumen de la base y video.
 *
 * Variables esperadas: $totalCategorias, $totalClientes, $totalProductos,
 * $totalPedidos, $totalDetalles.
 */
declare(strict_types=1);
?>

<!-- HERO SECTION -->
<section class="hero" aria-labelledby="hero-titulo">
    <div class="hero-copy">
        <p class="eyebrow">Panel integral de cafetería</p>
        <h1 id="hero-titulo">Todo tu café,<br><span>bien organizado.</span></h1>
        <p>
            Administra categorías, clientes, productos, pedidos y detalles; consulta
            los reportes de PostgreSQL y revisa el manual del sistema, cada sección
            en su propia página y con la misma barra de navegación.
        </p>

        <div class="hero-chips" aria-label="Resumen de la base de datos">
            <span class="hero-chip"><strong><?= $totalCategorias ?></strong> categorías</span>
            <span class="hero-chip"><strong><?= $totalClientes ?></strong> clientes</span>
            <span class="hero-chip"><strong><?= $totalProductos ?></strong> productos</span>
            <span class="hero-chip"><strong><?= $totalPedidos ?></strong> pedidos</span>
            <span class="hero-chip"><strong><?= $totalDetalles ?></strong> detalles</span>
        </div>

        <div class="hero-acciones">
            <a class="btn btn-claro" href="<?= h(baseUrl() . 'gestion.php') ?>">Ir a Gestión</a>
            <a class="btn btn-borde" href="<?= h(baseUrl() . 'reportes.php') ?>">Ver Reportes</a>
            <a class="btn btn-borde" href="<?= h(baseUrl() . 'manual.php') ?>">Leer el manual</a>
        </div>
    </div>

    <aside class="hero-panel" aria-label="Estado actual de la base de datos">
        <h2>Estado de la base</h2>
        <p>Información disponible en este momento</p>
        <ul class="hero-stats">
            <li><span>Categorías</span><strong><?= $totalCategorias ?></strong></li>
            <li><span>Clientes</span><strong><?= $totalClientes ?></strong></li>
            <li><span>Productos</span><strong><?= $totalProductos ?></strong></li>
            <li><span>Pedidos</span><strong><?= $totalPedidos ?></strong></li>
            <li><span>Detalles</span><strong><?= $totalDetalles ?></strong></li>
        </ul>
        <p class="hero-nota">
            PostgreSQL &middot; base <strong>CoffeeAndTea</strong> &middot;
            actualizado el <?= h(date('d/m/Y')) ?>
        </p>
    </aside>
</section>

<?php require __DIR__ . '/vista_video.php'; ?>
