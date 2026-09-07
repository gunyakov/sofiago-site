<!doctype html>
<html lang="<?= e(locale()) ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'SofiaGO') ?></title>
    <script>
        // Applied before any CSS loads, so a stored dark preference doesn't flash light-then-dark
        // on every navigation — script.js (loaded below) only sets this on the toggle click and
        // on its own DOMContentLoaded, which is too late to avoid the flash.
        try {
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            }
        } catch (e) {}
    </script>
    <?php if (!empty($metaDescription)): ?>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <link rel="shortcut icon" href="<?= e(asset('theme/images/favicon.png')) ?>">

    <link href="<?= e(asset('theme/plugins/aos/aos.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('theme/plugins/bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('theme/plugins/fontawesome/css/all.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('theme/plugins/OwlCarousel2/css/owl.carousel.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('theme/plugins/OwlCarousel2/css/owl.theme.default.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('theme/plugins/select2/select2.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('theme/plugins/select2-bootstrap-5/select2-bootstrap-5-theme.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('theme/css/style.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('theme/css/custom.css')) ?>" rel="stylesheet">

    <?= $pageStyles ?? '' ?>
</head>

<body>
    <?= partial('nav.tpl.php') ?>

    <?php if ($notice = flash('notice')): ?>
    <div class="container mt-3"><div class="alert alert-info mb-0"><?= e($notice) ?></div></div>
    <?php endif; ?>
    <?php if ($warning = flash('warning')): ?>
    <div class="container mt-3"><div class="alert alert-warning mb-0"><?= e($warning) ?></div></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
    <div class="container mt-3"><div class="alert alert-danger mb-0"><?= e($error) ?></div></div>
    <?php endif; ?>

    <?= $content ?>

    <?= partial('footer.tpl.php') ?>

    <script src="<?= e(asset('theme/plugins/jQuery/jquery.min.js')) ?>"></script>
    <script src="<?= e(asset('theme/plugins/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
    <script src="<?= e(asset('theme/plugins/aos/aos.min.js')) ?>"></script>
    <script src="<?= e(asset('theme/plugins/OwlCarousel2/owl.carousel.min.js')) ?>"></script>
    <script src="<?= e(asset('theme/plugins/select2/select2.min.js')) ?>"></script>
    <script>AOS.init();</script>
    <script>
        // Vendored theme/js/script.js calls $(...).theiaStickySidebar() unconditionally, for a
        // filter-sidebar layout page we don't have (that plugin's own JS was never bundled). An
        // uncaught throw there aborts the REST of script.js's top-level code in the same file —
        // silently killing the dark-mode toggle wiring below it. No-op shim instead of pulling
        // in a whole plugin we don't otherwise need.
        (function ($) {
            if ($ && !$.fn.theiaStickySidebar) {
                $.fn.theiaStickySidebar = function () { return this; };
            }
        })(window.jQuery);
    </script>
    <?php // Theme "glue" script — dark-mode toggle (#themeToggleBtn in nav.tpl.php), back-to-top
    // button, sticky-navbar scroll class, etc. Was never actually included before this — the
    // toggle button existed nowhere in our markup either, so there was nothing to wire up yet. ?>
    <script src="<?= e(asset('theme/js/script.js')) ?>"></script>
    <script src="<?= e(asset('js/favorite.js')) ?>" defer></script>
    <?= $pageScripts ?? '' ?>
</body>

</html>
