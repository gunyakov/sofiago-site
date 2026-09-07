<?php

declare(strict_types=1);

namespace Sofiago\Models;

final class Favorite
{
    /** Toggles and returns the new state (true = now favorited). */
    public static function toggle(int $userId, int $listingId): bool
    {
        if (self::isFavorited($userId, $listingId)) {
            db()->delete('favorites', 'user_id = ? AND listing_id = ?', [$userId, $listingId]);

            return false;
        }

        db()->insert('favorites', ['user_id' => $userId, 'listing_id' => $listingId]);

        return true;
    }

    public static function isFavorited(int $userId, int $listingId): bool
    {
        return db()->value(
            'SELECT 1 FROM favorites WHERE user_id = ? AND listing_id = ?',
            [$userId, $listingId]
        ) !== null;
    }

    /** @return array<int, int> Every listing_id this user has favorited — for "is this card favorited?" checks in views. */
    public static function idsForUser(int $userId): array
    {
        return array_map('intval', array_column(
            db()->all('SELECT listing_id FROM favorites WHERE user_id = ?', [$userId]),
            'listing_id'
        ));
    }

    /** @return array<int, array<string, mixed>> Favorited listings (active ones only), newest first. */
    public static function forUser(int $userId): array
    {
        return db()->all(
            "SELECT l.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                    (SELECT path FROM listing_media m WHERE m.listing_id = l.id ORDER BY is_cover DESC, sort_order ASC LIMIT 1) AS cover_path
             FROM favorites f
             JOIN listings l ON l.id = f.listing_id
             JOIN categories c ON c.id = l.category_id
             WHERE f.user_id = ? AND l.status = 'active'
             ORDER BY f.created_at DESC",
            [$userId]
        );
    }
}
