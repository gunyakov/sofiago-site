<?php

declare(strict_types=1);

namespace Sofiago\Core;

/**
 * Simple JSON-file translations (the pattern from the user's old BookTorium project) —
 * lang/{locale}.json is a flat key => string map, loaded once per request. No gettext/.po
 * tooling, no framework: just t('some.key') falling back to the English string, then to the
 * raw key itself, so a missing translation is visible/obvious rather than a blank.
 *
 * Locale choice persists across pages via a plain cookie (not the PHP session) — same
 * selector-independent, works-forever-until-changed pattern as the "old" pattern the user
 * described, and survives a session_regenerate/logout, which a site language preference should.
 */
final class Lang
{
    public const SUPPORTED = ['en', 'bg', 'ru'];
    public const DEFAULT_LOCALE = 'en';
    private const COOKIE = 'sofiago_locale';

    private string $locale;

    /** @var array<string, string> */
    private array $strings;

    /** @var array<string, string> */
    private array $fallback;

    public function __construct(private string $langPath)
    {
        $this->locale = $this->resolve();
        $this->strings = $this->loadFile($this->locale);
        $this->fallback = $this->locale === self::DEFAULT_LOCALE
            ? $this->strings
            : $this->loadFile(self::DEFAULT_LOCALE);
    }

    public function locale(): string
    {
        return $this->locale;
    }

    /** @param array<string, string|int> $replace ':name' => value substitutions in the string. */
    public function get(string $key, array $replace = []): string
    {
        return $this->format($this->strings[$key] ?? $this->fallback[$key] ?? $key, $replace);
    }

    /**
     * Same as get(), but in an explicitly given locale rather than the current request's
     * resolved one. For text addressed to a specific user (an email) where the recipient's own
     * stored locale preference should win — the request sending it may be a different user's
     * browser (forgot-password) or no request at all (the cron job), so the cookie-resolved
     * $this->locale is the wrong thing to read from.
     *
     * @param array<string, string|int> $replace
     */
    public function getFor(string $locale, string $key, array $replace = []): string
    {
        if (!in_array($locale, self::SUPPORTED, true)) {
            $locale = self::DEFAULT_LOCALE;
        }

        $strings = $locale === $this->locale ? $this->strings : $this->loadFile($locale);
        $fallback = $locale === self::DEFAULT_LOCALE ? $strings : $this->fallback;

        return $this->format($strings[$key] ?? $fallback[$key] ?? $key, $replace);
    }

    /** @param array<string, string|int> $replace */
    private function format(string $value, array $replace): string
    {
        foreach ($replace as $name => $val) {
            $value = str_replace(':' . $name, (string) $val, $value);
        }

        return $value;
    }

    /** ?lang=xx (a switcher link) always wins and re-persists the cookie; else the existing cookie; else English. */
    private function resolve(): string
    {
        $requested = $_GET['lang'] ?? null;

        if (is_string($requested) && in_array($requested, self::SUPPORTED, true)) {
            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                setcookie(self::COOKIE, $requested, [
                    'expires' => time() + 60 * 60 * 24 * 365,
                    'path' => '/',
                    'samesite' => 'Lax',
                ]);
            }

            return $requested;
        }

        $cookie = $_COOKIE[self::COOKIE] ?? null;

        if (is_string($cookie) && in_array($cookie, self::SUPPORTED, true)) {
            return $cookie;
        }

        return self::DEFAULT_LOCALE;
    }

    /** @return array<string, string> */
    private function loadFile(string $locale): array
    {
        $file = $this->langPath . '/' . $locale . '.json';

        if (!is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }
}
