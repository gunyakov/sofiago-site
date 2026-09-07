<?php

declare(strict_types=1);

namespace Sofiago\Controllers\Api;

use Sofiago\Controllers\ListingManageController;
use Sofiago\Core\Upload;
use Sofiago\Middleware\Guards;
use Sofiago\Models\Amenity;
use Sofiago\Models\Category;
use Sofiago\Models\City;
use Sofiago\Models\Listing;
use Sofiago\Models\Tag;

/**
 * JSON listing management for sofiago-flutter's Account tab — "My Listings" list, create/
 * update/delete/renew, single-photo/icon removal, plus the category/amenity lookups its
 * add-listing form needs. Extends ListingManageController purely to reuse its `protected`
 * validate()/syncAmenitiesAndTags()/handleUploads()/handleIconUpload() (see that class's doc
 * comment for why those are protected) — a multipart POST from Dio populates $_POST/$_FILES
 * exactly like a browser form submit does, so those methods work completely unchanged here.
 * Every action below is otherwise a fresh override: bearer-token auth (Guards::requireApiAuth())
 * instead of session+CSRF, JSON responses instead of flash+redirect, owner-only (no admin path —
 * moderating other people's listings from the app isn't in scope).
 */
final class ListingApiController extends ListingManageController
{
    /**
     * GET /api/my-listings — every status, is_renewable flag added per row for the app's own
     * "renew" button. category_name overwritten with Category::label()'s translated string —
     * Listing::forOwner()'s own `c.name AS category_name` is the raw English categories.name
     * column, same as every web template works around by calling Category::label() itself
     * rather than printing category_name directly (see e.g. my-listings.tpl.php).
     */
    public function index(array $params): void
    {
        $user = Guards::requireApiAuth();

        $listings = array_map(static function (array $listing): array {
            $listing['is_renewable'] = Listing::isRenewable($listing);
            $listing['category_name'] = Category::label($listing);

            return $listing;
        }, Listing::forOwner((int) $user['id']));

        $this->json($listings);
    }

    /**
     * GET /api/listings/{id}/edit — one owned listing's full row plus what the plain row doesn't
     * carry (selected amenity ids, tag names, decoded opening_hours, media list) — everything
     * the app's edit form needs to pre-fill, mirroring ListingManageController::edit()'s view data.
     */
    public function edit(array $params): void
    {
        $user = Guards::requireApiAuth();
        $listing = Listing::findOwned((int) $params['id'], (int) $user['id']);
        if (!$listing) {
            abortJson(404, 'not_found');
        }

        $listingId = (int) $listing['id'];
        $listing['media'] = Listing::mediaFor($listingId);
        $listing['amenity_ids'] = array_map('intval', array_column(Listing::amenitiesFor($listingId), 'id'));
        $listing['tag_ids'] = array_map('intval', array_column(Listing::tagsFor($listingId), 'id'));
        $listing['opening_hours'] = Listing::decodeHours($listing['opening_hours'] ?? null);
        // Not otherwise in findOwned()'s row (no `c.name` joined) — the app's edit form itself
        // doesn't display it (pre-fills the category dropdown from category_id instead), but
        // filled in anyway for parity with index()'s response shape.
        $listing['category_name'] = Category::label($listing);

        $this->json($listing);
    }

    /** POST /api/listings — same field set/validation as the web form (ListingManageController::validate()). */
    public function store(array $params): void
    {
        $user = Guards::requireApiVerifiedUser();

        $data = $this->validate($errors);
        if ($errors !== []) {
            abortJson(422, 'validation_failed', ['errors' => $errors]);
        }

        $listingId = (int) Listing::create((int) $user['id'], City::defaultId(), $data);
        $this->syncAmenitiesAndTags($listingId, (int) $data['category_id']);
        $warnings = $this->collectUploadWarnings($listingId, null);

        $this->json(['id' => $listingId, 'warnings' => $warnings], 201);
    }

    /** POST /api/listings/{id} — editing always re-queues for moderation, same rule as the web (see ListingManageController::update()'s comment on why). */
    public function update(array $params): void
    {
        $user = Guards::requireApiAuth();
        $listing = Listing::findOwned((int) $params['id'], (int) $user['id']);
        if (!$listing) {
            abortJson(404, 'not_found');
        }

        $data = $this->validate($errors);
        if ($errors !== []) {
            abortJson(422, 'validation_failed', ['errors' => $errors]);
        }

        $data['status'] = 'pending';
        $data['rejection_reason'] = null;

        Listing::updateOwned((int) $listing['id'], $data);
        $this->syncAmenitiesAndTags((int) $listing['id'], (int) $data['category_id']);
        $warnings = $this->collectUploadWarnings((int) $listing['id'], $listing['map_icon_path'] ?? null);

        $this->json(['ok' => true, 'warnings' => $warnings]);
    }

