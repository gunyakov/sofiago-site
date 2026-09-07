<?php
/**
 * Horizontal card for /explore's "list" view — same content as listing-card.tpl.php (the grid
 * card), laid out like ListOn's listings-map.html cards: image left, info right.
 *
 * @var array<string, mixed> $listing
 * @var array<int, int> $favoriteIds Optional — listing ids the current viewer has favorited.
 */
$favoriteIds ??= [];
?>
<div class="card border-0 shadow-sm overflow-hidden rounded-4 mb-3 card-hover card-hover-bg"
     <?php if (!empty($listing['lat']) && !empty($listing['lng'])): ?>data-lat="<?= e($listing['lat']) ?>" data-lng="<?= e($listing['lng']) ?>"<?php endif; ?>>
    <a href="<?= e(url('/listings/' . $listing['slug'])) ?>" class="stretched-link"></a>
    <div class="card-body p-0">
        <div class="g-0 row">
            <div class="col-md-5 col-lg-4 position-relative">
                <div class="card-image-hover dark-overlay h-100 overflow-hidden position-relative" style="min-height:160px;">
                    <?php if (!empty($listing['cover_path'])): ?>
                        <img src="<?= e($listing['cover_path']) ?>" alt="<?= e($listing['title']) ?>" class="h-100 w-100 object-fit-cover">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center bg-light h-100">
                            <i class="fa-solid <?= e($listing['category_icon'] ?: 'fa-map-pin') ?> fs-1 text-primary opacity-50"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-7 col-lg-8 p-3 p-lg-4">
                <div class="d-flex flex-column h-100">
                    <?php if (!empty($currentUser)): ?>
                    <div class="d-flex end-0 gap-2 me-3 mt-3 position-absolute top-0 z-1">
                        <?= partial('favorite-button.tpl.php', ['listingId' => (int) $listing['id'], 'isFavorited' => in_array((int) $listing['id'], $favoriteIds, true)]) ?>
                    </div>
                    <?php endif; ?>
                    <div class="small text-primary fw-medium mb-1">
                        <i class="fa-solid <?= e($listing['category_icon'] ?: 'fa-map-pin') ?> me-1"></i><?= e(\Sofiago\Models\Category::label($listing)) ?>
                    </div>
                    <h4 class="fs-18 fw-semibold mb-2"><?= e($listing['title']) ?></h4>
                    <?php $cardDescription = \Sofiago\Models\Listing::descriptionFor($listing); ?>
                    <?php if ($cardDescription !== ''): ?>
                        <p class="mb-2 text-muted"><?= e(mb_substr($cardDescription, 0, 140)) ?><?= mb_strlen($cardDescription) > 140 ? '…' : '' ?></p>
                    <?php endif; ?>
                    <?php if (!empty($listing['address']) || !empty($listing['district'])): ?>
                    <div class="d-flex flex-wrap gap-3 mt-auto z-1 small text-muted">
                        <span class="d-flex gap-2 align-items-center">
                            <i class="fa-solid fa-location-dot"></i>
                            <span><?= e($listing['address'] ?: $listing['district']) ?></span>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
