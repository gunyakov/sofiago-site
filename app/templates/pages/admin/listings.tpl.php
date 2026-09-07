<?php
/**
 * @var string $activeStatus
 * @var array<int, array<string, mixed>> $listings
 * @var array<string, int> $counts
 * @var array<int, array<string, mixed>> $categories Category::topLevel() — for the filter dropdown.
 * @var array{q: string, category: string} $filters Current search box / category filter values.
 * @var int $total Rows matching $activeStatus + $filters (not the tab badge — see $counts).
 * @var int $page
 * @var int $pages
 */
// Real listings.status values only — used for the per-row status badge. 'expiring' below is a
// separate filtered view of status='active' (see Listing::forModerationExpiringSoon()), it's
// never a row's actual status, so it deliberately isn't in this map.
$labels = [
    'pending' => t('dashboard.my_listings.tab_pending'),
    'active' => t('dashboard.my_listings.tab_active'),
    'rejected' => t('dashboard.my_listings.tab_rejected'),
    'expired' => t('dashboard.my_listings.tab_expired'),
];
$statusBadge = ['pending' => 'warning', 'active' => 'success', 'rejected' => 'danger', 'expired' => 'secondary'];

// Tabs shown across the top — includes the synthetic 'expiring' tab in its own place.
$tabs = [
    'pending' => t('dashboard.my_listings.tab_pending'),
    'active' => t('dashboard.my_listings.tab_active'),
    'expiring' => t('dashboard.my_listings.tab_expiring'),
    'rejected' => t('dashboard.my_listings.tab_rejected'),
    'expired' => t('dashboard.my_listings.tab_expired'),
];

// The current search box/category carry across tab switches and pagination links — an admin
// searching "Zara" shouldn't have that reset just from checking a different status tab.
$filterParams = array_filter(['q' => $filters['q'], 'category' => $filters['category']], static fn ($v) => $v !== '');

