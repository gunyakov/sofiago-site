<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

use Sofiago\Middleware\Guards;
use Sofiago\Models\Favorite;

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
