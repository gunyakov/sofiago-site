<?php

declare(strict_types=1);

namespace Sofiago\Models;

final class City
{
    /**
     * Only Sofia is seeded for now (see schema.sql) — no city picker in the add-listing form
     * yet, every new listing just gets this one. Revisit once/if the catalog goes multi-city.
     */
    public static function defaultId(): int
    {
        return (int) db()->value("SELECT id FROM cities WHERE slug = 'sofia'");
    }
}
