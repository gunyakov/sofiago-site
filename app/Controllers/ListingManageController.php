<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

use Sofiago\Core\Upload;
use Sofiago\Middleware\Guards;
use Sofiago\Models\Amenity;
use Sofiago\Models\Category;
use Sofiago\Models\City;
use Sofiago\Models\Listing;
use Sofiago\Models\Tag;
use Sofiago\Models\Venue;

/**
 * Web (session/CSRF/flash+redirect) listing management. `Api\ListingApiController` extends
 * this — `validate()`/`syncAmenitiesAndTags()`/`handleUploads()`/`handleIconUpload()` below are
 * `protected` specifically so it can reuse them: they all just read $_POST/$_FILES and touch
 * the Listing model, nothing web-specific, so the only thing actually different for the API is
 * how a request authenticates and how a result gets reported back (JSON vs. flash+redirect).
 */
class ListingManageController
{
    protected const MAX_PHOTOS = 8;

    public function myListings(array $params): void
    {
        Guards::requireAuth();

        $listings = Listing::forOwner((int) auth()->id());
        $grouped = ['pending' => [], 'active' => [], 'expiring' => [], 'rejected' => [], 'expired' => []];

        foreach ($listings as $listing) {
            $grouped[$listing['status']][] = $listing;

            // 'expiring' isn't a real status — same rows as 'active', duplicated into their own
            // tab when inside the renewal window, mirroring the admin moderation page (see
            // my-listings.tpl.php's note up top for why the renew button lives only here now).
            if ($listing['status'] === 'active' && Listing::isRenewable($listing)) {
                $grouped['expiring'][] = $listing;
            }
        }

        echo view('dashboard/my-listings.tpl.php', [
            'title' => t('dashboard.my_listings.title'),
            'grouped' => $grouped,
        ], 'layout/dashboard.tpl.php');
    }

    public function create(array $params): void
    {
        Guards::requireVerifiedUser();

        echo view('dashboard/listing-form.tpl.php', [
            'title' => t('page.add_listing_title'),
            'listing' => null,
            'categories' => Category::topLevel(),
            'amenities' => Amenity::all(),
            'selectedAmenities' => [],
            'allTags' => Tag::all(),
            'selectedTagIds' => [],
            'venues' => Venue::all(),
            'selectedUnitId' => null,
            'selectedVenueId' => null,
            'selectedFloorId' => null,
            'pageStyles' => map_widget_styles(),
            'pageScripts' => map_widget_scripts() . gallery_widget_scripts(),
        ], 'layout/dashboard.tpl.php');
    }

    public function store(array $params): void
    {
        Guards::requireVerifiedUser();
        Guards::verifyCsrf();

        $data = $this->validate($errors);

        if ($errors !== []) {
            flash_errors($errors);
            app()->session->keepOld($_POST);
            redirect('/dashboard/listings/new');
        }

        $listingId = Listing::create((int) auth()->id(), City::defaultId(), $data);

        $this->syncAmenitiesAndTags($listingId, (int) $data['category_id']);
        $this->flashWarnings($this->handleUploads($listingId));
        $this->flashWarnings([$this->handleIconUpload($listingId, null)]);

        app()->session->flash('notice', t('listing_manage.submitted'));
        redirect('/dashboard/listings');
    }

    /**
     * Owner or admin — an admin can open (and, in update() below, save) any listing at all, not
     * just their own, so staff can fix an owner's listing directly instead of talking them
     * through it. Same form, same template, just a wider lookup for admins.
     *
     * @return array<string, mixed>|null
     */
    private function findEditable(int $id): ?array
    {
        return auth()->isAdmin() ? Listing::findAny($id) : Listing::findOwned($id, (int) auth()->id());
    }

    public function edit(array $params): void
    {
        Guards::requireAuth();

        $listing = $this->findEditable((int) $params['id']);

        if (!$listing) {
            abort(404, t('listing_manage.not_found'));
        }

        $selectedAmenities = array_column(Listing::amenitiesFor((int) $listing['id']), 'id');
        $selectedTagIds = array_map('intval', array_column(Listing::tagsFor((int) $listing['id']), 'id'));
        $selectedUnitId = $listing['indoor_unit_id'] !== null ? (int) $listing['indoor_unit_id'] : null;
        $unitLocation = $selectedUnitId !== null ? Venue::locateUnit($selectedUnitId) : null;

        echo view('dashboard/listing-form.tpl.php', [
            'title' => t('page.edit_listing_title'),
            'listing' => $listing,
            'media' => Listing::mediaFor((int) $listing['id']),
            'categories' => Category::topLevel(),
            'amenities' => Amenity::all(),
            'selectedAmenities' => $selectedAmenities,
            'allTags' => Tag::all(),
            'selectedTagIds' => $selectedTagIds,
            'venues' => Venue::all(),
            'selectedUnitId' => $selectedUnitId,
            'selectedVenueId' => $unitLocation['venue_listing_id'] ?? null,
            'selectedFloorId' => $unitLocation['floor_id'] ?? null,
            'pageStyles' => map_widget_styles(),
            'pageScripts' => map_widget_scripts() . gallery_widget_scripts(),
        ], 'layout/dashboard.tpl.php');
    }

