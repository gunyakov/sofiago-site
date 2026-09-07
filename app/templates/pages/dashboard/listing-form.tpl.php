<?php
/**
 * @var array<string, mixed>|null $listing Null when adding, populated when editing.
 * @var array<int, array<string, mixed>> $categories
 * @var array<int, array<string, mixed>> $amenities
 * @var array<int, int> $selectedAmenities
 * @var array<int, array<string, mixed>> $allTags Fixed vocabulary (Tag::all()) — see Tag's doc comment for why this isn't free text.
 * @var array<int, int> $selectedTagIds
 * @var array<int, array<string, mixed>> $venues Every mall (Venue::all()) — for the "located inside a mall?" picker.
 * @var int|null $selectedUnitId This listing's current indoor_unit_id, if any.
 * @var int|null $selectedVenueId Which venue $selectedUnitId belongs to (Venue::locateUnit()) — pre-selects the cascade below on edit.
 * @var int|null $selectedFloorId Which floor $selectedUnitId belongs to — same as above.
 */
$isEdit = $listing !== null;
$action = $isEdit ? url('/dashboard/listings/' . $listing['id']) : url('/dashboard/listings');

// Opening hours: prefer a just-failed submission's own values (nested old() — see old_all())
// over the stored listing (edit), over empty (create/first load).
$oldHours = old_all()['hours'] ?? null;
if ($oldHours !== null) {
    $hours = $oldHours;
} elseif ($isEdit) {
    $hours = \Sofiago\Models\Listing::decodeHours($listing['opening_hours'] ?? null);
} else {
    $hours = [];
}
?>
<style>
    .gallery-dropzone { border: 2px dashed #dcdfe5; border-radius: 12px; padding: 24px; text-align: center; cursor: pointer; color: #6c757d; }
    .gallery-dropzone.dragover { border-color: #ff5a1f; color: #ff5a1f; }
    .gallery-preview { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
    .gallery-thumb { width: 110px; height: 110px; }
    .gallery-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .gallery-remove { position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border: 0; border-radius: 50%; background: rgba(0,0,0,.6); color: #fff; line-height: 1; }
    .existing-media { width: 110px; height: 110px; object-fit: cover; border-radius: 12px; }
    .map-icon-preview {
        object-fit: contain;
        background-image: linear-gradient(45deg, #e9ecef 25%, transparent 25%), linear-gradient(-45deg, #e9ecef 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #e9ecef 75%), linear-gradient(-45deg, transparent 75%, #e9ecef 75%);
        background-size: 16px 16px;
        background-position: 0 0, 0 8px, 8px -8px, -8px 0;
    }
</style>

<div class="container-xxl py-4">
    <h1 class="mb-4"><?= e($title) ?></h1>

    <?= partial('form-errors.tpl.php') ?>

    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><?= e(t('dashboard.form.basic_info')) ?></h6></div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-sm-6">
                        <label class="required fw-medium mb-2"><?= e(t('dashboard.form.name')) ?></label>
                        <input type="text" name="title" class="form-control" value="<?= $isEdit ? e($listing['title']) : old('title') ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="required fw-medium mb-2"><?= e(t('dashboard.form.category')) ?></label>
                        <select name="category_id" id="category-select" class="form-select" required>
                            <option value=""><?= e(t('dashboard.form.select_category')) ?></option>
                            <?php foreach ($categories as $category): ?>
                                <?php $selected = $isEdit ? (int) $listing['category_id'] === (int) $category['id'] : false; ?>
                                <option value="<?= (int) $category['id'] ?>" <?= $selected ? 'selected' : '' ?>><?= e(\Sofiago\Models\Category::label($category)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-12">
                        <label class="fw-medium mb-2"><?= e(t('dashboard.form.tags')) ?> <span class="fs-13 text-muted"><?= e(t('dashboard.form.tags_hint')) ?></span></label>
                        <!-- Fixed list, not free text (see Tag's doc comment) — a listing owner
                             picks from Tag::all() same as the amenities checkboxes below, so a
                             tag can always be relied on to be one of a known, stable set. Each
                             checkbox also carries which categories offer it (category_tags) —
                             tag-category-filter.js below shows only the ones matching whatever
                             category is currently selected, so a Nightlife listing is never shown
                             a Kids & Toys checkbox. Tag::validIdsForCategory() re-checks this
                             server-side too, since a hidden checkbox is still just CSS. -->
                        <div class="row gx-3 gy-2" id="tags-container">
                            <?php foreach ($allTags as $tag): ?>
                            <div class="col-auto tag-option" data-category-ids="<?= e(implode(',', $tag['category_ids'])) ?>">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tags[]" value="<?= (int) $tag['id'] ?>" id="tag-<?= (int) $tag['id'] ?>" <?= in_array((int) $tag['id'], $selectedTagIds, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="tag-<?= (int) $tag['id'] ?>"><?= e(\Sofiago\Models\Tag::label($tag)) ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p id="tags-empty-hint" class="fs-13 text-muted mb-0" hidden><?= e(t('dashboard.form.tags_none_for_category')) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function () {
            // Shows only the tag checkboxes whose category_tags include the selected category —
            // see the tags-container comment above. Unchecks anything hidden by a category
            // switch so a stale, no-longer-offered tag can't silently ride along in the submit.
            var categorySelect = document.getElementById('category-select');
            var tagOptions = document.querySelectorAll('#tags-container .tag-option');
            var emptyHint = document.getElementById('tags-empty-hint');

            /**
             * uncheckHidden=false on the initial page load, so an already-saved listing's
             * checked tags aren't silently cleared just because the page happened to render
             * with the category select's value already set to their category (they're always
             * consistent at that point anyway — the checkbox couldn't have been checked under a
             * different category to begin with). uncheckHidden=true on every actual category
             * change, since switching category for real should drop tags that no longer apply.
             */
            function refreshTagVisibility(uncheckHidden) {
                var categoryId = categorySelect.value;
                var anyVisible = false;

                tagOptions.forEach(function (option) {
                    var ids = (option.dataset.categoryIds || '').split(',').filter(Boolean);
                    var show = categoryId !== '' && ids.indexOf(categoryId) !== -1;
                    option.hidden = !show;
                    if (show) { anyVisible = true; }
                    if (!show && uncheckHidden) {
                        var checkbox = option.querySelector('input[type="checkbox"]');
                        if (checkbox) { checkbox.checked = false; }
                    }
                });

                emptyHint.hidden = categoryId === '' || anyVisible;
            }

            categorySelect.addEventListener('change', function () { refreshTagVisibility(true); });
            refreshTagVisibility(false);
        })();
        </script>

        <!-- "This place has its own indoor map" (listings.has_map) — the opposite direction from
             the cascade below: this listing IS a venue (mall, museum, ...), not a shop inside
             one. Turning it on doesn't draw anything by itself, it only queues the listing for
             AdminVenueMapController's "Карта на обект" screen, where staff build the actual
             floors/units. -->
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><?= e(t('dashboard.form.has_map')) ?></h6></div>
            <div class="card-body">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="has_map" value="1" id="has-map-toggle" <?= ($isEdit ? !empty($listing['has_map']) : (bool) old('has_map')) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="has-map-toggle"><?= e(t('dashboard.form.has_map_hint')) ?></label>
                </div>
            </div>
        </div>

        <!-- Venue/floor/unit cascade — see the mall-map research thread: a shop inside a mall is
             just this same listing plus indoor_unit_id, walked venue -> floor -> unit through
             the public /api/venues endpoints (VenueApiController) rather than a second content
             type. A plain 3-select cascade rather than a visual tap-a-polygon picker — the
             latter exists already in sofiago-flutter's MallFloorPainter; porting that rendering
             to this server-rendered page's vanilla JS is future work, not needed for this flow
             to work end to end (units are shown by their code, e.g. "GF-03", not by shape). -->
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><?= e(t('dashboard.form.in_mall')) ?></h6></div>
            <div class="card-body">
                <p class="fs-13 text-muted mb-3"><?= e(t('dashboard.form.in_mall_hint')) ?></p>
                <div class="row g-4">
                    <div class="col-sm-4">
                        <label class="fw-medium mb-2"><?= e(t('dashboard.form.venue')) ?></label>
                        <select id="venue-select" class="form-select">
                            <option value=""><?= e(t('dashboard.form.select_venue')) ?></option>
                            <?php foreach ($venues as $venue): ?>
                                <option value="<?= (int) $venue['id'] ?>"><?= e($venue['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="fw-medium mb-2"><?= e(t('dashboard.form.floor')) ?></label>
                        <select id="floor-select" class="form-select" disabled>
                            <option value=""><?= e(t('dashboard.form.select_floor')) ?></option>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="fw-medium mb-2"><?= e(t('dashboard.form.unit')) ?></label>
                        <!-- The only field actually submitted — venue/floor above just narrow
                             this dropdown's options, they aren't stored anywhere themselves. -->
                        <select id="unit-select" name="indoor_unit_id" class="form-select" disabled>
                            <option value=""><?= e(t('dashboard.form.select_unit')) ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function () {
            var venueSelect = document.getElementById('venue-select');
            var floorSelect = document.getElementById('floor-select');
            var unitSelect = document.getElementById('unit-select');
            var selectedVenueId = <?= $selectedVenueId !== null ? (int) $selectedVenueId : 'null' ?>;
            var selectedFloorId = <?= $selectedFloorId !== null ? (int) $selectedFloorId : 'null' ?>;
            var selectedUnitId = <?= $selectedUnitId !== null ? (int) $selectedUnitId : 'null' ?>;
            var floorPlaceholder = <?= json_encode(t('dashboard.form.select_floor')) ?>;
            var unitPlaceholder = <?= json_encode(t('dashboard.form.select_unit')) ?>;
            var unitTakenSuffix = <?= json_encode(t('dashboard.form.unit_taken')) ?>;
            var unitCurrentSuffix = <?= json_encode(t('dashboard.form.unit_current')) ?>;

            function resetSelect(select, placeholderText) {
                select.innerHTML = '';
                var opt = document.createElement('option');
                opt.value = '';
                opt.textContent = placeholderText;
                select.appendChild(opt);
            }

            function loadUnits(venueId, floorId) {
                resetSelect(unitSelect, unitPlaceholder);
                unitSelect.disabled = true;
                if (!floorId) return;

                fetch('/api/venues/' + venueId + '/floors/' + floorId + '/units')
                    .then(function (r) { return r.json(); })
                    .then(function (units) {
                        units.forEach(function (u) {
                            var isCurrent = selectedUnitId !== null && u.id === selectedUnitId;
                            var isTaken = u.listing_id !== null && !isCurrent;
                            var opt = document.createElement('option');
                            opt.value = u.id;
                            opt.textContent = (u.unit_code || ('#' + u.id)) + (isCurrent ? ' ' + unitCurrentSuffix : isTaken ? ' ' + unitTakenSuffix : '');
                            if (isTaken) opt.disabled = true;
                            if (isCurrent) opt.selected = true;
                            unitSelect.appendChild(opt);
                        });
                        unitSelect.disabled = false;
                    });
            }

            function loadFloors(venueId, preselectFloorId) {
                resetSelect(floorSelect, floorPlaceholder);
                resetSelect(unitSelect, unitPlaceholder);
                floorSelect.disabled = true;
                unitSelect.disabled = true;
                if (!venueId) return;

                fetch('/api/venues/' + venueId + '/floors')
                    .then(function (r) { return r.json(); })
                    .then(function (floors) {
                        floors.forEach(function (f) {
                            var opt = document.createElement('option');
                            opt.value = f.id;
                            opt.textContent = f.short_label + (f.name ? ' — ' + f.name : '');
                            floorSelect.appendChild(opt);
                        });
                        floorSelect.disabled = false;
                        if (preselectFloorId) {
                            floorSelect.value = preselectFloorId;
                            loadUnits(venueId, preselectFloorId);
                        }
                    });
            }

            venueSelect.addEventListener('change', function () {
                loadFloors(this.value || null, null);
            });
            floorSelect.addEventListener('change', function () {
                loadUnits(venueSelect.value, this.value || null);
            });

            if (selectedVenueId !== null) {
                venueSelect.value = selectedVenueId;
                loadFloors(selectedVenueId, selectedFloorId);
            }
        })();
        </script>

        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><?= e(t('dashboard.form.location')) ?></h6></div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-sm-8">
                        <label class="required fw-medium mb-2"><?= e(t('dashboard.form.address')) ?></label>
                        <input type="text" name="address" class="form-control" value="<?= $isEdit ? e($listing['address']) : old('address') ?>" required>
                    </div>
                    <div class="col-sm-4">
                        <label class="fw-medium mb-2"><?= e(t('dashboard.form.district')) ?></label>
                        <input type="text" name="district" class="form-control" value="<?= $isEdit ? e($listing['district']) : old('district') ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="fw-medium mb-2"><?= e(t('dashboard.form.postal_code')) ?></label>
                        <input type="text" name="postal_code" class="form-control" value="<?= $isEdit ? e($listing['postal_code']) : old('postal_code') ?>">
                    </div>
                    <div class="col-sm-12">
                        <label class="fw-medium mb-2"><?= e(t('dashboard.form.map_point')) ?> <span class="fs-13 text-muted"><?= e(t('dashboard.form.map_hint')) ?></span></label>
                        <div id="map-picker" class="rounded-4" style="height: 320px;"
                             data-lat="<?= $isEdit && $listing['lat'] ? e($listing['lat']) : '' ?>"
                             data-lng="<?= $isEdit && $listing['lng'] ? e($listing['lng']) : '' ?>"
                             data-lat-input="lat-input" data-lng-input="lng-input"></div>
                        <input type="hidden" id="lat-input" name="lat" value="<?= $isEdit ? e($listing['lat']) : '' ?>">
                        <input type="hidden" id="lng-input" name="lng" value="<?= $isEdit ? e($listing['lng']) : '' ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><?= e(t('dashboard.form.photos')) ?></h6></div>
            <div class="card-body">
                <?php if ($isEdit && !empty($media)): ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php foreach ($media as $item): ?>
                    <div class="position-relative">
                        <img src="<?= e($item['path']) ?>" class="existing-media" alt="">
                        <form method="post" action="<?= e(url('/dashboard/listings/' . $listing['id'] . '/media/' . $item['id'] . '/delete')) ?>" class="position-absolute top-0 end-0 m-1">
                            <?= csrf_field() ?>
                            <button type="submit" class="gallery-remove" title="<?= e(t('dashboard.form.remove_photo')) ?>">&times;</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="gallery-upload" data-remove-label="<?= e(t('dashboard.form.remove_photo')) ?>" data-photos-suffix="<?= e(t('dashboard.form.photos_suffix')) ?>">
                    <div class="gallery-dropzone">
                        <?= e(t('dashboard.form.dropzone')) ?>
                        <div class="gallery-hint small mt-1">0 / 8 <?= e(t('dashboard.form.photos_suffix')) ?></div>
                    </div>
                    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple hidden>
                    <div class="gallery-preview"></div>
                </div>
                <div class="form-text"><?= e(t('dashboard.form.photos_hint')) ?></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><?= e(t('dashboard.form.map_icon')) ?></h6></div>
            <div class="card-body">
                <?php if ($isEdit && !empty($listing['map_icon_path'])): ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <div class="position-relative">
                        <img src="<?= e($listing['map_icon_path']) ?>" class="existing-media map-icon-preview" alt="">
                        <form method="post" action="<?= e(url('/dashboard/listings/' . $listing['id'] . '/icon/delete')) ?>" class="position-absolute top-0 end-0 m-1">
                            <?= csrf_field() ?>
                            <button type="submit" class="gallery-remove" title="<?= e(t('dashboard.form.remove_icon')) ?>">&times;</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <input type="file" name="map_icon" accept="image/jpeg,image/png,image/webp" class="form-control">
                <div class="form-text"><?= e(t('dashboard.form.map_icon_hint')) ?></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><?= e(t('dashboard.form.details')) ?></h6></div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-sm-12">
                        <label class="required fw-medium mb-2"><?= e(t('dashboard.form.description_en')) ?></label>
                        <textarea name="description_en" class="form-control" rows="6" required><?= $isEdit ? e($listing['description_en']) : old('description_en') ?></textarea>
                        <div class="form-text"><?= e(t('dashboard.form.description_en_hint')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="fw-medium mb-2"><?= e(t('dashboard.form.description_bg')) ?></label>
                        <textarea name="description_bg" class="form-control" rows="4"><?= $isEdit ? e((string) $listing['description_bg']) : old('description_bg') ?></textarea>
                    </div>
                    <div class="col-sm-6">
                        <label class="fw-medium mb-2"><?= e(t('dashboard.form.description_ru')) ?></label>
                        <textarea name="description_ru" class="form-control" rows="4"><?= $isEdit ? e((string) $listing['description_ru']) : old('description_ru') ?></textarea>
                    </div>
                    <div class="col-sm-4">
                        <label class="fw-medium mb-2"><?= e(t('dashboard.form.phone')) ?></label>
                        <input type="text" name="phone" class="form-control" value="<?= $isEdit ? e($listing['phone']) : old('phone') ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="fw-medium mb-2"><?= e(t('dashboard.form.website')) ?></label>
                        <input type="text" name="website" class="form-control" placeholder="https://" value="<?= $isEdit ? e($listing['website']) : old('website') ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="fw-medium mb-2"><?= e(t('dashboard.form.email')) ?></label>
                        <input type="email" name="email" class="form-control" value="<?= $isEdit ? e($listing['email']) : old('email') ?>">
                    </div>
                    <div class="col-sm-12"><hr></div>
                    <div class="col-sm-6">
                        <label class="fw-medium mb-2">Facebook</label>
                        <input type="text" name="facebook_url" class="form-control" placeholder="https://facebook.com/..." value="<?= $isEdit ? e($listing['facebook_url']) : old('facebook_url') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="fw-medium mb-2">Instagram</label>
                        <input type="text" name="instagram_url" class="form-control" placeholder="https://instagram.com/..." value="<?= $isEdit ? e($listing['instagram_url']) : old('instagram_url') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="fw-medium mb-2">Twitter / X</label>
                        <input type="text" name="twitter_url" class="form-control" placeholder="https://x.com/..." value="<?= $isEdit ? e($listing['twitter_url']) : old('twitter_url') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="fw-medium mb-2">LinkedIn</label>
                        <input type="text" name="linkedin_url" class="form-control" placeholder="https://linkedin.com/..." value="<?= $isEdit ? e($listing['linkedin_url']) : old('linkedin_url') ?>">
                    </div>
                    <div class="col-sm-12"><hr></div>
                    <div class="col-12">
                        <div class="fw-medium text-dark mb-3"><?= e(t('dashboard.form.amenities')) ?></div>
                        <div class="row gx-3 gy-2">
                            <?php foreach ($amenities as $amenity): ?>
                            <div class="col-auto">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="amenities[]" value="<?= (int) $amenity['id'] ?>" id="amenity-<?= (int) $amenity['id'] ?>" <?= in_array((int) $amenity['id'], $selectedAmenities, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="amenity-<?= (int) $amenity['id'] ?>"><?= e(\Sofiago\Models\Amenity::label($amenity)) ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><?= e(t('dashboard.form.opening_hours')) ?> <span class="fs-13 text-muted fw-normal"><?= e(t('dashboard.form.optional')) ?></span></h6></div>
            <div class="card-body">
                <?php foreach (\Sofiago\Models\Listing::DAYS as $day): ?>
                <div class="row g-3 align-items-center mb-3">
                    <label class="col-sm-2 col-form-label fw-medium"><?= e(t('days.' . $day)) ?></label>
                    <div class="col-sm-5">
                        <input type="time" name="hours[<?= $day ?>][open]" class="form-control" value="<?= e($hours[$day]['open'] ?? '') ?>" placeholder="<?= e(t('dashboard.form.hours_open')) ?>">
                    </div>
                    <div class="col-sm-5">
                        <input type="time" name="hours[<?= $day ?>][close]" class="form-control" value="<?= e($hours[$day]['close'] ?? '') ?>" placeholder="<?= e(t('dashboard.form.hours_close')) ?>">
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="form-text"><?= e(t('dashboard.form.hours_hint')) ?></div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg"><?= $isEdit ? e(t('dashboard.form.submit_edit')) : e(t('dashboard.form.submit_new')) ?></button>
    </form>
</div>
