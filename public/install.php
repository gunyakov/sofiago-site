<?php

declare(strict_types=1);

/**
 * Thin public entry point for database/install.php, which lives under app root (private/ on
 * HestiaCP, see docs/deploy.md) and is therefore not reachable by URL on its own. All the real
 * logic — token check, self-locking, schema execution — stays in database/install.php.
 *
 * Delete this file (or just let install_token stop matching) once the schema is installed and
 * this build is promoted off a throwaway staging subdomain.
 */
$appRoot = is_dir(__DIR__ . '/../app') ? dirname(__DIR__) : dirname(__DIR__) . '/private';

require $appRoot . '/database/install.php';