    public function update(array $params): void
    {
        Guards::requireAuth();
        Guards::verifyCsrf();

        $isAdmin = auth()->isAdmin();
        $listing = $this->findEditable((int) $params['id']);

        if (!$listing) {
            abort(404, t('listing_manage.not_found'));
        }

        $data = $this->validate($errors, (int) $listing['id']);

        if ($errors !== []) {
            flash_errors($errors);
            app()->session->keepOld($_POST);
            redirect('/dashboard/listings/' . $listing['id'] . '/edit');
        }

        // Editing content sends the listing back to moderation, regardless of its current
        // status — active, rejected, whatever. This is deliberately separate from renew()
        // above: clicking "still relevant" on an unchanged, already-approved listing must NOT
        // re-queue it for review (see the user's spec), only actually changing the content
        // does. AdminController::approve() already resets expires_at from scratch on the next
        // approval, so nothing needs doing here about the expiry clock.
        // Exception: an admin editing someone else's listing on their behalf (support/fixing a
        // typo, say) does NOT re-queue it — the admin *is* the moderator, forcing them to
        // re-approve their own fix would just be busywork, and would wrongly unpublish an
        // otherwise-fine active listing while they do.
        if (!$isAdmin) {
            $data['status'] = 'pending';
            $data['rejection_reason'] = null;
        }

        Listing::updateOwned((int) $listing['id'], $data);
        $this->syncAmenitiesAndTags((int) $listing['id'], (int) $data['category_id']);
        $this->flashWarnings($this->handleUploads((int) $listing['id']));
        $this->flashWarnings([$this->handleIconUpload((int) $listing['id'], $listing['map_icon_path'] ?? null)]);

        app()->session->flash('notice', t($isAdmin ? 'listing_manage.saved_admin' : 'listing_manage.saved'));
        redirect($isAdmin ? '/admin/listings' : '/dashboard/listings');
    }

    public function destroy(array $params): void
    {
        Guards::requireAuth();
        Guards::verifyCsrf();

        $listing = Listing::findOwned((int) $params['id'], (int) auth()->id());

        if (!$listing) {
            abort(404, t('listing_manage.not_found'));
        }

        foreach (Listing::mediaFor((int) $listing['id']) as $media) {
            Upload::deleteByPublicPath($media['path']);
        }

        Listing::deleteOwned((int) $listing['id']);

        app()->session->flash('notice', t('listing_manage.deleted'));
        redirect('/dashboard/listings');
    }

    /** Self-service "ещё актуально" — only inside Listing::isRenewable()'s window, never earlier. */
    public function renew(array $params): void
    {
        Guards::requireAuth();
        Guards::verifyCsrf();

        $listing = Listing::findOwned((int) $params['id'], (int) auth()->id());

        if (!$listing) {
            abort(404, t('listing_manage.not_found'));
        }

        if (!Listing::isRenewable($listing)) {
            abort(403, t('listing_manage.not_renewable_yet'));
        }

        Listing::approve((int) $listing['id']);

        app()->session->flash('notice', t('listing_manage.renewed', ['days' => Listing::LIFETIME_DAYS]));
        redirect('/dashboard/listings');
    }

    /** Removes a listing's custom map icon, reverting it to the default pin. */
    public function deleteIcon(array $params): void
    {
        Guards::requireAuth();
        Guards::verifyCsrf();

        $listing = Listing::findOwned((int) $params['id'], (int) auth()->id());

        if (!$listing) {
            abort(404, t('listing_manage.not_found'));
        }

        if (!empty($listing['map_icon_path'])) {
            Upload::deleteByPublicPath($listing['map_icon_path']);
            Listing::setMapIcon((int) $listing['id'], null);
        }

        app()->session->flash('notice', t('listing_manage.icon_deleted'));
        redirect('/dashboard/listings/' . $listing['id'] . '/edit');
    }

