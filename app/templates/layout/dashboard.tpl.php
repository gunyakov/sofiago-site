<!doctype html>
<html lang="<?= e(locale()) ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'SofiaGO') ?> <?= e(t('dashboard.title_suffix')) ?></title>
    <link rel="shortcut icon" href="<?= e(asset('dashboard/dist/img/favicon.png')) ?>">
    <script>
        // Same anti-FOUC idea as the public layout, but app.min.js toggles a plain `dark` class
        // on <html> (key "dark-mode" in localStorage) rather than a data-bs-theme attribute —
        // that's the dashboard theme's own convention, not ours to change.
        try {
            if (localStorage.getItem('dark-mode') === 'dark') {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {}
    </script>

    <link href="<?= e(asset('dashboard/plugins/bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('dashboard/plugins/metisMenu/metisMenu.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('dashboard/plugins/fontawesome/css/all.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('dashboard/dist/css/app.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('dashboard/dist/css/style.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('dashboard/dist/css/custom.css')) ?>" rel="stylesheet">

    <?= $pageStyles ?? '' ?>
</head>

<body class="fixed sidebar-mini">
    <div class="wrapper">
        <?php
        // Highlights the matching sidebar link — a simple prefix match against the real
        // request path is enough, this sidebar only has a handful of real destinations
        // (unlike the ListOn demo's, which links to ~40 template showcase pages).
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $isActive = static fn (string $prefix): string => str_starts_with($currentPath, $prefix) ? 'mm-active' : '';
        $localeNames = ['en' => 'EN', 'bg' => 'BG', 'ru' => 'RU'];
        ?>
        <nav class="sidebar">
            <div class="sidebar-header">
                <a href="<?= e(url('/dashboard')) ?>" class="sidebar-brand">
                    <img class="sidebar-brand_icon" src="<?= e(asset('dashboard/dist/img/mini-logo.png')) ?>" alt="">
                    <span class="sidebar-brand_text">Sofia<span>GO</span></span>
                </a>
            </div>
            <div class="sidebar-body">
                <nav class="sidebar-nav">
                    <ul class="metismenu">
                        <li class="<?= $currentPath === '/dashboard' ? 'mm-active' : '' ?>">
                            <a href="<?= e(url('/dashboard')) ?>">
                                <i class="fa-solid fa-gauge"></i>
                                <span class="ms-2"><?= e(t('dashboard.nav.home')) ?></span>
                            </a>
                        </li>
                        <li class="<?= $currentPath === '/dashboard/listings/new' ? 'mm-active' : '' ?>">
                            <a href="<?= e(url('/dashboard/listings/new')) ?>">
                                <i class="fa-solid fa-square-plus"></i>
                                <span class="ms-2"><?= e(t('nav.add_listing')) ?></span>
                            </a>
                        </li>
                        <?php
                        // "/dashboard/listings/new" and "/dashboard/listings/{id}/edit" are the
                        // sidebar's own separate "Добавить место" entry / opened from this list —
                        // a plain prefix match would wrongly light up both rows at once.
                        $isMyListings = $isActive('/dashboard/listings') && $currentPath !== '/dashboard/listings/new' && !str_ends_with($currentPath, '/edit');
                        ?>
                        <li class="<?= $isMyListings ? 'mm-active' : '' ?>">
                            <a href="<?= e(url('/dashboard/listings')) ?>">
                                <i class="fa-solid fa-list"></i>
                                <span class="ms-2"><?= e(t('nav.my_listings')) ?></span>
                            </a>
                        </li>
                        <li class="<?= $isActive('/dashboard/favorites') ?>">
                            <a href="<?= e(url('/dashboard/favorites')) ?>">
                                <i class="fa-solid fa-heart"></i>
                                <span class="ms-2"><?= e(t('dashboard.nav.favorites')) ?></span>
                            </a>
                        </li>
                        <?php if (auth()->isAdmin()): ?>
                        <li class="nav-label"><span class="nav-label_text"><?= e(t('dashboard.nav.admin_label')) ?></span></li>
                        <li class="<?= $isActive('/admin/listings') ?>">
                            <a href="<?= e(url('/admin/listings')) ?>">
                                <i class="fa-solid fa-shield-halved"></i>
                                <span class="ms-2"><?= e(t('nav.moderation')) ?></span>
                                <?php $pendingCount = \Sofiago\Models\Listing::countsByStatus()['pending']; ?>
                                <?php if ($pendingCount > 0): ?>
                                    <span class="badge bg-danger rounded-pill ms-auto"><?= $pendingCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="<?= $isActive('/admin/comments') ?>">
                            <a href="<?= e(url('/admin/comments')) ?>">
                                <i class="fa-solid fa-comments"></i>
                                <span class="ms-2"><?= e(t('nav.comments_moderation')) ?></span>
                                <?php $pendingCommentCount = \Sofiago\Models\Comment::countsByStatus()['pending']; ?>
                                <?php if ($pendingCommentCount > 0): ?>
                                    <span class="badge bg-danger rounded-pill ms-auto"><?= $pendingCommentCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="<?= $isActive('/admin/reports') ?>">
                            <a href="<?= e(url('/admin/reports')) ?>">
                                <i class="fa-solid fa-flag"></i>
                                <span class="ms-2"><?= e(t('nav.reports_moderation')) ?></span>
                                <?php $pendingReportCount = \Sofiago\Models\ListingReport::countsByStatus()['pending']; ?>
                                <?php if ($pendingReportCount > 0): ?>
                                    <span class="badge bg-danger rounded-pill ms-auto"><?= $pendingReportCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="<?= $isActive('/admin/ownership-claims') ?>">
                            <a href="<?= e(url('/admin/ownership-claims')) ?>">
                                <i class="fa-solid fa-hand-holding"></i>
                                <span class="ms-2"><?= e(t('nav.ownership_claims_moderation')) ?></span>
                                <?php $pendingClaimCount = \Sofiago\Models\OwnershipClaim::countsByStatus()['pending']; ?>
                                <?php if ($pendingClaimCount > 0): ?>
                                    <span class="badge bg-danger rounded-pill ms-auto"><?= $pendingClaimCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="<?= $isActive('/admin/venue-maps') ?>">
                            <a href="<?= e(url('/admin/venue-maps')) ?>">
                                <i class="fa-solid fa-building"></i>
                                <span class="ms-2"><?= e(t('nav.venue_maps')) ?></span>
                            </a>
                        </li>
                        <li class="<?= $isActive('/admin/school-scores') ?>">
                            <a href="<?= e(url('/admin/school-scores')) ?>">
                                <i class="fa-solid fa-graduation-cap"></i>
                                <span class="ms-2"><?= e(t('nav.school_scores')) ?></span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </nav>

        <div class="content-wrapper">
            <div class="main-content">
                <nav class="navbar-custom-menu navbar navbar-expand-xl m-0 navbar-transfarent">
                    <div class="sidebar-toggle">
                        <div class="sidebar-toggle-icon" id="sidebarCollapse">
                            sidebar toggle<span></span>
                        </div>
                    </div>

                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav">
                            <li class="nav-item">
                                <a class="nav-link" href="<?= e(url('/')) ?>">
                                    <i class="fa-solid fa-arrow-left me-1"></i><?= e(t('dashboard.nav.to_site')) ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= e(url('/explore')) ?>"><?= e(t('nav.explore')) ?></a>
                            </li>
                        </ul>
                    </div>

                    <div class="navbar-icon d-flex ms-auto">
                        <ul class="navbar-nav flex-row align-items-center">
                            <li class="nav-item dropdown">
                                <button class="nav-link dropdown-toggle border-0 bg-transparent fw-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= e(t('nav.language')) ?>">
                                    <?= e($localeNames[locale()] ?? strtoupper(locale())) ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php foreach ($localeNames as $code => $label): ?>
                                    <li><a class="dropdown-item <?= locale() === $code ? 'active' : '' ?>" href="<?= e(lang_switch_url($code)) ?>"><?= e($label) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link dark-button" title="<?= e(t('nav.toggle_theme')) ?>">
                                    <i class="fa-solid fa-moon"></i>
                                </button>
                                <button class="nav-link light-button" title="<?= e(t('nav.toggle_theme')) ?>">
                                    <i class="fa-solid fa-sun"></i>
                                </button>
                            </li>
                            <li class="nav-item dropdown user-menu user-menu-custom">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="profile-element d-flex align-items-center flex-shrink-0 p-0 text-start">
                                        <div class="avatar online">
                                            <i class="fa-solid fa-circle-user fs-2"></i>
                                        </div>
                                        <div class="profile-text">
                                            <h6 class="m-0 fw-medium fs-14"><?= e($currentUser['name'] ?? '') ?></h6>
                                            <span class="fs-12"><?= e($currentUser['email'] ?? '') ?></span>
                                        </div>
                                    </div>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a href="<?= e(url('/dashboard')) ?>" class="dropdown-item">
                                        <i class="fa-solid fa-user me-2"></i><?= e(t('dashboard.nav.home')) ?>
                                    </a>
                                    <form method="post" action="<?= e(url('/logout')) ?>" class="m-0">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="dropdown-item">
                                            <i class="fa-solid fa-right-from-bracket me-2"></i><?= e(t('dashboard.nav.logout')) ?>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                        <i class="fa-solid fa-bars fs-18"></i>
                    </button>
                </nav>

                <?php if ($notice = flash('notice')): ?>
                <div class="container-xxl mt-4"><div class="alert alert-info mb-0"><?= e($notice) ?></div></div>
                <?php endif; ?>
                <?php if ($warning = flash('warning')): ?>
                <div class="container-xxl mt-4"><div class="alert alert-warning mb-0"><?= e($warning) ?></div></div>
                <?php endif; ?>
                <?php if ($error = flash('error')): ?>
                <div class="container-xxl mt-4"><div class="alert alert-danger mb-0"><?= e($error) ?></div></div>
                <?php endif; ?>

                <?= $content ?>
            </div>
        </div>
    </div>

    <script src="<?= e(asset('dashboard/plugins/jQuery/jquery.min.js')) ?>"></script>
    <script src="<?= e(asset('dashboard/plugins/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
    <script src="<?= e(asset('dashboard/plugins/metisMenu/metisMenu.min.js')) ?>"></script>
    <script src="<?= e(asset('dashboard/plugins/perfect-scrollbar/perfect-scrollbar.min.js')) ?>"></script>
    <script src="<?= e(asset('dashboard/dist/js/app.min.js')) ?>"></script>
    <?= $pageScripts ?? '' ?>
</body>

</html>
