<?php
/**
 * Full-bleed half-map layout modeled on the theme's listings-map.html (view=list, default) and
 * listings-map-grid-1.html (view=grid): edge-to-edge search bar, then a filters sidebar + results
 * column + map column that runs to the browser edge — no centered container anywhere on this
 * page, unlike the rest of the site. "list" vs "grid" only changes the card style (and therefore
 * the results column's grid), so this stays one route/one template rather than two.
 */
$otherParams = array_filter(['q' => $filters['q'], 'category' => $filters['category']], static fn ($v) => $v !== '');
$categoryLinkParams = static fn (string $categorySlug) => array_filter(
    ['q' => $filters['q'], 'category' => $categorySlug, 'view' => $view],
    static fn ($v) => $v !== ''
);
?>
<div class="explore-search-bar border-top border-bottom">
    <form method="get" action="<?= e(url('/explore')) ?>" class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 gap-lg-3 px-3 px-xl-4 py-3">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <input type="hidden" name="category" value="<?= e($filters['category']) ?>">
        <div class="align-items-center d-flex search-field flex-grow-1">
            <div class="svg-icon">
                <i class="fa-solid fa-search"></i>
            </div>
            <input type="text" name="q" class="form-control search-input" placeholder="<?= e(t('explore.search_placeholder')) ?>" value="<?= e($filters['q']) ?>">
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary d-lg-none" id="explore-filters-toggle-btn">
                <i class="fa-solid fa-sliders me-1"></i><?= e(t('explore.filters')) ?>
            </button>
            <button type="submit" class="btn btn-primary rounded-3"><?= e(t('explore.search_button')) ?></button>
        </div>
    </form>
</div>

<div class="explore-layout">
    <aside class="explore-filters-col" id="explore-filters-col">
        <div class="d-flex justify-content-between align-items-center border-bottom p-3 d-lg-none">
            <span class="fw-semibold"><?= e(t('explore.filters')) ?></span>
            <button type="button" class="btn-icon btn-light rounded-circle" id="explore-filters-close-btn" aria-label="<?= e(t('explore.close_filters')) ?>">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="p-3 p-xl-4">
            <h4 class="fs-6 fw-semibold text-uppercase text-muted mb-3"><?= e(t('home.categories_kicker')) ?></h4>
            <div class="list-group list-group-flush">
                <a href="<?= e(url('/explore?' . http_build_query($categoryLinkParams('')))) ?>"
                   class="list-group-item list-group-item-action <?= $filters['category'] === '' ? 'active' : '' ?>">
                    <?= e(t('explore.all_categories')) ?>
                </a>
                <?php foreach ($categories as $category): ?>
                <a href="<?= e(url('/explore?' . http_build_query($categoryLinkParams($category['slug'])))) ?>"
                   class="list-group-item list-group-item-action <?= $filters['category'] === $category['slug'] ? 'active' : '' ?>">
                    <?= e(\Sofiago\Models\Category::label($category)) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>

    <div class="explore-list-col">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="text-muted"><?= e(t('explore.results_count', ['count' => (int) $total])) ?></div>
            <div class="btn-group explore-view-toggle" role="group">
                <a href="<?= e(url('/explore?' . http_build_query(array_merge($otherParams, ['view' => 'list'])))) ?>"
                   class="btn btn-sm btn-outline-secondary <?= $view === 'list' ? 'active' : '' ?>">
                    <i class="fa-solid fa-list me-1"></i><?= e(t('explore.view_list')) ?>
                </a>
                <a href="<?= e(url('/explore?' . http_build_query(array_merge($otherParams, ['view' => 'grid'])))) ?>"
                   class="btn btn-sm btn-outline-secondary <?= $view === 'grid' ? 'active' : '' ?>">
                    <i class="fa-solid fa-table-cells-large me-1"></i><?= e(t('explore.view_grid')) ?>
                </a>
            </div>
        </div>

        <?php if ($listings === []): ?>
            <div class="alert alert-light border text-center py-5"><?= e(t('explore.empty')) ?></div>
        <?php elseif ($view === 'grid'): ?>
            <div class="row g-4">
                <?php foreach ($listings as $listing): ?>
                <div class="col-sm-6">
                    <?= partial('listing-card.tpl.php', ['listing' => $listing, 'favoriteIds' => $favoriteIds]) ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?php foreach ($listings as $listing): ?>
                <?= partial('listing-card-list.tpl.php', ['listing' => $listing, 'favoriteIds' => $favoriteIds]) ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= e(url('/explore?' . http_build_query(array_merge($otherParams, ['view' => $view, 'page' => $p])))) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <div class="explore-map-col" id="explore-map-col">
        <button type="button" class="btn btn-icon btn-light rounded-circle explore-map-close d-lg-none" id="explore-map-close-btn" aria-label="<?= e(t('explore.close_map')) ?>">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div id="map-explore" data-lat="42.6977" data-lng="23.3219" data-zoom="12"
             data-api="<?= e(url('/api/listings')) ?>" data-filters='<?= e(json_encode($filters)) ?>'></div>
    </div>
</div>

<button type="button" class="btn btn-primary rounded-pill explore-map-toggle d-lg-none" id="explore-map-toggle-btn">
    <i class="fa-solid fa-map-location-dot me-2"></i><?= e(t('explore.show_map')) ?>
</button>

<script>
    // Mobile-only "show map" / "show filters" overlays — see .explore-map-col.mobile-open and
    // .explore-filters-col.mobile-open in custom.css for why (below the half-map breakpoint the
    // map/sidebar would otherwise sit off-screen at the bottom of a long list). Plain inline
    // script: this is the only page that needs it, not worth its own vue-widgets entry.
    (function () {
        var mapCol = document.getElementById('explore-map-col');
        var mapOpenBtn = document.getElementById('explore-map-toggle-btn');
        var mapCloseBtn = document.getElementById('explore-map-close-btn');
        if (mapCol && mapOpenBtn && mapCloseBtn) {
            mapOpenBtn.addEventListener('click', function () {
                mapCol.classList.add('mobile-open');
                document.body.style.overflow = 'hidden';
                window.dispatchEvent(new Event('resize')); // MapLibre needs a nudge — it was 0x0 while display:none
            });
            mapCloseBtn.addEventListener('click', function () {
                mapCol.classList.remove('mobile-open');
                document.body.style.overflow = '';
            });
        }

        var filtersCol = document.getElementById('explore-filters-col');
        var filtersOpenBtn = document.getElementById('explore-filters-toggle-btn');
        var filtersCloseBtn = document.getElementById('explore-filters-close-btn');
        if (filtersCol && filtersOpenBtn && filtersCloseBtn) {
            filtersOpenBtn.addEventListener('click', function () {
                filtersCol.classList.add('mobile-open');
                document.body.style.overflow = 'hidden';
            });
            filtersCloseBtn.addEventListener('click', function () {
                filtersCol.classList.remove('mobile-open');
                document.body.style.overflow = '';
            });
        }
    })();
</script>
