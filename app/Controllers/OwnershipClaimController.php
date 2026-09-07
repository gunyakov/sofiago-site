<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

use Sofiago\Core\Turnstile;
use Sofiago\Middleware\Guards;
use Sofiago\Models\Listing;
use Sofiago\Models\OwnershipClaim;

/**
 * "This is my object" — signed-in users only (see Guards::requireAuth()/requireApiAuth()),
 * requesting to take over a listing (become listings.user_id). Mirrors
 * ListingReportController's web/JSON pairing, Turnstile included on the web form (2026-09-08
 * feedback: being logged in isn't by itself a spam bar, a compromised/throwaway account can
 * still mass-file claims) — sofiago-flutter's storeJson() skips it, same as
 * ListingReportController::storeJson(), since there's no mobile Turnstile widget to check.
 *
 * The message is required (MIN/MAX_MESSAGE_LENGTH), also 2026-09-08 feedback: an empty "just
 * send it" claim gives the admin nothing to tell a genuine owner from a land-grab — the
 * claimant has to actually say something that lets a human recognize them as the real owner.
 */
final class OwnershipClaimController
{
    private const MIN_MESSAGE_LENGTH = 10;
    private const MAX_MESSAGE_LENGTH = 1000;

    public function store(array $params): void
    {
        Guards::requireAuth();
        Guards::verifyCsrf();

        $listing = Listing::findBySlug((string) $params['slug']);
        if (!$listing) {
            abort(404, t('listing.not_found'));
        }

        $eligibility = $this->checkEligible($listing, (int) auth()->id());
        if ($eligibility !== null) {
            app()->session->flash('error', t('listing.claim_' . $eligibility));
            redirect('/listings/' . $listing['slug']);
        }

        $message = $this->validateMessage($errors);

        if (!Turnstile::verify($_POST['cf-turnstile-response'] ?? null)) {
            $errors[] = t('comment.captcha_failed');
        }

        if ($errors !== []) {
            flash_errors($errors);
            app()->session->keepOld($_POST);
            redirect('/listings/' . $listing['slug'] . '#claim-modal');
        }

        OwnershipClaim::create([
            'listing_id' => (int) $listing['id'],
            'user_id' => auth()->id(),
            'message' => $message,
            'status' => 'pending',
        ]);

        app()->session->flash('notice', t('listing.claim_submitted'));
        redirect('/listings/' . $listing['slug']);
    }

    /** POST /api/listings/{slug}/claim-ownership — no Turnstile, see class doc comment. */
    public function storeJson(array $params): void
    {
        $user = Guards::requireApiAuth();

        $listing = Listing::findBySlug((string) $params['slug']);
        if (!$listing) {
            abortJson(404, 'not_found');
        }

        $eligibility = $this->checkEligible($listing, (int) $user['id']);
        if ($eligibility !== null) {
            abortJson(409, $eligibility);
        }

        $message = $this->validateMessage($errors);
        if ($errors !== []) {
            abortJson(422, 'validation_failed', ['errors' => $errors]);
        }

        OwnershipClaim::create([
            'listing_id' => (int) $listing['id'],
            'user_id' => (int) $user['id'],
            'message' => $message,
            'status' => 'pending',
        ]);

        http_response_code(201);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $listing
     * @return string|null 'own_listing'/'already_pending' if the request should be blocked
     * (store() prefixes it into a 'listing.claim_*' lang key, storeJson() uses it as-is as the
     * JSON error code), null if the claim is fine to create.
     */
    private function checkEligible(array $listing, int $userId): ?string
    {
        if ((int) $listing['user_id'] === $userId) {
            return 'own_listing';
        }

        if (OwnershipClaim::hasPending((int) $listing['id'], $userId)) {
            return 'already_pending';
        }

        return null;
    }

    /**
     * Own field name ('claim_message', not 'message') so old()/errors() on the listing page
     * can't confuse a failed claim submission with a failed report submission — both forms sit
     * on the same page and would otherwise fight over the same old-input key.
     *
     * @param array<int, string>|null $errors
     */
    private function validateMessage(?array &$errors): string
    {
        $errors = [];
        $message = trim((string) ($_POST['claim_message'] ?? ''));

        if (mb_strlen($message) < self::MIN_MESSAGE_LENGTH || mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $errors[] = t('validation.claim_message_length');
        }

        return $message;
    }
}
