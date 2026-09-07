<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

use Sofiago\Core\Turnstile;
use Sofiago\Models\Amenity;
use Sofiago\Models\Category;
use Sofiago\Models\Comment;
use Sofiago\Models\Favorite;
use Sofiago\Models\Listing;
use Sofiago\Models\OwnershipClaim;
use Sofiago\Models\SchoolAdmissionScore;
use Sofiago\Models\Venue;

final class ListingController
{
    public function index(array $params): void
    {
        $filters = [
            'q' => (string) ($_GET['q'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $listings = Listing::search($filters, $page);
        $total = Listing::countSearch($filters);
        $pages = (int) ceil($total / Listing::perPage());

        // Half-map layout (see docs/liston-theme's listings-map.html / listings-map-grid-1.html
        // — same page, just two card styles): "list" is the default, matching that pair.
        $view = ($_GET['view'] ?? '') === 'grid' ? 'grid' : 'list';

        echo view('listings/index.tpl.php', [
            'title' => t('page.explore_title'),
            'listings' => $listings,
            'categories' => Category::topLevel(),
            'filters' => $filters,
            'view' => $view,
            'page' => $page,
            'pages' => max($pages, 1),
            'total' => $total,
            'favoriteIds' => auth()->check() ? Favorite::idsForUser((int) auth()->id()) : [],
            'pageStyles' => map_widget_styles(),
            'pageScripts' => map_widget_scripts(),
        ]);
    }

    public function show(array $params): void
    {
        $listing = Listing::findBySlug((string) $params['slug']);

        if (!$listing) {
            abort(404, t('listing.not_found'));
        }

        Listing::incrementViews((int) $listing['id']);

        $viewerId = auth()->check() ? (int) auth()->id() : null;
        $isFavorited = $viewerId !== null && Favorite::isFavorited($viewerId, (int) $listing['id']);
        // Drives the "This is my object" button (show.tpl.php): hidden entirely for the
        // current owner, shown disabled ("request pending") if they already asked once, a real
        // submit form otherwise. Both false for a guest — OwnershipClaimController itself still
        // enforces both rules server-side regardless of what the button looks like.
        $isOwner = $viewerId !== null && $viewerId === (int) $listing['user_id'];
        $hasPendingOwnershipClaim = $viewerId !== null && !$isOwner
            && OwnershipClaim::hasPending((int) $listing['id'], $viewerId);

        echo view('listings/show.tpl.php', [
            'title' => $listing['title'] . ' — SofiaGO',
            'metaDescription' => mb_substr(Listing::descriptionFor($listing), 0, 160),
            'listing' => $listing,
            'media' => Listing::mediaFor((int) $listing['id']),
            'amenities' => Listing::amenitiesFor((int) $listing['id']),
            // Empty array for every listing that isn't a school with profiled 7th-grade intake —
            // see SchoolAdmissionScore's doc comment. show.tpl.php only renders the card when non-empty.
            'admissionScores' => SchoolAdmissionScore::forListing((int) $listing['id']),
            // Null unless this listing occupies a unit inside a venue whose map an admin has
            // actually published — see Venue::objectMapFor()'s doc comment for why that check
            // lives there rather than here. Drives the "View on map" button below.
            'objectMap' => Venue::objectMapFor($listing['indoor_unit_id'] !== null ? (int) $listing['indoor_unit_id'] : null),
            'isFavorited' => $isFavorited,
            'isOwner' => $isOwner,
            'hasPendingOwnershipClaim' => $hasPendingOwnershipClaim,
            'hours' => Listing::decodeHours($listing['opening_hours'] ?? null),
            'comments' => Comment::approvedForListing((int) $listing['id']),
            'turnstileSiteKey' => Turnstile::siteKey(),
            'pageStyles' => map_widget_styles(),
            'pageScripts' => map_widget_scripts() . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>',
        ]);
    }

    /**
     * GET /api/listings — small JSON payload for the map island. `featured=1` (home page's map
     * only — see home.tpl.php/vue-widgets/src/map.js) switches to Listing::randomFeatured()'s
     * capped VIP-first sample instead of the full matching set /explore's own map still gets;
     * see that method's doc comment for why the home page needed this at all.
     */
    public function markers(array $params): void
    {
        $filters = [
            'q' => (string) ($_GET['q'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
            // Narrows within a category that groups several source types (see
            // Listing::buildWhere()'s doc comment) — only the sofiago-flutter app's per-type
            // toggles pass this, the web /explore filters never do.
            'tag' => (string) ($_GET['tag'] ?? ''),
        ];

        $featured = ($_GET['featured'] ?? '') === '1';

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            $featured ? Listing::randomFeatured($filters) : Listing::markers($filters),
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * GET /api/listings/{slug} — full listing detail as JSON, for sofiago-flutter's native
     * listing screen (no WebView: the app renders this itself, same data show.tpl.php's HTML
     * page shows a browser). Public/unauthenticated, same visibility rule as show()/markers()
     * (status='active' only, enforced inside findBySlug()).
     */
    public function showJson(array $params): void
    {
        $listing = Listing::findBySlug((string) $params['slug']);

        header('Content-Type: application/json; charset=utf-8');

        if (!$listing) {
            http_response_code(404);
            echo json_encode(['error' => 'not_found'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $listingId = (int) $listing['id'];
        $rating = db()->one(
            "SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS reviews_count
             FROM comments WHERE listing_id = ? AND status = 'approved'",
            [$listingId]
        );

        // Null unless this listing occupies a unit inside a venue whose map an admin has
        // actually published (see Venue::objectMapFor()'s doc comment) — sofiago-flutter only
        // draws its "View on map" button when this is present, so a shop inside an in-progress
        // venue never exposes raw/unfinished floor data to a visitor.
        $objectMap = Venue::objectMapFor($listing['indoor_unit_id'] !== null ? (int) $listing['indoor_unit_id'] : null);

        // Same "This is my object" button state as show()'s web view data, just bearer-token
        // based instead of session-based — see that method's comment.
        $viewerId = auth()->attemptBearer() ? (int) auth()->user()['id'] : null;
        $isOwner = $viewerId !== null && $viewerId === (int) $listing['user_id'];
        $hasPendingOwnershipClaim = $viewerId !== null && !$isOwner
            && OwnershipClaim::hasPending($listingId, $viewerId);

        // Empty array for every listing that isn't a school with profiled 7th-grade intake —
        // sofiago-flutter renders nothing when this is empty, same as show.tpl.php's web card.
        // Shaped as a list of year-blocks (newest first, same order SchoolAdmissionScore::
        // forListing() already sorts in) rather than a year-keyed object, so the client derives
        // its list of available years straight from the payload instead of hardcoding any — see
        // that method's doc comment for why every round 1-4 shows up per paralelka. Each round
        // is always present (1-4), with per-field null standing for "no data for this round/
        // gender" — a genuine recorded 0 comes through as 0, never coerced to null.
        $admissionScores = [];
        foreach (SchoolAdmissionScore::forListing($listingId) as $year => $paralelki) {
            $admissionScores[] = [
                'year' => $year,
                'paralelki' => array_values(array_map(static function (array $p): array {
                    $rounds = [];
                    for ($round = 1; $round <= 4; $round++) {
                        $r = $p['rounds'][$round] ?? null;
                        $rounds[(string) $round] = [
                            'boy_min' => $r['boy_min'] ?? null,
                            'boy_max' => $r['boy_max'] ?? null,
                            'girl_min' => $r['girl_min'] ?? null,
                            'girl_max' => $r['girl_max'] ?? null,
                        ];
                    }

                    return [
                        'class_number' => $p['class_number'],
                        'class_name' => $p['class_name'],
                        'quote' => $p['quote'],
                        'rounds' => $rounds,
                    ];
                }, $paralelki)),
            ];
        }

        echo json_encode([
            'id' => $listingId,
            'slug' => $listing['slug'],
            'title' => $listing['title'],
            // 'description' is resolved to whatever locale this request's Lang ended up in
            // (?lang=/cookie — same resolution the HTML pages use), for API clients that just
            // want to display something without doing their own fallback logic; the three raw
            // columns are also included below for a client (sofiago-flutter) that wants to pick
            // its own UI locale independent of this request's.
            'description' => Listing::descriptionFor($listing),
            'description_en' => $listing['description_en'],
            'description_bg' => $listing['description_bg'],
            'description_ru' => $listing['description_ru'],
            'address' => $listing['address'],
            'district' => $listing['district'],
            'postal_code' => $listing['postal_code'],
            'lat' => $listing['lat'],
            'lng' => $listing['lng'],
            'phone' => $listing['phone'],
            'website' => $listing['website'],
            'email' => $listing['email'],
            'facebook_url' => $listing['facebook_url'],
            'instagram_url' => $listing['instagram_url'],
            'twitter_url' => $listing['twitter_url'],
            'linkedin_url' => $listing['linkedin_url'],
            'category_slug' => $listing['category_slug'],
            'category_name' => Category::label($listing),
            'map_icon_path' => $listing['map_icon_path'],
            'opening_hours' => Listing::decodeHours($listing['opening_hours'] ?? null),
            'photos' => array_column(Listing::mediaFor($listingId), 'path'),
            'amenities' => array_map([Amenity::class, 'label'], Listing::amenitiesFor($listingId)),
            'avg_rating' => $rating['avg_rating'] ?? null,
            'reviews_count' => (int) ($rating['reviews_count'] ?? 0),
            'views_count' => (int) $listing['views_count'],
            'is_owner' => $isOwner,
            'has_pending_ownership_claim' => $hasPendingOwnershipClaim,
            'has_object_map' => $objectMap !== null,
            'object_map' => $objectMap !== null ? [
                'venue_id' => $objectMap['venue_listing_id'],
                'venue_slug' => $objectMap['venue_slug'],
                'venue_title' => $objectMap['venue_title'],
                'floor_id' => $objectMap['floor_id'],
                'unit_id' => (int) $listing['indoor_unit_id'],
            ] : null,
            'admission_scores' => $admissionScores,
        ], JSON_UNESCAPED_UNICODE);
    }
}
