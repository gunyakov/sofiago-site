<?php

declare(strict_types=1);

namespace Sofiago\Models;

final class Amenity
{
    /**
     * Amenity names are free-text rows in the DB (see database/schema.sql seed), not
     * translatable by themselves. This maps the exact English name as stored there to a
     * lang/*.json key so label() can show it in the visitor's locale. Extend this when a new
     * amenity is added to the seed/DB — anything missing here just falls back to the raw name.
     */
    private const KEYS = [
        'Parking' => 'amenity.parking',
        'Wi-Fi' => 'amenity.wifi',
        'Wheelchair accessible' => 'amenity.wheelchair_accessible',
        'Outdoor seating' => 'amenity.outdoor_seating',
        'Card payment' => 'amenity.card_payment',
        'Pet friendly' => 'amenity.pet_friendly',
        'Delivery' => 'amenity.delivery',
        'Air conditioning' => 'amenity.air_conditioning',
    ];

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return db()->all('SELECT * FROM amenities ORDER BY name');
    }

    /** @param array<string, mixed> $amenity A row from all()/Listing::amenitiesFor(). */
    public static function label(array $amenity): string
    {
        $key = self::KEYS[$amenity['name']] ?? null;

        return $key !== null ? t($key) : (string) $amenity['name'];
    }
}
