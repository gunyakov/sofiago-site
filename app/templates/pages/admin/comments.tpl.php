<?php
/**
 * @var string $activeStatus
 * @var array<int, array<string, mixed>> $comments
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
    <h1 class="mb-4"><?= e(t('admin.comments_title')) ?></h1>

    <ul class="nav nav-tabs mb-4">
        <?php foreach ($labels as $status => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $status === $activeStatus ? 'active' : '' ?>" href="<?= e(url('/admin/comments?status=' . $status)) ?>">
                <?= e($label) ?> <span class="badge bg-light text-dark ms-1"><?= (int) $counts[$status] ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($comments === []): ?>
        <div class="alert alert-light border text-center py-5"><?= e(t('dashboard.my_listings.empty')) ?></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th><?= e(t('dashboard.my_listings.col_place')) ?></th>
                    <th><?= e(t('admin.col_author')) ?></th>
                    <th><?= e(t('admin.col_rating')) ?></th>
                    <th><?= e(t('admin.col_comment')) ?></th>
                    <th><?= e(t('admin.col_added')) ?></th>
                    <th><?= e(t('dashboard.my_listings.col_status')) ?></th>
                    <th class="text-end"><?= e(t('admin.col_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comments as $comment): ?>
                <tr>
                    <td>
                        <a href="<?= e(url('/listings/' . $comment['listing_slug'])) ?>" target="_blank" class="fw-semibold text-decoration-none">
                            <?= e($comment['listing_title']) ?>
                        </a>
                    </td>
                    <td>
                        <div><?= e($comment['display_name']) ?><?= $comment['user_id'] === null ? ' <span class="badge bg-light text-dark">' . e(t('admin.guest_badge')) . '</span>' : '' ?></div>
                        <?php $email = $comment['user_email'] ?? $comment['guest_email']; ?>
                        <?php if (!empty($email)): ?>
                            <div class="small text-muted"><?= e($email) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <span class="text-warning"><?= str_repeat('★', (int) $comment['rating']) ?><span class="text-muted"><?= str_repeat('☆', 5 - (int) $comment['rating']) ?></span></span>
                        <span class="small text-muted">(<?= (int) $comment['rating'] ?>/5)</span>
                    </td>
                    <td style="max-width:380px;"><?= nl2br(e($comment['body'])) ?></td>
                    <td class="small text-muted"><?= e(date('d.m.Y H:i', strtotime((string) $comment['created_at']))) ?></td>
                    <td><span class="badge bg-<?= $statusBadge[$comment['status']] ?>"><?= e($labels[$comment['status']]) ?></span></td>
                    <td class="text-end" style="min-width:180px;">
                        <div class="d-flex justify-content-end gap-2">
                            <?php if ($comment['status'] !== 'approved'): ?>
                            <form method="post" action="<?= e(url('/admin/comments/' . $comment['id'] . '/approve')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="back" value="<?= e($activeStatus) ?>">
                                <button type="submit" class="btn btn-sm btn-success"><?= e(t('admin.approve')) ?></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($comment['status'] !== 'rejected'): ?>
                            <form method="post" action="<?= e(url('/admin/comments/' . $comment['id'] . '/reject')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="back" value="<?= e($activeStatus) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(t('admin.reject')) ?></button>
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