    public function deleteMedia(array $params): void
    {
        Guards::requireAuth();
        Guards::verifyCsrf();

        $media = Listing::findOwnedMedia((int) $params['mediaId'], (int) auth()->id());

        if (!$media) {
            abort(404, t('listing_manage.file_not_found'));
        }

        Upload::deleteByPublicPath($media['path']);
        Listing::deleteMedia((int) $media['id']);

        app()->session->flash('notice', t('listing_manage.photo_deleted'));
        redirect('/dashboard/listings/' . $media['listing_id'] . '/edit');
    }

    /** @param array<int, string>|null $errors */
    /**
     * @param int|null $currentListingId The listing being edited (null when creating) — needed
     *   only to let indoor_unit_id's occupancy check exclude the listing's own current unit
     *   (otherwise every edit of an already-placed listing would look like "someone else already
     *   took this unit"). The actual race-condition-safe guarantee is the DB's
     *   uq_listings_indoor_unit constraint; this is just a friendlier pre-check.
     */
    protected function validate(?array &$errors, ?int $currentListingId = null): array
    {
        $errors = [];

        $title = trim((string) ($_POST['title'] ?? ''));
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $descriptionEn = trim((string) ($_POST['description_en'] ?? ''));
        $descriptionBg = trim((string) ($_POST['description_bg'] ?? ''));
        $descriptionRu = trim((string) ($_POST['description_ru'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $district = trim((string) ($_POST['district'] ?? ''));
        $postalCode = trim((string) ($_POST['postal_code'] ?? ''));
        $lat = trim((string) ($_POST['lat'] ?? ''));
        $lng = trim((string) ($_POST['lng'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $website = trim((string) ($_POST['website'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $facebook = trim((string) ($_POST['facebook_url'] ?? ''));
        $instagram = trim((string) ($_POST['instagram_url'] ?? ''));
        $twitter = trim((string) ($_POST['twitter_url'] ?? ''));
        $linkedin = trim((string) ($_POST['linkedin_url'] ?? ''));

        if (mb_strlen($title) < 3 || mb_strlen($title) > 160) {
            $errors[] = t('validation.title_length');
        }

        if (!Category::findById($categoryId)) {
            $errors[] = t('validation.category_required');
        }

        // English is the one description every listing must have — the site's primary audience
        // is foreign tourists (see schema.sql's column comment on description_en). bg/ru are
        // optional extras: no minimum length, just whatever the owner bothers to add.
        if (mb_strlen($descriptionEn) < 10) {
            $errors[] = t('validation.description_length');
        }

        if ($address === '') {
            $errors[] = t('validation.address_required');
        }

        $latValue = null;
        $lngValue = null;
        if ($lat !== '' || $lng !== '') {
            if (!is_numeric($lat) || !is_numeric($lng) || (float) $lat < -90 || (float) $lat > 90 || (float) $lng < -180 || (float) $lng > 180) {
                $errors[] = t('validation.coords_invalid');
            } else {
                $latValue = (float) $lat;
                $lngValue = (float) $lng;
            }
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = t('validation.email_field_invalid');
        }

        foreach (['website' => $website, 'facebook_url' => $facebook, 'instagram_url' => $instagram, 'twitter_url' => $twitter, 'linkedin_url' => $linkedin] as $field => $value) {
            if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                $errors[] = t('validation.url_invalid', ['field' => $field]);
            }
        }

        // Opening hours (optional, per-day — see dashboard/listing-form.tpl.php): a day with
        // both fields left blank means closed, one filled without the other is a mistake worth
        // flagging, and both filled must actually look like HH:MM (native <input type="time">
        // always sends that, but $_POST is attacker-controlled, so still checked here).
        $hours = [];
        foreach (Listing::DAYS as $day) {
            $open = trim((string) ($_POST['hours'][$day]['open'] ?? ''));
            $close = trim((string) ($_POST['hours'][$day]['close'] ?? ''));

            if ($open === '' && $close === '') {
                continue;
            }

            $validTime = '/^([01]\d|2[0-3]):[0-5]\d$/';
            if ($open === '' || $close === '' || !preg_match($validTime, $open) || !preg_match($validTime, $close)) {
                $errors[] = t('validation.hours_invalid', ['day' => t('days.' . $day)]);
                continue;
            }

            $hours[$day] = ['open' => $open, 'close' => $close];
        }

        // "This shop is inside a mall" (see the mall-map research thread) — optional, a plain
        // lat/lng listing leaves this null exactly as today. The app's picker only ever submits
        // an id that came from GET /api/venues/.../units, but that's still attacker-controlled
        // input by the time it reaches here, so it gets the same exists-and-free check the unit
        // picker itself uses to decide what's selectable (Venue::unitTakenByOther()).
        $indoorUnitId = trim((string) ($_POST['indoor_unit_id'] ?? ''));
        $indoorUnitIdValue = null;
        if ($indoorUnitId !== '' && $indoorUnitId !== '0') {
            $indoorUnitIdValue = (int) $indoorUnitId;
            if (Venue::unitTakenByOther($indoorUnitIdValue, $currentListingId)) {
                $errors[] = t('validation.indoor_unit_taken');
                $indoorUnitIdValue = null;
            }
        }

        // "This place has its own indoor map" (mall, museum, ...) — see listings.has_map's doc
        // comment in schema.sql. Plain owner-settable checkbox; it only queues the listing for
        // AdminVenueMapController, nothing here builds a map itself.
        $hasMap = !empty($_POST['has_map']) ? 1 : 0;

        return [
            'title' => $title,
            'category_id' => $categoryId,
            'description_en' => $descriptionEn,
            'description_bg' => $descriptionBg !== '' ? $descriptionBg : null,
            'description_ru' => $descriptionRu !== '' ? $descriptionRu : null,
            'address' => $address,
            'district' => $district,
            'postal_code' => $postalCode,
            'lat' => $latValue,
            'lng' => $lngValue,
            'indoor_unit_id' => $indoorUnitIdValue,
            'has_map' => $hasMap,
            'phone' => $phone,
            'website' => $website,
            'email' => $email,
            'facebook_url' => $facebook,
            'instagram_url' => $instagram,
            'twitter_url' => $twitter,
            'linkedin_url' => $linkedin,
            'opening_hours' => $hours !== [] ? json_encode($hours, JSON_UNESCAPED_UNICODE) : null,
        ];
    }

    /** @param int $categoryId The listing's own category_id (just-validated $data['category_id']) — decides which submitted tag ids are even legal, see Tag::validIdsForCategory(). */
    protected function syncAmenitiesAndTags(int $listingId, int $categoryId): void
    {
        $validIds = array_column(Amenity::all(), 'id');
        $submitted = array_map('intval', (array) ($_POST['amenities'] ?? []));
        Listing::syncAmenities($listingId, array_values(array_intersect($submitted, $validIds)));

        $submittedTagIds = array_map('intval', (array) ($_POST['tags'] ?? []));
        Listing::syncTags($listingId, Tag::validIdsForCategory($submittedTagIds, $categoryId));
    }

    /** @param array<int, string|null> $warnings */
    private function flashWarnings(array $warnings): void
    {
        foreach ($warnings as $warning) {
            if ($warning !== null) {
                app()->session->flash('warning', $warning);
            }
        }
    }

    /** @return array<int, string> Non-fatal per-photo warnings (e.g. one bad file among several good ones) — caller decides how to surface them (flash vs. JSON 'warnings'). */
    protected function handleUploads(int $listingId): array
    {
        $files = $_FILES['photos'] ?? null;
        $warnings = [];

        if (!$files || !is_array($files['name'])) {
            return $warnings;
        }

        $existingCount = count(Listing::mediaFor($listingId));
        $hasCover = $existingCount > 0;
        $count = count($files['name']);

        for ($i = 0; $i < $count && ($existingCount + $i) < static::MAX_PHOTOS; $i++) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];

            $path = Upload::storeImage($file, 'listings/' . $listingId, $error);

            if ($path !== null) {
                Listing::addMedia($listingId, $path, !$hasCover, $existingCount + $i);
                $hasCover = true;
            } elseif ($error !== null) {
                $warnings[] = $error;
            }
        }

        return $warnings;
    }

    /**
     * Single-slot custom map icon, separate from the multi-photo gallery above: at most one
     * file, no gallery semantics (cover/sort_order), stored directly on listings.map_icon_path.
     * $currentPath is the icon already on file (null on create, or on edit if none set yet) —
     * a new upload replaces it, deleting the old file so it doesn't linger orphaned on disk.
     */
    /** @return string|null A non-fatal warning (e.g. wrong aspect ratio) if the icon was skipped, null otherwise. */
    protected function handleIconUpload(int $listingId, ?string $currentPath): ?string
    {
        $file = $_FILES['map_icon'] ?? null;

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $path = Upload::storeIcon($file, 'listings/' . $listingId . '/icon', $error);

        if ($path === null) {
            return $error;
        }

        if ($currentPath) {
            Upload::deleteByPublicPath($currentPath);
        }

        Listing::setMapIcon($listingId, $path);

        return null;
    }
}
