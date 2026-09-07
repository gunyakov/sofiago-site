<div class="container-xxl py-4">
    <h1 class="mb-4"><?= e(t('dashboard.favorites.title')) ?></h1>

    <?php if ($listings === []): ?>
        <div class="alert alert-light border text-center py-5">
            <?= t('dashboard.favorites.empty', ['link' => '<a href="' . e(url('/explore')) . '">' . e(t('dashboard.favorites.view_catalog')) . '</a>']) ?>
        </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($listings as $listing): ?>
        <div class="col-sm-6 col-lg-4">
            <?= partial('listing-card.tpl.php', ['listing' => $listing, 'favoriteIds' => [(int) $listing['id']]]) ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
