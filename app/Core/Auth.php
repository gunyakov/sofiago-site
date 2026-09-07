<?php

declare(strict_types=1);

namespace Sofiago\Core;

/**
 * Auth state lives in its own httponly cookie backed by the `sessions` table (a
 * selector/verifier pair — the DB never stores the raw token, only its SHA-256 hash), not in
 * PHP's native session. This lets a user log out "everywhere" and lets us list/revoke active
 * sessions later without touching session.* config.
 */
final class Auth
{
    private const COOKIE = 'sofiago_auth';
    private const TTL_SECONDS = 60 * 60 * 24 * 30; // 30 days

    /** @var array<string, mixed>|null */
    private ?array $user = null;

    public function __construct(private DB $db, private Config $config)
    {
        $this->loadFromCookie();
    }

    private function loadFromCookie(): void
    {
        $raw = $_COOKIE[self::COOKIE] ?? null;
        if (is_string($raw)) {
            $this->loginFromToken($raw);
        }
    }

    /**
     * @return string|null The new session token (see login()) on success — still works with
     *   existing `if (!auth()->attempt(...))`-style callers unchanged (a string is truthy, null
     *   is falsy), while giving AuthApiController the token value a plain bool couldn't.
     */
    public function attempt(string $email, string $password): ?string
    {
        $user = $this->db->one('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);

        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $this->login($user);
    }

    /**
     * @param array<string, mixed> $user
     * @return string The same `selector:verifier` pair just written to the `sessions` row and
     *   set as the web cookie — sofiago-flutter's API controllers (AuthApiController) hand this
     *   back to the app as its bearer token instead. One mechanism, two transports: a browser
     *   gets it via the httponly cookie, the app gets it in a JSON response body and sends it
     *   back as `Authorization: Bearer <token>` (see attemptBearer()) — same `sessions` table,
     *   same expiry, same logout-by-selector, no separate token system to keep in sync.
     */
    public function login(array $user): string
    {
        $this->user = $user;

        $selector = bin2hex(random_bytes(9));
        $verifier = bin2hex(random_bytes(33));

        $this->db->insert('sessions', [
            'user_id' => $user['id'],
            'selector' => $selector,
            'verifier_hash' => hash('sha256', $verifier),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_SECONDS),
        ]);

        $token = $selector . ':' . $verifier;

        // Harmless for API callers — nothing reads cookies on a Dio request, this just also
        // exists on the response and gets ignored, same as any other header/cookie an app-only
        // response doesn't otherwise care about.
        setcookie(self::COOKIE, $token, [
            'expires' => time() + self::TTL_SECONDS,
            'path' => '/',
            'secure' => (bool) $this->config->get('session.secure', true),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $this->db->update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);

        return $token;
    }

    /**
     * `Authorization: Bearer <selector:verifier>` equivalent of loadFromCookie() — sofiago-
     * flutter has no cookie jar, so its API requests carry the same token pair in a header
     * instead. Sets $this->user on success (same as a valid cookie would), same as attempt().
     */
    public function attemptBearer(): bool
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (!is_string($header) || !str_starts_with($header, 'Bearer ')) {
            return false;
        }

        return $this->loginFromToken(substr($header, 7));
    }

    /** Shared by loadFromCookie()/attemptBearer() — same selector:verifier lookup either way. */
    private function loginFromToken(string $raw): bool
    {
        if (!str_contains($raw, ':')) {
            return false;
        }

        [$selector, $verifier] = explode(':', $raw, 2);

        $row = $this->db->one('SELECT * FROM sessions WHERE selector = ? AND expires_at > NOW()', [$selector]);
        if (!$row || !hash_equals($row['verifier_hash'], hash('sha256', $verifier))) {
            return false;
        }

        $user = $this->db->one('SELECT * FROM users WHERE id = ? AND status = "active"', [$row['user_id']]);
        if (!$user) {
            return false;
        }

        $this->user = $user;
        $this->db->update('sessions', ['last_seen_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);

        return true;
    }

    /** Bearer-token equivalent of logout() — revokes by selector, same as a cookie logout would. */
    public function logoutToken(string $raw): void
    {
        if (str_contains($raw, ':')) {
            [$selector] = explode(':', $raw, 2);
            $this->db->delete('sessions', 'selector = ?', [$selector]);
        }

        $this->user = null;
    }

    public function logout(): void
    {
        $raw = $_COOKIE[self::COOKIE] ?? null;

        if (is_string($raw) && str_contains($raw, ':')) {
            [$selector] = explode(':', $raw, 2);
            $this->db->delete('sessions', 'selector = ?', [$selector]);
        }

        setcookie(self::COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
        $this->user = null;
    }

    /** Log out every device for the current user (e.g. after a password reset). */
    public function logoutEverywhere(): void
    {
        if ($this->user !== null) {
            $this->db->delete('sessions', 'user_id = ?', [$this->user['id']]);
        }

        $this->logout();
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        return $this->user;
    }

    public function check(): bool
    {
        return $this->user !== null;
    }

    public function id(): ?int
    {
        return isset($this->user['id']) ? (int) $this->user['id'] : null;
    }

    public function isAdmin(): bool
    {
        return ($this->user['role'] ?? null) === 'admin';
    }

    public function isVerified(): bool
    {
        return !empty($this->user['email_verified_at']);
    }
}
