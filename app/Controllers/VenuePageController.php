<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

use Sofiago\Models\Venue;

/**
 * GET /venues/{id} — the public read-only floor-plan viewer a shop's own listing page links to
 * via its "View on map" button (see ListingController::show()'s $objectMap / Listing::showJson()'s
 * has_object_map). Server only supplies the venue's title/id here; the actual floors/units are
 * fetched client-side from the same public /api/venues/{id}/floors and .../floors/{floorId}/units
 * endpoints sofiago-flutter's MallMapScreen already uses (see VenueApiController) — one geometry
 * source, two renderers (this page's plain SVG one, and Flutter's isometric CustomPaint one).
 *
 * Deliberately thin: Venue::find() already refuses a venue whose map isn't both has_map = 1 and
 * map_published = 1 (see that method's doc comment), so a 404 here is the same "not finished /
 * doesn't exist" answer either way — nothing about *why* it's 404 is exposed.
 */
final class VenuePageController
{
    public function show(array $params): void
    {
        $venue = Venue::find((int) $params['id']);
        if (!$venue) {
            abort(404, t('listing.not_found'));
        }

        // Both optional: a plain /venues/{id} visit (e.g. from a venue-picker list) just opens on
        // the first floor with nothing highlighted. ?unit= without ?floor= still works — the
        // client-side script resolves which floor actually holds that unit_id itself once floors
        // load (see venues/show.tpl.php), so this controller doesn't need to look it up.
        $highlightUnitId = isset($_GET['unit']) ? (int) $_GET['unit'] : null;
        $initialFloorId = isset($_GET['floor']) ? (int) $_GET['floor'] : null;

        echo view('venues/show.tpl.php', [
            'title' => $venue['title'] . ' — SofiaGO',
            'venue' => $venue,
            'highlightUnitId' => $highlightUnitId,
            'initialFloorId' => $initialFloorId,
        ]);
    }
}
