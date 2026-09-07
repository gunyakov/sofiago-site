<?php
/**
 * @var array<int, array<string, mixed>> $venues Venue::adminEligible() — every listing with
 *   has_map = 1, regardless of moderation status (see AdminVenueMapController's doc comment).
 *   Each row also carries map_published for the "Завършена" toggle below — see that column's
 *   doc comment in schema.sql for what flipping it actually gates (the public venue picker +
 *   every occupying shop's "View on map" button).
 */
?>
<div class="container-xxl py-4">
    <h1 class="mb-2"><?= e(t('admin.venue_maps_title')) ?></h1>
    <p class="text-muted mb-4"><?= e(t('admin.venue_maps_hint')) ?></p>

    <?php if ($venues === []): ?>
        <div class="alert alert-light border text-center py-5"><?= e(t('admin.venue_maps_empty')) ?></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th><?= e(t('dashboard.my_listings.col_place')) ?></th>
                    <th><?= e(t('dashboard.my_listings.col_category')) ?></th>
                    <th><?= e(t('dashboard.my_listings.col_status')) ?></th>
                    <th><?= e(t('admin.venue_maps_col_progress')) ?></th>
                    <th><?= e(t('admin.venue_map_published_col')) ?></th>
                    <th class="text-end"><?= e(t('admin.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($venues as $venue): ?>
                <tr>
                    <td class="fw-semibold"><?= e($venue['title']) ?></td>
                    <td><?= e(\Sofiago\Models\Category::label($venue)) ?></td>
                    <td><span class="badge bg-secondary"><?= e($venue['status']) ?></span></td>
                    <td class="small text-muted">
                        <?php if ((int) $venue['floor_count'] === 0): ?>
                            <?= e(t('admin.venue_maps_not_started')) ?>
                        <?php else: ?>
                            <?= e(t('admin.venue_maps_progress', ['floors' => (int) $venue['floor_count'], 'units' => (int) $venue['unit_count']])) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- Publish gate (listings.map_published) — see AdminVenueMapController::
                             setPublished() and that column's doc comment in schema.sql. Auto-
                             submits on change, same one-flag-one-form pattern as a plain checkbox
                             toggle elsewhere in the admin, no separate "Save" button needed. -->
                        <form method="post" action="<?= e(url('/admin/venue-maps/' . $venue['id'] . '/publish')) ?>" class="form-check form-switch mb-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="redirect_to" value="/admin/venue-maps">
                            <input class="form-check-input" type="checkbox" role="switch" name="published" value="1" onchange="this.form.submit()" <?= !empty($venue['map_published']) ? 'checked' : '' ?>>
                            <label class="form-check-label small">
                                <?= !empty($venue['map_published']) ? e(t('admin.venue_map_published')) : e(t('admin.venue_map_unpublished')) ?>
                            </label>
                        </form>
                    </td>
                    <td class="text-end">
                        <a href="<?= e(url('/admin/venue-maps/' . $venue['id'])) ?>" class="btn btn-sm btn-primary"><?= e(t('admin.venue_maps_open')) ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
