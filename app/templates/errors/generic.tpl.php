<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($status ?? '') ?> — <?= e($title ?? t('errors.default_title')) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600&display=swap" rel="stylesheet">
    <?= partial('error-style.tpl.php') ?>
</head>
<body>
    <div class="error-page">
        <a href="<?= e(url('/')) ?>" class="error-brand"><?= partial('brand.tpl.php') ?></a>
        <div class="error-card">
            <div class="error-code"><?= e($status ?? '') ?></div>
            <h1><?= e($title ?? t('errors.default_title')) ?></h1>
            <p><?= !empty($message) ? e($message) : e(t('errors.default_message')) ?></p>
            <a href="<?= e(url('/')) ?>" class="error-btn"><?= e(t('errors.home_button')) ?></a>
        </div>
    </div>
</body>
</html>
