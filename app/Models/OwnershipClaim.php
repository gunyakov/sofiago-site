<?php

declare(strict_types=1);

namespace Sofiago\Models;

/**
 * "This is my object" — a signed-in user's request to take over a listing (become its
 * listings.user_id), reviewed by an admin (AdminController::ownershipClaims()/
 * approveOwnershipClaim()/rejectOwnershipClaim()). See approve()'s doc comment for the
 * transfer + stale-claims cleanup that happens on approval.
 */
final class OwnershipClaim
{
    /** @param array<string, mixed> $data */
    public static function create(array $data): int
    {
        return (int) db()->insert('ownership_claims', $data);
    }

    /** Blocks a duplicate submission (OwnershipClaimController) and drives the "request pending" button state on the listing page/app. */
    public static function hasPending(int $listingId, int $userId): bool
    {
        return (bool) db()->value(
            "SELECT 1 FROM ownership_claims WHERE listing_id = ? AND user_id = ? AND status = 'pending'",
            [$listingId, $userId]
        );
    }

    /**
     * @return array<int, array<string, mixed>> Newest first, joined with the listing, the
     * claimant, and the listing's *current* owner (o.* — two separate `users` joins) so the
     * admin queue can show who's asking and who they'd be replacing.
     */
    public static function forModeration(string $status): array
    {
        return db()->all(
            'SELECT c.*,
                    l.title AS listing_title,
                    l.slug AS listing_slug,
                    u.name AS claimant_name,
                    u.email AS claimant_email,
                    o.name AS owner_name,
                    o.email AS owner_email
             FROM ownership_claims c
             JOIN listings l ON l.id = c.listing_id
             JOIN users u ON u.id = c.user_id
             JOIN users o ON o.id = l.user_id
             WHERE c.status = ?
             ORDER BY c.created_at DESC',
            [$status]
        );
    }

    /** @return array<string, int> */
    public static function countsByStatus(): array
    {
        $rows = db()->all('SELECT status, COUNT(*) AS n FROM ownership_claims GROUP BY status');
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return db()->one('SELECT * FROM ownership_claims WHERE id = ?', [$id]);
    }

    /**
     * Approves $id, transfers the listing to its claimant (Listing::transferOwner()), and
     * auto-rejects every other still-pending claim on the same listing — once one claimant is
     * approved, a stale second request for the same place would otherwise sit in the queue
     * forever pointing at an owner who's already been replaced.
     */
    public static function approve(int $id): void
    {
        $claim = self::find($id);
        if (!$claim) {
            return;
        }

        db()->update(
            'ownership_claims',
            ['status' => 'approved', 'reviewed_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $id]
        );

        Listing::transferOwner((int) $claim['listing_id'], (int) $claim['user_id']);

        db()->query(
            "UPDATE ownership_claims
             SET status = 'rejected', rejection_reason = 'another request for this place was approved', reviewed_at = ?
             WHERE listing_id = ? AND id != ? AND status = 'pending'",
            [date('Y-m-d H:i:s'), $claim['listing_id'], $id]
        );
    }

    public static function reject(int $id, string $reason): void
    {
        db()->update(
            'ownership_claims',
            ['status' => 'rejected', 'rejection_reason' => $reason !== '' ? $reason : null, 'reviewed_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $id]
        );
    }
}
