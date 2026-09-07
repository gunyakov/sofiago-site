<?php

declare(strict_types=1);

namespace Sofiago\Models;

final class Listing
{
    private const PER_PAGE = 12;

    /** Fixed day order for the `opening_hours` JSON column — see decodeHours(). */
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * Decodes the `opening_hours` JSON column into a day => ['open' => 'HH:MM', 'close' =>
     * 'HH:MM'] map. A day missing from the result means closed all day — that's the only
     * "closed" representation, there's no separate flag. Used both by the dashboard form
     * (pre-filling on edit) and the public listing page (Opening Hours card).
     *
     * @return array<string, array{open: string, close: string}>
     */
    /**
     * Locale-aware description text for a listing row (needs description_en/bg/ru — i.e. a row
     * from a query that did `l.*` or otherwise selected them). description_en is the only one
     * guaranteed non-empty (see schema.sql's column comment), so it's the fallback whenever the
     * requested locale's own column is empty or NULL — including for a listing nobody has
     * translated into bg/ru yet, and for a locale that isn't 'bg'/'ru' at all.
     *
     * @param array<string, mixed> $listing
     */
    public static function descriptionFor(array $listing, ?string $locale = null): string
    {
        $locale = $locale ?? locale();

        if (in_array($locale, ['bg', 'ru'], true) && !empty($listing['description_' . $locale])) {
            return (string) $listing['description_' . $locale];
        }

        return (string) ($listing['description_en'] ?? '');
    }

    public static function decodeHours(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $hours = [];
        foreach (self::DAYS as $day) {
            $entry = $decoded[$day] ?? null;
            if (is_array($entry) && !empty($entry['open']) && !empty($entry['close'])) {
                $hours[$day] = ['open' => (string) $entry['open'], 'close' => (string) $entry['close']];
            }
        }

        return $hours;
    }

    /**
     * Public catalog search. Only ever returns status='active' listings — pending/rejected/
     * expired ones are not for public eyes (the owner's dashboard, phase 3, will query
     * separately with the owner_id + all statuses).
     *
     * @param array{q?: string, category?: string, tag?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function search(array $filters, int $page = 1): array
    {
        [$where, $params] = self::buildWhere($filters);
        $offset = (max($page, 1) - 1) * self::PER_PAGE;

        // lat/lng re-selected at the end as COALESCE(own, venue's) — overwrites the raw l.lat/
        // l.lng that `l.*` already pulled in (PDO's assoc fetch keeps the last column when two
        // share a name). A mall shop placed via indoor_unit_id (see that column's doc comment in
        // schema.sql) has no GPS point of its own — this resolves it to its venue's, so the
        // /explore list's hover-to-center-the-map still has *something* to center on instead of
        // silently doing nothing for every listing inside a mall.
        $sql = "SELECT l.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                       (SELECT path FROM listing_media m WHERE m.listing_id = l.id ORDER BY is_cover DESC, sort_order ASC LIMIT 1) AS cover_path,
                       COALESCE(l.lat, venue.lat) AS lat,
                       COALESCE(l.lng, venue.lng) AS lng
                FROM listings l
                JOIN categories c ON c.id = l.category_id
                LEFT JOIN venue_units vu ON vu.id = l.indoor_unit_id
                LEFT JOIN venue_floors vf ON vf.id = vu.floor_id
                LEFT JOIN listings venue ON venue.id = vf.venue_listing_id
                WHERE {$where}
                ORDER BY l.published_at DESC
                LIMIT " . self::PER_PAGE . " OFFSET {$offset}";

        return db()->all($sql, $params);
    }

    /** @param array{q?: string, category?: string} $filters */
    public static function countSearch(array $filters): int
    {
        [$where, $params] = self::buildWhere($filters);

        // buildWhere() may reference c.slug (category filter) — join categories here too,
        // even though we don't select anything from it, or that reference dangles.
        return (int) db()->value(
            "SELECT COUNT(*) FROM listings l JOIN categories c ON c.id = l.category_id WHERE {$where}",
            $params
        );
    }

