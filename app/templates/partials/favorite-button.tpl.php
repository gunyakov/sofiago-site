<?php /** @var int $listingId @var bool $isFavorited */ ?>
<button type="button"
        class="favorite-btn btn-icon shadow-sm d-flex align-items-center justify-content-center text-primary bg-light rounded-circle"
        data-listing-id="<?= (int) $listingId ?>"
        data-favorited="<?= $isFavorited ? '1' : '0' ?>"
        data-toggle-url="<?= e(url('/api/favorites/' . $listingId . '/toggle')) ?>"
        data-login-url="<?= e(url('/sign-in')) ?>"
        data-csrf="<?= e(csrf_token()) ?>"
        title="<?= e(t('listing.favorite_tooltip')) ?>">
    <i class="fa-heart <?= $isFavorited ? 'fa-solid' : 'fa-regular' ?>"></i>
</button>
