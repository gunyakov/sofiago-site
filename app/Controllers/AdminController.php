<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

use Sofiago\Middleware\Guards;
use Sofiago\Models\Category;
use Sofiago\Models\Comment;
use Sofiago\Models\Listing;
use Sofiago\Models\ListingReport;
use Sofiago\Models\OwnershipClaim;
use Sofiago\Models\SchoolAdmissionScore;
use Sofiago\Models\Venue;

/**
 * Moderation queue (phase 4). Every action here is admin-only — Guards::requireAdmin() checks
 * `users.role = 'admin'`, set manually in the DB for now (see docs/deploy.md); there's no
 * "promote to admin" UI, on purpose, this isn't self-service.
 */
final class AdminController
{
    // 'expiring' isn't a real DB status (see Listing::forModerationExpiringSoon()) — it's a
    // filtered view of currently-active listings sliding into the renewal window, kept as its
    // own tab so the "Продлить" button only ever shows up there (see admin/listings.tpl.php).
    private const TABS = ['pending', 'active', 'expiring', 'rejected', 'expired'];

    public function listings(array $params): void
    {
        Guards::requireAdmin();

        $status = (string) ($params['status'] ?? $_GET['status'] ?? 'pending');
        if (!in_array($status, self::TABS, true)) {
            $status = 'pending';
        }

        // Search box + category dropdown — added 2026-09-07 once the 'active' tab alone passed
        // 1200 rows with no way to narrow it down. Not paginated for 'expiring' (see
        // Listing::forModerationExpiringSoon()'s doc comment), so $page/$pages/$total there are
        // just 1/1/count(listings) — the template only renders pagination links when $pages > 1.
        $filters = [
            'q' => (string) ($_GET['q'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        if ($status === 'expiring') {
            $listings = Listing::forModerationExpiringSoon($filters);
            $total = count($listings);
            $pages = 1;
        } else {
            $listings = Listing::forModeration($status, $filters, $page);
            $total = Listing::countModeration($status, $filters);
            $pages = max((int) ceil($total / Listing::moderationPerPage()), 1);
        }

        echo view('admin/listings.tpl.php', [
            'title' => t('admin.listings_title'),
            'activeStatus' => $status,
            'listings' => $listings,
            'counts' => Listing::countsByStatus() + ['expiring' => Listing::countExpiringSoon()],
            'categories' => Category::topLevel(),
            'filters' => $filters,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ], 'layout/dashboard.tpl.php');
    }

    /** Same template as the public listing page, but bypasses the status='active' restriction. */
    public function preview(array $params): void
    {
        Guards::requireAdmin();

        $listing = Listing::findAny((int) $params['id']);

        if (!$listing) {
            abort(404, t('listing_manage.not_found'));
        }

        echo view('listings/show.tpl.php', [
            'title' => $listing['title'] . ' — ' . t('admin.preview'),
            'metaDescription' => mb_substr(Listing::descriptionFor($listing), 0, 160),
            'listing' => $listing,
            'media' => Listing::mediaFor((int) $listing['id']),
            'amenities' => Listing::amenitiesFor((int) $listing['id']),
            'admissionScores' => SchoolAdmissionScore::forListing((int) $listing['id']),
            'objectMap' => Venue::objectMapFor($listing['indoor_unit_id'] !== null ? (int) $listing['indoor_unit_id'] : null),
            'isFavorited' => false,
            'isOwner' => false,
            'hasPendingOwnershipClaim' => false,
            'hours' => Listing::decodeHours($listing['opening_hours'] ?? null),
            'comments' => Comment::approvedForListing((int) $listing['id']),
            'turnstileSiteKey' => null, // preview only — the listing isn't published yet, so no comment form
            'pageStyles' => map_widget_styles(),
            'pageScripts' => map_widget_scripts(),
        ]);
    }

    public function approve(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $listing = Listing::findAny((int) $params['id']);

        if (!$listing) {
            abort(404, t('listing_manage.not_found'));
        }

        Listing::approve((int) $listing['id']);

        app()->session->flash('notice', t('admin.listing_published', ['title' => $listing['title']]));
        $this->redirectToListings('pending');
    }

    /**
     * Deliberately separate from approve() above, and deliberately gated by the same window as
     * the owner's self-service renew — publishing and renewing are different actions, and an
     * active listing with weeks left has no renew button anywhere (admin included), so this
     * only ever runs from a form the "Истекают" tab renders. The check below still guards the
     * endpoint itself against a stale tab or a hand-crafted request.
     */
    public function renewListing(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $listing = Listing::findAny((int) $params['id']);

        if (!$listing) {
            abort(404, t('listing_manage.not_found'));
        }

        if ($listing['status'] !== 'active' || !Listing::isRenewable($listing)) {
            app()->session->flash('error', t('admin.renew_not_available'));
            $this->redirectToListings('expiring');
        }

        Listing::approve((int) $listing['id']);

        app()->session->flash('notice', t('admin.listing_renewed', ['title' => $listing['title'], 'days' => Listing::LIFETIME_DAYS]));
        $this->redirectToListings('expiring');
    }

    public function reject(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $listing = Listing::findAny((int) $params['id']);

        if (!$listing) {
            abort(404, t('listing_manage.not_found'));
        }

        $reason = trim((string) ($_POST['reason'] ?? ''));
        Listing::reject((int) $listing['id'], $reason);

        app()->session->flash('notice', t('admin.listing_rejected', ['title' => $listing['title']]));
        $this->redirectToListings('pending');
    }

    /**
     * Sends the admin back to wherever they came from — admin/listings.tpl.php posts the exact
     * query string it was rendered with (status + current search box/category/page, see its
     * $backQuery) as 'back' on every approve/reject/renew form, so acting on a listing found via
     * a search or on page 3 doesn't dump the admin back into the full unfiltered/page-1 tab.
     * $default covers a request with no 'back' at all (shouldn't happen from the real form, but
     * cheap to not 500 on a hand-crafted one).
     */
    private function redirectToListings(string $default): never
    {
        $back = (string) ($_POST['back'] ?? ('status=' . $default));
        redirect('/admin/listings?' . $back);
    }

    private const COMMENT_TABS = ['pending', 'approved', 'rejected'];

    public function comments(array $params): void
    {
        Guards::requireAdmin();

        $status = (string) ($_GET['status'] ?? 'pending');
        if (!in_array($status, self::COMMENT_TABS, true)) {
            $status = 'pending';
        }

        echo view('admin/comments.tpl.php', [
            'title' => t('admin.comments_title'),
            'activeStatus' => $status,
            'comments' => Comment::forModeration($status),
            'counts' => Comment::countsByStatus(),
        ], 'layout/dashboard.tpl.php');
    }

    public function approveComment(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $comment = Comment::find((int) $params['id']);

        if (!$comment) {
            abort(404, t('admin.comment_not_found'));
        }

        Comment::approve((int) $comment['id']);

        app()->session->flash('notice', t('admin.comment_approved'));
        redirect('/admin/comments?status=' . ((string) ($_POST['back'] ?? 'pending')));
    }

    public function rejectComment(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $comment = Comment::find((int) $params['id']);

        if (!$comment) {
            abort(404, t('admin.comment_not_found'));
        }

        Comment::reject((int) $comment['id']);

        app()->session->flash('notice', t('admin.comment_rejected'));
        redirect('/admin/comments?status=' . ((string) ($_POST['back'] ?? 'pending')));
    }

    private const REPORT_TABS = ['pending', 'resolved', 'dismissed'];

    public function reports(array $params): void
    {
        Guards::requireAdmin();

        $status = (string) ($_GET['status'] ?? 'pending');
        if (!in_array($status, self::REPORT_TABS, true)) {
            $status = 'pending';
        }

        echo view('admin/reports.tpl.php', [
            'title' => t('admin.reports_title'),
            'activeStatus' => $status,
            'reports' => ListingReport::forModeration($status),
            'counts' => ListingReport::countsByStatus(),
        ], 'layout/dashboard.tpl.php');
    }

    public function resolveReport(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $report = ListingReport::find((int) $params['id']);
        if (!$report) {
            abort(404, t('admin.report_not_found'));
        }

        ListingReport::resolve((int) $report['id']);

        app()->session->flash('notice', t('admin.report_resolved'));
        redirect('/admin/reports?status=' . ((string) ($_POST['back'] ?? 'pending')));
    }

    public function dismissReport(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $report = ListingReport::find((int) $params['id']);
        if (!$report) {
            abort(404, t('admin.report_not_found'));
        }

        ListingReport::dismiss((int) $report['id']);

        app()->session->flash('notice', t('admin.report_dismissed'));
        redirect('/admin/reports?status=' . ((string) ($_POST['back'] ?? 'pending')));
    }

    private const CLAIM_TABS = ['pending', 'approved', 'rejected'];

    public function ownershipClaims(array $params): void
    {
        Guards::requireAdmin();

        $status = (string) ($_GET['status'] ?? 'pending');
        if (!in_array($status, self::CLAIM_TABS, true)) {
            $status = 'pending';
        }

        echo view('admin/ownership-claims.tpl.php', [
            'title' => t('admin.ownership_claims_title'),
            'activeStatus' => $status,
            'claims' => OwnershipClaim::forModeration($status),
            'counts' => OwnershipClaim::countsByStatus(),
        ], 'layout/dashboard.tpl.php');
    }

    /** Approving transfers the listing to the claimant — see OwnershipClaim::approve()'s doc comment. */
    public function approveOwnershipClaim(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $claim = OwnershipClaim::find((int) $params['id']);
        if (!$claim) {
            abort(404, t('admin.ownership_claim_not_found'));
        }

        OwnershipClaim::approve((int) $claim['id']);

        $listing = Listing::findAny((int) $claim['listing_id']);
        app()->session->flash('notice', t('admin.ownership_claim_approved', [
            'title' => $listing['title'] ?? '',
            'name' => $listing['owner_name'] ?? '',
        ]));
        redirect('/admin/ownership-claims?status=' . ((string) ($_POST['back'] ?? 'pending')));
    }

    public function rejectOwnershipClaim(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $claim = OwnershipClaim::find((int) $params['id']);
        if (!$claim) {
            abort(404, t('admin.ownership_claim_not_found'));
        }

        $reason = trim((string) ($_POST['reason'] ?? ''));
        OwnershipClaim::reject((int) $claim['id'], $reason);

        $listing = Listing::findAny((int) $claim['listing_id']);
        app()->session->flash('notice', t('admin.ownership_claim_rejected', ['title' => $listing['title'] ?? '']));
        redirect('/admin/ownership-claims?status=' . ((string) ($_POST['back'] ?? 'pending')));
    }
}
