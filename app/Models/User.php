<?php

declare(strict_types=1);

namespace Sofiago\Models;

final class User
{
    /** @return array<string, mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        return db()->one('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    /** @return array<string, mixed>|null */
    public static function findById(int $id): ?array
    {
        return db()->one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function emailExists(string $email): bool
    {
        return self::findByEmail($email) !== null;
    }

    /**
     * @param string|null $locale Overrides the request-resolved locale() — AuthApiController
     *   passes the app's own device/UI locale explicitly, since locale()'s cookie/`?lang=`
     *   resolution is a web-only concept a bearer-token API request doesn't carry.
     */
    public static function create(string $name, string $email, string $password, ?string $locale = null): string
    {
        return db()->insert('users', [
            'name' => trim($name),
            'email' => mb_strtolower(trim($email)),
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            // The site's language at the moment of sign-up — stuck to the account from here on,
            // so transactional emails always go out in it regardless of which locale the request
            // sending them happens to be in later (see Lang::getFor()).
            'locale' => $locale ?? locale(),
            'role' => 'user',
            'status' => 'active',
        ]);
    }

    /**
     * Verification token + email — shared by AuthController::register()/resendVerification()
     * (web) and AuthApiController::register() (sofiago-flutter), extracted here since it's
     * substantial enough (token generation, mailer, locale-aware template) to be worth not
     * duplicating, unlike the two controllers' own small field-validation blocks.
     */
    public static function sendVerificationEmail(array $user): void
    {
        db()->delete('email_verification_tokens', 'user_id = ? AND used_at IS NULL', [$user['id']]);
        [$raw, $hash] = \Sofiago\Core\SignedToken::generate();
        db()->insert('email_verification_tokens', [
            'user_id' => $user['id'],
            'token_hash' => $hash,
            'expires_at' => date('Y-m-d H:i:s', time() + 24 * 3600),
        ]);

        $locale = (string) ($user['locale'] ?? 'en');

        app()->mailer->send(
            $user['email'],
            $user['name'],
            t_for($locale, 'email.verify.subject'),
            render('emails/verify-email.tpl.php', ['name' => $user['name'], 'link' => url('/verify-email?token=' . $raw), 'locale' => $locale])
        );
    }

    public static function markEmailVerified(int $userId): void
    {
        db()->update('users', ['email_verified_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $userId]);
    }

    public static function updatePassword(int $userId, string $password): void
    {
        db()->update(
            'users',
            ['password_hash' => password_hash($password, PASSWORD_BCRYPT)],
            'id = :id',
            ['id' => $userId]
        );
    }
}
