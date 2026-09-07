<?php

declare(strict_types=1);

/**
 * app/, config/, database/ and vendor/ can live either as siblings of public/ (simple hosting,
 * local dev) or under a private/ folder next to public_html (HestiaCP's convention — see
 * docs/deploy.md). Whichever exists wins; this is the only file that needs to know.
 */
$appRoot = is_dir(__DIR__ . '/../app') ? dirname(__DIR__) : dirname(__DIR__) . '/private';

// This file's own directory IS the public webroot in both layouts (named `public/` locally,
// `public_html/` on HestiaCP) — no guessing needed, unlike $appRoot above. Upload.php writes
// here directly instead of trying to derive it via ../.. from app/Core.
define('SOFIAGO_PUBLIC_PATH', __DIR__);

require $appRoot . '/vendor/autoload.php';
require $appRoot . '/app/Core/helpers.php';

use Sofiago\Core\App;

$configFile = $appRoot . '/config/config.php';

if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing config/config.php — copy config/config.example.php, fill in real values, and upload it.');
}

$config = require $configFile;

if (($config['app']['env'] ?? 'production') === 'local') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

App::boot($config);

require $appRoot . '/app/routes.php';
