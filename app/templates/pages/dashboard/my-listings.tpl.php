<?php
/**
 * @var array<string, array<int, array<string, mixed>>> $grouped 'expiring' is not a real
 *   listings.status value — it's the same rows already in 'active', duplicated into their own
 *   tab when they're inside the renewal window (see ListingManageController::myListings()).
 *   That's on purpose, mirroring the admin moderation page: "still active" and "about to lapse"
 *   are different concerns, so the renew button only ever shows in that one tab, never in the
 *   plain 'active' list (where it used to sit among every active listing regardless of expiry).
 */
$labels = [
    'active' => t('dashboard.my_listings.tab_active'),
    'expiring' => t('dashboard.my_listings.tab_expiring'),
    'pending' => t('dashboard.my_listings.tab_pending'),
    'rejected' => t('dashboard.my_listings.tab_rejected'),
    'expired' => t('dashboard.my_listings.tab_expired'),
];
// Per-row status badge — 'expiring' rows are real status='active', so this never needs that key.
$statusBadge = ['active' => 'success', 'pending' => 'warning', 'rejected' => 'danger', 'expired' => 'secondary'];
?>
<div class="container-xxl py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="mb-0"><?= e(t('dashboard.my_listings.title')) ?></h1>
        <a href="<?= e(url('/dashboard/listings/new')) ?>" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i><?= e(t('nav.add_listing')) ?>
        </a>
    </div>

    <ul class="nav nav-tabs mb-4" role="tablist">
        <?php foreach ($labels as $status => $label): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $status === 'active' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-<?= $status ?>" type="button">
                <?= e($label) ?> <span class="badge bg-light text-dark ms-1"><?= count($grouped[$status]) ?></span>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php foreach ($labels as $status => $label): ?>
        <div class="tab-pane fade <?= $status === 'active' ? 'show active' : '' ?>" id="tab-<?= $status ?>">
            <?php if ($grouped[$status] === []): ?>
                <div class="alert alert-light border text-center py-5"><?= e(t('dashboard.my_listings.empty')) ?></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th><?= e(t('dashboard.my_listings.col_place')) ?></th>
                            <th><?= e(t('dashboard.my_listings.col_category')) ?></th>
                            <th><?= e(t('dashboard.my_listings.col_expiry')) ?></th>
                            <th><?= e(t('dashboard.my_listings.col_status')) ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grouped[$status] as $listing): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <?php if (!empty($listing['cover_path'])): ?>
                                        <img src="<?= e($listing['cover_path']) ?>" class="rounded-3" style="width:56px;height:56px;object-fit:cover;" alt="">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center bg-light rounded-3" style="width:56px;height:56px;">
                                            <i class="fa-solid <?= e($listing['category_icon'] ?: 'fa-map-pin') ?> text-primary"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-semibold"><?= e($listing['title']) ?></div>
                                        <?php if ($listing['status'] === 'rejected' && !empty($listing['rejection_reason'])): ?>
                                            <div class="small text-danger"><?= e($listing['rejection_reason']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= e(\Sofiago\Models\Category::label($listing)) ?></td>
                            <td class="small <?= $status === 'expiring' ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                <?php if (!empty($listing['expires_at'])): ?>
                                    <?= e(date('d.m.Y', strtotime((string) $listing['expires_at']))) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <?php // Real status label, not the current tab's — 'expiring' rows are truly still 'active'. ?>
                            <td><span class="badge bg-<?= $statusBadge[$listing['status']] ?>"><?= e($labels[$listing['status']]) ?></span></td>
                            <td class="text-end">
                                <?php if ($listing['status'] === 'active'): ?>
                                <a href="<?= e(url('/listings/' . $listing['slug'])) ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><?= e(t('dashboard.my_listings.view')) ?></a>
                                <?php endif; ?>
                                <?php // Only shown in the 'expiring' tab (active, near expiry) or the 'expired' tab
                                // (self-resurrection) — never in the plain 'active' list, see the note up top. ?>
                                <?php if ($status === 'expiring' || ($status === 'expired' && \Sofiago\Models\Listing::isRenewable($listing))): ?>
                                <form method="post" action="<?= e(url('/dashboard/listings/' . $listing['id'] . '/renew')) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-success" title="<?= e(t('dashboard.my_listings.renew_tooltip')) ?>"><?= e(t('dashboard.my_listings.renew')) ?></button>
                                </form>
                                <?php endif; ?>
                                <a href="<?= e(url('/dashboard/listings/' . $listing['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-primary"><?= e(t('dashboard.my_listings.edit')) ?></a>
                                <form method="post" action="<?= e(url('/dashboard/listings/' . $listing['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('<?= e(t('dashboard.my_listings.delete_confirm')) ?>');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(t('dashboard.my_listings.delete')) ?></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
