<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

/**
 * GET /s/{code} — the page a stop shared from the SofiaGO app opens (the app's stop sheet
 * "Сподели" button sends https://sofiago.eu/s/<stop code>).
 *
 * With the app installed, Android hands this URL straight to it (App Links: the app's manifest
 * claims /s/ and public_html/.well-known/assetlinks.json vouches for it), so this page is what
 * people *without* the app see: the stop's next departures, live, plus a way into the app or to
 * Google Play. Standalone markup rather than layout/main.tpl.php — it is opened from a chat on a
 * phone, and the listing site's navigation and footer would only be in the way.
 *
 * The server only resolves the stop (name and position for the title and the link preview);
 * departures are fetched by the page itself from the same public /gtfs endpoint the app uses
 * (same origin, so no CORS), refreshed every 30 s.
 *
 * Stops are looked up by their public code in a stop index read from OTP and cached for a day
 * — OTP's own `stops(name:)` does not match codes, and one code covers several OTP stops (the
 * same pole as A1135 bus, TB1135 trolleybus, TM1135 tram).
 */
final class StopShareController
{
    /** Sofia's feed id in OTP. */
    private const FEED = '1';

    private const CACHE_TTL = 86400;

    public function show(array $params): void
    {
        $code = (string) ($params['code'] ?? '');
        if (!preg_match('/^[A-Za-z0-9]{1,8}$/', $code)) {
            abort(404, t('stop_share.not_found'));
        }

        $stops = $this->stopsWithCode($code);
        if ($stops === []) {
            abort(404, t('stop_share.not_found'));
        }

        $first = $stops[0];
        echo render('pages/stops/share.tpl.php', [
            'code' => $code,
            'name' => self::nameCase((string) $first['name']),
            'lat' => (float) $first['lat'],
            'lon' => (float) $first['lon'],
            'ids' => array_map(static fn (array $stop): string => (string) $stop['gtfsId'], $stops),
        ]);
    }

    /** @return list<array{gtfsId: string, code: ?string, name: string, lat: float, lon: float}> */
    private function stopsWithCode(string $code): array
    {
        $index = $this->stopIndex();
        $matches = $index[$code] ?? [];
        // Metro stations: code-less or numbered differently in the app — accept the bare OTP id.
        if ($matches === []) {
            foreach ($index as $stops) {
                foreach ($stops as $stop) {
                    if ($stop['gtfsId'] === self::FEED . ':' . $code) {
                        $matches[] = $stop;
                    }
                }
            }
        }

        return $matches;
    }

    /** @return array<string, list<array>> code => OTP stops */
    private function stopIndex(): array
    {
        $file = sys_get_temp_dir() . '/sofiago-stop-index-' . self::FEED . '.json';
        if (is_file($file) && time() - (int) filemtime($file) < self::CACHE_TTL) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $response = $this->otp('{ stops { gtfsId code name lat lon } }');
        $stops = $response['data']['stops'] ?? null;
        if (!is_array($stops)) {
            // OTP down: an old index beats a 404 for every shared link.
            $stale = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

            return is_array($stale) ? $stale : [];
        }

        $index = [];
        foreach ($stops as $stop) {
            if (!is_array($stop) || !str_starts_with((string) ($stop['gtfsId'] ?? ''), self::FEED . ':')) {
                continue;
            }
            $key = (string) ($stop['code'] ?? '');
            if ($key === '') {
                $key = substr((string) $stop['gtfsId'], strlen(self::FEED) + 1);
            }
            $index[$key][] = $stop;
        }
        @file_put_contents($file, json_encode($index, JSON_UNESCAPED_UNICODE), LOCK_EX);

        return $index;
    }

    private function otp(string $query): ?array
    {
        $url = (string) app()->config->get('otp.gtfs_url', 'http://127.0.0.1:8087/otp/gtfs/v1');
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode(['query' => $query]),
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);

        return $body === false ? null : json_decode($body, true);
    }

    /**
     * OTP names stops in capitals ("НДК", "БУЛ. ВИТОША"); the app shows them in sentence-ish
     * case. Same idea, kept simple: lower-case, then capitalise each word, leaving short
     * abbreviations like "НДК" / "ДКЦ" as they are.
     */
    public static function nameCase(string $name): string
    {
        $words = preg_split('/(\s+)/u', $name, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$name];
        foreach ($words as $i => $word) {
            if (trim($word) === '' || mb_strlen($word) <= 3 && !str_contains($word, '.')) {
                continue;
            }
            $lower = mb_strtolower($word);
            $words[$i] = mb_strtoupper(mb_substr($lower, 0, 1)) . mb_substr($lower, 1);
        }

        return implode('', $words);
    }
}
