<?php

declare(strict_types=1);

namespace Sofiago\Core;

/**
 * Cloudflare Turnstile — the captcha gating the public comment form (see CommentController).
 * Domain already sits behind Cloudflare, so this needs no extra vendor account: just a
 * Turnstile widget created in the same Cloudflare dashboard (Turnstile → Add widget), giving
 * a site key (public, goes in the HTML) and a secret key (server-side only, config.php).
 */
final class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function siteKey(): string
    {
        return (string) app()->config->get('turnstile.site_key', '');
    }

    /** Renders the widget `<div>` — include the `https://challenges.cloudflare.com/turnstile/v0/api.js` script once per page. */
    public static function widget(): string
    {
        return '<div class="cf-turnstile" data-sitekey="' . e(self::siteKey()) . '" data-theme="light"></div>';
    }

    /** Verifies the `cf-turnstile-response` field a submitted form carries. */
    public static function verify(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $secret = (string) app()->config->get('turnstile.secret_key', '');
        if ($secret === '') {
            // Not configured — fail closed rather than silently accepting every submission.
            return false;
        }

        $ch = curl_init(self::VERIFY_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!is_string($response)) {
            return false;
        }

        $result = json_decode($response, true);

        return is_array($result) && ($result['success'] ?? false) === true;
    }
}
