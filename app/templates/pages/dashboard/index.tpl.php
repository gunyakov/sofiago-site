<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="mb-4"><?= e(t('dashboard.index.title')) ?></h1>

            <?php if (empty($currentUser['email_verified_at'])): ?>
            <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-3">
                <span><?= t('dashboard.index.email_unverified', ['email' => e($currentUser['email'])]) ?></span>
                <form method="post" action="<?= e(url('/verify-email/resend')) ?>" class="m-0">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-dark"><?= e(t('dashboard.index.resend')) ?></button>
                </form>
            </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3"><?= e(t('dashboard.index.profile')) ?></h5>
                    <p class="mb-1"><strong><?= e(t('dashboard.index.name_label')) ?></strong> <?= e($currentUser['name']) ?></p>
                    <p class="mb-1"><strong><?= e(t('dashboard.index.email_label')) ?></strong> <?= e($currentUser['email']) ?></p>
                    <p class="mb-0"><strong><?= e(t('dashboard.index.email_status_label')) ?></strong> <?= empty($currentUser['email_verified_at']) ? e(t('dashboard.index.not_verified')) : e(t('dashboard.index.verified')) ?></p>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <a href="<?= e(url('/dashboard/listings/new')) ?>" class="btn btn-primary w-100 py-3"><i class="fa-solid fa-plus me-1"></i><?= e(t('nav.add_listing')) ?></a>
                </div>
                <div class="col-sm-4">
                    <a href="<?= e(url('/dashboard/listings')) ?>" class="btn btn-outline-primary w-100 py-3"><i class="fa-solid fa-list me-1"></i><?= e(t('nav.my_listings')) ?></a>
                </div>
                <div class="col-sm-4">
                    <a href="<?= e(url('/dashboard/favorites')) ?>" class="btn btn-outline-primary w-100 py-3"><i class="fa-solid fa-heart me-1"></i><?= e(t('dashboard.nav.favorites')) ?></a>
                </div>
                <?php if (auth()->isAdmin()): ?>
                <div class="col-sm-4">
                    <a href="<?= e(url('/admin/listings')) ?>" class="btn btn-outline-dark w-100 py-3"><i class="fa-solid fa-shield-halved me-1"></i><?= e(t('nav.moderation')) ?></a>
                </div>
                <?php endif; ?>
            </div>

            <form method="post" action="<?= e(url('/logout')) ?>" class="m-0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger"><?= e(t('dashboard.nav.logout')) ?></button>
            </form>
        </div>
    </div>
</div>
