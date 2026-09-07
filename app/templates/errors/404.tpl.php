<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — <?= e(t('errors.404.title')) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600&display=swap" rel="stylesheet">
    <?= partial('error-style.tpl.php') ?>
</head>
<body>
    <div class="error-page">
        <a href="<?= e(url('/')) ?>" class="error-brand"><?= partial('brand.tpl.php') ?></a>
        <div class="error-card">
            <div class="error-code">404</div>
            <h1><?= e(t('errors.404.title')) ?></h1>
            <p><?= e(t('errors.404.text')) ?><?= !empty($message) ? ' ' . e($message) : '' ?></p>
            <a href="<?= e(url('/')) ?>" class="error-btn"><?= e(t('errors.home_button')) ?></a>
        </div>
    </div>
</body>
</html>
