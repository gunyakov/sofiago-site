#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Nightly listing-expiry job. Meant to run once a day (the user's crontab runs it at 02:00
 * Europe/Sofia — pick a quiet hour, not literal midnight; the name is just what the feature
 * was called while planning it) via a plain cron entry, e.g.:
 *
 *   0 2 * * * /usr/bin/php /home/<user>/web/<domain>/private/cron/midnight.php >> /home/<user>/web/<domain>/private/cron/midnight.log 2>&1
 *
 * Deliberately lives under private/ (this file's own parent, alongside app/config/database/
 * vendor — see docs/deploy.md), never under public_html — there is no reason this should ever
 * be reachable over HTTP, so the CLI-only guard below is a backstop, not the actual protection.
 *
 * Two jobs, in order (they touch disjoint sets of rows, order doesn't matter for correctness):
 *   1. Flip overdue active listings to 'expired' — Listing::expireOverdue() (schema already had
 *      the 'expired' status since phase 0; nothing ever set it until now).
 *   2. Email owners of listings entering the Listing::RENEWAL_WINDOW_DAYS-day warning window,
 *      once per window (Listing::expiry_notified_at guards against repeating this every night).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

$appRoot = dirname(__DIR__);

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

$startedAt = date('Y-m-d H:i:s');
echo "[{$startedAt}] midnight.php starting\n";

// 1) Hide listings whose time has run out.
$expiredCount = Listing::expireOverdue();
echo "  expired {$expiredCount} listing(s) past their expiry date\n";

// 2) Warn owners whose listing enters the renewal window tonight, one email each, once.
$expiringSoon = Listing::findExpiringForNotification();
$sent = 0;
$failed = 0;

foreach ($expiringSoon as $listing) {
    // No HTTP request here (this is a cron job) — the owner's own stored locale, not a
    // request-resolved one, is the only signal for which language to send in. See
    // Lang::getFor()/t_for().
    $locale = (string) ($listing['owner_locale'] ?? 'en');

    $ok = app()->mailer->send(
        (string) $listing['owner_email'],
        (string) $listing['owner_name'],
        t_for($locale, 'email.expiring.subject'),
        render('emails/listing-expiring.tpl.php', [
            'name' => $listing['owner_name'],
            'title' => $listing['title'],
            'expiresAt' => $listing['expires_at'],
            'link' => url('/dashboard/listings'),
            'locale' => $locale,
        ])
    );

    if ($ok) {
        Listing::markExpiryNotified((int) $listing['id']);
        $sent++;
    } else {
        // Mailer::send() already error_log()s the underlying exception. Not marking notified
        // here is deliberate — leaves it eligible so tomorrow night's run retries automatically.
        $failed++;
        echo "  WARNING: failed to email listing #{$listing['id']} owner ({$listing['owner_email']})\n";
    }
}

echo "  sent {$sent}/" . count($expiringSoon) . " expiry-warning email(s)"
    . ($failed > 0 ? ", {$failed} failed (will retry tomorrow)" : '') . "\n";

echo '[' . date('Y-m-d H:i:s') . "] midnight.php done\n";
