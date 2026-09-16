#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * One-off scraper for visitsofia.bg's "What to see" catalog (Sofia's municipal tourism site) —
 * pulls only structural facts (name, address, phone, email, website, coordinates), never that
 * site's own descriptive text or photos: the footer marks the whole site "(c) Tourist Service.
 * All rights reserved.", and while plain facts about a place aren't copyrightable anywhere,
 * their prose and images are. import.php (same directory) reads this script's output and
 * inserts pending listings for admin review — nothing here touches sofiago-site's database.
 *
 * Usage: php scripts/import-visitsofia/scrape.php [category-slug ...]   (default: all below)
 * Writes one output/<slug>.json per category scraped.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

const BASE = 'https://visitsofia.bg';
const OUTPUT_DIR = __DIR__ . '/output';

// visitsofia.bg category slug => [sofiago-site category_slug, tag_slug|null].
// Mirrors sofiago-site's database/schema.sql category_tags seed (attractions-sightseeing offers
// gallery/museum/temple tags; monuments/monasteries/archaeological & architectural monuments have
// no matching tag yet, so they land in the category untagged).
const CATEGORY_MAP = [
    'museums' => ['attractions-sightseeing', 'museum'],
    'galleries-and-exhibition-halls' => ['attractions-sightseeing', 'gallery'],
    'temples' => ['attractions-sightseeing', 'temple'],
    'monasteries' => ['attractions-sightseeing', null],
    'archaeological-monuments' => ['attractions-sightseeing', null],
    'architectural-monuments' => ['attractions-sightseeing', null],
    'monuments' => ['attractions-sightseeing', null],
];

const LISTING_PATH = '/en/cityinfrastructure/what-to-see/%s';
const DETAIL_URL = BASE . '/index.php?option=com_cityinfrastructure&view=objectdetails&Itemid=483&obektid=%s&layer=50000&lang=en';

function fetchUrl(string $url, int $attempts = 3): string
{
    $ctx = stream_context_create(['http' => [
        'header' => "User-Agent: SofiaGO-data-import/1.0 (+https://sofiago.eu)\r\n",
        'timeout' => 20,
        'follow_location' => 1,
    ]]);

    for ($i = 1; $i <= $attempts; $i++) {
        $html = @file_get_contents($url, false, $ctx);
        usleep(500_000); // stay polite — at most ~2 req/s against a small municipal site
        if ($html !== false) {
            return $html;
        }
        if ($i < $attempts) {
            usleep(1_000_000 * $i); // back off a bit more each retry — the site occasionally hiccups
        }
    }

    throw new RuntimeException("failed to fetch $url after $attempts attempts");
}

function domXPath(string $html): DOMXPath
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    return new DOMXPath($doc);
}

function normalizeSpace(string $s): string
{
    return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
}

/** @return array<int, array{obektid: string, name: string, address: ?string}> keyed by obektid to dedupe */
function parseListingPage(string $html): array
{
    $xpath = domXPath($html);
    $items = [];

    foreach ($xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " portfolio-item ")]') as $article) {
        $link = $xpath->query('.//a[contains(@href, "obektid=")]', $article)->item(0);
        if (!$link || !preg_match('/obektid=(\d+)/', $link->getAttribute('href'), $m)) {
            continue;
        }

        $h3 = $xpath->query('.//h3', $article)->item(0);
        $addr = $xpath->query('.//p[contains(@class, "obektloc")]', $article)->item(0);

        $items[$m[1]] = [
            'obektid' => $m[1],
            'name' => $h3 ? normalizeSpace($h3->textContent) : '',
            'address' => $addr ? normalizeSpace($addr->textContent) : null,
        ];
    }

    return array_values($items);
}

