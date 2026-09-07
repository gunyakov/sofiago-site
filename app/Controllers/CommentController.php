<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

use Sofiago\Core\Turnstile;
use Sofiago\Middleware\Guards;
use Sofiago\Models\Comment;
use Sofiago\Models\Listing;

/**
 * POST /listings/{slug}/comments — anyone can post (guest or signed-in, see
 * "Кто может оставлять комментарии" decision), gated by Turnstile. Comments only appear on the
 * listing page once an admin approves them (AdminController::approveComment()).
 *
 * indexJson()/storeJson() mirror ListingController's show()/showJson() and
 * ListingReportController's store()/storeJson() pairing: this same controller also owns the
 * JSON endpoints sofiago-flutter's comments tab uses (no session/CSRF; storeJson() also skips
 * Turnstile entirely — a native app has no web widget to solve — same call as
 * ListingReportController::storeJson()).
 */
final class CommentController
{
    public function store(array $params): void
    {
        Guards::verifyCsrf();

        $listing = Listing::findBySlug((string) $params['slug']);

        if (!$listing) {
            abort(404, t('listing.not_found'));
        }

        $data = $this->validate($errors);

        if (!Turnstile::verify($_POST['cf-turnstile-response'] ?? null)) {
            $errors[] = t('comment.captcha_failed');
        }

        if ($errors !== []) {
            flash_errors($errors);
            app()->session->keepOld($_POST);
            redirect('/listings/' . $listing['slug'] . '#comment-form');
        }

        $row = [
            'listing_id' => (int) $listing['id'],
            'body' => $data['body'],
            'rating' => $data['rating'],
            'status' => 'pending',
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ];

        if (auth()->check()) {
            $row['user_id'] = auth()->id();
            $row['guest_name'] = null;
            $row['guest_email'] = null;
        } else {
            $row['user_id'] = null;
            $row['guest_name'] = $data['name'];
            $row['guest_email'] = $data['email'] !== '' ? $data['email'] : null;
        }

        Comment::create($row);

        app()->session->flash('notice', t('comment.submitted'));
        redirect('/listings/' . $listing['slug'] . '#comment-form');
    }

    /** GET /api/listings/{slug}/comments — approved comments for the native comments tab, newest first. */
    public function indexJson(array $params): void
    {
        $listing = Listing::findBySlug((string) $params['slug']);

        header('Content-Type: application/json; charset=utf-8');

        if (!$listing) {
            http_response_code(404);
            echo json_encode(['error' => 'not_found'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $comments = array_map(static fn (array $c): array => [
            'id' => (int) $c['id'],
            'display_name' => $c['display_name'],
            'rating' => (int) $c['rating'],
            'body' => $c['body'],
            'created_at' => $c['created_at'],
        ], Comment::approvedForListing((int) $listing['id']));

        echo json_encode($comments, JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/listings/{slug}/comments — same field/validation rules as store(), just JSON in
     * (no Turnstile — see this class's doc comment) and JSON out instead of session flash +
     * redirect. Submitted comments start 'pending', same as the web form: they won't appear in
     * indexJson() above until an admin approves them.
     */
    public function storeJson(array $params): void
    {
        $listing = Listing::findBySlug((string) $params['slug']);

        if (!$listing) {
            abortJson(404, 'not_found');
        }

        // Before validate(), which checks auth()->check() to decide whether name/email are
        // required — attemptBearer() populates that same auth() state from the header when a
        // token is present, same as loadFromCookie() would for the web form.
        $loggedIn = auth()->attemptBearer();

        $data = $this->validate($errors);
        if ($errors !== []) {
            abortJson(422, 'validation_failed', ['errors' => $errors]);
        }

        $row = [
            'listing_id' => (int) $listing['id'],
            'body' => $data['body'],
            'rating' => $data['rating'],
            'status' => 'pending',
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ];

        if ($loggedIn) {
            $row['user_id'] = auth()->id();
            $row['guest_name'] = null;
            $row['guest_email'] = null;
        } else {
            $row['user_id'] = null;
            $row['guest_name'] = $data['name'];
            $row['guest_email'] = $data['email'] !== '' ? $data['email'] : null;
        }

        Comment::create($row);

        http_response_code(201);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }

    /** @param array<int, string>|null $errors */
    private function validate(?array &$errors): array
    {
        $errors = [];

        $body = trim((string) ($_POST['body'] ?? ''));
        if (mb_strlen($body) < 3 || mb_strlen($body) > 2000) {
            $errors[] = t('validation.comment_body_length');
        }

        // Required for everyone (guest or signed-in) — email is the only optional field.
        $rating = (int) ($_POST['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            $errors[] = t('validation.rating_required');
        }

        $name = '';
        $email = '';

        if (!auth()->check()) {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));

            if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
                $errors[] = t('validation.name_length');
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = t('validation.email_field_invalid');
            }
        }

        return ['body' => $body, 'name' => $name, 'email' => $email, 'rating' => $rating];
    }
}
