<?php
/**
 * Text-based wordmark — replaces the theme's original logo.png/logo-white.png, which are
 * literally the ListOn wordmark baked into a raster image, not something a find-and-replace on
 * markup could fix. Reused by nav.tpl.php (light background) and footer.tpl.php (dark
 * background, footer-dark) via $variant.
 *
 * @var string|null $variant 'dark' (default, for a light background) or 'light' (dark background).
 */
$variant = $variant ?? 'dark';
?>
<span class="brand-mark d-inline-flex align-items-center gap-2 fw-bold fs-3 <?= $variant === 'light' ? 'text-white' : 'text-dark' ?>">
    <?php // Inline style beats every context's own img-sizing rule (.navbar-brand img,
    // .footer-logo img, ...) — those set only `height`, leaving width to the HTML width="28"
    // attribute (a low-priority presentational hint), so the square pin icon rendered squashed
    // wherever the surrounding height didn't happen to also be 28px. Forcing both dimensions
    // inline keeps it square everywhere this partial is dropped. ?>
    <img src="<?= e(asset('dashboard/dist/img/mini-logo.png')) ?>" alt="" style="width:28px;height:28px;">
    Sofia<span class="font-caveat text-primary">GO</span>
</span>
