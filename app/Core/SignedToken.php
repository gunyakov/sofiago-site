<?php

declare(strict_types=1);

namespace Sofiago\Core;

/**
 * Shared helper for the email-verification / password-reset link pattern: a random token goes
 * out in the URL, only its SHA-256 hash is stored in the DB, so a leaked/logged database never
 * exposes a usable link.
 */
final class SignedToken
{
    /** @return array{0: string, 1: string} [$rawTokenForTheUrl, $hashToStoreInTheDb] */
    public static function generate(): array
    {
        $raw = bin2hex(random_bytes(32));

        return [$raw, self::hash($raw)];
    }

    public static function hash(string $raw): string
    {
        return hash('sha256', $raw);
    }
}
