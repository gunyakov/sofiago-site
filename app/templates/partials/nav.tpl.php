<?php
// 'ru' stays a fully supported locale (lang/ru.json, ?lang=ru, the ru cookie once set) — just
// not offered as a one-click switch here. Per the user (2026-09-08): direct language-switch UI
// is hidden site-wide/dashboard-wide given current bg sentiment around Russian, without pulling
// ru support itself. Don't add 'ru' back to this list without asking first.
$localeNames = ['en' => 'EN', 'bg' => 'BG'];
?>
<nav class="navbar navbar-expand-lg navbar-light sticky-top">
    <div class="container">
        <a class="navbar-brand m-0" href="<?= e(url('/')) ?>">
            <?= partial('brand.tpl.php') ?>
        </a>

        <div class="d-flex order-lg-2 align-items-center gap-2">
            <div class="dropdown">
                <button class="d-flex align-items-center justify-content-center p-0 btn-user fw-semibold fs-13" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= e(t('nav.language')) ?>">
                    <?= e($localeNames[locale()] ?? strtoupper(locale())) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach ($localeNames as $code => $label): ?>
                    <li><a class="dropdown-item <?= locale() === $code ? 'active' : '' ?>" href="<?= e(lang_switch_url($code)) ?>" rel="nofollow"><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <button type="button" id="themeToggleBtn" class="align-items-center bg-transparent border-0 btn-user d-flex justify-content-center p-0" title="<?= e(t('nav.toggle_theme')) ?>">
                <i class="fa-solid fa-moon"></i>
            </button>

            <?php if (!empty($currentUser)): ?>
                <a href="<?= e(url('/dashboard')) ?>" class="d-flex align-items-center justify-content-center p-0 btn-user" title="<?= e(t('nav.dashboard')) ?>">
                    <i class="fa-solid fa-user"></i>
                </a>
            <?php else: ?>
                <a href="<?= e(url('/sign-in')) ?>" class="d-flex align-items-center justify-content-center p-0 btn-user" title="<?= e(t('nav.sign_in')) ?>">
                    <i class="fa-solid fa-user-plus"></i>
                </a>
            <?php endif; ?>

            <a href="<?= e(url('/dashboard/listings/new')) ?>" class="btn btn-primary d-none d-sm-flex fw-medium gap-2 hstack rounded-5">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-plus-circle" viewBox="0 0 16 16">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z" />
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z" />
                </svg>
                <span><?= e(t('nav.add_listing')) ?></span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span id="nav-icon"><span></span><span></span><span></span></span>
            </button>
        </div>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav m-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('/explore')) ?>"><?= e(t('nav.explore')) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('/about')) ?>"><?= e(t('nav.about')) ?></a></li>
                <?php if (!empty($currentUser)): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('/dashboard')) ?>"><?= e(t('nav.my_listings')) ?></a></li>
                <?php endif; ?>
                <?php if (auth()->isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url('/admin/listings')) ?>">
                            <?= e(t('nav.moderation')) ?>
                            <?php $pendingCount = \Sofiago\Models\Listing::countsByStatus()['pending']; ?>
                            <?php if ($pendingCount > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
