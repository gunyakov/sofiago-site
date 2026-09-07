<?php

declare(strict_types=1);

namespace Sofiago\Models;

/**
 * Comments on a listing. Anyone can post (guest or logged-in, see CommentController::store —
 * gated by Turnstile), but a comment only shows up on the listing page once an admin approves
 * it (see AdminController::comments*() — same pending/approve/reject shape as Listing).
 */
final class Comment
{
    public static function perPage(): int
    {
        return 20;
    }

    /** @return array<int, array<string, mixed>> Approved comments for a listing, newest first. */
    public static function approvedForListing(int $listingId): array
    {
        return db()->all(
            'SELECT c.*,
                    COALESCE(u.name, c.guest_name) AS display_name,
                    u.avatar_path AS user_avatar_path
             FROM comments c
             LEFT JOIN users u ON u.id = c.user_id
             WHERE c.listing_id = ? AND c.status = "approved"
             ORDER BY c.created_at DESC',
            [$listingId]
        );
    }

    public static function countApprovedForListing(int $listingId): int
    {
        return (int) db()->value(
            'SELECT COUNT(*) FROM comments WHERE listing_id = ? AND status = "approved"',
            [$listingId]
        );
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): int
    {
        return (int) db()->insert('comments', $data);
    }

    /** @return array<int, array<string, mixed>> Newest first, joined with listing + author info for the admin queue. */
    public static function forModeration(string $status): array
    {
        return db()->all(
            'SELECT c.*,
                    COALESCE(u.name, c.guest_name) AS display_name,
                    u.email AS user_email,
                    l.title AS listing_title,
                    l.slug AS listing_slug
             FROM comments c
             LEFT JOIN users u ON u.id = c.user_id
             JOIN listings l ON l.id = c.listing_id
             WHERE c.status = ?
             ORDER BY c.created_at DESC',
            [$status]
        );
    }

    /** @return array<string, int> */
    public static function countsByStatus(): array
    {
        $rows = db()->all('SELECT status, COUNT(*) AS n FROM comments GROUP BY status');
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return db()->one('SELECT * FROM comments WHERE id = ?', [$id]);
    }

    public static function approve(int $id): void
    {
        db()->update('comments', ['status' => 'approved'], 'id = :id', ['id' => $id]);
    }

    public static function reject(int $id): void
    {
        db()->update('comments', ['status' => 'rejected'], 'id = :id', ['id' => $id]);
    }
}
