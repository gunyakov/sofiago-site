<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

use Sofiago\Core\Upload;
use Sofiago\Middleware\Guards;
use Sofiago\Models\Venue;

/**
 * "Карта на обект" — the admin screen for building a venue's indoor floor plan (see mall-map
 * research thread). Separate file from AdminController on purpose: that one is moderation
 * (approve/reject), this is a distinct, sizeable editor (floors + a canvas-based polygon
 * picker for units) that happens to also be admin-only.
 *
 * Flow: any listing owner can flip listings.has_map on for their own place (plain checkbox on
 * the listing form, same as any other field) — that only queues it here, it draws nothing by
 * itself. An admin then opens it from index() below and adds floors/units; a shop elsewhere on
 * the site "moves in" by picking one of those units from its own listing form's venue/floor/
 * unit cascade (unchanged, see ListingManageController) — this controller never touches other
 * listings, only the venue_floors/venue_units geometry.
 */
final class AdminVenueMapController
{
    private const SHAPE_TYPES = ['rectangle', 'circle', 'polygon'];

    /** GET /admin/venue-maps */
    public function index(array $params): void
    {
        Guards::requireAdmin();

        echo view('admin/venue-maps.tpl.php', [
            'title' => t('admin.venue_maps_title'),
            'venues' => Venue::adminEligible(),
        ], 'layout/dashboard.tpl.php');
    }

    /** GET /admin/venue-maps/{id} — floors + the selected floor's units editor. */
    public function edit(array $params): void
    {
        Guards::requireAdmin();

        $listing = Venue::adminFind((int) $params['id']);
        if (!$listing) {
            abort(404, t('listing_manage.not_found'));
        }

        $floors = Venue::floorsFor((int) $listing['id']);
        $selectedFloorId = isset($_GET['floor']) ? (int) $_GET['floor'] : null;
        $selectedFloor = null;
        if ($selectedFloorId !== null) {
            foreach ($floors as $floor) {
                if ((int) $floor['id'] === $selectedFloorId) {
                    $selectedFloor = $floor;
                    break;
                }
            }
        }
        if ($selectedFloor === null && $floors !== []) {
            $selectedFloor = $floors[0];
        }

        echo view('admin/venue-map-edit.tpl.php', [
            'title' => $listing['title'] . ' — ' . t('admin.venue_maps_title'),
            'listing' => $listing,
            'floors' => $floors,
            'selectedFloor' => $selectedFloor,
            'units' => $selectedFloor !== null ? Venue::unitsFor((int) $selectedFloor['id']) : [],
        ], 'layout/dashboard.tpl.php');
    }

    /**
     * POST /admin/venue-maps/{id}/publish — the "Карта завершена" toggle (see map_published's
     * doc comment in schema.sql). Plain form POST + redirect back, same as the floor forms
     * below, not the JSON the unit editor uses: this is a single infrequent flip, not something
     * driven by the canvas JS.
     */
    public function setPublished(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $listing = Venue::adminFind((int) $params['id']);
        if (!$listing) {
            abort(404, t('listing_manage.not_found'));
        }

        $published = !empty($_POST['published']);
        Venue::setMapPublished((int) $listing['id'], $published);

        app()->session->flash('notice', $published ? t('admin.venue_map_published') : t('admin.venue_map_unpublished'));
        redirect($_POST['redirect_to'] ?? '/admin/venue-maps');
    }

    /** POST /admin/venue-maps/{id}/floors */
    public function createFloor(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $listing = Venue::adminFind((int) $params['id']);
        if (!$listing) {
            abort(404, t('listing_manage.not_found'));
        }

        [$floorOrder, $shortLabel, $name, $error] = $this->readFloorFields();
        if ($error !== null) {
            app()->session->flash('error', $error);
            redirect('/admin/venue-maps/' . $listing['id']);
        }

        $floorId = Venue::createFloor((int) $listing['id'], $floorOrder, $shortLabel, $name);

        app()->session->flash('notice', t('admin.venue_floor_added'));
        redirect('/admin/venue-maps/' . $listing['id'] . '?floor=' . $floorId);
    }

    /** POST /admin/venue-maps/{id}/floors/{floorId} */
    public function updateFloor(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $floor = $this->findOwnedFloor((int) $params['id'], (int) $params['floorId']);

        [$floorOrder, $shortLabel, $name, $error] = $this->readFloorFields();
        if ($error !== null) {
            app()->session->flash('error', $error);
            redirect('/admin/venue-maps/' . $params['id'] . '?floor=' . $floor['id']);
        }

        Venue::updateFloor((int) $floor['id'], $floorOrder, $shortLabel, $name);

        app()->session->flash('notice', t('admin.venue_floor_saved'));
        redirect('/admin/venue-maps/' . $params['id'] . '?floor=' . $floor['id']);
    }

