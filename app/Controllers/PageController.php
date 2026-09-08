<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

use Sofiago\Models\Category;
use Sofiago\Models\Listing;

final class PageController
{
    // Real contact mailbox (also the admin account created for moderation, phase 4) — one
    // constant so /about, /terms and /privacy never drift from each other.
    private const SUPPORT_EMAIL = 'support@sofiago.eu';


    public function home(array $params): void
    {
        echo view('home.tpl.php', [
            'title' => t('page.home_title'),
            'metaDescription' => t('page.home_description'),
            'categories' => Category::topLevel(),
            // Optional ?category= scopes the home map's random sample (Listing::
            // randomFeatured()) to one category, same idea as /explore's own ?category= but
            // capped at 20 VIP-first instead of the full list — no UI links here yet, just
            // the plumbing (see home.tpl.php's data-filters).
            'selectedCategory' => (string) ($_GET['category'] ?? ''),
            'pageStyles' => map_widget_styles(),
            'pageScripts' => map_widget_scripts(),
        ]);
    }

    public function about(array $params): void
    {
        echo view('static.tpl.php', [
            'title' => t('page.about_title'),
            'metaDescription' => t('about.meta_description'),
            'heading' => t('about.heading'),
            'body' => t('about.body', ['support_email' => self::SUPPORT_EMAIL]),
        ]);
    }

    // Terms/Privacy are long-form legal text — kept as separate whole documents per locale
    // (legal/terms-en.tpl.php etc.) rather than fragmented into dozens of translation keys,
    // same reasoning as any real site's /en/terms vs /bg/terms being different documents.
    public function terms(array $params): void
    {
        echo view('legal/terms-' . locale() . '.tpl.php', [
            'title' => t('page.terms_title'),
            'supportEmail' => self::SUPPORT_EMAIL,
        ]);
    }

    public function privacy(array $params): void
    {
        echo view('legal/privacy-' . locale() . '.tpl.php', [
            'title' => t('page.privacy_title'),
            'supportEmail' => self::SUPPORT_EMAIL,
        ]);
    }

    // Google Play's Data Safety section requires its own public URL describing the account
    // deletion process (not just a paragraph inside the privacy policy) — same per-locale
    // whole-document pattern as terms()/privacy() above.
    public function deleteAccount(array $params): void
    {
        echo view('legal/delete-account-' . locale() . '.tpl.php', [
            'title' => t('page.delete_account_title'),
            'supportEmail' => self::SUPPORT_EMAIL,
        ]);
    }

    public function robots(array $params): void
    {
        header('Content-Type: text/plain; charset=utf-8');

        // Staging subdomains must never get indexed by accident — config.php controls this
        // explicitly (see config.example.php) rather than guessing from the domain name, so
        // promoting to the real domain requires a conscious flip, not "it happened to work".
        if (!app()->config->get('app.allow_indexing', false)) {
            echo "User-agent: *\nDisallow: /\n";

            return;
        }

        echo "User-agent: *\n";
        echo "Allow: /\n";
        foreach (['/dashboard', '/sign-in', '/sign-up', '/forgot-password', '/reset-password', '/verify-email'] as $path) {
            echo "Disallow: {$path}\n";
        }
        echo "\nSitemap: " . url('/sitemap.xml') . "\n";
    }

    public function sitemap(array $params): void
    {
        header('Content-Type: application/xml; charset=utf-8');

        $staticPages = ['/', '/about', '/explore', '/terms', '/privacy', '/delete-account'];
        $listings = Listing::allActiveForSitemap();

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($staticPages as $path) {
            echo '<url><loc>' . e(url($path)) . '</loc></url>' . "\n";
        }

        foreach ($listings as $listing) {
            $lastmod = date('Y-m-d', strtotime((string) $listing['updated_at']));
            echo '<url><loc>' . e(url('/listings/' . $listing['slug'])) . '</loc><lastmod>' . $lastmod . '</lastmod></url>' . "\n";
        }

        echo '</urlset>' . "\n";
    }
}
