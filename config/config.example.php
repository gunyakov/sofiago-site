<?php

declare(strict_types=1);

/**
 * Copy this file to config/config.php (which is gitignored) and fill in real values there.
 * config.php must never be committed — it holds DB and SMTP credentials.
 */

return [

    'app' => [
        // No trailing slash. Used to build every url()/asset() in templates and emails.
        'base_url' => 'https://sofiago.eu',
        'env' => 'production', // 'local' | 'production' — controls error display, see public/index.php
        // Only true on the real production domain — a staging subdomain must never let search
        // engines index its throwaway demo content. See PageController::robots().
        'allow_indexing' => true,
    ],

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'sofiago',
        'user' => 'sofiago',
        'pass' => 'CHANGE_ME',
    ],

    'session' => [
        'name' => 'sofiago_sid',
        'domain' => 'sofiago.eu',
        // Keep this true in production (HTTPS only). Set to false only for local http:// dev.
        'secure' => true,
    ],

    // Real SMTP account — never PHP's mail(). Filled in once you hand over the mailbox creds.
    // If the domain is Cloudflare-proxied, 'host' must be the mail server's real origin IP —
    // Cloudflare doesn't proxy SMTP ports, so the domain name itself won't route there. In that
    // case leave verify_tls false (see Mailer.php): the box's shared TLS cert won't match a bare
    // IP, so hostname verification is switched off for just this connection. Set verify_tls to
    // true once SMTP moves to a hostname the certificate actually covers.
    'smtp' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls', // 'tls' or 'ssl'
        'username' => 'no-reply@sofiago.eu',
        'password' => 'CHANGE_ME',
        'from_email' => 'no-reply@sofiago.eu',
        'from_name' => 'SofiaGO',
        'verify_tls' => false,
    ],

    // Cloudflare Turnstile — gates the comment form on listing pages (CommentController::store).
    // The domain is already proxied through Cloudflare, so no separate vendor account is needed:
    // Cloudflare dashboard → Turnstile → Add widget (domain: sofiago.eu) gives both keys below.
    // Until real keys are set, Comment::create() is unreachable — Turnstile::verify() fails
    // closed on an empty secret rather than silently accepting every submission.
    'turnstile' => [
        'site_key' => 'CHANGE_ME',
        'secret_key' => 'CHANGE_ME',
    ],

    // Secret used by database/install.php — pick a long random string, keep it out of git.
    // Example to generate one: php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
    'install_token' => 'CHANGE_ME',

    // Optional: if set, database/install.php will create/promote this user to admin.
    'bootstrap_admin' => [
        'name' => 'Admin',
        'email' => '',
        'password' => '',
    ],

];