    public static function perPage(): int
    {
        return self::PER_PAGE;
    }

    /**
     * Shared SELECT list for markers()/randomFeatured() — small payload (id, slug, title,
     * coords, category icon, cover photo, rating) rather than the full search() row shape.
     * map_icon_path (null for most listings today) is the FlipMarker icon (vue-widgets/src/
     * flip-marker.js) for a listing with its own upload; category_slug lets the front end fall
     * back to that category's default icon (public/assets/img/category-icons/) otherwise.
     */
    private const MARKER_COLUMNS = "l.id, l.slug, l.title, l.lat, l.lng, l.map_icon_path, c.slug AS category_slug, c.icon AS category_icon,
               (SELECT path FROM listing_media m WHERE m.listing_id = l.id ORDER BY is_cover DESC, sort_order ASC LIMIT 1) AS cover_path,
               (SELECT ROUND(AVG(rating), 1) FROM comments cm WHERE cm.listing_id = l.id AND cm.status = 'approved') AS avg_rating,
               (SELECT COUNT(*) FROM comments cm WHERE cm.listing_id = l.id AND cm.status = 'approved') AS reviews_count";

    /**
     * Map markers for the whole active catalog (optionally filtered) — every matching row, no
     * cap (well, 500 as a sanity ceiling). This is what /explore's own map uses; the home page's
     * map uses randomFeatured() below instead — see that method's doc comment for why they're
     * two different queries rather than one with an optional cap.
     *
     * @param array{q?: string, category?: string, tag?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function markers(array $filters): array
    {
        [$where, $params] = self::buildWhere($filters);

        $sql = "SELECT " . self::MARKER_COLUMNS . "
                FROM listings l
                JOIN categories c ON c.id = l.category_id
                WHERE {$where} AND l.lat IS NOT NULL AND l.lng IS NOT NULL
                LIMIT 500";

        return db()->all($sql, $params);
    }

    /**
     * VIP-first random sample, capped at $limit — what the home page's map shows instead of
     * markers()'s full (potentially 1000+ row) result, which is what actually prompted this:
     * once the sofiago-flutter POI migration populated the catalog, the home page map went from
     * "a handful of pins" to "every point at once", user-reported as unusable. /explore keeps
     * showing the complete filtered set via markers() above — deliberately untouched, "the full
     * list by category is only on /explore" is the rule this method's cap doesn't apply to.
     *
     * Two queries, not one "ORDER BY is_vip DESC, RAND()": that ordering would still put every
     * VIP listing before every plain one *deterministically* whenever VIP count >= $limit worth
     * showing is fine, but would also mean plain listings only ever get picked in whatever
     * (semi-)consistent order RAND() breaks ties in among themselves — not what "randomly fill
     * the rest" means. Sampling VIP and plain separately (each ORDER BY RAND() LIMIT on its own)
     * makes both halves genuinely independent random draws.
     *
     * @param array{category?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function randomFeatured(array $filters, int $limit = 20): array
    {
        [$where, $params] = self::buildWhere($filters);
        $baseWhere = "{$where} AND l.lat IS NOT NULL AND l.lng IS NOT NULL";

        $vip = db()->all(
            "SELECT " . self::MARKER_COLUMNS . "
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             WHERE {$baseWhere} AND l.is_vip = 1
             ORDER BY RAND()
             LIMIT {$limit}",
            $params
        );

        $remaining = $limit - count($vip);
        if ($remaining <= 0) {
            return $vip;
        }

        // Exclude the VIP rows just drawn so the fill-in half can't redraw one of them —
        // named placeholders throughout (mixing named/positional markers in one PDO prepared
        // statement isn't allowed, and buildWhere() above already committed this query to named).
        $excludeSql = '';
        $fillParams = $params;
        foreach (array_column($vip, 'id') as $i => $id) {
            $excludeSql .= ($i === 0 ? ' AND l.id NOT IN (:exclude0' : ", :exclude{$i}");
            $fillParams["exclude{$i}"] = $id;
        }
        if ($vip !== []) {
            $excludeSql .= ')';
        }

        $fill = db()->all(
            "SELECT " . self::MARKER_COLUMNS . "
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             WHERE {$baseWhere} AND l.is_vip = 0{$excludeSql}
             ORDER BY RAND()
             LIMIT {$remaining}",
            $fillParams
        );

        return [...$vip, ...$fill];
    }

    /** @return array<string, mixed>|null */
    public static function findBySlug(string $slug): ?array
    {
        return db()->one(
            'SELECT l.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                    ci.name AS city_name
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             JOIN cities ci ON ci.id = l.city_id
             WHERE l.slug = ? AND l.status = "active"',
            [$slug]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function mediaFor(int $listingId): array
    {
        return db()->all(
            'SELECT * FROM listing_media WHERE listing_id = ? ORDER BY is_cover DESC, sort_order ASC',
            [$listingId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function amenitiesFor(int $listingId): array
    {
        return db()->all(
            'SELECT a.* FROM amenities a
             JOIN listing_amenities la ON la.amenity_id = a.id
             WHERE la.listing_id = ?
             ORDER BY a.name',
            [$listingId]
        );
    }

    /** @return array<int, array<string, mixed>> slug + updated_at for every active listing, for sitemap.xml. */
    public static function allActiveForSitemap(): array
    {
        return db()->all("SELECT slug, updated_at FROM listings WHERE status = 'active' ORDER BY updated_at DESC LIMIT 5000");
    }

    public static function incrementViews(int $listingId): void
    {
        db()->query('UPDATE listings SET views_count = views_count + 1 WHERE id = ?', [$listingId]);
    }

    // -------------------------------------------------------------------------------------
    // Admin moderation (phase 4): unlike search()/findBySlug() above, these ignore status and
    // ownership entirely — only Guards::requireAdmin() stands between a controller and these.
    // -------------------------------------------------------------------------------------

    private const MODERATION_STATUSES = ['pending', 'active', 'rejected', 'expired'];

    /** Rows per page on the moderation table — see forModeration()/countModeration(). Coarser than the public PER_PAGE (12): an admin scanning a list benefits from fewer clicks, and there are no photos-heavy cards here, just one table row each. */
    private const MODERATION_PER_PAGE = 30;

    /**
     * Listings in $status, owner name/email joined in, newest first — paginated and optionally
     * narrowed by $filters (see buildModerationWhere()). Added 2026-09-07 once the 'active' tab
     * alone passed 1200 rows and rendering the whole thing on one page with no way to search it
     * stopped being usable for an admin looking for one specific listing.
     *
     * @param array{q?: string, category?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function forModeration(string $status, array $filters = [], int $page = 1): array
    {
        if (!in_array($status, self::MODERATION_STATUSES, true)) {
            $status = 'pending';
        }

        [$where, $params] = self::buildModerationWhere('l.status = :status', ['status' => $status], $filters);
        $offset = (max($page, 1) - 1) * self::MODERATION_PER_PAGE;

        return db()->all(
            "SELECT l.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                    u.name AS owner_name, u.email AS owner_email,
                    (SELECT path FROM listing_media m WHERE m.listing_id = l.id ORDER BY is_cover DESC, sort_order ASC LIMIT 1) AS cover_path
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             JOIN users u ON u.id = l.user_id
             WHERE {$where}
             ORDER BY l.created_at DESC
             LIMIT " . self::MODERATION_PER_PAGE . " OFFSET {$offset}",
            $params
        );
    }

    /** Total rows matching $status + $filters — for the moderation page's pagination, not the tab badges (those stay unfiltered, see countsByStatus()). @param array{q?: string, category?: string} $filters */
    public static function countModeration(string $status, array $filters = []): int
    {
        if (!in_array($status, self::MODERATION_STATUSES, true)) {
            $status = 'pending';
        }

        [$where, $params] = self::buildModerationWhere('l.status = :status', ['status' => $status], $filters);

        return (int) db()->value(
            "SELECT COUNT(*) FROM listings l JOIN categories c ON c.id = l.category_id JOIN users u ON u.id = l.user_id WHERE {$where}",
            $params
        );
    }

    public static function moderationPerPage(): int
    {
        return self::MODERATION_PER_PAGE;
    }

    /**
     * Adds the moderation page's search box (matches title OR owner name/email — an admin often
     * knows who submitted something, not just what it's called) and category dropdown on top of
     * a base WHERE clause, the same way buildWhere() does for the public catalog. Kept separate
     * from buildWhere() rather than shared: that one hardcodes status='active' and has no notion
     * of an owner join, neither of which fits here.
     *
     * @param array<string, mixed> $baseParams
     * @param array{q?: string, category?: string} $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function buildModerationWhere(string $baseWhere, array $baseParams, array $filters): array
    {
        $where = $baseWhere;
        $params = $baseParams;

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (l.title LIKE :q1 OR u.name LIKE :q2 OR u.email LIKE :q3)';
            $params['q1'] = $params['q2'] = $params['q3'] = '%' . $q . '%';
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $where .= ' AND c.slug = :category';
            $params['category'] = $category;
        }

        return [$where, $params];
    }

    /** @return array<string, int> Count per status, for the moderation page's tab badges. */
    public static function countsByStatus(): array
    {
        $rows = db()->all('SELECT status, COUNT(*) AS n FROM listings GROUP BY status');
        $counts = array_fill_keys(self::MODERATION_STATUSES, 0);

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /**
     * The admin moderation page's "Истекают" tab: currently-*active* listings sliding into the
     * renewal window — not the same population as status='expired' (those already lapsed, and
     * get republished via approve()/"Опубликовать", not renewed). Soonest-to-expire first, since
     * those are the most urgent. This is the only place admin's "Продлить" button shows up —
     * see AdminController::renewListing() for the matching server-side gate.
     *
     * Not paginated (unlike forModeration() above) — this population is naturally capped by
     * RENEWAL_WINDOW_DAYS rather than growing with the whole catalog, so it's never had the
     * "1200 rows on one page" problem. Still takes $filters for a consistent search box across
     * every tab on the page, even though narrowing it is rarely needed here in practice.
     *
     * @param array{q?: string, category?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function forModerationExpiringSoon(array $filters = []): array
    {
        [$where, $params] = self::buildModerationWhere(
            "l.status = 'active' AND l.expires_at IS NOT NULL AND l.expires_at <= DATE_ADD(NOW(), INTERVAL " . self::RENEWAL_WINDOW_DAYS . ' DAY)',
            [],
            $filters
        );

        return db()->all(
            "SELECT l.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                    u.name AS owner_name, u.email AS owner_email,
                    (SELECT path FROM listing_media m WHERE m.listing_id = l.id ORDER BY is_cover DESC, sort_order ASC LIMIT 1) AS cover_path
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             JOIN users u ON u.id = l.user_id
             WHERE {$where}
             ORDER BY l.expires_at ASC",
            $params
        );
    }

    public static function countExpiringSoon(): int
    {
        return (int) db()->value(
            "SELECT COUNT(*) FROM listings
             WHERE status = 'active' AND expires_at IS NOT NULL
               AND expires_at <= DATE_ADD(NOW(), INTERVAL " . self::RENEWAL_WINDOW_DAYS . " DAY)"
        );
    }

    /** @return array<string, mixed>|null Any listing regardless of status/owner — admin-only lookup. */
    public static function findAny(int $id): ?array
    {
        return db()->one(
            'SELECT l.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                    ci.name AS city_name, u.name AS owner_name, u.email AS owner_email
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             JOIN cities ci ON ci.id = l.city_id
             JOIN users u ON u.id = l.user_id
             WHERE l.id = ?',
            [$id]
        );
    }

    /** Listings live for this many days once (re)published — see approve()/isRenewable(). */
    public const LIFETIME_DAYS = 30;

    /** The renew button/email-warning window: how many days before expiry either kicks in. */
    public const RENEWAL_WINDOW_DAYS = 3;

    /**
     * Publishes a pending/rejected/expired listing: status -> active, sets published_at on
     * first approval, and (re)starts the 30-day expiry clock from now. Also the underlying
     * implementation of "renew" (self-service by the owner, or admin bypassing the window) —
     * publishing and renewing are the same operation on this schema, just reached from two
     * different guarded controller actions. See cron/midnight.php for what acts on expires_at.
     */
    public static function approve(int $id): void
    {
        db()->query(
            "UPDATE listings SET status = 'active', rejection_reason = NULL,
                    published_at = COALESCE(published_at, NOW()),
                    expires_at = DATE_ADD(NOW(), INTERVAL " . self::LIFETIME_DAYS . " DAY),
                    expiry_notified_at = NULL
             WHERE id = ?",
            [$id]
        );
    }

    public static function reject(int $id, string $reason): void
    {
        db()->query(
            "UPDATE listings SET status = 'rejected', rejection_reason = ? WHERE id = ?",
            [$reason !== '' ? $reason : null, $id]
        );
    }

    /** Reassigns a listing's owner — used by OwnershipClaim::approve() once an admin approves a "this is my object" request. */
    public static function transferOwner(int $id, int $newUserId): void
    {
        db()->update('listings', ['user_id' => $newUserId], 'id = :id', ['id' => $id]);
    }

    /**
     * Whether a "Продлить" button should work for $listing: already expired, or inside the
     * last RENEWAL_WINDOW_DAYS days before expiry. Used by both the owner's self-service
     * "ещё актуально" button and admin's renew action (AdminController::renewListing()) —
     * admin renewal is gated by exactly the same window, on purpose: renewing and publishing
     * are different actions (see the "Истекают" tab), an active listing with weeks left simply
     * has no renew button anywhere, admin included.
     *
     * @param array<string, mixed> $listing
     */
    public static function isRenewable(array $listing): bool
    {
        if ($listing['status'] === 'expired') {
            return true;
        }

        if ($listing['status'] !== 'active' || empty($listing['expires_at'])) {
            return false;
        }

        $windowStart = time() + self::RENEWAL_WINDOW_DAYS * 86400;

        return strtotime((string) $listing['expires_at']) <= $windowStart;
    }

    // -------------------------------------------------------------------------------------
    // Expiry (cron/midnight.php, run nightly): hide listings whose time is up, and warn
    // owners RENEWAL_WINDOW_DAYS out so they have a chance to click "Продлить" first.
    // -------------------------------------------------------------------------------------

    /** Flips overdue active listings to 'expired' (they stop showing in the public catalog/map). */
    public static function expireOverdue(): int
    {
        return db()->query(
            "UPDATE listings SET status = 'expired'
             WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at <= NOW()"
        )->rowCount();
    }

    /**
     * Active listings entering the renewal window that haven't been warned about it yet —
     * owner name/email joined in so the cron can email them directly.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function findExpiringForNotification(): array
    {
        return db()->all(
            "SELECT l.id, l.title, l.expires_at, u.name AS owner_name, u.email AS owner_email, u.locale AS owner_locale
             FROM listings l
             JOIN users u ON u.id = l.user_id
             WHERE l.status = 'active'
               AND l.expires_at IS NOT NULL
               AND l.expires_at <= DATE_ADD(NOW(), INTERVAL " . self::RENEWAL_WINDOW_DAYS . " DAY)
               AND l.expires_at > NOW()
               AND l.expiry_notified_at IS NULL"
        );
    }

    public static function markExpiryNotified(int $id): void
    {
        db()->query('UPDATE listings SET expiry_notified_at = NOW() WHERE id = ?', [$id]);
    }

    // -------------------------------------------------------------------------------------
    // Owner-facing (phase 3): create/edit/delete a listing, list "my listings" across every
    // status. Public search()/findBySlug() above never surface anything but status='active'.
    // -------------------------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> Every listing owned by $userId, any status. */
    public static function forOwner(int $userId): array
    {
        return db()->all(
            "SELECT l.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                    (SELECT path FROM listing_media m WHERE m.listing_id = l.id ORDER BY is_cover DESC, sort_order ASC LIMIT 1) AS cover_path
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             WHERE l.user_id = ?
             ORDER BY l.created_at DESC",
            [$userId]
        );
    }

    /** @return array<string, mixed>|null Only returns the row if $userId actually owns it. */
    public static function findOwned(int $id, int $userId): ?array
    {
        return db()->one(
            'SELECT l.*, c.slug AS category_slug FROM listings l JOIN categories c ON c.id = l.category_id
             WHERE l.id = ? AND l.user_id = ?',
            [$id, $userId]
        );
    }

    /**
     * @param array{title: string, category_id: int, description_en: string, description_bg: string,
     *   description_ru: string, address: string,
     *   district: string, postal_code: string, lat: ?float, lng: ?float, phone: string,
     *   website: string, email: string, facebook_url: string, instagram_url: string,
     *   twitter_url: string, linkedin_url: string} $data
     */
    public static function create(int $userId, int $cityId, array $data): int
    {
        $data['user_id'] = $userId;
        $data['city_id'] = $cityId;
        $data['slug'] = self::generateUniqueSlug($data['title']);
        $data['status'] = 'pending';

        return (int) db()->insert('listings', $data);
    }

    /** @param array<string, mixed> $data Same shape as create(), minus user_id/city_id/status/slug. */
    public static function updateOwned(int $id, array $data): void
    {
        db()->update('listings', $data, 'id = :id', ['id' => $id]);
    }

    public static function deleteOwned(int $id): void
    {
        // listing_media/listing_amenities/listing_tags/favorites all cascade via FK — but the
        // actual image *files* on disk don't delete themselves, caller does that first.
        db()->delete('listings', 'id = ?', [$id]);
    }

    private static function generateUniqueSlug(string $title): string
    {
        $base = self::slugify($title);
        $slug = $base;
        $i = 2;

        while (db()->value('SELECT 1 FROM listings WHERE slug = ?', [$slug]) !== null) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    /**
     * Cyrillic (Bulgarian/Russian) -> Latin map for slugs. NOT iconv('UTF-8', 'ASCII//TRANSLIT',
     * ...): on this system (and plenty of others — it depends on the libc's locale data) that
     * turns every Cyrillic character into a literal '?', which the old code then stripped
     * entirely — so any two Cyrillic titles/tags collapsed to the same empty-string fallback
     * slug and collided (caught via a real "Duplicate entry" SQL error while testing tags).
     * Most listing titles here will be Bulgarian/Russian, so this has to actually work.
     */
    private const CYRILLIC_TO_LATIN = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sht',
        'ъ' => 'a', 'ь' => '', 'ю' => 'yu', 'я' => 'ya', 'ы' => 'y', 'э' => 'e',
    ];

    private static function slugify(string $title): string
    {
        $lower = mb_strtolower($title);
        $transliterated = strtr($lower, self::CYRILLIC_TO_LATIN);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $transliterated);
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'listing';
    }

    /** @param array<int, int> $amenityIds */
    public static function syncAmenities(int $listingId, array $amenityIds): void
    {
        db()->delete('listing_amenities', 'listing_id = ?', [$listingId]);

        foreach (array_unique($amenityIds) as $amenityId) {
            db()->insert('listing_amenities', ['listing_id' => $listingId, 'amenity_id' => (int) $amenityId]);
        }
    }

    /**
     * @param array<int, int> $tagIds Already filtered against Tag::validIds() by the caller
     *   (ListingManageController::syncAmenitiesAndTags()) — this method itself doesn't
     *   re-validate, same division of responsibility as syncAmenities() above. No row is ever
     *   inserted into `tags` here (unlike the free-text version this replaced) — the vocabulary
     *   only grows via database/schema.sql's seed, see Tag's doc comment.
     */
    public static function syncTags(int $listingId, array $tagIds): void
    {
        db()->delete('listing_tags', 'listing_id = ?', [$listingId]);

        foreach (array_unique($tagIds) as $tagId) {
            db()->insert('listing_tags', ['listing_id' => $listingId, 'tag_id' => (int) $tagId]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function tagsFor(int $listingId): array
    {
        return db()->all(
            'SELECT t.* FROM tags t JOIN listing_tags lt ON lt.tag_id = t.id WHERE lt.listing_id = ? ORDER BY t.name',
            [$listingId]
        );
    }

    /**
     * Sets (or clears, passing null) the listing's custom map marker icon — a single column on
     * `listings`, unlike photos which live in listing_media as many-per-listing rows. Caller is
     * responsible for deleting the previous file on disk first if it's being replaced/removed;
     * see ListingManageController::handleIconUpload().
     */
    public static function setMapIcon(int $id, ?string $path): void
    {
        db()->update('listings', ['map_icon_path' => $path], 'id = :id', ['id' => $id]);
    }

    public static function addMedia(int $listingId, string $path, bool $isCover, int $sortOrder): void
    {
        db()->insert('listing_media', [
            'listing_id' => $listingId,
            'path' => $path,
            'is_cover' => $isCover ? 1 : 0,
            'sort_order' => $sortOrder,
        ]);
    }

    /** @return array<string, mixed>|null Only returns the row if it belongs to a listing owned by $userId. */
    public static function findOwnedMedia(int $mediaId, int $userId): ?array
    {
        return db()->one(
            'SELECT m.* FROM listing_media m JOIN listings l ON l.id = m.listing_id
             WHERE m.id = ? AND l.user_id = ?',
            [$mediaId, $userId]
        );
    }

    public static function deleteMedia(int $mediaId): void
    {
        db()->delete('listing_media', 'id = ?', [$mediaId]);
    }

    /**
     * @param array{q?: string, category?: string, tag?: string} $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function buildWhere(array $filters): array
    {
        $where = "l.status = 'active'";
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            // Two distinct placeholders, not :q reused twice — DB.php runs with
            // PDO::ATTR_EMULATE_PREPARES off (real server-side prepares), which MySQL's
            // protocol doesn't support binding the same named parameter to twice in one query
            // ("Invalid parameter number"). Caught live: any /explore?q=... search 500'd.
            // All three description columns, not just the current locale's — a Bulgarian search
            // term should still find a listing whose bg description matches even when the visitor
            // is browsing in English (description_en for that row may not mention it at all).
            $where .= ' AND (l.title LIKE :q1 OR l.description_en LIKE :q2 OR l.description_bg LIKE :q3 OR l.description_ru LIKE :q4)';
            $params['q1'] = $params['q2'] = $params['q3'] = $params['q4'] = '%' . $q . '%';
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $where .= ' AND c.slug = :category';
            $params['category'] = $category;
        }

        // Narrows within a category that groups several distinct source types under one
        // browsable category (e.g. 'nightlife' holds both bars and nightclubs, 'education'
        // holds schools/kindergartens/libraries) — added for the sofiago-flutter POI migration,
        // whose per-type toggles (Bars vs Nightclubs, Schools vs Libraries, …) need to fetch just
        // their own slice rather than the whole merged category. Reuses the existing
        // tags/listing_tags tables rather than adding a new column — every migrated row is
        // tagged with its source type (see the import script) purely for this filter.
        $tag = trim((string) ($filters['tag'] ?? ''));
        if ($tag !== '') {
            $where .= ' AND EXISTS (
                SELECT 1 FROM listing_tags lt JOIN tags t ON t.id = lt.tag_id
                WHERE lt.listing_id = l.id AND t.slug = :tag
            )';
            $params['tag'] = $tag;
        }

        return [$where, $params];
    }
}
