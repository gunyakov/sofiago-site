<?php

declare(strict_types=1);

namespace Sofiago\Controllers\Api;

use Sofiago\Models\Venue;

/**
 * Public read-only venue -> floor -> unit walk for the mall-map "variant A" viewer and the
 * add-listing form's venue/floor/unit picker (see mall-map research thread). No auth, same as
 * /api/categories — a venue is just a listing, so nothing here exposes more than the public map
 * already does.
 */
final class VenueApiController
{
    /** GET /api/venues — every listing with a floor plan, for the venue-picker list. */
    public function index(array $params): void
    {
        $this->json(Venue::all());
    }

    /** GET /api/venues/{id}/floors */
    public function floors(array $params): void
    {
        $venue = Venue::find((int) $params['id']);
        if (!$venue) {
            abortJson(404, 'venue_not_found');
        }

        // Venue::floorsFor() also carries image_path (the admin's tracing reference — see its
        // doc comment in schema.sql) and canvas_width/canvas_height (the admin editor's own
        // drawing-canvas size, meaningless to the app since MallFloorLayout derives its bounds
        // from the units themselves). Never exposed here.
        $floors = array_map(
            static fn (array $floor) => array_diff_key($floor, ['image_path' => true, 'canvas_width' => true, 'canvas_height' => true]),
            Venue::floorsFor((int) $venue['id'])
        );

        $this->json($floors);
    }

    /**
     * GET /api/venues/{id}/floors/{floorId}/units — see Venue::unitsFor()'s doc comment for why
     * this is the one endpoint both the public map and the owner's "pick your unit" flow share.
     */
    public function units(array $params): void
    {
        $venue = Venue::find((int) $params['id']);
        if (!$venue) {
            abortJson(404, 'venue_not_found');
        }

        $floor = Venue::findFloor((int) $params['floorId'], (int) $venue['id']);
        if (!$floor) {
            abortJson(404, 'floor_not_found');
        }

        $this->json(Venue::unitsFor((int) $floor['id']));
    }

    private function json(mixed $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