    /** POST /admin/venue-maps/{id}/floors/{floorId}/delete */
    public function deleteFloor(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $floor = $this->findOwnedFloor((int) $params['id'], (int) $params['floorId']);

        if (!empty($floor['image_path'])) {
            Upload::deleteByPublicPath($floor['image_path']);
        }

        Venue::deleteFloor((int) $floor['id']);

        app()->session->flash('notice', t('admin.venue_floor_deleted'));
        redirect('/admin/venue-maps/' . $params['id']);
    }

    /** POST /admin/venue-maps/{id}/floors/{floorId}/image — replaces the admin-only tracing reference. */
    public function uploadFloorImage(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $floor = $this->findOwnedFloor((int) $params['id'], (int) $params['floorId']);

        $error = null;
        $path = Upload::storeImage($_FILES['image'] ?? [], 'venue-floors', $error);

        if ($path !== null) {
            if (!empty($floor['image_path'])) {
                Upload::deleteByPublicPath($floor['image_path']);
            }
            Venue::setFloorImage((int) $floor['id'], $path);
            app()->session->flash('notice', t('admin.venue_floor_image_saved'));
        } elseif ($error !== null) {
            app()->session->flash('error', $error);
        }

        redirect('/admin/venue-maps/' . $params['id'] . '?floor=' . $floor['id']);
    }

    /** POST /admin/venue-maps/{id}/floors/{floorId}/image/delete */
    public function deleteFloorImage(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $floor = $this->findOwnedFloor((int) $params['id'], (int) $params['floorId']);

        if (!empty($floor['image_path'])) {
            Upload::deleteByPublicPath($floor['image_path']);
            Venue::setFloorImage((int) $floor['id'], null);
        }

        redirect('/admin/venue-maps/' . $params['id'] . '?floor=' . $floor['id']);
    }

