<?php
/**
 * @var string $activeStatus
 * @var array<int, array<string, mixed>> $reports
 * @var array<string, int> $counts
 */
$labels = [
    'pending' => t('dashboard.my_listings.tab_pending'),
    'resolved' => t('admin.tab_resolved'),
    'dismissed' => t('admin.tab_dismissed'),
];
$statusBadge = ['pending' => 'warning', 'resolved' => 'success', 'dismissed' => 'danger'];
?>
<div class="container-xxl py-4">
    <h1 class="mb-4"><?= e(t('admin.reports_title')) ?></h1>

    <ul class="nav nav-tabs mb-4">
        <?php foreach ($labels as $status => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $status === $activeStatus ? 'active' : '' ?>" href="<?= e(url('/admin/reports?status=' . $status)) ?>">
                <?= e($label) ?> <span class="badge bg-light text-dark ms-1"><?= (int) $counts[$status] ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($reports === []): ?>
        <div class="alert alert-light border text-center py-5"><?= e(t('dashboard.my_listings.empty')) ?></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th><?= e(t('dashboard.my_listings.col_place')) ?></th>
                    <th><?= e(t('admin.col_author')) ?></th>
                    <th><?= e(t('admin.reports_col_reason')) ?></th>
                    <th><?= e(t('admin.reports_col_message')) ?></th>
                    <th><?= e(t('admin.col_added')) ?></th>
                    <th><?= e(t('dashboard.my_listings.col_status')) ?></th>
                    <th class="text-end"><?= e(t('admin.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <tr>
                    <td>
                        <a href="<?= e(url('/listings/' . $report['listing_slug'])) ?>" target="_blank" class="fw-semibold text-decoration-none">
                            <?= e($report['listing_title']) ?>
                        </a>
                    </td>
                    <td>
                        <div><?= e($report['reporter_name'])?><?= $report['user_id'] === null ? ' <span class="badge bg-light text-dark">' . e(t('admin.guest_badge')) . '</span>' : '' ?></div>
                        <?php if (!empty($report['reporter_email'])): ?>
                            <div class="small text-muted"><?= e($report['reporter_email']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-light text-dark"><?= e(t('report.reason_' . $report['reason'])) ?></span></td>
                    <td style="max-width:320px;"><?= $report['message'] !== null ? nl2br(e($report['message'])) : '<span class="text-muted">—</span>' ?></td>
                    <td class="small text-muted"><?= e(date('d.m.Y H:i', strtotime((string) $report['created_at']))) ?></td>
                    <td><span class="badge bg-<?= $statusBadge[$report['status']] ?>"><?= e($labels[$report['status']]) ?></span></td>
                    <td class="text-end" style="min-width:180px;">
                        <div class="d-flex justify-content-end gap-2">
                            <?php if ($report['status'] !== 'resolved'): ?>
                            <form method="post" action="<?= e(url('/admin/reports/' . $report['id'] . '/resolve')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="back" value="<?= e($activeStatus) ?>">
                                <button type="submit" class="btn btn-sm btn-success"><?= e(t('admin.report_resolve')) ?></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($report['status'] !== 'dismissed'): ?>
                            <form method="post" action="<?= e(url('/admin/reports/' . $report['id'] . '/dismiss')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="back" value="<?= e($activeStatus) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(t('admin.report_dismiss')) ?></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
