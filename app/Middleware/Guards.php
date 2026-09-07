<?php

declare(strict_types=1);

namespace Sofiago\Middleware;

/**
 * Called as the first line of a controller action, e.g.:
 *   public function create(array $params): void { Guards::requireVerifiedUser(); ... }
 */
final class Guards
{
    public static function requireGuest(): void
    {
        if (auth()->check()) {
            redirect('/dashboard');
        }
    }

    public static function requireAuth(): void
    {
        if (!auth()->check()) {
            app()->session->flash('notice', t('guards.sign_in_notice'));
            redirect('/sign-in');
        }
    }

    public static function requireVerifiedUser(): void
    {
        self::requireAuth();

        if (!auth()->isVerified()) {
            app()->session->flash('warning', t('guards.verify_email_warning'));
            redirect('/dashboard');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireAuth();

        if (!auth()->isAdmin()) {
            abort(403, t('guards.admins_only'));
        }
    }

    /** Verifies the `_csrf` field of the current POST/PUT/PATCH/DELETE request, aborts on mismatch. */
    public static function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        if (!app()->session->checkCsrf(is_string($token) ? $token : null)) {
            abort(419, t('guards.session_expired'));
        }
    }

    // -------------------------------------------------------------------------------------
    // sofiago-flutter's API controllers (Api\*) — `Authorization: Bearer <token>` instead of
    // the session cookie + CSRF pair above, JSON 401/403 instead of a redirect/HTML abort. No
    // CSRF check needed on these: CSRF exploits a browser automatically attaching cookies to a
    // cross-site request, which doesn't apply to a bearer token the app must explicitly attach
    // to every request itself.
    // -------------------------------------------------------------------------------------

    /** @return array<string, mixed> the authenticated user row. */
    public static function requireApiAuth(): array
    {
        if (!auth()->attemptBearer()) {
            abortJson(401, 'unauthenticated');
        }

        return auth()->user();
    }

    /** @return array<string, mixed> the authenticated, email-verified user row. */
    public static function requireApiVerifiedUser(): array
    {
        $user = self::requireApiAuth();

        if (empty($user['email_verified_at'])) {
            abortJson(403, 'email_not_verified');
        }

        return $user;
    }
}
