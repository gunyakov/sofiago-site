<?php
/**
 * @var string $activeStatus
 * @var array<int, array<string, mixed>> $claims
 * @var array<string, int> $counts
 */
$labels = [
    'pending' => t('dashboard.my_listings.tab_pending'),
    'approved' => t('admin.tab_approved'),
    'rejected' => t('dashboard.my_listings.tab_rejected'),
];
$statusBadge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
?>
<div class="container-xxl py-4">
    <h1 class="mb-4"><?= e(t('admin.ownership_claims_title')) ?></h1>

    <ul class="nav nav-tabs mb-4">
        <?php foreach ($labels as $status => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $status === $activeStatus ? 'active' : '' ?>" href="<?= e(url('/admin/ownership-claims?status=' . $status)) ?>">
                <?= e($label) ?> <span class="badge bg-light text-dark ms-1"><?= (int) $counts[$status] ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($claims === []): ?>
        <div class="alert alert-light border text-center py-5"><?= e(t('dashboard.my_listings.empty')) ?></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th><?= e(t('dashboard.my_listings.col_place')) ?></th>
                    <th><?= e(t('admin.ownership_claims_col_claimant')) ?></th>
                    <th><?= e(t('admin.ownership_claims_col_current_owner')) ?></th>
                    <th><?= e(t('admin.ownership_claims_col_message')) ?></th>
                    <th><?= e(t('admin.col_added')) ?></th>
                    <th><?= e(t('dashboard.my_listings.col_status')) ?></th>
                    <th class="text-end"><?= e(t('admin.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($claims as $claim): ?>
                <tr>
                    <td>
                        <a href="<?= e(url('/listings/' . $claim['listing_slug'])) ?>" target="_blank" class="fw-semibold text-decoration-none">
                            <?= e($claim['listing_title']) ?>
                        </a>
                    </td>
                    <td>
                        <div><?= e($claim['claimant_name']) ?></div>
                        <div class="small text-muted"><?= e($claim['claimant_email']) ?></div>
                    </td>
                    <td>
                        <div><?= e($claim['owner_name']) ?></div>
                        <div class="small text-muted"><?= e($claim['owner_email']) ?></div>
                    </td>
                    <td style="max-width:320px;"><?= $claim['message'] !== null ? nl2br(e($claim['message'])) : '<span class="text-muted">—</span>' ?></td>
                    <td class="small text-muted"><?= e(date('d.m.Y H:i', strtotime((string) $claim['created_at']))) ?></td>
                    <td>
                        <span class="badge bg-<?= $statusBadge[$claim['status']] ?>"><?= e($labels[$claim['status']]) ?></span>
                        <?php if ($claim['status'] === 'rejected' && !empty($claim['rejection_reason'])): ?>
                            <div class="small text-danger"><?= e(t('admin.reason_label', ['reason' => $claim['rejection_reason']])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end" style="min-width:220px;">
                        <?php if ($claim['status'] === 'pending'): ?>
                        <div class="d-flex flex-column gap-2 align-items-end">
                            <form method="post" action="<?= e(url('/admin/ownership-claims/' . $claim['id'] . '/approve')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="back" value="<?= e($activeStatus) ?>">
                                <button type="submit" class="btn btn-sm btn-success"><?= e(t('admin.approve')) ?></button>
                            </form>
                            <form method="post" action="<?= e(url('/admin/ownership-claims/' . $claim['id'] . '/reject')) ?>" class="d-flex gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="back" value="<?= e($activeStatus) ?>">
                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="<?= e(t('admin.rejection_reason_placeholder')) ?>" maxlength="500">
                                <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap"><?= e(t('admin.reject')) ?></button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
