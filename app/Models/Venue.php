<?php

declare(strict_types=1);

namespace Sofiago\Models;

/**
 * A venue (mall, museum, ...) is just a normal `listings` row — already an outdoor marker under
 * its own category — that happens to have floor plan rows in venue_floors/venue_units (see
 * database/schema.sql's doc comment on those tables, added for the mall-map research thread).
 * "Is this listing a venue?" is answered by EXISTS-ing against venue_floors, not a flag column,
 * so there's nothing to keep in sync if a venue's last floor is ever removed.
 *
 * Two independent flags gate that further, in sequence:
 *  - listings.has_map is the earlier-stage, owner-facing "I'd like a map" request — it only
 *    decides whether the listing shows up in AdminVenueMapController's queue for staff to start
 *    building floors/units. On its own it proves nothing about the map's state.
 *  - listings.map_published is the later, admin-only "staff have reviewed this and it's actually
 *    finished" sign-off — flipped by hand once the floors/units are done, never automatically.
 * The public methods below (all(), find(), objectMapFor()) require both, on top of the
 * venue_floors EXISTS check — a venue an admin has only started sketching stays invisible to the
 * app and to every listing form's unit-picker cascade, and no shop inside it shows a "View on
 * map" button, until an admin explicitly marks the map published. See AdminVenueMapController's
 * queue (has_map-filtered, unaffected by map_published) vs. this class doc comment.
 */
final class Venue
{
    /** @return array<int, array<string, mixed>> Every listing with at least one floor, for the app's venue-picker list. Public, no auth. */
    public static function all(): array
    {
        return db()->all(
            "SELECT DISTINCT l.id, l.slug, l.title, l.lat, l.lng, l.district
             FROM listings l
             JOIN venue_floors vf ON vf.venue_listing_id = l.id
             WHERE l.status = 'active' AND l.has_map = 1 AND l.map_published = 1
             ORDER BY l.title"
        );
    }

    /** @return array<string, mixed>|null Active venue by id, or null if it doesn't exist / isn't a published venue. */
    public static function find(int $id): ?array
    {
        return db()->one(
            "SELECT l.id, l.slug, l.title FROM listings l
             WHERE l.id = ? AND l.status = 'active' AND l.has_map = 1 AND l.map_published = 1
               AND EXISTS (SELECT 1 FROM venue_floors vf WHERE vf.venue_listing_id = l.id)",
            [$id]
        );
    }

    /**
     * @return array<int, array<string, mixed>> Highest floor first — matches MallMapScreen's
     *   floor-switcher order. image_path/canvas_width/canvas_height are admin-editor-only data
     *   along for the ride here — harmless to include since VenueApiController::floors() is the
     *   only public caller and just hands whatever this returns straight through as JSON...
     *   except it isn't harmless (image_path would leak the tracing reference), so that
     *   controller strips all three before responding. See its floors() method.
     */
    public static function floorsFor(int $venueListingId): array
    {
        return db()->all(
            'SELECT id, floor_order, short_label, name, image_path, canvas_width, canvas_height
             FROM venue_floors WHERE venue_listing_id = ? ORDER BY floor_order DESC',
            [$venueListingId]
        );
    }

    /** @return array<string, mixed>|null */
    public static function findFloor(int $floorId, int $venueListingId): ?array
    {
        return db()->one(
            'SELECT id, floor_order, short_label, name FROM venue_floors WHERE id = ? AND venue_listing_id = ?',
            [$floorId, $venueListingId]
        );
    }

