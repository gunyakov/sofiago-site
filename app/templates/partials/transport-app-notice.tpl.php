<?php
// One-time heads-up for visitors who still expect the transport map at this domain — the
// live map/stops/routes app moved to app.sofiago.eu (see the Flutter/Vue migration plan;
// this domain's root used to serve that Vue app directly before sofiago-site took it over).
// Shown sitewide (included from layout/main.tpl.php) since old bookmarks/links can land
// anywhere, not just "/". Visibility logic lives in assets/js/transport-app-notice.js —
// this partial is just the (initially hidden, Bootstrap-controlled) markup.
?>
<div class="modal fade" id="transportAppNoticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= e(t('transport_notice.title')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0"><?= e(t('transport_notice.body')) ?></p>
            </div>
            <div class="modal-footer flex-wrap justify-content-between">
                <button type="button" class="btn btn-link text-muted p-0" id="transportAppNoticeDismissForever"><?= e(t('transport_notice.dismiss_forever')) ?></button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('transport_notice.close')) ?></button>
                    <a href="https://app.sofiago.eu/" target="_blank" rel="noopener" class="btn btn-primary"><?= e(t('transport_notice.open_app')) ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
