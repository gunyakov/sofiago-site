-- Demo/dev-only content: a handful of fictional Sofia places so the catalog (Phase 2) isn't
-- empty while "Add listing" (Phase 3) doesn't exist yet. NOT part of schema.sql on purpose —
-- run it by hand, and drop this data (or just re-seed the DB from scratch) before the site
-- promotes off the new.sofiago.eu staging subdomain to the real sofiago.eu.

SET NAMES utf8mb4;

INSERT INTO users (id, name, email, password_hash, role, status, email_verified_at)
VALUES (1, 'SofiaGO Demo', 'demo@sofiago.eu', '$2y$10$abcdefghijklmnopqrstuuVYQpX0V0V0V0V0V0V0V0V0V0V0V0V0', 'user', 'active', NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO listings
    (user_id, category_id, city_id, title, slug, description, address, district, lat, lng, phone, status, published_at)
VALUES
    (1, (SELECT id FROM categories WHERE slug = 'restaurants-cafes'),
        (SELECT id FROM cities WHERE slug = 'sofia'),
        'Кафе Виктория', 'kafe-viktoriya',
        'Уютное кафе с домашней выпечкой и видом на бульвар Витоша.',
        'бул. Витоша 45', 'Center', 42.6883, 23.3182, '+359 2 555 0101', 'active', NOW()),

    (1, (SELECT id FROM categories WHERE slug = 'restaurants-cafes'),
        (SELECT id FROM cities WHERE slug = 'sofia'),
        'Ресторан Балкан', 'restoran-balkan',
        'Традиционная болгарская кухня в самом сердце города.',
        'ул. Раковски 112', 'Center', 42.6959, 23.3247, '+359 2 555 0102', 'active', NOW()),

    (1, (SELECT id FROM categories WHERE slug = 'attractions-sightseeing'),
        (SELECT id FROM cities WHERE slug = 'sofia'),
        'Собор Александра Невского', 'sobor-aleksandra-nevskogo',
        'Один из символов Софии и крупнейший православный собор на Балканах.',
        'пл. Александър Невски', 'Center', 42.6955, 23.3327, NULL, 'active', NOW()),

    (1, (SELECT id FROM categories WHERE slug = 'shopping'),
        (SELECT id FROM cities WHERE slug = 'sofia'),
        'ТЦ Paradise Center', 'tc-paradise-center',
        'Крупный торговый центр с магазинами, кино и фудкортом.',
        'бул. Черни връх 100', 'Lozenets', 42.6688, 23.3178, '+359 2 555 0103', 'active', NOW()),

    (1, (SELECT id FROM categories WHERE slug = 'health-beauty'),
        (SELECT id FROM cities WHERE slug = 'sofia'),
        'Спа-салон Роза', 'spa-salon-roza',
        'Массаж, косметология и релакс-программы в центре города.',
        'ул. Граф Игнатиев 20', 'Center', 42.6900, 23.3260, '+359 2 555 0104', 'active', NOW()),

    (1, (SELECT id FROM categories WHERE slug = 'nightlife'),
        (SELECT id FROM cities WHERE slug = 'sofia'),
        'Бар Джаз Клуб', 'bar-dzhaz-klub',
        'Живая музыка по вечерам и большой выбор коктейлей.',
        'ул. Солунска 10', 'Center', 42.6935, 23.3225, NULL, 'active', NOW()),

    (1, (SELECT id FROM categories WHERE slug = 'sports-recreation'),
        (SELECT id FROM cities WHERE slug = 'sofia'),
        'Парк Борисова градина', 'park-borisova-gradina',
        'Крупнейший парк Софии — прогулки, спорт и озёра.',
        'бул. Цар Освободител', 'Oborishte', 42.6812, 23.3421, NULL, 'active', NOW()),

    (1, (SELECT id FROM categories WHERE slug = 'services'),
        (SELECT id FROM cities WHERE slug = 'sofia'),
        'Автосервис Скорост', 'avtoservis-skorost',
        'Диагностика и ремонт автомобилей всех марок.',
        'ул. Продан Таракчиев 5', 'Lozenets', 42.6795, 23.3050, '+359 2 555 0105', 'active', NOW()),

    -- One pending listing so the (future, Phase 4) moderation queue has something to show.
    (1, (SELECT id FROM categories WHERE slug = 'culture-art'),
        (SELECT id FROM cities WHERE slug = 'sofia'),
        'Галерия Модерна', 'galeriya-moderna',
        'Современное искусство молодых болгарских художников.',
        'ул. Шипка 6', 'Center', 42.6940, 23.3300, NULL, 'pending', NULL)
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- ON DUPLICATE KEY UPDATE (no-op — listing_amenities has no non-key column to reassign) makes
-- these safe to re-run, same as every other statement in this file — needed now that this file
-- gets run again on an already-seeded DB to add the mall-map demo data below (2026-09-06).
INSERT INTO listing_amenities (listing_id, amenity_id)
SELECT l.id, a.id FROM listings l, amenities a
WHERE l.slug = 'kafe-viktoriya' AND a.name IN ('Wi-Fi', 'Outdoor seating', 'Card payment')
ON DUPLICATE KEY UPDATE listing_id = VALUES(listing_id);

INSERT INTO listing_amenities (listing_id, amenity_id)
SELECT l.id, a.id FROM listings l, amenities a
WHERE l.slug = 'restoran-balkan' AND a.name IN ('Parking', 'Card payment', 'Air conditioning')
ON DUPLICATE KEY UPDATE listing_id = VALUES(listing_id);

-- Sample opening_hours (see Listing::decodeHours()) for some of the demo listings — enough to
-- actually show the Opening Hours card on their pages without every single demo place needing
-- one (a landmark and a park are left without hours on purpose, that's a valid real state too).
UPDATE listings SET opening_hours = '{"mon":{"open":"08:00","close":"19:00"},"tue":{"open":"08:00","close":"19:00"},"wed":{"open":"08:00","close":"19:00"},"thu":{"open":"08:00","close":"19:00"},"fri":{"open":"08:00","close":"19:00"},"sat":{"open":"09:00","close":"19:00"}}'
WHERE slug = 'kafe-viktoriya';

UPDATE listings SET opening_hours = '{"mon":{"open":"11:00","close":"23:00"},"tue":{"open":"11:00","close":"23:00"},"wed":{"open":"11:00","close":"23:00"},"thu":{"open":"11:00","close":"23:00"},"fri":{"open":"11:00","close":"23:00"},"sat":{"open":"11:00","close":"23:00"},"sun":{"open":"11:00","close":"22:00"}}'
WHERE slug = 'restoran-balkan';

-- map_published = 1 alongside has_map: the demo venue below is a complete two-floor plan, not a
-- work in progress, so it should behave like a real admin-signed-off venue (see that column's
-- comment in schema.sql) rather than sitting invisible behind the new completion gate.
UPDATE listings SET opening_hours = '{"mon":{"open":"10:00","close":"22:00"},"tue":{"open":"10:00","close":"22:00"},"wed":{"open":"10:00","close":"22:00"},"thu":{"open":"10:00","close":"22:00"},"fri":{"open":"10:00","close":"22:00"},"sat":{"open":"10:00","close":"22:00"},"sun":{"open":"10:00","close":"20:00"}}',
    has_map = 1,
    map_published = 1
WHERE slug = 'tc-paradise-center';

UPDATE listings SET opening_hours = '{"mon":{"open":"09:00","close":"20:00"},"tue":{"open":"09:00","close":"20:00"},"wed":{"open":"09:00","close":"20:00"},"thu":{"open":"09:00","close":"20:00"},"fri":{"open":"09:00","close":"20:00"},"sat":{"open":"10:00","close":"18:00"}}'
WHERE slug = 'spa-salon-roza';

UPDATE listings SET opening_hours = '{"wed":{"open":"18:00","close":"23:59"},"thu":{"open":"18:00","close":"23:59"},"fri":{"open":"18:00","close":"23:59"},"sat":{"open":"18:00","close":"23:59"},"sun":{"open":"18:00","close":"23:00"}}'
WHERE slug = 'bar-dzhaz-klub';

UPDATE listings SET opening_hours = '{"mon":{"open":"09:00","close":"18:00"},"tue":{"open":"09:00","close":"18:00"},"wed":{"open":"09:00","close":"18:00"},"thu":{"open":"09:00","close":"18:00"},"fri":{"open":"09:00","close":"18:00"}}'
WHERE slug = 'avtoservis-skorost';

-- ---------------------------------------------------------------------------
-- Indoor mall map demo data (see mall-map research thread) — two floors and
-- a handful of tenant listings inside the 'tc-paradise-center' demo listing
-- above, replacing sofiago-flutter's old bundled assets/data/mall_demo.json.
-- Shape coordinates are the same ones that placeholder file used, in the
-- floor's own 400x260 local canvas units (not geographic).
-- ---------------------------------------------------------------------------

INSERT INTO venue_floors (venue_listing_id, floor_order, short_label, name)
SELECT id, 0, '0', 'Партер' FROM listings WHERE slug = 'tc-paradise-center'
UNION ALL
SELECT id, 1, '1', 'Първи етаж' FROM listings WHERE slug = 'tc-paradise-center'
ON DUPLICATE KEY UPDATE short_label = VALUES(short_label), name = VALUES(name);

INSERT INTO venue_units (floor_id, unit_code, shape_type, shape_points, radius)
SELECT vf.id, u.unit_code, u.shape_type, u.shape_points, u.radius
FROM venue_floors vf
JOIN listings l ON l.id = vf.venue_listing_id
JOIN (
    SELECT 0 AS floor_order, 'GF-01' AS unit_code, 'rectangle' AS shape_type, '[[20,20],[140,110]]' AS shape_points, NULL AS radius
    UNION ALL SELECT 0, 'GF-02', 'rectangle', '[[150,20],[260,110]]', NULL
    UNION ALL SELECT 0, 'GF-03', 'polygon', '[[270,20],[380,20],[380,110],[320,130],[270,110]]', NULL
    UNION ALL SELECT 0, 'GF-04', 'rectangle', '[[20,150],[110,240]]', NULL
    UNION ALL SELECT 0, 'GF-05', 'circle', '[[165,195]]', 45
    UNION ALL SELECT 0, 'GF-06', 'polygon', '[[240,150],[380,150],[380,240],[280,240],[240,195]]', NULL
    UNION ALL SELECT 1, '1F-01', 'rectangle', '[[20,20],[170,120]]', NULL
    UNION ALL SELECT 1, '1F-02', 'rectangle', '[[180,20],[380,120]]', NULL
    UNION ALL SELECT 1, '1F-03', 'polygon', '[[20,140],[380,140],[380,240],[200,240],[20,200]]', NULL
) u ON u.floor_order = vf.floor_order
WHERE l.slug = 'tc-paradise-center'
ON DUPLICATE KEY UPDATE shape_type = VALUES(shape_type), shape_points = VALUES(shape_points), radius = VALUES(radius);

INSERT INTO listings
    (user_id, category_id, city_id, title, slug, description, address, district, phone, status, published_at, indoor_unit_id)
SELECT 1, (SELECT id FROM categories WHERE slug = cat), (SELECT id FROM cities WHERE slug = 'sofia'),
       title, slug, descr, 'бул. Черни връх 100', 'Lozenets', phone, 'active', NOW(),
       (SELECT vu.id FROM venue_units vu JOIN venue_floors vf ON vf.id = vu.floor_id JOIN listings vl ON vl.id = vf.venue_listing_id
        WHERE vl.slug = 'tc-paradise-center' AND vu.unit_code = code)
FROM (
    SELECT 'shopping' AS cat, 'Zara' AS title, 'zara-paradise-center' AS slug, 'Модна верига за дамско, мъжко и детско облекло.' AS descr, '+359 2 555 0201' AS phone, 'GF-01' AS code
    UNION ALL SELECT 'shopping', 'H&M', 'hm-paradise-center', 'Модна верига за цялото семейство.', '+359 2 555 0202', 'GF-02'
    UNION ALL SELECT 'shopping', 'Billa', 'billa-paradise-center', 'Супермаркет за хранителни стоки.', '+359 2 555 0203', 'GF-03'
    UNION ALL SELECT 'restaurants-cafes', 'KFC', 'kfc-paradise-center', 'Бързо хранене — пиле.', '+359 2 555 0204', 'GF-04'
    UNION ALL SELECT 'restaurants-cafes', 'Starbucks', 'starbucks-paradise-center', 'Кафене верига.', '+359 2 555 0205', 'GF-05'
    UNION ALL SELECT 'cinemas', 'Cinema City', 'cinema-city-paradise-center', 'Мултиплекс кино.', '+359 2 555 0206', 'GF-06'
    UNION ALL SELECT 'shopping', 'Sport Depot', 'sport-depot-paradise-center', 'Спортни стоки и екипировка.', '+359 2 555 0207', '1F-01'
    UNION ALL SELECT 'shopping', 'Технополис', 'tehnopolis-paradise-center', 'Битова и компютърна техника.', '+359 2 555 0208', '1F-02'
    UNION ALL SELECT 'restaurants-cafes', 'Фуд корт', 'food-court-paradise-center', 'Зона с няколко бързи кухни.', '+359 2 555 0209', '1F-03'
) t
ON DUPLICATE KEY UPDATE title = VALUES(title), indoor_unit_id = VALUES(indoor_unit_id);

INSERT INTO listing_tags (listing_id, tag_id)
SELECT l.id, t.id
FROM (
    SELECT 'zara-paradise-center' AS slug, 'fashion' AS tag
    UNION ALL SELECT 'hm-paradise-center', 'fashion'
    UNION ALL SELECT 'billa-paradise-center', 'supermarket'
    UNION ALL SELECT 'kfc-paradise-center', 'fast-food'
    UNION ALL SELECT 'starbucks-paradise-center', 'cafe'
    UNION ALL SELECT 'sport-depot-paradise-center', 'sports-goods'
    UNION ALL SELECT 'tehnopolis-paradise-center', 'electronics-books'
    UNION ALL SELECT 'food-court-paradise-center', 'food-court'
) x
JOIN listings l ON l.slug = x.slug
JOIN tags t ON t.slug = x.tag
ON DUPLICATE KEY UPDATE listing_id = VALUES(listing_id);