    /**
     * Every unit on a floor, each carrying its occupying listing's *public* fields (null when
     * vacant) — id/slug/title/category_slug/tags only. Deliberately not phone/opening_hours/
     * photos: this one query serves both the public map (which skips vacant units and renders
     * the rest by category_slug/tags, then fetches full detail via GET /api/listings/{slug}
     * only for the unit actually tapped) and the add-listing form's "pick your unit" flow (which
     * needs to know vacant vs. taken, not the occupant's contact info) — see the mall-map
     * research thread's anti-scraping split for why full detail is never bundled in here.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function unitsFor(int $floorId): array
    {
        $units = db()->all(
            "SELECT vu.id, vu.unit_code, vu.shape_type, vu.shape_points, vu.radius,
                    l.id AS listing_id, l.slug AS listing_slug, l.title AS listing_title, c.slug AS category_slug
             FROM venue_units vu
             LEFT JOIN listings l ON l.indoor_unit_id = vu.id AND l.status = 'active'
             LEFT JOIN categories c ON c.id = l.category_id
             WHERE vu.floor_id = ?
             ORDER BY vu.id",
            [$floorId]
        );

        $listingIds = array_values(array_filter(array_map(
            static fn (array $u) => $u['listing_id'] !== null ? (int) $u['listing_id'] : null,
            $units
        )));

        $tagsByListing = [];
        if ($listingIds !== []) {
            $placeholders = implode(',', array_fill(0, count($listingIds), '?'));
            foreach (
                db()->all(
                    "SELECT lt.listing_id, t.slug FROM listing_tags lt JOIN tags t ON t.id = lt.tag_id
                     WHERE lt.listing_id IN ($placeholders)",
                    $listingIds
                ) as $row
            ) {
                $tagsByListing[(int) $row['listing_id']][] = $row['slug'];
            }
        }

        foreach ($units as &$unit) {
            $unit['shape_points'] = json_decode((string) $unit['shape_points'], true) ?? [];
            $unit['radius'] = $unit['radius'] !== null ? (float) $unit['radius'] : null;
            $unit['tags'] = $unit['listing_id'] !== null ? ($tagsByListing[(int) $unit['listing_id']] ?? []) : [];
        }
        unset($unit);

        return $units;
    }

    /**
     * The "View on map" button's data source — Listing::showJson()/ListingController::show()
     * call this with a listing's own indoor_unit_id (null for a listing that isn't inside a
     * venue at all, in which case this returns null straight away). Deliberately re-checks
     * map_published itself rather than trusting the caller: this is the one place that decides
     * whether a shop's page/API response is allowed to reveal its venue's indoor map exists, so
     * an unfinished map must never leak through here even if some other caller forgets the
     * check — see map_published's doc comment in schema.sql for why that matters (raw/incomplete
     * floor data isn't something a public visitor should see).
     *
     * @return array{venue_listing_id: int, venue_slug: string, venue_title: string, floor_id: int}|null
     */
    public static function objectMapFor(?int $indoorUnitId): ?array
    {
        if ($indoorUnitId === null) {
            return null;
        }

        $row = db()->one(
            "SELECT l.id AS venue_listing_id, l.slug AS venue_slug, l.title AS venue_title, vf.id AS floor_id
             FROM venue_units vu
             JOIN venue_floors vf ON vf.id = vu.floor_id
             JOIN listings l ON l.id = vf.venue_listing_id
             WHERE vu.id = ? AND l.status = 'active' AND l.has_map = 1 AND l.map_published = 1",
            [$indoorUnitId]
        );

        return $row !== null
            ? ['venue_listing_id' => (int) $row['venue_listing_id'], 'venue_slug' => $row['venue_slug'], 'venue_title' => $row['venue_title'], 'floor_id' => (int) $row['floor_id']]
            : null;
    }

    /**
     * Which venue/floor a unit belongs to — the web dashboard's edit form uses this once (see
     * ListingManageController::edit()) to pre-select its cascading venue/floor/unit dropdowns
     * for a listing that already has an indoor_unit_id; the app's own edit flow doesn't need it,
     * it already has the full venue/floor/unit chain from whichever screens the owner picked
     * through originally.
     *
     * @return array{venue_listing_id: int, floor_id: int}|null
     */
    public static function locateUnit(int $unitId): ?array
    {
        $row = db()->one(
            'SELECT vf.venue_listing_id, vu.floor_id FROM venue_units vu JOIN venue_floors vf ON vf.id = vu.floor_id WHERE vu.id = ?',
            [$unitId]
        );

        return $row !== null ? ['venue_listing_id' => (int) $row['venue_listing_id'], 'floor_id' => (int) $row['floor_id']] : null;
    }

    /**
     * For the add-listing form's unit picker: is [$unitId] free, or already claimed by a listing
     * other than [$ignoreListingId] (the one being edited, if any)? Mirrors the DB-level
     * uq_listings_indoor_unit constraint as a friendlier pre-check (see ListingManageController::
     * validate()) — that constraint is still the actual race-condition-safe guarantee.
     */
    public static function unitTakenByOther(int $unitId, ?int $ignoreListingId): bool
    {
        $row = db()->one(
            "SELECT id FROM listings WHERE indoor_unit_id = ? AND status != 'expired'" .
            ($ignoreListingId !== null ? ' AND id != ?' : ''),
            $ignoreListingId !== null ? [$unitId, $ignoreListingId] : [$unitId]
        );

        return $row !== null;
    }

    // -------------------------------------------------------------------------------------
    // Admin ("Карта на обект") — AdminVenueMapController only, always behind Guards::
    // requireAdmin(). Deliberately not status/has_map-filtered the way the public methods
    // above are: an admin needs to be able to build a venue's floor plan before it goes
    // live (listing still 'pending'), or keep editing it after the owner flips has_map back
    // off, without either blocking the work.
    // -------------------------------------------------------------------------------------