/** @return array{phone: ?string, email: ?string, website: ?string, address: ?string, lat: ?float, lng: ?float} */
function parseDetailPage(string $html): array
{
    $xpath = domXPath($html);
    $fields = ['phone' => null, 'email' => null, 'website' => null, 'address' => null, 'lat' => null, 'lng' => null];

    foreach ($xpath->query('//div[@id="detailinfo"]//div[contains(concat(" ", normalize-space(@class), " "), " row ")]') as $row) {
        $label = $xpath->query('./div[contains(@class, "col-md-3")]', $row)->item(0);
        $value = $xpath->query('./div[contains(@class, "col-md-9")]', $row)->item(0);
        if (!$label || !$value) {
            continue;
        }

        $labelText = strtolower(normalizeSpace($label->textContent));
        $valueText = normalizeSpace($value->textContent);

        if (str_starts_with($labelText, 'phone')) {
            $fields['phone'] = $valueText !== '' ? $valueText : null;
        } elseif ($labelText === 'e-mail') {
            $fields['email'] = $valueText !== '' ? $valueText : null;
        } elseif ($labelText === 'website') {
            $fields['website'] = $valueText !== '' ? $valueText : null;
        } elseif ($labelText === 'address') {
            $fields['address'] = $valueText !== '' ? $valueText : null;
        } elseif ($labelText === 'location' && preg_match('/(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)/', $valueText, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if ($lat !== 0.0 || $lng !== 0.0) { // "0,0" means the site itself never geocoded this object
                $fields['lat'] = $lat;
                $fields['lng'] = $lng;
            }
        }
        // Deliberately not reading the "Description" row — see file doc comment: that text is
        // visitsofia.bg's own copyrighted prose, not a fact, and isn't wanted in this scrape.
    }

    return $fields;
}

/**
 * Fallback for objects visitsofia.bg never geocoded itself (its own "Location" field is "0,0").
 * Queries OpenStreetMap's Nominatim (open ODbL data, no key needed) by address — a courtesy
 * User-Agent per Nominatim's usage policy, no personal contact info attached since this is a
 * one-off low-volume run, not a registered heavy user.
 */
function geocode(string $address): ?array
{
    $query = http_build_query(['format' => 'json', 'limit' => 1, 'q' => $address . ', Sofia, Bulgaria']);
    $ctx = stream_context_create(['http' => [
        'header' => "User-Agent: SofiaGO-data-import/1.0 (+https://sofiago.eu)\r\n",
        'timeout' => 20,
    ]]);

    $json = @file_get_contents("https://nominatim.openstreetmap.org/search?$query", false, $ctx);
    usleep(1_100_000); // Nominatim's usage policy: max 1 request/second

    if ($json === false) {
        return null;
    }

    $results = json_decode($json, true);
    if (!is_array($results) || empty($results[0]['lat']) || empty($results[0]['lon'])) {
        return null;
    }

    return ['lat' => (float) $results[0]['lat'], 'lng' => (float) $results[0]['lon']];
}

$targets = array_slice($argv, 1) ?: array_keys(CATEGORY_MAP);

if (!is_dir(OUTPUT_DIR)) {
    mkdir(OUTPUT_DIR, 0775, true);
}

foreach ($targets as $slug) {
    if (!isset(CATEGORY_MAP[$slug])) {
        fwrite(STDERR, "Unknown category '$slug' — known: " . implode(', ', array_keys(CATEGORY_MAP)) . "\n");
        continue;
    }

    [$categorySlug, $tagSlug] = CATEGORY_MAP[$slug];
    $listUrl = BASE . sprintf(LISTING_PATH, $slug);

    fwrite(STDERR, "Fetching listing page: $listUrl\n");
    $items = parseListingPage(fetchUrl($listUrl));
    fwrite(STDERR, '  found ' . count($items) . " items\n");

    $records = [];
    foreach ($items as $i => $item) {
        $detailUrl = sprintf(DETAIL_URL, $item['obektid']);
        fwrite(STDERR, sprintf("  [%d/%d] %s\n", $i + 1, count($items), $item['name']));

        try {
            $detail = parseDetailPage(fetchUrl($detailUrl));
        } catch (RuntimeException $e) {
            // visitsofia.bg itself 500s on a handful of object pages (confirmed with curl, not a
            // transient blip) — still record the name/address the listing page already gave us
            // rather than dropping the object entirely; phone/email/coords stay null.
            fwrite(STDERR, "    detail page failed ({$e->getMessage()}) — keeping listing-page fields only\n");
            $detail = ['phone' => null, 'email' => null, 'website' => null, 'address' => null, 'lat' => null, 'lng' => null];
        }

        $address = $detail['address'] ?? $item['address'];
        $lat = $detail['lat'];
        $lng = $detail['lng'];

        if ($lat === null && $address) {
            $geo = geocode($address);
            if ($geo) {
                $lat = $geo['lat'];
                $lng = $geo['lng'];
                fwrite(STDERR, "    geocoded via Nominatim: $lat,$lng\n");
            }
        }

        $records[] = [
            'source' => 'visitsofia.bg',
            'source_id' => $item['obektid'],
            'source_url' => $detailUrl,
            'name' => $item['name'],
            'category_slug' => $categorySlug,
            'tag_slug' => $tagSlug,
            'address' => $address,
            'phone' => $detail['phone'],
            'email' => $detail['email'],
            'website' => $detail['website'],
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    $outFile = OUTPUT_DIR . "/$slug.json";
    file_put_contents($outFile, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    fwrite(STDERR, 'Wrote ' . count($records) . " records to $outFile\n\n");
}
