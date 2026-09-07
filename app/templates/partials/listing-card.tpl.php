<?php
/**
 * @var array<string, mixed> $listing
 * @var array<int, int> $favoriteIds Optional — listing ids the current viewer has favorited.
 */
$favoriteIds ??= [];
?>
<div class="card rounded-3 w-100 flex-fill overflow-hidden h-100"
     <?php if (!empty($listing['lat']) && !empty($listing['lng'])): ?>data-lat="<?= e($listing['lat']) ?>" data-lng="<?= e($listing['lng']) ?>"<?php endif; ?>>
    <a href="<?= e(url('/listings/' . $listing['slug'])) ?>" class="stretched-link"></a>
    <div class="card-img-wrap card-image-hover overflow-hidden">
        <?php if (!empty($currentUser)): ?>
        <div class="d-flex end-0 me-3 mt-3 position-absolute top-0 z-1">
            <?= partial('favorite-button.tpl.php', ['listingId' => (int) $listing['id'], 'isFavorited' => in_array((int) $listing['id'], $favoriteIds, true)]) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($listing['cover_path'])): ?>
            <img src="<?= e($listing['cover_path']) ?>" alt="<?= e($listing['title']) ?>" class="img-fluid">
        <?php else: ?>
            <div class="d-flex align-items-center justify-content-center bg-light" style="height:200px;">
                <i class="fa-solid <?= e($listing['category_icon'] ?: 'fa-map-pin') ?> fs-1 text-primary opacity-50"></i>
            </div>
        <?php endif; ?>
    </div>
    <div class="d-flex flex-column h-100 position-relative p-4">
        <div class="align-items-center bg-primary cat-icon d-flex justify-content-center position-absolute rounded-circle text-white">
            <i class="fa-solid <?= e($listing['category_icon'] ?: 'fa-map-pin') ?>"></i>
        </div>
        <div class="small text-muted mb-2"><?= e(\Sofiago\Models\Category::label($listing)) ?><?= !empty($listing['district']) ? ' · ' . e($listing['district']) : '' ?></div>
        <h4 class="fs-5 fw-semibold mb-0"><?= e($listing['title']) ?></h4>
    </div>
</div>