    public function destroy(array $params): void
    {
        $user = Guards::requireApiAuth();
        $listing = Listing::findOwned((int) $params['id'], (int) $user['id']);
        if (!$listing) {
            abortJson(404, 'not_found');
        }

        foreach (Listing::mediaFor((int) $listing['id']) as $media) {
            Upload::deleteByPublicPath($media['path']);
        }
        Listing::deleteOwned((int) $listing['id']);

        $this->json(['ok' => true]);
    }

    /** POST /api/listings/{id}/renew — same Listing::isRenewable() window as the web self-service button. */
    public function renew(array $params): void
    {
        $user = Guards::requireApiAuth();
        $listing = Listing::findOwned((int) $params['id'], (int) $user['id']);
        if (!$listing) {
            abortJson(404, 'not_found');
        }
        if (!Listing::isRenewable($listing)) {
            abortJson(403, 'not_renewable_yet');
        }

        Listing::approve((int) $listing['id']);

        $this->json(['ok' => true]);
    }

    public function deleteMedia(array $params): void
    {
        $user = Guards::requireApiAuth();
        $media = Listing::findOwnedMedia((int) $params['mediaId'], (int) $user['id']);
        if (!$media) {
            abortJson(404, 'not_found');
        }

        Upload::deleteByPublicPath($media['path']);
        Listing::deleteMedia((int) $media['id']);

        $this->json(['ok' => true]);
    }

    public function deleteIcon(array $params): void
    {
        $user = Guards::requireApiAuth();
        $listing = Listing::findOwned((int) $params['id'], (int) $user['id']);
        if (!$listing) {
            abortJson(404, 'not_found');
        }

        if (!empty($listing['map_icon_path'])) {
            Upload::deleteByPublicPath($listing['map_icon_path']);
            Listing::setMapIcon((int) $listing['id'], null);
        }

        $this->json(['ok' => true]);
    }

    /** GET /api/categories — add/edit-listing form's category picker. Public, no auth needed. */
    public function categories(array $params): void
    {
        $this->json(array_map(
            static fn (array $c): array => ['id' => (int) $c['id'], 'slug' => $c['slug'], 'name' => Category::label($c)],
            Category::topLevel()
        ));
    }

    /** GET /api/amenities — add/edit-listing form's amenity checkboxes. Public, no auth needed. */
    public function amenities(array $params): void
    {
        $this->json(array_map(
            static fn (array $a): array => ['id' => (int) $a['id'], 'name' => Amenity::label($a)],
            Amenity::all()
        ));
    }

    /**
     * GET /api/tags — the add-listing form's tag picker, a fixed list the user selects from
     * (see Tag's doc comment for why this replaced free-text tags). Public, no auth needed.
     */
    /**
     * GET /api/tags — the full fixed vocabulary, each tag carrying category_ids (see
     * category_tags' doc comment in schema.sql). Deliberately not filtered by a ?category_id=
     * query param: sofiago-flutter's mall floor tag filter (VenueApi.tags()) needs the whole
     * catalog regardless of category since a single floor mixes shops/restaurants/cinemas — only
     * the add-listing form narrows by category, and it does that client-side against this same
     * response (see listing-form.tpl.php / listing_form_screen.dart), not via a second endpoint.
     */
    public function tags(array $params): void
    {
        $this->json(array_map(
            static fn (array $t): array => [
                'id' => (int) $t['id'],
                'slug' => $t['slug'],
                'name' => Tag::label($t),
                'category_ids' => $t['category_ids'],
            ],
            Tag::all()
        ));
    }

    /** @return array<int, string> */
    private function collectUploadWarnings(int $listingId, ?string $currentIconPath): array
    {
        $warnings = $this->handleUploads($listingId);
        $iconWarning = $this->handleIconUpload($listingId, $currentIconPath);
        if ($iconWarning !== null) {
            $warnings[] = $iconWarning;
        }

        return $warnings;
    }

    private function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
