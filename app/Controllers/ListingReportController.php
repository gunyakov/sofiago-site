<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

use Sofiago\Core\Turnstile;
use Sofiago\Middleware\Guards;
use Sofiago\Models\Listing;
use Sofiago\Models\ListingReport;

/**
 * "Report a problem" on a listing — anyone can post (guest or signed-in, same "who can
 * submit" decision as CommentController), gated by Turnstile on the web. Mirrors
 * ListingController's show()/showJson() pairing: one controller owns both the web form
 * (store(), session+CSRF, flash+redirect) and the JSON endpoint sofiago-flutter posts to
 * (storeJson(), no session — bearer token is optional here since reporting doesn't require
 * being logged in at all).
 */
final class ListingReportController
{
    /** @var array<int, string> */
    private const REASONS = ['closed_permanently', 'wrong_info', 'duplicate', 'inappropriate', 'spam', 'other'];

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
            redirect('/listings/' . $listing['slug'] . '#report-form');
        }

        ListingReport::create([
            'listing_id' => (int) $listing['id'],
            'user_id' => auth()->check() ? auth()->id() : null,
            'reason' => $data['reason'],
            'message' => $data['message'] !== '' ? $data['message'] : null,
            'status' => 'pending',
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);

        app()->session->flash('notice', t('report.submitted'));
        redirect('/listings/' . $listing['slug'] . '#report-form');
    }

    /** POST /api/listings/{slug}/report — public; attaches the caller's user_id only if a bearer token happens to be present. */
    public function storeJson(array $params): void
    {
        $listing = Listing::findBySlug((string) $params['slug']);

        if (!$listing) {
            abortJson(404, 'not_found');
        }

        $data = $this->validate($errors);
        if ($errors !== []) {
            abortJson(422, 'validation_failed', ['errors' => $errors]);
        }

        ListingReport::create([
            'listing_id' => (int) $listing['id'],
            'user_id' => auth()->attemptBearer() ? auth()->id() : null,
            'reason' => $data['reason'],
            'message' => $data['message'] !== '' ? $data['message'] : null,
            'status' => 'pending',
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);

        http_response_code(201);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }

    /** @param array<int, string>|null $errors */
    private function validate(?array &$errors): array
    {
        $errors = [];

        $reason = (string) ($_POST['reason'] ?? '');
        if (!in_array($reason, self::REASONS, true)) {
            $errors[] = t('validation.report_reason_required');
        }

        $message = trim((string) ($_POST['message'] ?? ''));
        if (mb_strlen($message) > 2000) {
            $errors[] = t('validation.report_message_length');
        }

        return ['reason' => $reason, 'message' => $message];
    }
}