    /**
     * POST /admin/venue-maps/{id}/floors/{floorId}/units — create (no unit_id in the body) or
     * update (unit_id present) a single unit. JSON in, JSON out: this is the one part of the
     * editor driven by JS (the click-to-draw canvas — see admin/venue-map-edit.tpl.php), a full
     * page reload per point/shape would make it unusable.
     */
    public function saveUnit(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $floor = $this->findOwnedFloor((int) $params['id'], (int) $params['floorId']);
        $body = $this->jsonBody();

        $shape = $this->validateShape($body);
        if ($shape['errors'] !== []) {
            abortJson(422, 'validation_failed', ['messages' => $shape['errors']]);
        }

        $unitId = isset($body['unit_id']) && $body['unit_id'] !== null ? (int) $body['unit_id'] : null;

        try {
            if ($unitId !== null) {
                $unit = Venue::unitById($unitId);
                if (!$unit || (int) $unit['floor_id'] !== (int) $floor['id']) {
                    abortJson(404, 'unit_not_found');
                }
                Venue::updateUnit($unitId, $shape['unit_code'], $shape['shape_type'], $shape['points'], $shape['radius']);
            } else {
                $unitId = Venue::createUnit((int) $floor['id'], $shape['unit_code'], $shape['shape_type'], $shape['points'], $shape['radius']);
            }
        } catch (\PDOException $e) {
            // uq_venue_units_floor_code — the only other constraint on this table.
            abortJson(422, 'unit_code_taken');
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'id' => $unitId,
            'unit_code' => $shape['unit_code'],
            'shape_type' => $shape['shape_type'],
            'shape_points' => $shape['points'],
            'radius' => $shape['radius'],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /admin/venue-maps/{id}/floors/{floorId}/units/import — bulk-create units parsed
     * client-side from an admin-uploaded SVG floor plan (see admin/venue-map-edit.tpl.php's
     * import wizard — traced in a real vector editor like Inkscape, since building a from-
     * scratch pen-tool editor here isn't worth it; this endpoint is the "receiving end" of that
     * workflow). Best-effort per item rather than all-or-nothing: a large real floor plan can
     * easily be 80-100 shapes, and one bad/duplicate code shouldn't sink the other 99 — the
     * response reports success/failure per submitted index so the wizard can show exactly which
     * ones need a manual fix, same as a spreadsheet import would.
     */
    public function importUnits(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $floor = $this->findOwnedFloor((int) $params['id'], (int) $params['floorId']);
        $body = $this->jsonBody();

        $canvasWidth = isset($body['canvas_width']) && is_numeric($body['canvas_width']) ? max(1, (int) $body['canvas_width']) : null;
        $canvasHeight = isset($body['canvas_height']) && is_numeric($body['canvas_height']) ? max(1, (int) $body['canvas_height']) : null;
        if ($canvasWidth !== null && $canvasHeight !== null) {
            Venue::setFloorCanvasSize((int) $floor['id'], $canvasWidth, $canvasHeight);
        }

        $results = [];
        foreach ((array) ($body['units'] ?? []) as $index => $item) {
            if (!is_array($item)) {
                $results[] = ['index' => $index, 'ok' => false, 'error' => 'invalid_item'];
                continue;
            }

            $shape = $this->validateShape($item);
            if ($shape['errors'] !== []) {
                $results[] = ['index' => $index, 'ok' => false, 'error' => implode(' ', $shape['errors'])];
                continue;
            }

            try {
                $unitId = Venue::createUnit((int) $floor['id'], $shape['unit_code'], $shape['shape_type'], $shape['points'], $shape['radius']);
                $results[] = ['index' => $index, 'ok' => true, 'id' => $unitId, 'unit_code' => $shape['unit_code']];
            } catch (\PDOException $e) {
                // uq_venue_units_floor_code — almost always a re-import of a file already imported once.
                $results[] = ['index' => $index, 'ok' => false, 'error' => 'unit_code_taken'];
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
    }

    /** POST /admin/venue-maps/{id}/floors/{floorId}/units/{unitId}/delete */
    public function deleteUnit(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $floor = $this->findOwnedFloor((int) $params['id'], (int) $params['floorId']);
        $unit = Venue::unitById((int) $params['unitId']);

        if (!$unit || (int) $unit['floor_id'] !== (int) $floor['id']) {
            abortJson(404, 'unit_not_found');
        }

        Venue::deleteUnit((int) $unit['id']);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
    }

    /** Floor must both exist and belong to the listing named in the URL — guards against one admin tab's stale floor id pointing at a different venue. */
    private function findOwnedFloor(int $listingId, int $floorId): array
    {
        $floor = Venue::floorById($floorId);

        if (!$floor || (int) $floor['venue_listing_id'] !== $listingId) {
            abort(404, t('listing_manage.not_found'));
        }

        return $floor;
    }

    /** @return array{0: int, 1: string, 2: ?string, 3: ?string} [floorOrder, shortLabel, name, error] */
    private function readFloorFields(): array
    {
        $floorOrder = (int) ($_POST['floor_order'] ?? 0);
        $shortLabel = trim((string) ($_POST['short_label'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));

        if ($shortLabel === '' || mb_strlen($shortLabel) > 8) {
            return [$floorOrder, $shortLabel, $name !== '' ? $name : null, t('validation.venue_floor_label_invalid')];
        }

        return [$floorOrder, $shortLabel, $name !== '' ? $name : null, null];
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        $decoded = json_decode((string) file_get_contents('php://input'), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{errors: array<int, string>, shape_type: string, points: array<int, array{0: float, 1: float}>, radius: ?float, unit_code: ?string}
     */
    private function validateShape(array $body): array
    {
        $errors = [];

        $shapeType = (string) ($body['shape_type'] ?? '');
        if (!in_array($shapeType, self::SHAPE_TYPES, true)) {
            $errors[] = t('validation.venue_shape_type_invalid');
            $shapeType = 'polygon';
        }

        $points = [];
        foreach ((array) ($body['shape_points'] ?? []) as $point) {
            if (!is_array($point) || count($point) !== 2 || !is_numeric($point[0] ?? null) || !is_numeric($point[1] ?? null)) {
                continue;
            }
            $points[] = [(float) $point[0], (float) $point[1]];
        }

        $radius = isset($body['radius']) && is_numeric($body['radius']) ? (float) $body['radius'] : null;

        if ($shapeType === 'rectangle' && count($points) !== 2) {
            $errors[] = t('validation.venue_shape_points_invalid');
        } elseif ($shapeType === 'circle' && (count($points) !== 1 || $radius === null || $radius <= 0)) {
            $errors[] = t('validation.venue_shape_points_invalid');
        } elseif ($shapeType === 'polygon' && count($points) < 3) {
            $errors[] = t('validation.venue_shape_points_invalid');
        }

        $unitCode = trim((string) ($body['unit_code'] ?? ''));
        if (mb_strlen($unitCode) > 40) {
            $errors[] = t('validation.venue_unit_code_too_long');
        }

        return [
            'errors' => $errors,
            'shape_type' => $shapeType,
            'points' => $points,
            'radius' => $shapeType === 'circle' ? $radius : null,
            'unit_code' => $unitCode !== '' ? $unitCode : null,
        ];
    }
}