    /**
     * Every listing with has_map = 1, for the admin queue — plus enough of a progress summary
     * (floor/unit counts, and map_published so the table can show "Published" vs. a toggle to
     * flip it) that staff can tell "not started" from "already has a plan" from "done and live"
     * at a glance without opening each one.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function adminEligible(): array
    {
        return db()->all(
            "SELECT l.id, l.slug, l.title, l.status, l.map_published, c.slug AS category_slug, c.name AS category_name,
                    (SELECT COUNT(*) FROM venue_floors vf WHERE vf.venue_listing_id = l.id) AS floor_count,
                    (SELECT COUNT(*) FROM venue_units vu JOIN venue_floors vf ON vf.id = vu.floor_id WHERE vf.venue_listing_id = l.id) AS unit_count
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             WHERE l.has_map = 1
             ORDER BY l.title"
        );
    }

    /** @return array<string, mixed>|null Any listing (any status), regardless of has_map — see class doc comment. */
    public static function adminFind(int $listingId): ?array
    {
        return db()->one('SELECT id, slug, title, has_map, map_published FROM listings WHERE id = ?', [$listingId]);
    }

    /**
     * Admin-only "mark this venue's map done" toggle (see map_published's doc comment in
     * schema.sql) — flipped from either the venue-maps list or the floor editor. Intentionally
     * has no guard against publishing a venue with zero floors/units; the templates warn staff
     * about that instead of this method silently refusing, since there's no reason to hard-block
     * a legitimate (if unusual) single-shape venue.
     */
    public static function setMapPublished(int $listingId, bool $published): void
    {
        db()->update('listings', ['map_published' => $published ? 1 : 0], 'id = :id', ['id' => $listingId]);
    }

    public static function createFloor(int $venueListingId, int $floorOrder, string $shortLabel, ?string $name): int
    {
        return (int) db()->insert('venue_floors', [
            'venue_listing_id' => $venueListingId,
            'floor_order' => $floorOrder,
            'short_label' => $shortLabel,
            'name' => $name,
        ]);
    }

    public static function updateFloor(int $floorId, int $floorOrder, string $shortLabel, ?string $name): void
    {
        db()->update('venue_floors', [
            'floor_order' => $floorOrder,
            'short_label' => $shortLabel,
            'name' => $name,
        ], 'id = :id', ['id' => $floorId]);
    }

    public static function setFloorImage(int $floorId, ?string $path): void
    {
        db()->update('venue_floors', ['image_path' => $path], 'id = :id', ['id' => $floorId]);
    }

    /**
     * Called once, right when an SVG floor plan is imported (see AdminVenueMapController::
     * importUnits()) — switches this floor's drawing canvas to the imported file's own
     * dimensions so every shape landing in venue_units.shape_points, old or new, stays in one
     * consistent coordinate space (see canvas_width's doc comment in schema.sql). Never called
     * for a freehand-drawn floor, which just keeps the 1000x700 default forever.
     */
    public static function setFloorCanvasSize(int $floorId, int $width, int $height): void
    {
        db()->update('venue_floors', ['canvas_width' => $width, 'canvas_height' => $height], 'id = :id', ['id' => $floorId]);
    }

    /** Cascades to venue_units (ON DELETE CASCADE), which in turn NULLs out any listings.indoor_unit_id that pointed at them. */
    public static function deleteFloor(int $floorId): void
    {
        db()->delete('venue_floors', 'id = ?', [$floorId]);
    }

    /**
     * @param array<int, array{0: float, 1: float}> $shapePoints Two opposite corners for
     *   'rectangle', one center point for 'circle' (paired with $radius), the full vertex list
     *   for 'polygon' — see venue_units.shape_points' doc comment in schema.sql.
     */
    public static function createUnit(int $floorId, ?string $unitCode, string $shapeType, array $shapePoints, ?float $radius): int
    {
        return (int) db()->insert('venue_units', [
            'floor_id' => $floorId,
            'unit_code' => $unitCode,
            'shape_type' => $shapeType,
            'shape_points' => json_encode($shapePoints),
            'radius' => $radius,
        ]);
    }

    public static function updateUnit(int $unitId, ?string $unitCode, string $shapeType, array $shapePoints, ?float $radius): void
    {
        db()->update('venue_units', [
            'unit_code' => $unitCode,
            'shape_type' => $shapeType,
            'shape_points' => json_encode($shapePoints),
            'radius' => $radius,
        ], 'id = :id', ['id' => $unitId]);
    }

    /** Cascades to nothing directly, but ON DELETE SET NULL on listings.indoor_unit_id frees any listing that occupied it. */
    public static function deleteUnit(int $unitId): void
    {
        db()->delete('venue_units', 'id = ?', [$unitId]);
    }

    /** @return array<string, mixed>|null Raw floor row (unlike findFloor(), no venue_listing_id ownership check — caller does that itself via findFloor() first). */
    public static function floorById(int $floorId): ?array
    {
        return db()->one(
            'SELECT id, venue_listing_id, floor_order, short_label, name, image_path, canvas_width, canvas_height FROM venue_floors WHERE id = ?',
            [$floorId]
        );
    }

    /** @return array<string, mixed>|null Raw unit row, for ownership checks before update/delete. */
    public static function unitById(int $unitId): ?array
    {
        return db()->one('SELECT id, floor_id, unit_code, shape_type FROM venue_units WHERE id = ?', [$unitId]);
    }
}
