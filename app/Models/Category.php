<?php

declare(strict_types=1);

namespace Sofiago\Models;

final class Category
{
    /**
     * Category names are free-text rows in the DB (see database/schema.sql seed), not
     * translatable by themselves. This maps the slug (stable, unlike the English name) to a
     * lang/*.json key so label() can show it in the visitor's locale. Extend this when a new
     * category is added to the seed/DB — anything missing here just falls back to the raw name.
     */
    private const KEYS = [
        'restaurants-cafes' => 'category.restaurants_cafes',
        'shopping' => 'category.shopping',
        'health-beauty' => 'category.health_beauty',
        'nightlife' => 'category.nightlife',
        'attractions-sightseeing' => 'category.attractions_sightseeing',
        'hotels-accommodation' => 'category.hotels_accommodation',
        'services' => 'category.services',
        'sports-recreation' => 'category.sports_recreation',
        'culture-art' => 'category.culture_art',
        'automotive' => 'category.automotive',
        'education' => 'category.education',
        'cinemas' => 'category.cinemas',
        'nature-wellness' => 'category.nature_wellness',
    ];

    /** @return array<int, array<string, mixed>> Top-level categories, ordered for display. */
    public static function topLevel(): array
    {
        return db()->all(
            'SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort_order ASC, name ASC'
        );
    }

    /** @return array<string, mixed>|null */
    public static function findBySlug(string $slug): ?array
    {
        return db()->one('SELECT * FROM categories WHERE slug = ?', [$slug]);
    }

    /** @return array<string, mixed>|null */
    public static function findById(int $id): ?array
    {
        return $id > 0 ? db()->one('SELECT * FROM categories WHERE id = ?', [$id]) : null;
    }

    /**
     * Translated display name. Accepts either a categories row (has 'slug'/'name') or a listing
     * row with the category flattened in as 'category_slug'/'category_name'.
     *
     * @param array<string, mixed> $row
     */
    public static function label(array $row): string
    {
        $slug = $row['slug'] ?? $row['category_slug'] ?? null;
        $name = $row['name'] ?? $row['category_name'] ?? '';
        $key = $slug !== null ? (self::KEYS[$slug] ?? null) : null;

        return $key !== null ? t($key) : (string) $name;
    }
}
