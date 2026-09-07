<?php

declare(strict_types=1);

namespace Sofiago\Models;

/**
 * The fixed tag vocabulary (see database/schema.sql's seed for why this is a closed list, not
 * free text) — narrows within a category that groups several distinct sub-types under one
 * browsable category, same mechanism `categorySlug`/`tagSlug` already reuses on the
 * sofiago-flutter side (poi_catalog.dart) for bars/nightclubs under nightlife etc., now also
 * used for a mall unit's sub-category (fashion/electronics/supermarket/... under shopping — see
 * the mall-map research thread) instead of a second, parallel category system.
 *
 * Each tag is also scoped to one or more categories via category_tags — a listing's category
 * (picked first) decides which of these 21 tags are even offered, so a Nightlife listing is
 * never shown a Kids & Toys checkbox. See forCategory()/validIdsForCategory() below and
 * schema.sql's category_tags seed for the actual mapping.
 */
final class Tag
{
    /**
     * Tag names are free-text rows in the DB (see schema.sql seed), not translatable by
     * themselves. This maps the slug (stable, unlike the English name) to a lang/*.json key so
     * label() can show it in the visitor's locale. Extend this — and lang/*.json's tag.* keys —
     * whenever a new row is added to the seed; anything missing here just falls back to the raw
     * name.
     */
    private const KEYS = [
        'bar' => 'tag.bar',
        'nightclub' => 'tag.nightclub',
        'school' => 'tag.school',
        'kindergarten' => 'tag.kindergarten',
        'library' => 'tag.library',
        'gallery' => 'tag.gallery',
        'museum' => 'tag.museum',
        'temple' => 'tag.temple',
        'theatre' => 'tag.theatre',
        'fashion' => 'tag.fashion',
        'footwear-leather' => 'tag.footwear_leather',
        'lingerie' => 'tag.lingerie',
        'cosmetics-pharmacy' => 'tag.cosmetics_pharmacy',
        'kids-toys' => 'tag.kids_toys',
        'optics-jewelry-gifts' => 'tag.optics_jewelry_gifts',
        'home-furniture' => 'tag.home_furniture',
        'sports-goods' => 'tag.sports_goods',
        'electronics-books' => 'tag.electronics_books',
        'supermarket' => 'tag.supermarket',
        'food-court' => 'tag.food_court',
        'cafe' => 'tag.cafe',
        'fast-food' => 'tag.fast_food',
    ];

    /**
     * Every tag, each carrying which category ids it's offered under (category_tags — see that
     * table's doc comment in schema.sql). A tag with an empty category_ids is orphaned (exists
     * but no category currently offers it) rather than universal — forCategory() below would
     * never surface it, same as any other category's tags.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        $tags = db()->all('SELECT * FROM tags ORDER BY name');

        $byTag = [];
        foreach (db()->all('SELECT tag_id, category_id FROM category_tags') as $row) {
            $byTag[(int) $row['tag_id']][] = (int) $row['category_id'];
        }

        foreach ($tags as &$tag) {
            $tag['category_ids'] = $byTag[(int) $tag['id']] ?? [];
        }
        unset($tag);

        return $tags;
    }

    /** @return array<int, array<string, mixed>> Only the tags offered for [$categoryId] — the add-listing form's actual checkbox list once a category is picked. */
    public static function forCategory(int $categoryId): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $tag) => in_array($categoryId, $tag['category_ids'], true)
        ));
    }

    /** @param array<string, mixed> $tag A row from all()/Listing::tagsFor(). */
    public static function label(array $tag): string
    {
        $key = self::KEYS[$tag['slug']] ?? null;

        return $key !== null ? t($key) : (string) $tag['name'];
    }

    /**
     * Filters arbitrary submitted ids down to ones both (a) in the fixed vocabulary and (b)
     * actually offered under [$categoryId] — the enforcement point for "pick from the list, don't
     * type your own" AND for "the tags on offer match the category you picked, not some other
     * one" (see ListingManageController::syncAmenitiesAndTags()). A client can only ever have
     * shown checkboxes for the selected category's own tags, but $_POST is attacker-controlled
     * regardless, so this re-checks server-side rather than trusting what was rendered.
     *
     * @param array<int, int> $submittedIds
     * @return array<int, int>
     */
    public static function validIdsForCategory(array $submittedIds, int $categoryId): array
    {
        $validIds = array_column(self::forCategory($categoryId), 'id');

        return array_values(array_intersect($submittedIds, $validIds));
    }
}
