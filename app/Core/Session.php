<?php

declare(strict_types=1);

namespace Sofiago\Core;

/**
 * Wraps PHP's native session (used for CSRF tokens, flash messages and "old" form input —
 * never for the auth token itself, see Auth.php for that).
 */
final class Session
{
    public function __construct(Config $config)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => (string) $config->get('session.domain', ''),
            'secure' => (bool) $config->get('session.secure', true),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name((string) $config->get('session.name', 'sofiago_sid'));
        session_start();

        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
    }

    public function csrfToken(): string
    {
        return $_SESSION['_csrf'];
    }

    public function checkCsrf(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($_SESSION['_csrf'], $token);
    }

    public function regenerateCsrf(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    public function flash(string $key, mixed $value = null): mixed
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;

            return null;
        }

        $stored = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return $stored;
    }

    /** @param array<string, mixed> $data */
    public function keepOld(array $data): void
    {
        $_SESSION['_old'] = $data;
    }

    /** @return array<string, mixed> */
    public function pullOld(): array
    {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);

        return $old;
    }

    public function regenerateId(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
