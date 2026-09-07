<?php
/**
 * @var array<string, mixed> $listing Venue::adminFind()
 * @var array<int, array<string, mixed>> $floors Venue::floorsFor() — highest floor_order first
 * @var array<string, mixed>|null $selectedFloor
 * @var array<int, array<string, mixed>> $units Venue::unitsFor($selectedFloor['id']) when a floor is selected
 *
 * The editor draws on an abstract canvas whose size is this floor's own canvas_width/
 * canvas_height (1000x700 by default for a freehand-drawn floor — see CANVAS_W/CANVAS_H below —
 * but switched to match an imported SVG's own dimensions on first import, see the "Импорт SVG"
 * wizard's doc comment further down). shape_points are just numbers in that space, not
 * geographic coordinates (see venue_units.shape_points' doc comment in schema.sql), and
 * sofiago-flutter's MallFloorLayout derives its own rotation/bounds from whatever points end up
 * in the DB, so this canvas size is only ever an admin-editor drawing convenience.
 */
$backUrl = url('/admin/venue-maps');
?>
<style>
    #floor-canvas-wrap { position: relative; border: 1px solid #dcdfe5; border-radius: 8px; overflow: hidden; background: #fafbfc; }
    #floor-canvas { width: 100%; height: auto; display: block; cursor: crosshair; }
    #floor-canvas .unit-shape { fill: #bdbdbd; fill-opacity: .55; stroke: #757575; stroke-width: 2; cursor: pointer; }
    #floor-canvas .unit-shape.vacant { fill: #81c784; stroke: #4caf50; }
    #floor-canvas .unit-shape.selected { stroke: #1976d2; stroke-width: 3; }
    #floor-canvas .unit-label { font-size: 14px; fill: #263238; pointer-events: none; text-anchor: middle; dominant-baseline: middle; }
    #floor-canvas .draft-point { fill: #1976d2; stroke: #fff; stroke-width: 1.5; }
    #floor-canvas .draft-shape { fill: #1976d2; fill-opacity: .25; stroke: #1976d2; stroke-width: 2; stroke-dasharray: 4 3; }
    #floor-canvas .import-shape { fill: #ff9800; fill-opacity: .25; stroke: #ff9800; stroke-width: 1.5; cursor: pointer; }
    #floor-canvas .import-shape.import-selected { stroke: #e65100; stroke-width: 3; fill-opacity: .45; }
    .floor-tab.active { font-weight: 600; border-bottom: 2px solid var(--bs-primary, #ff5a1f); }
    #import-review-list { max-height: 320px; overflow-y: auto; }
    #import-review-list tr.import-row-excluded { opacity: .4; }
    #import-review-list tr.import-row-selected td { background: #fff3e0; }
</style>

<div class="container-xxl py-4">
    <a href="<?= e($backUrl) ?>" class="text-decoration-none small mb-2 d-inline-block">&larr; <?= e(t('admin.venue_maps_title')) ?></a>
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <h1 class="mb-0 me-auto"><?= e($listing['title']) ?></h1>
        <!-- Publish gate (listings.map_published) — see AdminVenueMapController::setPublished()
             and that column's doc comment in schema.sql. Kept here too (not just the list page)
             since this is where an admin actually finds out the floors/units are done. -->
        <form method="post" action="<?= e(url('/admin/venue-maps/' . $listing['id'] . '/publish')) ?>" class="form-check form-switch mb-0">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect_to" value="/admin/venue-maps/<?= (int) $listing['id'] ?><?= $selectedFloor ? '?floor=' . (int) $selectedFloor['id'] : '' ?>">
            <input class="form-check-input" type="checkbox" role="switch" id="map-published-toggle" name="published" value="1" onchange="this.form.submit()" <?= !empty($listing['map_published']) ? 'checked' : '' ?>>
            <label class="form-check-label fw-medium" for="map-published-toggle">
                <?= !empty($listing['map_published']) ? e(t('admin.venue_map_published')) : e(t('admin.venue_map_unpublished')) ?>
            </label>
        </form>
    </div>
    <?php if (!empty($listing['map_published']) && $floors === []): ?>
        <div class="alert alert-warning"><?= e(t('admin.venue_map_published_no_floors_warning')) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0"><?= e(t('admin.venue_floors')) ?></h6></div>
                <div class="list-group list-group-flush">
                    <?php foreach ($floors as $floor): ?>
                        <a href="<?= e(url('/admin/venue-maps/' . $listing['id'] . '?floor=' . $floor['id'])) ?>"
                           class="list-group-item list-group-item-action floor-tab <?= $selectedFloor && (int) $selectedFloor['id'] === (int) $floor['id'] ? 'active' : '' ?>">
                            <span class="badge bg-secondary me-2"><?= e($floor['short_label']) ?></span><?= e($floor['name'] ?: '') ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($floors === []): ?>
                        <div class="list-group-item text-muted small"><?= e(t('admin.venue_floors_empty')) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0"><?= e(t('admin.venue_floor_add')) ?></h6></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('/admin/venue-maps/' . $listing['id'] . '/floors')) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small"><?= e(t('admin.venue_floor_order')) ?></label>
                            <input type="number" name="floor_order" class="form-control form-control-sm" value="0" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small"><?= e(t('admin.venue_floor_short_label')) ?></label>
                            <input type="text" name="short_label" maxlength="8" class="form-control form-control-sm" placeholder="0, 1, -1, M" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small"><?= e(t('admin.venue_floor_name')) ?></label>
                            <input type="text" name="name" maxlength="120" class="form-control form-control-sm">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100"><?= e(t('admin.venue_floor_add')) ?></button>
                    </form>
                </div>
            </div>

            <?php if ($selectedFloor): ?>
            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0"><?= e(t('admin.venue_floor_settings')) ?></h6></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('/admin/venue-maps/' . $listing['id'] . '/floors/' . $selectedFloor['id'])) ?>" class="mb-3">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small"><?= e(t('admin.venue_floor_order')) ?></label>
                            <input type="number" name="floor_order" class="form-control form-control-sm" value="<?= (int) $selectedFloor['floor_order'] ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small"><?= e(t('admin.venue_floor_short_label')) ?></label>
                            <input type="text" name="short_label" maxlength="8" class="form-control form-control-sm" value="<?= e($selectedFloor['short_label']) ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small"><?= e(t('admin.venue_floor_name')) ?></label>
                            <input type="text" name="name" maxlength="120" class="form-control form-control-sm" value="<?= e((string) $selectedFloor['name']) ?>">
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100"><?= e(t('admin.venue_floor_save')) ?></button>
                    </form>

                    <!-- Admin-only tracing reference (venue_floors.image_path) — never served by
                         the public API, purely a "draw over this photo" aid in this editor. -->
                    <label class="form-label small mb-1"><?= e(t('admin.venue_floor_image')) ?></label>
                    <p class="fs-13 text-muted"><?= e(t('admin.venue_floor_image_hint')) ?></p>
                    <?php if (!empty($selectedFloor['image_path'])): ?>
                        <img src="<?= e($selectedFloor['image_path']) ?>" class="img-fluid rounded mb-2 border" alt="">
                        <form method="post" action="<?= e(url('/admin/venue-maps/' . $listing['id'] . '/floors/' . $selectedFloor['id'] . '/image/delete')) ?>" class="mb-2">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100"><?= e(t('admin.venue_floor_image_remove')) ?></button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/admin/venue-maps/' . $listing['id'] . '/floors/' . $selectedFloor['id'] . '/image')) ?>" enctype="multipart/form-data" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="file" name="image" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm">
                        <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><?= e(t('admin.venue_floor_image_upload')) ?></button>
                    </form>

                    <hr>
                    <form method="post" action="<?= e(url('/admin/venue-maps/' . $listing['id'] . '/floors/' . $selectedFloor['id'] . '/delete')) ?>" onsubmit="return confirm(<?= json_encode(t('admin.venue_floor_delete_confirm')) ?>);">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100"><?= e(t('admin.venue_floor_delete')) ?></button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-9">
            <?php if (!$selectedFloor): ?>
                <div class="alert alert-light border text-center py-5"><?= e(t('admin.venue_floors_empty')) ?></div>
            <?php else: ?>
            <?php $canvasWidth = (int) $selectedFloor['canvas_width']; $canvasHeight = (int) $selectedFloor['canvas_height']; ?>
            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <span class="me-auto"><?= e(t('admin.venue_units')) ?></span>
                    <select id="shape-type-select" class="form-select form-select-sm" style="width:auto;">
                        <option value="rectangle"><?= e(t('admin.venue_shape_rectangle')) ?></option>
                        <option value="circle"><?= e(t('admin.venue_shape_circle')) ?></option>
                        <option value="polygon"><?= e(t('admin.venue_shape_polygon')) ?></option>
                    </select>
                    <button type="button" id="btn-new-unit" class="btn btn-sm btn-primary"><?= e(t('admin.venue_unit_new')) ?></button>
                    <button type="button" id="btn-finish-shape" class="btn btn-sm btn-success" hidden><?= e(t('admin.venue_unit_finish')) ?></button>
                    <button type="button" id="btn-cancel-shape" class="btn btn-sm btn-outline-secondary" hidden><?= e(t('admin.venue_unit_cancel')) ?></button>
                    <!-- SVG import wizard (see its own doc comment further down) — an alternative
                         to freehand drawing for a complex real floor plan: trace it properly in
                         a real vector editor (Inkscape etc.) over a reference photo/PDF, export
                         plain SVG, upload it here, and this parses out one candidate shape per
                         drawn object instead of making an admin click out ~100 polygons by hand. -->
                    <input type="file" id="svg-import-input" accept=".svg,image/svg+xml" hidden>
                    <button type="button" id="btn-import-svg" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-file-import me-1"></i><?= e(t('admin.venue_import_svg')) ?></button>
                </div>
                <div class="card-body">
                    <p id="draw-hint" class="fs-13 text-muted"><?= e(t('admin.venue_unit_hint')) ?></p>

                    <div id="floor-canvas-wrap">
                        <svg id="floor-canvas" viewBox="0 0 <?= $canvasWidth ?> <?= $canvasHeight ?>" preserveAspectRatio="xMidYMid meet">
                            <?php if (!empty($selectedFloor['image_path'])): ?>
                                <image href="<?= e($selectedFloor['image_path']) ?>" x="0" y="0" width="<?= $canvasWidth ?>" height="<?= $canvasHeight ?>" opacity="0.45" preserveAspectRatio="none"></image>
                            <?php endif; ?>
                            <g id="units-layer"></g>
                            <g id="import-layer"></g>
                            <g id="draft-layer"></g>
                        </svg>
                    </div>

                    <!-- Import review — hidden until a file is actually parsed (see #btn-import-svg's
                         handler). Every candidate shape found in the uploaded SVG gets one row here:
                         admin confirms/edits its code (or unchecks it to skip, e.g. a stray icon
                         that isn't really a unit) before anything is sent to the server. -->
                    <div id="import-review" class="mt-3 border rounded p-3" hidden>
                        <div class="d-flex align-items-center mb-2">
                            <strong class="me-auto" id="import-review-title"></strong>
                            <button type="button" id="btn-import-commit" class="btn btn-sm btn-success me-2"><?= e(t('admin.venue_import_commit')) ?></button>
                            <button type="button" id="btn-import-cancel" class="btn btn-sm btn-outline-secondary"><?= e(t('admin.venue_import_cancel')) ?></button>
                        </div>
                        <p class="fs-13 text-muted"><?= e(t('admin.venue_import_hint')) ?></p>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:40px;"></th>
                                        <th><?= e(t('admin.venue_unit_code')) ?></th>
                                        <th><?= e(t('admin.venue_shape_polygon')) ?></th>
                                    </tr>
                                </thead>
                                <tbody id="import-review-list"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="unit-form" class="row g-2 align-items-end mt-3">
                        <div class="col-auto">
                            <label class="form-label small mb-1"><?= e(t('admin.venue_unit_code')) ?></label>
                            <input type="text" id="unit-code-input" maxlength="40" class="form-control form-control-sm" style="width:160px;" placeholder="A12">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-1 d-block"><?= e(t('admin.venue_unit_finetune')) ?></label>
                            <div class="btn-group" role="group" aria-label="<?= e(t('admin.venue_unit_finetune')) ?>">
                                <button type="button" id="btn-nudge-left" class="btn btn-sm btn-outline-secondary" disabled title="<?= e(t('admin.venue_unit_move_left')) ?>"><i class="fa-solid fa-arrow-left"></i></button>
                                <button type="button" id="btn-nudge-right" class="btn btn-sm btn-outline-secondary" disabled title="<?= e(t('admin.venue_unit_move_right')) ?>"><i class="fa-solid fa-arrow-right"></i></button>
                                <button type="button" id="btn-nudge-up" class="btn btn-sm btn-outline-secondary" disabled title="<?= e(t('admin.venue_unit_move_up')) ?>"><i class="fa-solid fa-arrow-up"></i></button>
                                <button type="button" id="btn-nudge-down" class="btn btn-sm btn-outline-secondary" disabled title="<?= e(t('admin.venue_unit_move_down')) ?>"><i class="fa-solid fa-arrow-down"></i></button>
                                <button type="button" id="btn-shrink" class="btn btn-sm btn-outline-secondary" disabled title="<?= e(t('admin.venue_unit_shrink')) ?>"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                                <button type="button" id="btn-enlarge" class="btn btn-sm btn-outline-secondary" disabled title="<?= e(t('admin.venue_unit_enlarge')) ?>"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                            </div>
                        </div>
                        <div class="col-auto">
                            <button type="button" id="btn-save-unit" class="btn btn-sm btn-success" disabled><?= e(t('admin.venue_unit_save')) ?></button>
                        </div>
                        <div class="col-auto">
                            <button type="button" id="btn-delete-unit" class="btn btn-sm btn-outline-danger" hidden><?= e(t('admin.venue_unit_delete')) ?></button>
                        </div>
                        <div class="col-auto">
                            <span id="unit-occupant" class="small text-muted"></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($selectedFloor): ?>
<script>
(function () {
    'use strict';

    // Mutable, not a constant: an SVG import can switch these mid-session (and persists the new
    // size via Venue::setFloorCanvasSize() — see the import wizard below) so every shape drawn
    // afterwards, by hand or by another import, lands in the same coordinate space.
    var CANVAS_W = <?= $canvasWidth ?>, CANVAS_H = <?= $canvasHeight ?>;
    var listingId = <?= (int) $listing['id'] ?>;
    var floorId = <?= (int) $selectedFloor['id'] ?>;
    var csrfToken = <?= json_encode(csrf_token()) ?>;
    var saveUrl = <?= json_encode(url('/admin/venue-maps/' . $listing['id'] . '/floors/' . $selectedFloor['id'] . '/units')) ?>;
    var importUrl = saveUrl + '/import';
    var deleteUrlBase = saveUrl + '/';

    var initialUnits = <?= json_encode(array_map(static function (array $u): array {
        return [
            'id' => (int) $u['id'],
            'unit_code' => $u['unit_code'],
            'shape_type' => $u['shape_type'],
            'shape_points' => $u['shape_points'],
            'radius' => $u['radius'],
            'occupant' => $u['listing_title'],
        ];
    }, $units), JSON_UNESCAPED_UNICODE) ?>;

    var svg = document.getElementById('floor-canvas');
    var unitsLayer = document.getElementById('units-layer');
    var draftLayer = document.getElementById('draft-layer');
    var shapeSelect = document.getElementById('shape-type-select');
    var btnNew = document.getElementById('btn-new-unit');
    var btnFinish = document.getElementById('btn-finish-shape');
    var btnCancel = document.getElementById('btn-cancel-shape');
    var btnSave = document.getElementById('btn-save-unit');
    var btnDelete = document.getElementById('btn-delete-unit');
    var codeInput = document.getElementById('unit-code-input');
    var occupantLabel = document.getElementById('unit-occupant');
    var hint = document.getElementById('draw-hint');
    var fineTuneButtons = [
        document.getElementById('btn-nudge-left'), document.getElementById('btn-nudge-right'),
        document.getElementById('btn-nudge-up'), document.getElementById('btn-nudge-down'),
        document.getElementById('btn-shrink'), document.getElementById('btn-enlarge'),
    ];

    var NS = 'http://www.w3.org/2000/svg';
    var NUDGE_STEP = 8; // canvas units per click — small relative to the 1000x700 canvas, a real "nudge"
    var SCALE_STEP = 1.06; // +/-6% per click
    var units = {}; // id -> saved unit data
    var drawing = false;
    var draftPoints = []; // [[x,y], ...] in canvas space, only while actively clicking out a new shape

    /**
     * The one shape currently being edited, whether that's an existing unit the admin clicked
     * on or a brand new one just finished drawing — {unitId: int|null, shape_type, shape_points,
     * radius}. null means nothing selected. Nudge/scale/Save/Delete all act on this, not on
     * [units] or [draftPoints] directly, so "draw it, then fine-tune before saving" and "select
     * an old one, then fine-tune it" go through the exact same code from this point on.
     */
    var activeShape = null;

    function el(tag, attrs) {
        var node = document.createElementNS(NS, tag);
        for (var k in attrs) { node.setAttribute(k, attrs[k]); }
        return node;
    }

    function clientToCanvas(evt) {
        var pt = svg.createSVGPoint();
        pt.x = evt.clientX;
        pt.y = evt.clientY;
        var ctm = svg.getScreenCTM().inverse();
        var loc = pt.matrixTransform(ctm);
        return [Math.max(0, Math.min(CANVAS_W, loc.x)), Math.max(0, Math.min(CANVAS_H, loc.y))];
    }

    function centroid(points) {
        var sx = 0, sy = 0;
        points.forEach(function (p) { sx += p[0]; sy += p[1]; });
        return [sx / points.length, sy / points.length];
    }

    function shapeNode(shape, extraClass) {
        // cssBase defaults to '' (not 'unit-shape') so a caller that wants the draft/active
        // outline styling (extraClass 'draft-shape') gets ONLY that class — renderUnits() below
        // is the one caller that opts into the grey/green unit-shape look by setting cssBase
        // itself.
        var cls = (shape.cssBase || '') + ' ' + (extraClass || '');
        var node;
        if (shape.shape_type === 'rectangle') {
            var p = shape.shape_points;
            var x = Math.min(p[0][0], p[1][0]), y = Math.min(p[0][1], p[1][1]);
            var w = Math.abs(p[1][0] - p[0][0]), h = Math.abs(p[1][1] - p[0][1]);
            node = el('rect', { x: x, y: y, width: w, height: h, class: cls });
        } else if (shape.shape_type === 'circle') {
            var c = shape.shape_points[0];
            node = el('circle', { cx: c[0], cy: c[1], r: shape.radius, class: cls });
        } else {
            var pts = shape.shape_points.map(function (pp) { return pp[0] + ',' + pp[1]; }).join(' ');
            node = el('polygon', { points: pts, class: cls });
        }
        return node;
    }

    function renderUnits() {
        unitsLayer.innerHTML = '';
        Object.keys(units).forEach(function (id) {
            var unit = units[id];
            var isActive = activeShape && activeShape.unitId !== null && String(activeShape.unitId) === String(id);
            var g = el('g', { 'data-unit-id': id });
            var shape = { shape_type: unit.shape_type, shape_points: unit.shape_points, radius: unit.radius, cssBase: 'unit-shape ' + (unit.occupant ? '' : 'vacant') };
            // The active shape (below) already draws its own live outline + label on top while
            // it's being fine-tuned — skip both here so nothing lags behind at the shape's old,
            // pre-nudge/scale position while the visible copy has already moved.
            if (!isActive) {
                g.appendChild(shapeNode(shape));
                var c = unit.shape_type === 'circle' ? unit.shape_points[0] : centroid(unit.shape_points);
                var label = el('text', { x: c[0], y: c[1], class: 'unit-label' });
                label.textContent = unit.unit_code || '#' + unit.id;
                g.appendChild(label);
            }
            g.addEventListener('click', function (evt) {
                evt.stopPropagation();
                if (drawing) { return; }
                selectUnit(unit.id);
            });
            unitsLayer.appendChild(g);
        });
    }

    function renderActive() {
        draftLayer.innerHTML = '';

        draftPoints.forEach(function (p) {
            draftLayer.appendChild(el('circle', { cx: p[0], cy: p[1], r: 5, class: 'draft-point' }));
        });

        if (activeShape) {
            draftLayer.appendChild(shapeNode(activeShape, 'draft-shape'));
            var ac = activeShape.shape_type === 'circle' ? activeShape.shape_points[0] : centroid(activeShape.shape_points);
            var activeLabel = el('text', { x: ac[0], y: ac[1], class: 'unit-label' });
            activeLabel.textContent = codeInput.value || (activeShape.unitId !== null ? '#' + activeShape.unitId : '?');
            draftLayer.appendChild(activeLabel);
        } else if (drawing) {
            var type = shapeSelect.value;
            if (type === 'rectangle' && draftPoints.length === 2) {
                draftLayer.appendChild(shapeNode({ shape_type: 'rectangle', shape_points: draftPoints }, 'draft-shape'));
            } else if (type === 'circle' && draftPoints.length === 2) {
                var c = draftPoints[0], e2 = draftPoints[1];
                draftLayer.appendChild(shapeNode({ shape_type: 'circle', shape_points: [c], radius: Math.hypot(e2[0] - c[0], e2[1] - c[1]) }, 'draft-shape'));
            } else if (type === 'polygon' && draftPoints.length >= 2) {
                var pts = draftPoints.map(function (pp) { return pp[0] + ',' + pp[1]; }).join(' ');
                draftLayer.appendChild(el('polyline', { points: pts, class: 'draft-shape' }));
            }
        }
    }

    function setFineTuneEnabled(enabled) {
        fineTuneButtons.forEach(function (btn) { btn.disabled = !enabled; });
    }

    function selectUnit(id) {
        // Selecting an existing unit always discards any not-yet-saved draw-in-progress —
        // editing one unit and drawing a brand new one at the same time isn't supported.
        draftPoints = [];
        var unit = units[id];
        activeShape = { unitId: unit.id, shape_type: unit.shape_type, shape_points: cloneShape(unit.shape_points), radius: unit.radius };
        codeInput.value = unit.unit_code || '';
        occupantLabel.textContent = unit.occupant ? <?= json_encode(t('admin.venue_unit_occupied_by')) ?> + ' ' + unit.occupant : '';
        btnDelete.hidden = false;
        btnSave.disabled = false;
        setFineTuneEnabled(true);
        renderUnits();
        renderActive();
    }

    function clearSelection() {
        activeShape = null;
        codeInput.value = '';
        occupantLabel.textContent = '';
        btnDelete.hidden = true;
        btnSave.disabled = true;
        setFineTuneEnabled(false);
        renderUnits();
        renderActive();
    }

    function cloneShape(points) {
        return points.map(function (p) { return [p[0], p[1]]; });
    }

    function draftIsComplete() {
        var type = shapeSelect.value;
        if (type === 'rectangle') { return draftPoints.length === 2; }
        if (type === 'circle') { return draftPoints.length === 2; }
        return draftPoints.length >= 3;
    }

    function startDrawing() {
        drawing = true;
        draftPoints = [];
        activeShape = null;
        codeInput.value = '';
        occupantLabel.textContent = '';
        btnDelete.hidden = true;
        btnSave.disabled = true;
        setFineTuneEnabled(false);
        btnNew.hidden = true;
        btnCancel.hidden = false;
        btnFinish.hidden = shapeSelect.value !== 'polygon';
        shapeSelect.disabled = true;
        hint.textContent = hintFor(shapeSelect.value);
        renderUnits();
        renderActive();
    }

    function hintFor(type) {
        if (type === 'rectangle') { return <?= json_encode(t('admin.venue_unit_hint_rectangle')) ?>; }
        if (type === 'circle') { return <?= json_encode(t('admin.venue_unit_hint_circle')) ?>; }
        return <?= json_encode(t('admin.venue_unit_hint_polygon')) ?>;
    }

    function stopDrawing(keepPoints) {
        drawing = false;
        btnNew.hidden = false;
        btnCancel.hidden = true;
        btnFinish.hidden = true;
        shapeSelect.disabled = false;
        hint.textContent = <?= json_encode(t('admin.venue_unit_hint')) ?>;
        if (!keepPoints) {
            draftPoints = [];
            renderActive();
        }
    }

    svg.addEventListener('click', function (evt) {
        if (!drawing) { return; }
        var pt = clientToCanvas(evt);
        var type = shapeSelect.value;

        draftPoints.push(pt);
        renderActive();

        if ((type === 'rectangle' || type === 'circle') && draftPoints.length === 2) {
            finishDraft();
        }
    });

    function finishDraft() {
        if (!draftIsComplete()) { return; }
        var type = shapeSelect.value;
        var points, radius = null;
        if (type === 'circle') {
            radius = Math.hypot(draftPoints[1][0] - draftPoints[0][0], draftPoints[1][1] - draftPoints[0][1]);
            points = [draftPoints[0]];
        } else {
            points = cloneShape(draftPoints);
        }
        stopDrawing(false);
        activeShape = { unitId: null, shape_type: type, shape_points: points, radius: radius };
        btnSave.disabled = false;
        setFineTuneEnabled(true);
        renderActive();
        codeInput.focus();
    }

    codeInput.addEventListener('input', function () { if (activeShape) { renderActive(); } });

    btnNew.addEventListener('click', startDrawing);
    btnCancel.addEventListener('click', function () { stopDrawing(false); clearSelection(); });
    btnFinish.addEventListener('click', finishDraft);
    shapeSelect.addEventListener('change', function () { hint.textContent = drawing ? hintFor(shapeSelect.value) : hint.textContent; });

    // Fine-tuning (see the user's explicit ask: a drawn shape should be nudgeable and
    // resizable, not just delete-and-redraw-from-scratch) — acts on activeShape regardless of
    // whether it came from a fresh draft or from clicking an existing unit, see activeShape's
    // own doc comment above for why those two cases share this code.
    function nudge(dx, dy) {
        if (!activeShape) { return; }
        activeShape.shape_points = activeShape.shape_points.map(function (p) {
            return [Math.max(0, Math.min(CANVAS_W, p[0] + dx)), Math.max(0, Math.min(CANVAS_H, p[1] + dy))];
        });
        renderActive();
    }

    function scaleActive(factor) {
        if (!activeShape) { return; }
        if (activeShape.shape_type === 'circle') {
            activeShape.radius = Math.max(4, activeShape.radius * factor);
        } else {
            var c = centroid(activeShape.shape_points);
            activeShape.shape_points = activeShape.shape_points.map(function (p) {
                return [c[0] + (p[0] - c[0]) * factor, c[1] + (p[1] - c[1]) * factor];
            });
        }
        renderActive();
    }

    document.getElementById('btn-nudge-left').addEventListener('click', function () { nudge(-NUDGE_STEP, 0); });
    document.getElementById('btn-nudge-right').addEventListener('click', function () { nudge(NUDGE_STEP, 0); });
    document.getElementById('btn-nudge-up').addEventListener('click', function () { nudge(0, -NUDGE_STEP); });
    document.getElementById('btn-nudge-down').addEventListener('click', function () { nudge(0, NUDGE_STEP); });
    document.getElementById('btn-shrink').addEventListener('click', function () { scaleActive(1 / SCALE_STEP); });
    document.getElementById('btn-enlarge').addEventListener('click', function () { scaleActive(SCALE_STEP); });

    // One save path now, regardless of where activeShape came from: unitId null -> create,
    // unitId set -> update in place (possibly fine-tuned, possibly just a code/name change).
    btnSave.addEventListener('click', function () {
        if (!activeShape) { return; }

        var body = {
            unit_id: activeShape.unitId,
            unit_code: codeInput.value,
            shape_type: activeShape.shape_type,
            shape_points: activeShape.shape_points,
            radius: activeShape.radius,
        };

        btnSave.disabled = true;
        fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(body),
        }).then(function (res) {
            if (!res.ok) { throw new Error('save_failed'); }
            return res.json();
        }).then(function (saved) {
            units[saved.id] = { id: saved.id, unit_code: saved.unit_code, shape_type: saved.shape_type, shape_points: saved.shape_points, radius: saved.radius, occupant: (units[saved.id] || {}).occupant || null };
            draftPoints = [];
            selectUnit(saved.id);
        }).catch(function () {
            alert(<?= json_encode(t('admin.venue_unit_save_error')) ?>);
        }).finally(function () {
            btnSave.disabled = false;
        });
    });

    btnDelete.addEventListener('click', function () {
        if (!activeShape || activeShape.unitId === null) { return; }
        if (!confirm(<?= json_encode(t('admin.venue_unit_delete_confirm')) ?>)) { return; }

        var unitId = activeShape.unitId;
        fetch(deleteUrlBase + unitId + '/delete', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
        }).then(function (res) {
            if (!res.ok) { throw new Error('delete_failed'); }
            delete units[unitId];
            clearSelection();
        }).catch(function () {
            alert(<?= json_encode(t('admin.venue_unit_delete_error')) ?>);
        });
    });

    initialUnits.forEach(function (u) { units[u.id] = u; });
    renderUnits();

    // -------------------------------------------------------------------------------------
    // SVG import wizard — the answer to "drawing ~100 precise polygons by hand isn't
    // realistic" for a real mall's floor plan: trace it properly in a real vector editor
    // (Inkscape etc.) over a reference photo/PDF export from the mall, one object per unit,
    // export as plain SVG, upload it here. This walks the uploaded file's own DOM and extracts
    // one candidate shape per drawn path/rect/circle/ellipse/polygon/polyline — including ones
    // nested under Inkscape's usual per-layer <g transform="..."> groups, resolved via the
    // browser's own getCTM() rather than any custom matrix math — and offers a code for each
    // from whichever of these the element carries (first match wins):
    //   1. a data-sofiago-id (or data-sofiago-code) attribute — set this in Inkscape's XML
    //      editor when you want a code that isn't a legal SVG id (spaces, etc.);
    //   2. its own id attribute, UNLESS it looks like one of Inkscape's own auto-generated ids
    //      (e.g. "path1234", "rect56") — those carry no real information, so they're treated as
    //      absent and the code is left blank for the admin to fill in.
    // Nothing is sent to the server until "Импортировать" — every candidate is first shown for
    // review (code editable, individually includable/excludable) exactly like a spreadsheet
    // import would, since a stray decorative shape (a drawn icon, a border) is easy to end up
    // with alongside the real units and there's no reliable way to guess which is which here.
    // -------------------------------------------------------------------------------------

    var importInput = document.getElementById('svg-import-input');
    var btnImportSvg = document.getElementById('btn-import-svg');
    var importPanel = document.getElementById('import-review');
    var importTitle = document.getElementById('import-review-title');
    var importList = document.getElementById('import-review-list');
    var importLayer = document.getElementById('import-layer');
    var btnImportCommit = document.getElementById('btn-import-commit');
    var btnImportCancel = document.getElementById('btn-import-cancel');

    var SHAPE_LABELS = {
        rectangle: <?= json_encode(t('admin.venue_shape_rectangle')) ?>,
        circle: <?= json_encode(t('admin.venue_shape_circle')) ?>,
        polygon: <?= json_encode(t('admin.venue_shape_polygon')) ?>,
    };

    var importCandidates = []; // {shape_type, shape_points, radius, code, include}
    var importSourceSize = null; // {width, height} — the uploaded file's own canvas, becomes this floor's new canvas_width/height on commit
    var importSelectedIndex = null;

    // Inkscape (and most tools) auto-name every object "<tagname><number>" unless the user
    // explicitly renames it — that pattern carries no real information, so an id matching it is
    // treated the same as no id at all rather than offered as a suggested unit code.
    var AUTO_ID_RE = /^(path|rect|circle|ellipse|polygon|polyline|line|use|g)[-_]?\d+$/i;

    function suggestedCodeFor(el) {
        var custom = el.getAttribute('data-sofiago-id') || el.getAttribute('data-sofiago-code');
        if (custom) { return custom; }
        var id = el.getAttribute('id');
        if (id && !AUTO_ID_RE.test(id)) { return id; }
        return null;
    }

    function svgPointsToCanvas(svgRoot, ctm, localPoints) {
        if (!ctm) { return localPoints; }
        return localPoints.map(function (p) {
            var pt = svgRoot.createSVGPoint();
            pt.x = p[0];
            pt.y = p[1];
            var transformed = pt.matrixTransform(ctm);
            return [transformed.x, transformed.y];
        });
    }

    /**
     * Parses raw SVG file contents into {width, height, shapes}. Runs the markup through a
     * live, off-screen DOM element rather than a detached parser — getCTM()/getPointAtLength()
     * (used to resolve nested group transforms and flatten curved paths respectively) are
     * native SVGGeometryElement methods with no JS-library equivalent worth pulling in, but
     * they only work on elements actually connected to the document.
     */
    function parseSvgFile(text) {
        // Cheap defense-in-depth against a malicious upload executing script in this admin's
        // own session — this file is expected to be something the admin (or a colleague) drew
        // in Inkscape, not untrusted input, but stripping <script> and inline event-handler
        // attributes before it ever becomes live DOM costs nothing and closes the obvious hole.
        var cleaned = text
            .replace(/<script[\s\S]*?<\/script>/gi, '')
            .replace(/\son\w+\s*=\s*"[^"]*"/gi, '')
            .replace(/\son\w+\s*=\s*'[^']*'/gi, '');

        var container = document.createElement('div');
        container.style.cssText = 'position:absolute;left:-99999px;top:-99999px;width:1px;height:1px;overflow:hidden;';
        document.body.appendChild(container);
        container.innerHTML = cleaned;

        var svgRoot = container.querySelector('svg');
        if (!svgRoot) {
            document.body.removeChild(container);
            return null;
        }

        var width, height;
        var viewBox = svgRoot.getAttribute('viewBox');
        if (viewBox) {
            var parts = viewBox.trim().split(/[\s,]+/).map(Number);
            width = parts[2];
            height = parts[3];
        } else {
            width = parseFloat(svgRoot.getAttribute('width')) || CANVAS_W;
            height = parseFloat(svgRoot.getAttribute('height')) || CANVAS_H;
        }

        var shapes = [];
        container.querySelectorAll('path, rect, polygon, polyline, circle, ellipse').forEach(function (element) {
            var tag = element.tagName.toLowerCase();
            var code = suggestedCodeFor(element);
            var ctm;
            try {
                ctm = element.getCTM();
            } catch (e) {
                ctm = null;
            }

            try {
                if (tag === 'circle') {
                    var cx = parseFloat(element.getAttribute('cx') || '0'), cy = parseFloat(element.getAttribute('cy') || '0');
                    var r = parseFloat(element.getAttribute('r') || '0');
                    if (r <= 0) { return; }
                    var center = svgPointsToCanvas(svgRoot, ctm, [[cx, cy]])[0];
                    var scale = ctm ? Math.sqrt(Math.abs(ctm.a * ctm.d - ctm.b * ctm.c)) : 1;
                    shapes.push({ shape_type: 'circle', shape_points: [center], radius: r * scale, code: code });
                } else if (tag === 'ellipse') {
                    var ecx = parseFloat(element.getAttribute('cx') || '0'), ecy = parseFloat(element.getAttribute('cy') || '0');
                    var rx = parseFloat(element.getAttribute('rx') || '0'), ry = parseFloat(element.getAttribute('ry') || '0');
                    if (rx <= 0 || ry <= 0) { return; }
                    var epts = [];
                    for (var i = 0; i < 32; i++) {
                        var angle = (i / 32) * Math.PI * 2;
                        epts.push([ecx + rx * Math.cos(angle), ecy + ry * Math.sin(angle)]);
                    }
                    shapes.push({ shape_type: 'polygon', shape_points: svgPointsToCanvas(svgRoot, ctm, epts), radius: null, code: code });
                } else if (tag === 'rect') {
                    var rx0 = parseFloat(element.getAttribute('x') || '0'), ry0 = parseFloat(element.getAttribute('y') || '0');
                    var rw = parseFloat(element.getAttribute('width') || '0'), rh = parseFloat(element.getAttribute('height') || '0');
                    if (rw <= 0 || rh <= 0) { return; }
                    var rpts = [[rx0, ry0], [rx0 + rw, ry0], [rx0 + rw, ry0 + rh], [rx0, ry0 + rh]];
                    shapes.push({ shape_type: 'polygon', shape_points: svgPointsToCanvas(svgRoot, ctm, rpts), radius: null, code: code });
                } else if (tag === 'polygon' || tag === 'polyline') {
                    var raw = (element.getAttribute('points') || '').trim();
                    if (!raw) { return; }
                    var nums = raw.split(/[\s,]+/).map(Number);
                    var ppts = [];
                    for (var j = 0; j + 1 < nums.length; j += 2) { ppts.push([nums[j], nums[j + 1]]); }
                    if (ppts.length < 3) { return; }
                    shapes.push({ shape_type: 'polygon', shape_points: svgPointsToCanvas(svgRoot, ctm, ppts), radius: null, code: code });
                } else if (tag === 'path') {
                    var total = element.getTotalLength ? element.getTotalLength() : 0;
                    if (!total || total <= 0) { return; }
                    var steps = Math.max(8, Math.min(200, Math.ceil(total / 8)));
                    var dpts = [];
                    for (var k = 0; k < steps; k++) {
                        var pt = element.getPointAtLength((k / steps) * total);
                        dpts.push([pt.x, pt.y]);
                    }
                    if (dpts.length < 3) { return; }
                    shapes.push({ shape_type: 'polygon', shape_points: svgPointsToCanvas(svgRoot, ctm, dpts), radius: null, code: code });
                }
            } catch (e) {
                // One malformed element (e.g. a <path> with no 'd') shouldn't sink the rest of
                // the import — it's just silently skipped, same as an element the selector
                // above didn't even try to interpret.
            }
        });

        document.body.removeChild(container);
        return { width: width, height: height, shapes: shapes };
    }

    function importShapeRow(candidate, index) {
        return shapeNode({ shape_type: candidate.shape_type, shape_points: candidate.shape_points, radius: candidate.radius }, 'import-shape');
    }

    function renderImportLayer() {
        importLayer.innerHTML = '';
        importCandidates.forEach(function (candidate, index) {
            if (!candidate.include) { return; }
            var g = el('g', { 'data-import-index': index });
            var node = importShapeRow(candidate, index);
            if (String(importSelectedIndex) === String(index)) { node.classList.add('import-selected'); }
            g.appendChild(node);
            g.addEventListener('click', function (evt) {
                evt.stopPropagation();
                importSelectedIndex = index;
                renderImportLayer();
                renderImportList();
                var row = importList.querySelector('tr[data-index="' + index + '"]');
                if (row) { row.scrollIntoView({ block: 'nearest' }); }
            });
            importLayer.appendChild(g);
        });
    }

    function renderImportList() {
        importList.innerHTML = '';
        importCandidates.forEach(function (candidate, index) {
            var tr = document.createElement('tr');
            tr.dataset.index = String(index);
            tr.className = (candidate.include ? '' : 'import-row-excluded') + (String(importSelectedIndex) === String(index) ? ' import-row-selected' : '');

            var tdCheck = document.createElement('td');
            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input';
            checkbox.checked = candidate.include;
            checkbox.addEventListener('change', function () {
                candidate.include = checkbox.checked;
                renderImportLayer();
                renderImportList();
            });
            tdCheck.appendChild(checkbox);

            var tdCode = document.createElement('td');
            var codeInput = document.createElement('input');
            codeInput.type = 'text';
            codeInput.maxLength = 40;
            codeInput.className = 'form-control form-control-sm';
            codeInput.placeholder = '#' + (index + 1);
            codeInput.value = candidate.code || '';
            codeInput.addEventListener('input', function () { candidate.code = codeInput.value; });
            tdCode.appendChild(codeInput);

            var tdType = document.createElement('td');
            tdType.className = 'small text-muted';
            tdType.textContent = SHAPE_LABELS[candidate.shape_type] || candidate.shape_type;

            tr.addEventListener('click', function (evt) {
                if (evt.target === checkbox || evt.target === codeInput) { return; }
                importSelectedIndex = index;
                renderImportLayer();
                renderImportList();
            });

            tr.appendChild(tdCheck);
            tr.appendChild(tdCode);
            tr.appendChild(tdType);
            importList.appendChild(tr);
        });
    }

    function openImportPanel(parsed) {
        importSourceSize = { width: parsed.width, height: parsed.height };
        importCandidates = parsed.shapes.map(function (s) {
            return { shape_type: s.shape_type, shape_points: s.shape_points, radius: s.radius, code: s.code, include: true };
        });
        importSelectedIndex = null;

        // Switch the live canvas to the imported file's own coordinate space right away so the
        // preview shapes line up 1:1 — committed or not, this is only a client-side viewBox
        // change until "Импортировать" actually persists it via Venue::setFloorCanvasSize().
        CANVAS_W = parsed.width;
        CANVAS_H = parsed.height;
        svg.setAttribute('viewBox', '0 0 ' + CANVAS_W + ' ' + CANVAS_H);

        importTitle.textContent = t_importFound(importCandidates.length);
        importPanel.hidden = false;
        renderImportLayer();
        renderImportList();
    }

    function t_importFound(count) {
        return <?= json_encode(t('admin.venue_import_found')) ?>.replace(':count', String(count));
    }

    function closeImportPanel() {
        importPanel.hidden = true;
        importCandidates = [];
        importSourceSize = null;
        importSelectedIndex = null;
        importLayer.innerHTML = '';
        importInput.value = '';
    }

    btnImportSvg.addEventListener('click', function () { importInput.click(); });

    importInput.addEventListener('change', function () {
        var file = importInput.files && importInput.files[0];
        if (!file) { return; }

        var reader = new FileReader();
        reader.onload = function () {
            var parsed = parseSvgFile(String(reader.result || ''));
            if (!parsed || parsed.shapes.length === 0) {
                alert(<?= json_encode(t('admin.venue_import_none_found')) ?>);
                importInput.value = '';
                return;
            }
            openImportPanel(parsed);
        };
        reader.onerror = function () {
            alert(<?= json_encode(t('admin.venue_import_read_error')) ?>);
            importInput.value = '';
        };
        reader.readAsText(file);
    });

    btnImportCancel.addEventListener('click', function () {
        // Restore whatever canvas size the floor actually had in the DB — openImportPanel()
        // only ever changed CANVAS_W/CANVAS_H/the viewBox locally for the preview.
        CANVAS_W = <?= $canvasWidth ?>;
        CANVAS_H = <?= $canvasHeight ?>;
        svg.setAttribute('viewBox', '0 0 ' + CANVAS_W + ' ' + CANVAS_H);
        closeImportPanel();
    });

    btnImportCommit.addEventListener('click', function () {
        var payload = {
            canvas_width: Math.round(importSourceSize.width),
            canvas_height: Math.round(importSourceSize.height),
            units: importCandidates.filter(function (c) { return c.include; }).map(function (c) {
                return { unit_code: c.code, shape_type: c.shape_type, shape_points: c.shape_points, radius: c.radius };
            }),
        };

        if (payload.units.length === 0) {
            alert(<?= json_encode(t('admin.venue_import_none_selected')) ?>);
            return;
        }

        btnImportCommit.disabled = true;
        fetch(importUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload),
        }).then(function (res) {
            if (!res.ok) { throw new Error('import_failed'); }
            return res.json();
        }).then(function (data) {
            var okCount = 0, failed = [];
            (data.results || []).forEach(function (result) {
                if (result.ok) {
                    okCount++;
                    units[result.id] = { id: result.id, unit_code: result.unit_code, shape_type: importCandidates[result.index].shape_type, shape_points: importCandidates[result.index].shape_points, radius: importCandidates[result.index].radius, occupant: null };
                } else {
                    failed.push((importCandidates[result.index].code || '#' + (result.index + 1)) + ': ' + result.error);
                }
            });

            closeImportPanel();
            renderUnits();

            var message = t_importDone(okCount);
            if (failed.length > 0) { message += '\n\n' + t_importFailed() + '\n' + failed.join('\n'); }
            alert(message);
        }).catch(function () {
            alert(<?= json_encode(t('admin.venue_import_save_error')) ?>);
        }).finally(function () {
            btnImportCommit.disabled = false;
        });
    });

    function t_importDone(count) {
        return <?= json_encode(t('admin.venue_import_done')) ?>.replace(':count', String(count));
    }

    function t_importFailed() {
        return <?= json_encode(t('admin.venue_import_failed')) ?>;
    }
})();
</script>
<?php endif; ?>