// Every approve/reject/renew form below posts this back as 'back', and the controller redirects
// straight to it — so publishing a listing found via a search drops the admin back into that
// same filtered/paginated view instead of resetting to the full unfiltered tab.
$backQuery = http_build_query(array_merge($filterParams, ['status' => $activeStatus, 'page' => $page]));
?>
<div class="container-xxl py-4">
    <h1 class="mb-4"><?= e(t('admin.listings_title')) ?></h1>

    <ul class="nav nav-tabs mb-4">
        <?php foreach ($tabs as $status => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $status === $activeStatus ? 'active' : '' ?>" href="<?= e(url('/admin/listings?' . http_build_query(array_merge($filterParams, ['status' => $status])))) ?>">
                <?= e($label) ?> <span class="badge bg-light text-dark ms-1"><?= (int) $counts[$status] ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- Search box + category filter — see Listing::forModeration()'s doc comment for why this
         was added (1200+ rows on the 'active' tab with no way to narrow them down). GET, not
         POST/JS: plain, bookmarkable/shareable, and the result list itself needs no JS either. -->
    <form method="get" action="<?= e(url('/admin/listings')) ?>" class="row g-2 align-items-center mb-3">
        <input type="hidden" name="status" value="<?= e($activeStatus) ?>">
        <div class="col-sm-6 col-lg-4">
            <input type="text" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="<?= e(t('admin.listings_search_placeholder')) ?>">
        </div>
        <div class="col-sm-4 col-lg-3">
            <select name="category" class="form-select">
                <option value=""><?= e(t('explore.all_categories')) ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category['slug']) ?>" <?= $filters['category'] === $category['slug'] ? 'selected' : '' ?>><?= e(\Sofiago\Models\Category::label($category)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary"><?= e(t('explore.search_button')) ?></button>
            <?php if ($filters['q'] !== '' || $filters['category'] !== ''): ?>
                <a href="<?= e(url('/admin/listings?status=' . $activeStatus)) ?>" class="btn btn-outline-secondary"><?= e(t('admin.listings_clear_filters')) ?></a>
            <?php endif; ?>
        </div>
        <div class="col-auto ms-auto text-muted small">
            <?= e(t('admin.listings_results_count', ['count' => $total])) ?>
        </div>
    </form>

    <?php if ($listings === []): ?>
        <div class="alert alert-light border text-center py-5"><?= e(t('dashboard.my_listings.empty')) ?></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th><?= e(t('dashboard.my_listings.col_place')) ?></th>
                    <th><?= e(t('dashboard.my_listings.col_category')) ?></th>
                    <th><?= e(t('admin.col_owner')) ?></th>
                    <th><?= e(t('admin.col_added')) ?></th>
                    <th><?= e(t('dashboard.my_listings.col_expiry')) ?></th>
                    <th><?= e(t('dashboard.my_listings.col_status')) ?></th>
                    <th class="text-end"><?= e(t('admin.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listings as $listing): ?>
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
                                <a href="<?= e(url('/admin/listings/' . $listing['id'] . '/preview')) ?>" target="_blank" class="fw-semibold text-decoration-none">
                                    <?= e($listing['title']) ?>
                                </a>
                                <div class="small text-muted"><?= e($listing['address']) ?></div>
                                <?php if ($listing['status'] === 'rejected' && !empty($listing['rejection_reason'])): ?>
                                    <div class="small text-danger"><?= e(t('admin.reason_label', ['reason' => $listing['rejection_reason']])) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><?= e(\Sofiago\Models\Category::label($listing)) ?></td>
                    <td>
                        <div><?= e($listing['owner_name']) ?></div>
                        <div class="small text-muted"><?= e($listing['owner_email']) ?></div>
                    </td>
                    <td class="small text-muted"><?= e(date('d.m.Y', strtotime((string) $listing['created_at']))) ?></td>
                    <td class="small <?= $activeStatus === 'expiring' ? 'text-danger fw-semibold' : 'text-muted' ?>">
                        <?php if (!empty($listing['expires_at'])): ?>
                            <?= e(date('d.m.Y', strtotime((string) $listing['expires_at']))) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?= $statusBadge[$listing['status']] ?>"><?= e($labels[$listing['status']]) ?></span></td>
                    <td class="text-end" style="min-width:260px;">
                        <div class="d-flex flex-column align-items-end gap-2">
                            <div class="d-flex gap-2">
                                <a href="<?= e(url('/dashboard/listings/' . $listing['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-secondary" title="<?= e(t('admin.edit_tooltip')) ?>"><?= e(t('admin.edit')) ?></a>
                                <?php if ($activeStatus === 'expiring'): ?>
                                <?php // Only tab this button appears in — publishing and renewing are different actions. ?>
                                <form method="post" action="<?= e(url('/admin/listings/' . $listing['id'] . '/renew')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="back" value="<?= e($backQuery) ?>">
                                    <button type="submit" class="btn btn-sm btn-success"><?= e(t('admin.renew')) ?></button>
                                </form>
                                <?php elseif ($listing['status'] !== 'active'): ?>
                                <form method="post" action="<?= e(url('/admin/listings/' . $listing['id'] . '/approve')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="back" value="<?= e($backQuery) ?>">
                                    <button type="submit" class="btn btn-sm btn-success"><?= e(t('admin.publish')) ?></button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php if ($listing['status'] !== 'rejected'): ?>
                            <form method="post" action="<?= e(url('/admin/listings/' . $listing['id'] . '/reject')) ?>" class="d-flex gap-2 w-100">
                                <?= csrf_field() ?>
                                <input type="hidden" name="back" value="<?= e($backQuery) ?>">
                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="<?= e(t('admin.rejection_reason_placeholder')) ?>" maxlength="500">
                                <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap"><?= e(t('admin.reject')) ?></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e(url('/admin/listings?' . http_build_query(array_merge($filterParams, ['status' => $activeStatus, 'page' => $p])))) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
