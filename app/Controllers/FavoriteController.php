<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

use Sofiago\Middleware\Guards;
use Sofiago\Models\Favorite;
use Sofiago\Models\Listing;

final class FavoriteController
{
    public function index(array $params): void
    {
        Guards::requireAuth();

        echo view('dashboard/favorites.tpl.php', [
            'title' => t('dashboard.favorites.title'),
            'listings' => Favorite::forUser((int) auth()->id()),
        ], 'layout/dashboard.tpl.php');
    }

    /**
     * GET /api/favorites — the bearer-token user's favorites, for sofiago-flutter's two-way sync
     * of the place page's heart (2026-10-03). Add/remove below are idempotent so a retried sync
     * can't flip anything.
     */
    public function indexJson(array $params): void
    {
        $user = Guards::requireApiAuth();
        $places = array_map(static fn (array $row): array => [
            'slug' => $row['slug'],
            'title' => $row['title'],
            'lat' => (float) $row['lat'],
            'lng' => (float) $row['lng'],
        ], Favorite::placesForUser((int) $user['id']));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($places, JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/listings/{slug}/favorite */
    public function addJson(array $params): void
    {
        $this->changeJson($params, true);
    }

    /** DELETE /api/listings/{slug}/favorite */
    public function removeJson(array $params): void
    {
        $this->changeJson($params, false);
    }

    private function changeJson(array $params, bool $add): void
    {
        $user = Guards::requireApiAuth();
        $listing = Listing::findBySlug((string) $params['slug']);
        if (!$listing) {
            abortJson(404, 'not_found');
        }

        if ($add) {
            Favorite::add((int) $user['id'], (int) $listing['id']);
        } else {
            Favorite::remove((int) $user['id'], (int) $listing['id']);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['favorited' => $add], JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/favorites/{id}/toggle — small JSON endpoint for the favorite-button island. */
    public function toggle(array $params): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!auth()->check()) {
            http_response_code(401);
            echo json_encode(['error' => t('guards.sign_in_notice')]);

            return;
        }

        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!app()->session->checkCsrf(is_string($token) ? $token : null)) {
            http_response_code(419);
            echo json_encode(['error' => 'CSRF']);

            return;
        }

        $favorited = Favorite::toggle((int) auth()->id(), (int) $params['id']);
        echo json_encode(['favorited' => $favorited]);
    }
}
