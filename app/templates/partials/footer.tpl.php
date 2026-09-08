<footer class="footer-dark main-footer overflow-hidden position-relative pt-5">
    <div class="container pt-4">
        <div class="py-5">
            <!-- "Download Our App" block from the theme's home-map.html — real Google Play link,
                 iOS still has none (per the user), the button stays as a visible placeholder.
                 app-screenshot.png replaced the theme's stock "ListOn" phone mockup with a real
                 sofiago-flutter screenshot (map + live vehicles) in a plain generated bezel. -->
            <div class="bg-primary rounded-4">
                <div class="col-xxl-10 col-md-11 col-10 d-flex flex-md-row flex-column-reverse align-items-md-end align-items-center mx-auto px-0 gap-4">
                    <img class="app-image flex-shrink-0" src="<?= e(asset('theme/images/app-screenshot.png')) ?>" width="270" alt="">
                    <div class="align-items-lg-center align-self-center d-flex flex-column flex-lg-row ps-xxl-4 pt-5 py-md-3 text-center text-md-start">
                        <div class="me-md-5">
                            <h4 class="text-white"><?= e(t('footer.app_title')) ?></h4>
                            <p class="mb-lg-0 text-white"><?= e(t('footer.app_subtitle')) ?></p>
                        </div>
                        <div class="d-flex flex-shrink-0 flex-wrap gap-3 justify-content-center">
                            <a class="align-items-center app-btn d-flex px-3 py-2 rounded-3 text-decoration-none text-white border" href="#" title="<?= e(t('footer.app_coming_soon')) ?>">
                                <i class="fa-apple fab fs-28 me-2"></i>
                                <div><span class="fs-13 d-block"><?= e(t('footer.app_store_kicker')) ?></span> <span class="fs-17 text-capitalize">App Store</span></div>
                            </a>
                            <a class="align-items-center app-btn d-flex fs-11 px-3 py-2 rounded-3 text-decoration-none text-white border" href="https://play.google.com/store/apps/details?id=eu.sofiago&amp;hl=en" target="_blank" rel="noopener">
                                <i class="fab fa-google-play fs-25 me-2"></i>
                                <div><span class="fs-13 d-block"><?= e(t('footer.google_play_kicker')) ?></span> <span class="fs-17 text-capitalize">Google Play</span></div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container border-top">
        <div class="align-items-center g-3 py-4 row">
            <div class="col-lg-auto">
                <ul class="list-unstyled list-separator mb-2 footer-nav">
                    <li class="list-inline-item"><a href="<?= e(url('/about')) ?>"><?= e(t('nav.about')) ?></a></li>
                    <li class="list-inline-item"><a href="<?= e(url('/terms')) ?>"><?= e(t('footer.terms')) ?></a></li>
                    <li class="list-inline-item"><a href="<?= e(url('/privacy')) ?>"><?= e(t('footer.privacy')) ?></a></li>
                    <li class="list-inline-item"><a href="<?= e(url('/delete-account')) ?>"><?= e(t('footer.delete_account')) ?></a></li>
                    <!-- sofiago-vue now lives at app.sofiago.eu (see the Flutter/Vue migration plan) — a
                         direct link keeps it reachable from the main site's footer on every page. -->
                    <li class="list-inline-item"><a href="https://app.sofiago.eu/" target="_blank" rel="noopener"><?= e(t('footer.open_app')) ?></a></li>
                </ul>
            </div>
            <div class="col-lg order-md-first">
                <div class="align-items-center row">
                    <a href="<?= e(url('/')) ?>" class="col-sm-auto footer-logo mb-2 mb-sm-0">
                        <?= partial('brand.tpl.php', ['variant' => 'light']) ?>
                    </a>
                    <div class="col-sm-auto copy">© <?= date('Y') ?> SofiaGO — <?= e(t('footer.rights')) ?></div>
                </div>
            </div>
        </div>
    </div>
</footer>
