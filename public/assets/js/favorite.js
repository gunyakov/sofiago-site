// Favorite/bookmark toggle button. Plain JS on purpose — a single boolean toggle doesn't need
// Vue's reactivity, unlike the map island (vue-widgets/src/map.js). Loaded on every page (see
// layout/main.tpl.php) since favorite buttons can appear on the catalog, listing detail and
// favorites pages alike.
(function () {
    function onClick(event) {
        var btn = event.currentTarget;
        var icon = btn.querySelector('i');

        fetch(btn.dataset.toggleUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': btn.dataset.csrf },
            credentials: 'same-origin',
        })
            .then(function (res) {
                if (res.status === 401) {
                    window.location.href = btn.dataset.loginUrl;
                    return null;
                }
                return res.json();
            })
            .then(function (data) {
                if (!data || typeof data.favorited === 'undefined') {
                    return;
                }
                btn.dataset.favorited = data.favorited ? '1' : '0';
                icon.classList.toggle('fa-solid', data.favorited);
                icon.classList.toggle('fa-regular', !data.favorited);
            })
            .catch(function (err) {
                console.error('SofiaGO favorite toggle failed', err);
            });
    }

    document.querySelectorAll('.favorite-btn').forEach(function (btn) {
        btn.addEventListener('click', onClick);
    });
})();
