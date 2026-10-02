<?php
/**
 * Shared stop page — see StopShareController. Self-contained (own CSS/JS, no Bootstrap) and
 * styled after the app (DESIGN.md in sofiago-flutter: page #F8F4F3, white cards with radius 16,
 * pill buttons, brand #F84525 for controls only, transport colors for line badges).
 *
 * @var string $code
 * @var string $name
 * @var float $lat
 * @var float $lon
 * @var list<string> $ids
 */
// Plain str_replace rather than t()'s own replacement: the deployed page printed ":code" verbatim.
$stopLabel = str_replace(':code', $code, t('stop_share.stop_code'));
$title = $name . ' · ' . $stopLabel;
$shareUrl = 'https://sofiago.eu/s/' . rawurlencode($code);
$playUrl = 'https://play.google.com/store/apps/details?id=eu.sofiago';
// Android: open the app's own /s/ handler when installed, else Google Play. Works whether or not
// App Links verification has gone through, because the intent names the package.
$intentUrl = 'intent://sofiago.eu/s/' . rawurlencode($code)
    . '#Intent;scheme=https;package=eu.sofiago;S.browser_fallback_url=' . rawurlencode($playUrl) . ';end';
$mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . $lat . ',' . $lon;
$strings = [
    'min' => t('stop_share.min'),
    'now' => t('stop_share.now'),
    'none' => t('stop_share.none'),
    'error' => t('stop_share.error'),
    'scheduled' => t('stop_share.scheduled'),
];
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($title) ?> — SofiaGO</title>
    <meta name="description" content="<?= e(t('stop_share.description')) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SofiaGO">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e(t('stop_share.description')) ?>">
    <meta property="og:url" content="<?= e($shareUrl) ?>">
    <meta property="og:image" content="<?= e(asset('theme/images/logo.png')) ?>">
    <meta name="theme-color" content="#F8F4F3">
    <link rel="canonical" href="<?= e($shareUrl) ?>">
    <meta name="robots" content="noindex">
    <link rel="shortcut icon" href="<?= e(asset('theme/images/favicon.png')) ?>">
    <style>
        :root {
            --page: #F8F4F3; --card: #FFFFFF; --ink: #2A2422; --muted: #8A817E; --line: #EFE9E7;
            --subtle: #F8F4F3; --brand: #F84525; --good: #2E9D4A; --late: #D93025;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --page: #141921; --card: #1B2027; --ink: #EDEBE9; --muted: #9AA3AE; --line: #2A313B;
                --subtle: #232932; --good: #4CC46A; --late: #FF6B5E;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--page); color: var(--ink);
            font: 15px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        main { max-width: 520px; margin: 0 auto; padding: 16px 16px 32px; }
        .brand { display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 17px; margin: 4px 0 16px; }
        .brand img { width: 28px; height: 28px; border-radius: 8px; }
        .card { background: var(--card); border-radius: 16px; padding: 16px; }
        h1 { font-size: 26px; line-height: 1.2; margin: 0; font-weight: 700; }
        .sub { color: var(--muted); margin-top: 4px; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; margin: 16px 0 4px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            min-height: 44px; padding: 0 18px; border-radius: 999px; font-weight: 600;
            text-decoration: none; color: var(--ink); background: var(--subtle); font-size: 15px;
        }
        .btn.primary { background: var(--brand); color: #fff; }
        .dep { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-top: 1px solid var(--line); }
        .dep:first-child { border-top: 0; }
        .badge {
            min-width: 52px; height: 32px; padding: 0 10px; border-radius: 10px; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center; font-size: 15px;
        }
        .mid { flex: 1; min-width: 0; }
        .dst { font-weight: 650; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .mode { font-size: 12.5px; color: var(--muted); }
        .tm { text-align: right; white-space: nowrap; }
        .tm .big { font-size: 19px; font-weight: 700; }
        .tm .big.live { color: var(--good); }
        .tm .nx { font-size: 12.5px; color: var(--muted); }
        .note { color: var(--muted); padding: 16px 0; text-align: center; }
        .section { font-size: 19px; font-weight: 700; margin: 24px 0 8px; }
        .app { margin-top: 24px; text-align: center; color: var(--muted); font-size: 13.5px; }
        .app .btn { margin-top: 10px; }
    </style>
</head>
<body>
<main>
    <div class="brand">
        <img src="<?= e(asset('theme/images/favicon.png')) ?>" alt="">
        SofiaGO
    </div>

    <div class="card">
        <h1><?= e($name) ?></h1>
        <div class="sub"><?= e($stopLabel) ?></div>
        <div class="actions">
            <a class="btn primary" id="open-app" href="<?= e($playUrl) ?>" data-intent="<?= e($intentUrl) ?>"><?= e(t('stop_share.open_app')) ?></a>
            <a class="btn" href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener"><?= e(t('stop_share.show_on_map')) ?></a>
        </div>
    </div>

    <div class="section"><?= e(t('stop_share.next_departures')) ?></div>
    <div class="card" id="departures"><div class="note">…</div></div>

    <div class="app">
        <?= e(t('stop_share.app_pitch')) ?><br>
        <a class="btn" href="<?= e($playUrl) ?>" target="_blank" rel="noopener"><?= e(t('stop_share.get_app')) ?></a>
    </div>
</main>
<script>
(function () {
    var ids = <?= json_encode($ids) ?>;
    var S = <?= json_encode($strings, JSON_UNESCAPED_UNICODE) ?>;
    var box = document.getElementById('departures');

    // Android gets the intent:// link (app if installed, Play otherwise); others keep Play.
    var open = document.getElementById('open-app');
    if (/Android/i.test(navigator.userAgent)) open.href = open.getAttribute('data-intent');

    var COLORS = {
        bus: ['#2AA9E0', '#000'], tram: ['#FFD800', '#000'], trolleybus: ['#E41F18', '#fff'],
        elbus: ['#3FB618', '#000'], nightbus: ['#000000', '#fff'],
        M1: ['#B71C1C', '#fff'], M2: ['#1565C0', '#fff'], M3: ['#388E3C', '#fff'], M4: ['#FFC107', '#000']
    };

    // Same split as the app (sofiago-data's dbTrips.ts): OTP reports Sofia's electric buses as
    // trolleybuses; real trolleybus routes are numbered up to 11.
    function typeOf(route) {
        var code = route.shortName || '';
        if (route.mode === 'SUBWAY') return code;
        if (route.mode === 'TRAM') return 'tram';
        if (/^N/i.test(code)) return 'nightbus';
        if (route.mode === 'TROLLEYBUS') {
            var n = parseInt(String(route.gtfsId || '').split(':').pop().slice(2), 10);
            return !isNaN(n) && n <= 11 ? 'trolleybus' : 'elbus';
        }
        return 'bus';
    }

    function titleCase(s) {
        return String(s || '').toLowerCase().replace(/(^|[\s.\-„"])(\S)/g, function (m, p, c) { return p + c.toUpperCase(); });
    }

    var QUERY = 'query($id:String!,$start:Long){stop(id:$id){stoptimesWithoutPatterns(startTime:$start,timeRange:7200,numberOfDepartures:60){scheduledDeparture realtimeDeparture realtime serviceDay headsign trip{tripHeadsign route{gtfsId shortName mode}}}}}';

    function fetchStop(id, start) {
        return fetch('/gtfs', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({query: QUERY, variables: {id: id, start: start}})
        }).then(function (r) { return r.json(); });
    }

    function load() {
        var now = Math.floor(Date.now() / 1000);
        Promise.all(ids.map(function (id) { return fetchStop(id, now).catch(function () { return null; }); }))
            .then(function (results) {
                var rows = {};
                results.forEach(function (res) {
                    var calls = res && res.data && res.data.stop && res.data.stop.stoptimesWithoutPatterns || [];
                    calls.forEach(function (c) {
                        var route = c.trip && c.trip.route; if (!route) return;
                        var t = c.serviceDay + (c.realtimeDeparture != null ? c.realtimeDeparture : c.scheduledDeparture);
                        var mins = Math.round((t - now) / 60);
                        if (mins < 0 || mins > 120) return;
                        var dest = titleCase(c.headsign || (c.trip && c.trip.tripHeadsign) || '');
                        var key = route.gtfsId + '|' + dest;
                        var row = rows[key] || (rows[key] = {route: route, dest: dest, times: []});
                        row.times.push({mins: mins, live: !!c.realtime});
                    });
                });
                var list = Object.keys(rows).map(function (k) {
                    var r = rows[k]; r.times.sort(function (a, b) { return a.mins - b.mins; }); return r;
                }).sort(function (a, b) { return a.times[0].mins - b.times[0].mins; });
                render(list);
            })
            .catch(function () { box.innerHTML = '<div class="note">' + S.error + '</div>'; });
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function render(list) {
        if (!list.length) { box.innerHTML = '<div class="note">' + S.none + '</div>'; return; }
        box.innerHTML = list.map(function (r) {
            var type = typeOf(r.route);
            var c = COLORS[type] || ['#888', '#fff'];
            var first = r.times[0], next = r.times[1];
            var big = first.mins === 0 ? S.now : first.mins + ' ' + S.min;
            return '<div class="dep">' +
                '<span class="badge" style="background:' + c[0] + ';color:' + c[1] + '">' + esc(r.route.shortName || '') + '</span>' +
                '<div class="mid"><div class="dst">' + esc(r.dest) + '</div>' +
                (first.live ? '' : '<div class="mode">' + S.scheduled + '</div>') + '</div>' +
                '<div class="tm"><div class="big' + (first.live ? ' live' : '') + '">' + big + '</div>' +
                (next ? '<div class="nx">' + next.mins + ' ' + S.min + '</div>' : '') + '</div>' +
                '</div>';
        }).join('');
    }

    load();
    setInterval(load, 30000);
})();
</script>
</body>
</html>
