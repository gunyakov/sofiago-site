<?php
/**
 * @var array<string, mixed> $venue Venue::find() — id/slug/title only, already access-checked
 *   (has_map = 1 AND map_published = 1 AND at least one floor — see that method's doc comment).
 * @var int|null $highlightUnitId ?unit= from the URL — the listing that sent the visitor here,
 *   drawn with an accent outline once its floor loads.
 * @var int|null $initialFloorId ?floor= from the URL — which floor to open on, when known
 *   up front (VenuePageController never resolves this itself, see its doc comment).
 *
 * Deliberately not the admin editor's canvas: no drawing tools, no unit-code input, nothing that
 * implies this is a work in progress. Floors/units come from the exact same public JSON this
 * page's own /api/venues/{id}/floors and /api/venues/{id}/floors/{floorId}/units endpoints
 * already serve sofiago-flutter's MallMapScreen — see VenueApiController — so there's no second
 * copy of the "what can the public see" decision to keep in sync with it.
 */
?>
<style>
    #venue-map-wrap { position: relative; border: 1px solid #dcdfe5; border-radius: 12px; overflow: hidden; background: #eceae4; }
    #venue-map-svg { width: 100%; height: auto; display: block; }
    #venue-map-svg .unit-shape { fill: #bdbdbd; fill-opacity: .75; stroke: #90928f; stroke-width: 2; cursor: pointer; transition: fill .15s; }
    #venue-map-svg .unit-shape.vacant { cursor: default; }
    #venue-map-svg .unit-shape:hover:not(.vacant) { fill: #a8c8f0; }
    #venue-map-svg .unit-shape.highlighted { fill: #ff5a1f; fill-opacity: .9; stroke: #c94411; stroke-width: 3; }
    #venue-map-svg .unit-label { font-size: 13px; fill: #263238; pointer-events: none; text-anchor: middle; dominant-baseline: middle; }
    .venue-floor-tab.active { font-weight: 700; border-bottom: 3px solid var(--bs-primary, #ff5a1f); }
</style>

<div class="container-xxl py-4">
    <a href="<?= e(url('/explore')) ?>" class="text-decoration-none small mb-2 d-inline-block" onclick="history.back(); return false;">&larr; <?= e(t('listing.back_to_catalog')) ?></a>
    <h1 class="mb-1"><?= e($venue['title']) ?></h1>
    <p class="text-muted mb-4"><?= e(t('venue_page.hint')) ?></p>

    <div id="venue-floor-tabs" class="d-flex gap-3 mb-3"></div>

    <div id="venue-map-wrap">
        <div id="venue-map-loading" class="text-center text-muted py-5"><?= e(t('venue_page.loading')) ?></div>
        <svg id="venue-map-svg" hidden viewBox="0 0 1000 700" preserveAspectRatio="xMidYMid meet">
            <g id="venue-units-layer"></g>
        </svg>
    </div>
</div>

<script>
(function () {
    'use strict';

    var venueId = <?= (int) $venue['id'] ?>;
    var initialFloorId = <?= $initialFloorId !== null ? (int) $initialFloorId : 'null' ?>;
    var highlightUnitId = <?= $highlightUnitId !== null ? (int) $highlightUnitId : 'null' ?>;
    var apiBase = <?= json_encode(url('/api/venues/' . (int) $venue['id'])) ?>;
    var listingUrlTemplate = <?= json_encode(url('/listings/')) ?>;

    var tabsEl = document.getElementById('venue-floor-tabs');
    var loadingEl = document.getElementById('venue-map-loading');
    var svg = document.getElementById('venue-map-svg');
    var unitsLayer = document.getElementById('venue-units-layer');
    var NS = 'http://www.w3.org/2000/svg';

    var floors = [];
    var floorIndex = 0;
    var unitsCache = {};

    function el(tag, attrs) {
        var node = document.createElementNS(NS, tag);
        for (var k in attrs) { node.setAttribute(k, attrs[k]); }
        return node;
    }

    function shapeNode(unit) {
        var cls = 'unit-shape' + (unit.listing_id ? '' : ' vacant') + (highlightUnitId !== null && unit.id === highlightUnitId ? ' highlighted' : '');
        if (unit.shape_type === 'rectangle') {
            var p = unit.shape_points;
            var x = Math.min(p[0][0], p[1][0]), y = Math.min(p[0][1], p[1][1]);
            return el('rect', { x: x, y: y, width: Math.abs(p[1][0] - p[0][0]), height: Math.abs(p[1][1] - p[0][1]), class: cls });
        }
        if (unit.shape_type === 'circle') {
            var c = unit.shape_points[0];
            return el('circle', { cx: c[0], cy: c[1], r: unit.radius, class: cls });
        }
        var pts = unit.shape_points.map(function (pp) { return pp[0] + ',' + pp[1]; }).join(' ');
        return el('polygon', { points: pts, class: cls });
    }

    function centroid(points) {
        var sx = 0, sy = 0;
        points.forEach(function (p) { sx += p[0]; sy += p[1]; });
        return [sx / points.length, sy / points.length];
    }

    function renderTabs() {
        tabsEl.innerHTML = '';
        floors.forEach(function (floor, index) {
            var a = document.createElement('a');
            a.href = '#';
            a.className = 'venue-floor-tab text-decoration-none' + (index === floorIndex ? ' active' : '');
            a.textContent = floor.short_label + (floor.name ? ' — ' + floor.name : '');
            a.addEventListener('click', function (evt) {
                evt.preventDefault();
                floorIndex = index;
                renderTabs();
                loadFloor(floor);
            });
            tabsEl.appendChild(a);
        });
    }

    function renderUnits(units) {
        var minX = 0, minY = 0, maxX = 1000, maxY = 700, first = true;
        units.forEach(function (unit) {
            var pts = unit.shape_type === 'circle'
                ? [[unit.shape_points[0][0] - unit.radius, unit.shape_points[0][1] - unit.radius], [unit.shape_points[0][0] + unit.radius, unit.shape_points[0][1] + unit.radius]]
                : unit.shape_points;
            pts.forEach(function (p) {
                if (first) { minX = maxX = p[0]; minY = maxY = p[1]; first = false; }
                minX = Math.min(minX, p[0]); maxX = Math.max(maxX, p[0]);
                minY = Math.min(minY, p[1]); maxY = Math.max(maxY, p[1]);
            });
        });
        var margin = 24;
        svg.setAttribute('viewBox', (minX - margin) + ' ' + (minY - margin) + ' ' + (maxX - minX + margin * 2) + ' ' + (maxY - minY + margin * 2));

        unitsLayer.innerHTML = '';
        units.forEach(function (unit) {
            var g = el('g', {});
            g.appendChild(shapeNode(unit));
            if (unit.listing_title) {
                var c = unit.shape_type === 'circle' ? unit.shape_points[0] : centroid(unit.shape_points);
                var label = el('text', { x: c[0], y: c[1], class: 'unit-label' });
                label.textContent = unit.listing_title;
                g.appendChild(label);
            }
            if (unit.listing_slug) {
                g.addEventListener('click', function () {
                    window.location.href = listingUrlTemplate + unit.listing_slug;
                });
            }
            unitsLayer.appendChild(g);
        });

        loadingEl.hidden = true;
        svg.hidden = false;
    }

    function loadFloor(floor) {
        if (unitsCache[floor.id]) {
            renderUnits(unitsCache[floor.id]);
            return;
        }
        fetch(apiBase + '/floors/' + floor.id + '/units')
            .then(function (res) { return res.json(); })
            .then(function (units) {
                unitsCache[floor.id] = units;
                renderUnits(units);
            });
    }

    fetch(apiBase + '/floors')
        .then(function (res) { return res.json(); })
        .then(function (data) {
            floors = data;
            if (floors.length === 0) { return; }

            if (initialFloorId !== null) {
                var idx = floors.findIndex(function (f) { return f.id === initialFloorId; });
                if (idx >= 0) { floorIndex = idx; }
            }

            renderTabs();
            loadFloor(floors[floorIndex]);
        });
})();
</script>
