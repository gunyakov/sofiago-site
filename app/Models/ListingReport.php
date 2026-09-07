<?php

declare(strict_types=1);

namespace Sofiago\Models;

/**
 * "Report a problem" on a listing. Anyone can post (guest or logged-in, see
 * ListingReportController — gated by Turnstile on the web, same bar as Comment), reviewed by
 * an admin (AdminController::reports()/resolveReport()/dismissReport()). Purely a moderation
 * queue: resolving/dismissing never touches the listing row itself — an admin fixes the
 * listing by hand (or via the usual edit form) if the report turns out to be valid.
 */
final class ListingReport
{
    /** @param array<string, mixed> $data */
    public static function create(array $data): int
    {
        return (int) db()->insert('listing_reports', $data);
    }

    /** @return array<int, array<string, mixed>> Newest first, joined with listing + reporter info for the admin queue. */
    public static function forModeration(string $status): array
    {
        return db()->all(
            'SELECT r.*,
                    COALESCE(u.name, "—") AS reporter_name,
                    u.email AS reporter_email,
                    l.title AS listing_title,
                    l.slug AS listing_slug
             FROM listing_reports r
             LEFT JOIN users u ON u.id = r.user_id
             JOIN listings l ON l.id = r.listing_id
             WHERE r.status = ?
             ORDER BY r.created_at DESC',
            [$status]
        );
    }

    /** @return array<string, int> */
    public static function countsByStatus(): array
    {
        $rows = db()->all('SELECT status, COUNT(*) AS n FROM listing_reports GROUP BY status');
        $counts = ['pending' => 0, 'resolved' => 0, 'dismissed' => 0];

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return db()->one('SELECT * FROM listing_reports WHERE id = ?', [$id]);
    }

    public static function resolve(int $id): void
    {
        db()->update('listing_reports', ['status' => 'resolved'], 'id = :id', ['id' => $id]);
    }

    public static function dismiss(int $id): void
    {
        db()->update('listing_reports', ['status' => 'dismissed'], 'id = :id', ['id' => $id]);
    }
}
