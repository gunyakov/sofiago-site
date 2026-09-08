// Auto-shows #transportAppNoticeModal (partials/transport-app-notice.tpl.php) once per page
// load, unless the visitor already dismissed it — "just close" is remembered only for this
// browser session (sessionStorage, so it comes back next visit), "don't show again" is
// remembered for good (localStorage). Plain JS on purpose, same reasoning as favorite.js —
// no framework needed for a one-shot visibility toggle.
(function () {
    var SESSION_KEY = 'sofiago_transport_notice_closed';
    var FOREVER_KEY = 'sofiago_transport_notice_dismissed';

    function dismissedAlready() {
        try {
            return (
                sessionStorage.getItem(SESSION_KEY) === '1' ||
                localStorage.getItem(FOREVER_KEY) === '1'
            );
        } catch (e) {
            // Storage unavailable (private mode, blocked cookies, etc.) — fail open and show
            // the notice rather than silently never telling anyone the app moved.
            return false;
        }
    }

    if (dismissedAlready()) {
        return;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('transportAppNoticeModal');
        if (!el || typeof bootstrap === 'undefined') {
            return;
        }

        var modal = new bootstrap.Modal(el);

        el.addEventListener('hidden.bs.modal', function () {
            try {
                sessionStorage.setItem(SESSION_KEY, '1');
            } catch (e) {}
        });

        var dismissForeverBtn = document.getElementById('transportAppNoticeDismissForever');
        if (dismissForeverBtn) {
            dismissForeverBtn.addEventListener('click', function () {
                try {
                    localStorage.setItem(FOREVER_KEY, '1');
                } catch (e) {}
                modal.hide();
            });
        }

        modal.show();
    });
})();
