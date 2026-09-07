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
        ];
    }
}
