#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Inserts scrape.php's output/*.json into sofiago-site's listings table. Uses Listing::create(),
 * so every row lands as 'pending' — the same status a real owner's submission gets — nothing
 * here bypasses the normal admin moderation queue. description_en is left '' on purpose: schema.sql's
 * own comment on listings.description_en says most of the bulk-imported catalog has none yet
 * either, and the alternative (paraphrasing visitsofia.bg's copyrighted text) is exactly what
 * this import is avoiding. An editor fills descriptions in by hand later, category by category.
 *
 * Usage:
 *   php scripts/import-visitsofia/import.php --user=<owner_user_id> [--city=sofia] [--dry-run] [file.json ...]
 *
 * With no file arguments, reads every output/*.json produced by scrape.php. --dry-run prints
 * what would be inserted/skipped without writing anything — always run that first.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$appRoot = dirname(__DIR__, 2);

require $appRoot . '/vendor/autoload.php';
require $appRoot . '/app/Core/helpers.php';

use Sofiago\Core\App;
use Sofiago\Models\Listing;

$configFile = $appRoot . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Missing config/config.php\n");
    exit(1);
}

App::boot(require $configFile);

$options = getopt('', ['user:', 'city:', 'dry-run']);
$dryRun = isset($options['dry-run']);
$userId = isset($options['user']) ? (int) $options['user'] : null;
$citySlug = $options['city'] ?? 'sofia';

if (!$dryRun && $userId === null) {
    fwrite(STDERR, "Pass --user=<id> (the account that should own these listings), or --dry-run to preview without writing.\n");
    exit(1);
}

$files = array_values(array_filter(
    array_slice($argv, 1),
    fn(string $a): bool => str_ends_with($a, '.json') && is_file($a)
));
if (empty($files)) {
    $files = glob(__DIR__ . '/output/*.json') ?: [];
}
if (empty($files)) {
    fwrite(STDERR, "No .json files found — run scrape.php first.\n");
    exit(1);
}

$cityId = db()->value('SELECT id FROM cities WHERE slug = ?', [$citySlug]);
if (!$cityId) {
    fwrite(STDERR, "Unknown city slug '$citySlug'\n");
    exit(1);
}

$categoryIds = [];
$tagIds = [];
$added = 0;
$skipped = 0;

foreach ($files as $file) {
    $records = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    fwrite(STDERR, '== ' . basename($file) . ' (' . count($records) . " records) ==\n");

    foreach ($records as $r) {
        if (empty($r['name'])) {
            fwrite(STDERR, "  skip (no name): {$r['source_url']}\n");
            $skipped++;
            continue;
        }

        $categoryIds[$r['category_slug']] ??= db()->value('SELECT id FROM categories WHERE slug = ?', [$r['category_slug']]);
        $categoryId = $categoryIds[$r['category_slug']];
        if (!$categoryId) {
            fwrite(STDERR, "  skip '{$r['name']}': unknown category {$r['category_slug']}\n");
            $skipped++;
            continue;
        }

        // Cheap duplicate guard: same title in the same category, or a point within ~30m of an
        // existing listing (DECIMAL(10,7) coords => 0.0003 deg is roughly 25-30m at Sofia's
        // latitude). Good enough for a few hundred rows reviewed by a human afterwards anyway.
        $dupe = db()->value(
            'SELECT id FROM listings WHERE category_id = ? AND (
                title = ?
                OR (lat IS NOT NULL AND lng IS NOT NULL AND ? IS NOT NULL AND ? IS NOT NULL
                    AND ABS(lat - ?) < 0.0003 AND ABS(lng - ?) < 0.0003)
            ) LIMIT 1',
            [$categoryId, $r['name'], $r['lat'], $r['lng'], $r['lat'], $r['lng']]
        );
        if ($dupe) {
            fwrite(STDERR, "  skip '{$r['name']}': looks like existing listing #$dupe\n");
            $skipped++;
            continue;
        }

        fwrite(STDERR, ($dryRun ? '  [dry-run] would add: ' : '  adding: ') . $r['name'] . "\n");
        $added++;
        if ($dryRun) {
            continue;
        }

        $id = Listing::create($userId, (int) $cityId, [
            'title' => $r['name'],
            'category_id' => $categoryId,
            'description_en' => '',
            'description_bg' => null,
            'description_ru' => null,
            'address' => $r['address'],
            'district' => null,
            'postal_code' => null,
            'lat' => $r['lat'],
            'lng' => $r['lng'],
            'phone' => $r['phone'],
            'website' => $r['website'],
            'email' => $r['email'],
            'facebook_url' => null,
            'instagram_url' => null,
            'twitter_url' => null,
            'linkedin_url' => null,
        ]);

        if (!empty($r['tag_slug'])) {
            $tagIds[$r['tag_slug']] ??= db()->value('SELECT id FROM tags WHERE slug = ?', [$r['tag_slug']]);
            if ($tagIds[$r['tag_slug']]) {
                Listing::syncTags($id, [$tagIds[$r['tag_slug']]]);
            }
        }
    }
}

fwrite(STDERR, sprintf(
    "\n%s: %d added, %d skipped.\n",
    $dryRun ? 'Dry run complete (no rows written)' : 'Done',
    $added,
    $skipped
));
