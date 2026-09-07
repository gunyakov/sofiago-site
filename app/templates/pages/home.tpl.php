<div class="align-items-end d-flex hero-header-map position-relative">
    <div class="h-100 position-absolute start-0 top-0 w-100">
        <?php
        // Capped, VIP-first random sample (Listing::randomFeatured(), via the 'featured' flag
        // below) — not the full catalog /explore's own map shows. Needed once the
        // sofiago-flutter POI migration made the catalog four-digit-sized: every point at once
        // here was reported unusable. $selectedCategory (PageController::home()) is unused by
        // any link on this page yet, just wired through for whenever one exists.
        $homeMapFilters = ['featured' => true];
        if ($selectedCategory !== '') {
            $homeMapFilters['category'] = $selectedCategory;
        }
        ?>
        <div id="map-explore" class="h-100 w-100" data-lat="42.6977" data-lng="23.3219" data-zoom="12" data-api="<?= e(url('/api/listings')) ?>" data-filters='<?= e(json_encode($homeMapFilters)) ?>'></div>
    </div>
    <div class="container position-relative z-1">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <form class="border-0 card d-flex flex-md-row position-relative search-wrapper mb-5 shadow" action="<?= e(url('/explore')) ?>" method="get">
                    <div class="align-items-center d-flex search-field w-100">
                        <div class="svg-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z" />
                            </svg>
                        </div>
                        <input type="text" name="q" class="form-control search-input" placeholder="<?= e(t('explore.search_placeholder')) ?>">
                    </div>
                    <div class="vertical-divider"></div>
                    <div class="align-items-center d-flex search-field w-100">
                        <div class="svg-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-geo-alt" viewBox="0 0 16 16">
                                <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A31.493 31.493 0 0 1 8 14.58a31.481 31.481 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94zM8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10z" />
                                <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" />
                            </svg>
                        </div>
                        <select name="category" class="form-select search-select-field">
                            <option value="" selected><?= e(t('home.category_placeholder')) ?></option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e($category['slug']) ?>"><?= e(\Sofiago\Models\Category::label($category)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary rounded-5 mt-3 mt-md-0"><?= e(t('explore.search_button')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="py-5 bg-light rounded-4 mx-3 mt-3">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-10 col-lg-8 col-xl-7">
                <div class="section-header text-center mb-5" data-aos="fade-down">
                    <div class="d-inline-block font-caveat fs-1 fw-medium section-header__subtitle text-capitalize text-primary"><?= e(t('home.categories_kicker')) ?></div>
                    <h2 class="display-5 fw-semibold mb-3 section-header__title text-capitalize"><?= e(t('home.categories_title')) ?></h2>
                    <div class="sub-title fs-16"><?= e(t('home.categories_subtitle')) ?></div>
                </div>
            </div>
        </div>
        <div class="row g-3 g-lg-4">
            <?php foreach ($categories as $category): ?>
            <div class="col-sm-6 col-md-4 col-lg-3 col-xl-2 d-flex">
                <div class="border-0 card card-hover company-card flex-fill rounded-3 w-100">
                    <a href="<?= e(url('/explore?category=' . $category['slug'])) ?>" class="stretched-link"></a>
                    <div class="card-body d-flex flex-column">
                        <div class="text-end mb-4 text-primary">
                            <i class="fa-solid <?= e($category['icon'] ?: 'fa-map-pin') ?> fs-2"></i>
                        </div>
                        <div class="mt-auto">
                            <h5 class="mb-2"><?= e(\Sofiago\Models\Category::label($category)) ?></h5>
                            <div class="small mt-2 d-flex align-items-center gap-2 fw-medium text-primary">
                                <span><?= e(t('common.view')) ?></span>
                                <i class="fa-solid fa-arrow-up-right-from-square fs-12"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
