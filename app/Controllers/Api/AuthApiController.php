<?php

declare(strict_types=1);

namespace Sofiago\Controllers\Api;

use Sofiago\Core\Lang;
use Sofiago\Middleware\Guards;
use Sofiago\Models\User;

/**
 * JSON auth for sofiago-flutter — register/login/logout/me. No session cookie, no CSRF (see
 * Guards::requireApiAuth()'s doc comment for why CSRF doesn't apply to a bearer token); the
 * bearer token itself is the same selector:verifier pair AuthController's cookie-based login
 * already writes to `sessions` (Auth::login()) — one mechanism, two transports.
 *
 * Field-validation rules are deliberately duplicated from AuthController rather than extracted
 * into a shared validator: each block is ~10 lines, and every other line around it differs
 * anyway (flash+redirect there vs. JSON here) — a shared abstraction would buy little for two
 * call sites. `User::sendVerificationEmail()` is the one piece actually extracted, being
 * substantial (token + mailer) and identical in both places.
 */
final class AuthApiController
{
    public function register(array $params): void
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');
        $locale = (string) ($_POST['locale'] ?? '');

        $errors = [];
        if (mb_strlen($name) < 2) {
            $errors[] = t('validation.name_length');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = t('validation.email_invalid');
        } elseif (User::emailExists($email)) {
            $errors[] = t('validation.email_taken');
        }
        if (mb_strlen($password) < 8) {
            $errors[] = t('validation.password_length');
        } elseif ($password !== $confirm) {
            $errors[] = t('validation.password_mismatch');
        }

        if ($errors !== []) {
            abortJson(422, 'validation_failed', ['errors' => $errors]);
        }

        $resolvedLocale = in_array($locale, Lang::SUPPORTED, true) ? $locale : null;
        $userId = (int) User::create($name, $email, $password, $resolvedLocale);
        $user = User::findById($userId);

        $token = auth()->login($user);
        User::sendVerificationEmail($user);

        $this->respondWithUser($user, $token);
    }

    public function login(array $params): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $token = auth()->attempt($email, $password);

        if ($token === null) {
            abortJson(401, 'invalid_credentials');
        }

        $this->respondWithUser(auth()->user(), $token);
    }

    public function logout(array $params): void
    {
        Guards::requireApiAuth();

        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            auth()->logoutToken(substr($header, 7));
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }

    public function me(array $params): void
    {
        $user = Guards::requireApiAuth();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(self::userPayload($user), JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/auth/me — the app's profile edit (2026-10-03). Only `name` for now; email stays
     * as registered, since changing it would need its own re-verification round trip.
     */
    public function update(array $params): void
    {
        $user = Guards::requireApiAuth();
        $name = trim((string) ($_POST['name'] ?? ''));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            abortJson(422, 'validation_failed', ['errors' => [t('validation.name_length')]]);
        }

        User::updateName((int) $user['id'], $name);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(self::userPayload(User::findById((int) $user['id'])), JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/auth/password — current_password + password + password_confirmation. Keeps this device's token. */
    public function changePassword(array $params): void
    {
        $user = Guards::requireApiAuth();
        $current = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');

        $errors = [];
        if (!password_verify($current, (string) $user['password_hash'])) {
            $errors[] = t('validation.current_password_wrong');
        }
        if (mb_strlen($password) < 8) {
            $errors[] = t('validation.password_length');
        } elseif ($password !== $confirm) {
            $errors[] = t('validation.password_mismatch');
        }
        if ($errors !== []) {
            abortJson(422, 'validation_failed', ['errors' => $errors]);
        }

        User::updatePassword((int) $user['id'], $password);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }

    private function respondWithUser(array $user, string $token): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['token' => $token, 'user' => self::userPayload($user)], JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $user @return array<string, mixed> */
    private static function userPayload(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'locale' => $user['locale'],
            'email_verified' => !empty($user['email_verified_at']),
            // Added 2026-10-03 for the app's profile stats; older builds ignore it.
            'reviews_count' => User::reviewsCount((int) $user['id']),
        ];
    }
}
