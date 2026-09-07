-- SofiaGO listing site — MariaDB schema
-- Charset/collation: utf8mb4 everywhere (emoji + Cyrillic safe).
-- Run once via database/install.php (see that file for the curl-based install flow).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Users & auth
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                VARCHAR(120) NOT NULL,
    email               VARCHAR(190) NOT NULL,
    password_hash       VARCHAR(255) NOT NULL,
    phone               VARCHAR(40) NULL,
    avatar_path         VARCHAR(255) NULL,
    -- Locale active at sign-up (Sofiago\Core\Lang::SUPPORTED) — not re-derived from the request
    -- on every page, so a user-targeted email (verification, password reset, listing-expiring
    -- cron) can always be sent in the language they registered in, even from a request/cron
    -- context with no locale cookie of its own. See Lang::getFor()/t_for().
    locale              VARCHAR(5) NOT NULL DEFAULT 'en',
    role                ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    status              ENUM('active', 'banned') NOT NULL DEFAULT 'active',
    email_verified_at   DATETIME NULL,
    last_login_at       DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  CHAR(64) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_evt_token_hash (token_hash),
    KEY idx_evt_user (user_id),
    CONSTRAINT fk_evt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  CHAR(64) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prt_token_hash (token_hash),
    KEY idx_prt_user (user_id),
    CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Persistent login sessions (selector/verifier pattern — see app/Core/Auth.php).
CREATE TABLE IF NOT EXISTS sessions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    selector        CHAR(18) NOT NULL,
    verifier_hash   CHAR(64) NOT NULL,
    user_agent      VARCHAR(255) NULL,
    ip              VARCHAR(45) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sessions_selector (selector),
    KEY idx_sessions_user (user_id),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Basic brute-force throttling for login/reset endpoints.
CREATE TABLE IF NOT EXISTS login_attempts (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email       VARCHAR(190) NOT NULL,
    ip          VARCHAR(45) NOT NULL,
    succeeded   TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_email_time (email, created_at),
    KEY idx_attempts_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Catalog taxonomy
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id   INT UNSIGNED NULL,
    name        VARCHAR(120) NOT NULL,
    slug        VARCHAR(140) NOT NULL,
    icon        VARCHAR(60) NULL COMMENT 'Font Awesome class, e.g. fa-utensils',
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent (parent_id),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cities (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(120) NOT NULL,
    slug        VARCHAR(140) NOT NULL,
    lat         DECIMAL(10, 7) NOT NULL,
    lng         DECIMAL(10, 7) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cities_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS amenities (
    id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name    VARCHAR(120) NOT NULL,
    icon    VARCHAR(60) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_amenities_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name    VARCHAR(80) NOT NULL,
    slug    VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which categories each tag is offered under (many-to-many — a few tags like 'museum' or
-- 'cosmetics-pharmacy' genuinely straddle two categories) — the add-listing form only shows/
-- accepts a tag once its listing's category_id matches a row here (see Tag::forCategory()/
-- Tag::validIdsForCategory()). Fixes the "picked Nightlife, got offered a Kids & Toys tag"
-- mismatch: a category's own tag checkboxes are now exactly this table's rows for it, not the
-- entire 21-tag vocabulary every time.
CREATE TABLE IF NOT EXISTS category_tags (
    category_id INT UNSIGNED NOT NULL,
    tag_id      INT UNSIGNED NOT NULL,
    PRIMARY KEY (category_id, tag_id),
    KEY idx_category_tags_tag (tag_id),
    CONSTRAINT fk_ct_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
    CONSTRAINT fk_ct_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Listings
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS listings (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED NOT NULL COMMENT 'Owner who submitted the listing',
    category_id         INT UNSIGNED NOT NULL,
    city_id             INT UNSIGNED NOT NULL,
    title               VARCHAR(160) NOT NULL,
    slug                VARCHAR(190) NOT NULL,
    -- Per-language descriptions (site serves en/bg/ru — see lang/*.json, Lang::SUPPORTED).
    -- English is the one guaranteed to exist (site's primary audience is foreign tourists, not
    -- Bulgarians — see project notes) and Listing::descriptionFor() always falls back to it, so
    -- it's NOT NULL; bg/ru are optional per-listing extras and stay NULL until someone actually
    -- translates that listing. NOT NULL DEFAULT '' rather than a hard "must be non-empty" CHECK
    -- because most of the bulk-imported catalog has no description yet at all (an ongoing
    -- enrichment pass fills these in category by category) — a CHECK would have to reject every
    -- row that hasn't been gotten to yet, which defeats the point; ListingManageController's
    -- validate() is what actually enforces "non-empty" for anything a real owner submits.
    description_en      TEXT NOT NULL DEFAULT '',
    description_bg      TEXT NULL,
    description_ru      TEXT NULL,
    address             VARCHAR(255) NULL,
    district            VARCHAR(120) NULL COMMENT 'Neighbourhood / quarter — replaces the template''s US-style "state" field',
    postal_code         VARCHAR(20) NULL,
    lat                 DECIMAL(10, 7) NULL,
    lng                 DECIMAL(10, 7) NULL,
    indoor_unit_id      INT UNSIGNED NULL COMMENT 'Set when this listing is a shop inside a mall rather than (or in addition to) a plain lat/lng point — the shape it occupies on its venue''s floor plan. See venue_units below; the listing keeps its own normal category_id/tags/phone/opening_hours either way, only the shape is new.',
    ruo_school_code     INT UNSIGNED NULL COMMENT 'RUO Sofia-grad''s own numeric school code (the "Училище Код" column in its admission-score exports, e.g. 2216306) — set only for listings that are schools with profiled 7th-grade intake. Lets SchoolAdmissionScore::importRows() match a future year''s/round''s export file straight to the right listing instead of fuzzy name-matching every time (see AdminSchoolAdmissionController). NULL for every other listing, including ordinary schools without profiled admission.',
    phone               VARCHAR(40) NULL,
    website             VARCHAR(255) NULL,
    email               VARCHAR(190) NULL,
    facebook_url        VARCHAR(255) NULL,
    instagram_url       VARCHAR(255) NULL,
    twitter_url         VARCHAR(255) NULL,
    linkedin_url        VARCHAR(255) NULL,
    map_icon_path       VARCHAR(255) NULL COMMENT 'Root-relative URL of a custom marker icon (PNG, alpha preserved) to show on the map instead of the default pin — set via Upload::storeIcon(), NULL until the owner uploads one.',
    opening_hours       JSON NULL COMMENT 'day => {open,close} in HH:MM, e.g. {"mon":{"open":"08:00","close":"18:00"}} — a missing day means closed; see Listing::decodeHours()',
    is_vip              TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'VIP tier vs. plain — no admin UI to set this yet, just the column and Listing::randomFeatured()''s VIP-first random sampling for the home page map (see that method). Everything is 0/plain until that UI exists.',
    has_map             TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Owner-facing toggle ("this place has its own indoor floor map") — settable by any owner on their own listing, e.g. a mall or a museum. Turning it on does NOT create the map itself, it only makes the listing appear in the admin''s Карта на обект screen (AdminVenueMapController), where staff actually draw the floors/units (venue_units below) — same reasoning as moderation: an owner can ask for a map, but only admin builds/verifies the geometry that other listings then attach to via indoor_unit_id. Does NOT by itself make the venue public — see map_published below.',
    map_published       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Admin-only "this venue''s floor plan is finished and reviewed" gate — separate from (and downstream of) has_map above, which is just the owner''s request flag. Flipped on AdminVenueMapController''s venue-maps list/editor once staff consider the floors/units done, not automatically. Venue::all()/find() (the public /api/venues list the app''s venue picker and every listing form''s indoor unit-picker cascade read from) require this to be 1, on top of has_map = 1 and at least one venue_floors row — so a venue an admin has only started drawing never appears for other listings to attach to, and Listing::showJson()''s has_object_map/object_map fields (the "View on map" button on a shop''s own page) stay false/null until this flips, so nobody sees an unfinished floor plan.',
    status              ENUM('pending', 'active', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
    rejection_reason    VARCHAR(500) NULL,
    views_count         INT UNSIGNED NOT NULL DEFAULT 0,
    published_at        DATETIME NULL,
    expires_at          DATETIME NULL,
    expiry_notified_at  DATETIME NULL COMMENT 'When the "expires in 3 days" email was sent — NULL until sent, reset on renew (see cron/midnight.php)',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_listings_slug (slug),
    KEY idx_listings_status (status),
    KEY idx_listings_vip (is_vip),
    KEY idx_listings_has_map (has_map),
    KEY idx_listings_map_published (map_published),
    KEY idx_listings_expires (expires_at),
    KEY idx_listings_category (category_id),
    KEY idx_listings_city (city_id),
    KEY idx_listings_owner (user_id),
    KEY idx_listings_location (lat, lng),
    UNIQUE KEY uq_listings_ruo_school_code (ruo_school_code),
    -- Nullable but unique: at most one active listing can claim a given unit at a time (NULL is
    -- excluded from uniqueness by InnoDB, so any number of listings can have no indoor placement
    -- at all). References venue_units below — forward reference is fine, FOREIGN_KEY_CHECKS is 0
    -- for this whole script (see top of file).
    UNIQUE KEY uq_listings_indoor_unit (indoor_unit_id),
    CONSTRAINT fk_listings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_listings_category FOREIGN KEY (category_id) REFERENCES categories (id),
    CONSTRAINT fk_listings_city FOREIGN KEY (city_id) REFERENCES cities (id),
    CONSTRAINT fk_listings_indoor_unit FOREIGN KEY (indoor_unit_id) REFERENCES venue_units (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS listing_media (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id  INT UNSIGNED NOT NULL,
    path        VARCHAR(255) NOT NULL,
    is_cover    TINYINT(1) NOT NULL DEFAULT 0,
    sort_order  INT NOT NULL DEFAULT 0,
    -- Attribution for media pulled from a source that requires it (e.g. Wikimedia Commons
    -- CC-BY-SA) rather than an owner's own upload — both NULL for owner uploads, which need
    -- none. credit is the free-text "Author, Source, License" line to render next to the
    -- photo; source_url links back to the original file/description page. Added for the
    -- internet-enrichment pass (see project notes) — Upload::storeImage() never sets these.
    credit      VARCHAR(255) NULL,
    source_url  VARCHAR(500) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_media_listing (listing_id),
    CONSTRAINT fk_media_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admission "класиране" (ranking round) score data for schools with profiled 7th-grade intake —
-- imported from RUO Sofia-grad's periodic "min/max bal по паралелки" .xlsx exports (currently 4
-- rounds a year, "1-во" through "4-то класиране") via AdminSchoolAdmissionController. Split into
-- two tables on purpose (2026-09-08 redesign, replacing an earlier single-table version that
-- collapsed straight to "latest round" and grouped by paralelka *name* — wrong on both counts:
-- RUO republishes all 4 rounds' cutoffs as genuinely separate numbers, not a running "final
-- result", and a school occasionally reworks a paralelka's officially registered name between
-- imports while its code stays put, or renumbers codes entirely between school years):
--
-- school_paralelki is the paralelka's *identity* — one row per RUO paralelka code, holding
-- whatever school it belongs to and its current name. paralelka_scores is pure time-series score
-- history keyed by that same code, decoupled from any listing — see each table's own comment.
CREATE TABLE IF NOT EXISTS school_paralelki (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id       INT UNSIGNED NOT NULL,
    school_number    INT UNSIGNED NOT NULL COMMENT 'RUO''s numeric school code (the "Училище Код" column) — same value as this listing''s ruo_school_code, kept here too so this table stands on its own per paralelka.',
    class_number     VARCHAR(20) NOT NULL COMMENT 'RUO''s own code for this paralelka (track) — the identity key joined against paralelka_scores.class_number below. NOT assumed stable across school years (a school can renumber its paralelki between admission cycles); stable within one year''s 4 rounds is the only guarantee.',
    class_name       VARCHAR(255) NOT NULL COMMENT 'Latest known name for this class_number — SchoolAdmissionScore::importRows() overwrites this on every import whose (year, round) is newer than name_source_year/round below, so out-of-order re-imports of an older file never clobber a newer name.',
    name_source_year  SMALLINT UNSIGNED NOT NULL,
    name_source_round TINYINT UNSIGNED NOT NULL,
    quote            TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'RUO marks a quota-admission paralelka ("прием по квоти" — set-aside seats) by coloring its whole row''s text blue (RGB FF0070C0, not a cell fill despite the sheet''s own "оцветените в синьо" note) — see XlsxReader::fontColors() and SchoolAdmissionScore::importRows(). Same "latest (year, round) wins" rule as class_name, since a paralelka''s quota status is set once per year (all 4 rounds share it) but isn''t guaranteed to stay the same across years.',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_school_paralelki_class_number (class_number),
    KEY idx_school_paralelki_listing (listing_id),
    CONSTRAINT fk_school_paralelki_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pure score history, independent of whether class_number's school has a matched listing yet (see
-- school_paralelki above) — so a school that gets matched later (ruo_school_code backfilled after
-- the fact) immediately has its full historical rounds available via the class_number join,
-- without needing those old export files re-imported. One row per (class_number, year, round);
-- boy/girl min/max only (RUO's "Общо" column is redundant with the two split ones and dropped).
CREATE TABLE IF NOT EXISTS paralelka_scores (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_number    VARCHAR(20) NOT NULL,
    year            SMALLINT UNSIGNED NOT NULL,
    round           TINYINT UNSIGNED NOT NULL COMMENT '1-4 — which "класиране" this row is from, within `year`.',
    boy_min         DECIMAL(6, 2) NULL,
    boy_max         DECIMAL(6, 2) NULL,
    girl_min        DECIMAL(6, 2) NULL,
    girl_max        DECIMAL(6, 2) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_paralelka_scores_code_year_round (class_number, year, round),
    KEY idx_paralelka_scores_code (class_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS listing_amenities (
    listing_id  INT UNSIGNED NOT NULL,
    amenity_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (listing_id, amenity_id),
    CONSTRAINT fk_la_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_la_amenity FOREIGN KEY (amenity_id) REFERENCES amenities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS listing_tags (
    listing_id  INT UNSIGNED NOT NULL,
    tag_id      INT UNSIGNED NOT NULL,
    PRIMARY KEY (listing_id, tag_id),
    CONSTRAINT fk_lt_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_lt_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Indoor venue map (see mall-map research thread) — a venue (mall, museum,
-- ...) is just a normal listing, already shown as an outdoor marker under
-- its own category; these two tables add its floor plan on top. A listing
-- counts as a "venue" for the public app only once it has rows in
-- venue_floors AND has_map = 1 AND map_published = 1 (Venue::all()/find()
-- check all three) — a venue that already has floors drawn still drops out
-- of the public app the moment either flag is switched back off, so an
-- owner really can "unpublish" their map without an admin having to delete
-- the underlying geometry, and an admin can just as easily un-finish a
-- venue that turns out to need more work after being published.
-- listings.has_map above only ever *adds* the listing to the admin's Карта
-- на обект queue for staff to build/maintain those floors/units — it's a
-- request flag, not itself proof a finished map exists; map_published is
-- the admin's separate "yes, this one's actually done" sign-off, and is
-- also what gates Listing::showJson()'s has_object_map field for a tenant
-- occupying one of this venue's units (see that column's own comment
-- above) — a shop's "View on map" button only appears once its venue's map
-- is published. A tenant (an individual shop)
-- points at the one venue_units row it
-- occupies via listings.indoor_unit_id above; it keeps its own normal
-- category_id/tags/phone/opening_hours exactly like any outdoor listing, so
-- no separate content type or category system exists for "a shop inside a
-- mall" — only the shape geometry is new.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS venue_floors (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    venue_listing_id    INT UNSIGNED NOT NULL,
    floor_order         INT NOT NULL COMMENT 'Sort + default-selection ordinal, e.g. 0 = ground — OSM/IMDF level convention, negative = below ground',
    short_label         VARCHAR(8) NOT NULL COMMENT 'Plain floor-switcher label shown in the app, e.g. "0", "1", "-1", "M" (see MallMapScreen)',
    name                VARCHAR(120) NULL COMMENT 'Optional descriptive name (e.g. "Партер") for admin reference — short_label is what the UI actually shows',
    image_path          VARCHAR(255) NULL COMMENT 'Admin-only tracing reference (e.g. a photographed directory board or floor render), uploaded via AdminVenueMapController so staff can draw unit polygons on top of it in the browser editor. Never served by the public API/app — only the vector venue_units rows are, so this never leaks anything scrapable.',
    canvas_width        INT UNSIGNED NOT NULL DEFAULT 1000 COMMENT 'The editor''s own abstract drawing canvas size for this floor (see admin/venue-map-edit.tpl.php) — defaults to a fixed 1000x700 for a freehand-drawn floor, but is overwritten to match the source file''s own viewBox when an SVG floor plan is imported (see AdminVenueMapController::importUnits()), so every venue_units.shape_points row on this floor stays in one consistent coordinate space without ever rescaling the imported geometry. Purely an admin-editor concern — sofiago-flutter''s MallFloorLayout derives its own bounds from the units'' own bounding box and never reads this.',
    canvas_height       INT UNSIGNED NOT NULL DEFAULT 700 COMMENT 'See canvas_width.',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_venue_floors_order (venue_listing_id, floor_order),
    KEY idx_venue_floors_venue (venue_listing_id),
    CONSTRAINT fk_venue_floors_venue FOREIGN KEY (venue_listing_id) REFERENCES listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venue_units (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    floor_id        INT UNSIGNED NOT NULL,
    unit_code       VARCHAR(40) NULL COMMENT 'Optional display code (e.g. "A12") for the owner-facing "pick your unit" list — not shown to public map viewers',
    shape_type      ENUM('rectangle', 'circle', 'polygon') NOT NULL,
    -- [[x,y], ...] in the floor's own local canvas units (not geographic) — two points
    -- (opposite corners) for rectangle, one center point for circle (paired with radius below),
    -- full vertex list for polygon. Mirrors MallShape's Flutter model 1:1 (mall_venue_data.dart)
    -- so the API hands this straight through with no reshaping.
    shape_points    JSON NOT NULL,
    radius          DECIMAL(10, 2) NULL COMMENT 'Circle only — see shape_points',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Lets seed-demo.sql (and any future re-import) upsert by code instead of duplicating rows on
    -- every run — multiple NULL unit_codes are still allowed (MySQL excludes NULL from
    -- uniqueness), so a venue that doesn't bother with codes isn't forced to invent them.
    UNIQUE KEY uq_venue_units_floor_code (floor_id, unit_code),
    KEY idx_venue_units_floor (floor_id),
    CONSTRAINT fk_venue_units_floor FOREIGN KEY (floor_id) REFERENCES venue_floors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IMPORTANT for a DB that already had a `listings` table before indoor_unit_id existed (this
-- project has no migrations framework — `CREATE TABLE IF NOT EXISTS listings` above is a silent
-- no-op on such a DB, so the column/constraint baked into that CREATE TABLE never actually lands
-- there): run this once by hand —
--   ALTER TABLE listings
--       ADD COLUMN indoor_unit_id INT UNSIGNED NULL AFTER lng,
--       ADD UNIQUE KEY uq_listings_indoor_unit (indoor_unit_id),
--       ADD CONSTRAINT fk_listings_indoor_unit FOREIGN KEY (indoor_unit_id) REFERENCES venue_units (id) ON DELETE SET NULL;
-- (an `IF NOT EXISTS` version that could run unconditionally as part of this file would be nicer,
-- but MariaDB — even 11.4 — rejects `ADD CONSTRAINT IF NOT EXISTS symbol FOREIGN KEY (...)` as a
-- syntax error, so there's no form of this that's actually safe to run blindly on every deploy).
-- Already applied to new.sofiago.eu's DB by hand on 2026-09-06 — a genuinely fresh install
-- doesn't need it, the CREATE TABLE above already has both.
--
-- Same story for has_map/venue_floors.image_path, added later the same day — on a DB that
-- already had `listings`/`venue_floors` tables before this point, run:
--   ALTER TABLE listings ADD COLUMN IF NOT EXISTS has_map TINYINT(1) NOT NULL DEFAULT 0 AFTER is_vip;
--   ALTER TABLE listings ADD INDEX IF NOT EXISTS idx_listings_has_map (has_map);
--   ALTER TABLE venue_floors ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) NULL AFTER name;
-- (plain columns/indexes, not constraints, so `IF NOT EXISTS` genuinely is safe here and these
-- three are idempotent to run on every deploy, unlike the FK/UNIQUE pair above.)
--
-- Same story again for venue_floors.canvas_width/canvas_height (SVG-import feature):
--   ALTER TABLE venue_floors ADD COLUMN IF NOT EXISTS canvas_width INT UNSIGNED NOT NULL DEFAULT 1000 AFTER image_path;
--   ALTER TABLE venue_floors ADD COLUMN IF NOT EXISTS canvas_height INT UNSIGNED NOT NULL DEFAULT 700 AFTER canvas_width;
--
-- Same story again for the single `description` column splitting into description_en/bg/ru
-- (per-language content — see the column comments above) — on a DB that already had `listings`
-- before this point, run:
--   ALTER TABLE listings
--       ADD COLUMN IF NOT EXISTS description_en TEXT NOT NULL DEFAULT '' AFTER slug,
--       ADD COLUMN IF NOT EXISTS description_bg TEXT NULL AFTER description_en,
--       ADD COLUMN IF NOT EXISTS description_ru TEXT NULL AFTER description_bg;
--   UPDATE listings SET description_en = description WHERE description IS NOT NULL AND description <> '';
--   -- (then translate description_en per row, and/or copy the original into description_bg/ru —
--   -- see the 2026-09-07 enrichment pass notes for how new.sofiago.eu's existing rows were split)
--   ALTER TABLE listings DROP COLUMN description;
-- Plain columns, so `IF NOT EXISTS` is safe to run unconditionally; the DROP COLUMN is not and
-- must only run once, after the data is migrated out of it — already done by hand on
-- new.sofiago.eu on 2026-09-07. A genuinely fresh install doesn't need any of this, the CREATE
-- TABLE above already has the three-column shape.
--
-- Same story again for listings.map_published (admin "map is finished" sign-off, split out from
-- has_map — see that column's comment): on a DB that already had `listings` before this point,
-- run:
--   ALTER TABLE listings ADD COLUMN IF NOT EXISTS map_published TINYINT(1) NOT NULL DEFAULT 0 AFTER has_map;
--   ALTER TABLE listings ADD INDEX IF NOT EXISTS idx_listings_map_published (map_published);
-- Plain column + index, so safe to run unconditionally. Any venue an admin had already fully
-- built before this point needs its existing rows backfilled by hand (there was no signal in the
-- old data for "done" vs. "in progress" to derive it from automatically) — e.g.:
--   UPDATE listings SET map_published = 1 WHERE id IN (1, 2, 3);
-- A genuinely fresh install doesn't need any of this, the CREATE TABLE above already has the
-- column.
--
-- Same story again for listings.ruo_school_code + the school_paralelki/paralelka_scores tables
-- (see all their comments above) — on a DB that already had `listings` before this point, run:
--   ALTER TABLE listings ADD COLUMN IF NOT EXISTS ruo_school_code INT UNSIGNED NULL AFTER indoor_unit_id;
--   ALTER TABLE listings ADD UNIQUE INDEX IF NOT EXISTS uq_listings_ruo_school_code (ruo_school_code);
-- then the two CREATE TABLE IF NOT EXISTS statements above create the new tables themselves.
-- All idempotent, safe to run unconditionally. A genuinely fresh install doesn't need any of
-- this, the CREATE TABLE above already has the column/tables.
--
-- 2026-09-08: school_admission_scores (the single-table first draft — grouped by paralelka
-- *name* and collapsed to "latest round only", both wrong, see school_paralelki's comment above)
-- was dropped and replaced by school_paralelki + paralelka_scores. On a DB that still has it:
--   DROP TABLE IF EXISTS school_admission_scores;

CREATE TABLE IF NOT EXISTS favorites (
    user_id     INT UNSIGNED NOT NULL,
    listing_id  INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, listing_id),
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comments on a listing (guests + logged-in users, see CommentController). Published only
-- after admin approval; posting is gated by a Cloudflare Turnstile challenge (see Turnstile.php).
CREATE TABLE IF NOT EXISTS comments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NULL COMMENT 'NULL for a guest comment — see guest_name/guest_email',
    guest_name  VARCHAR(120) NULL,
    guest_email VARCHAR(190) NULL,
    body        TEXT NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL COMMENT '1-5',
    status      ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    ip          VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comments_listing (listing_id, status),
    KEY idx_comments_status (status),
    CONSTRAINT fk_comments_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Report a problem" (ListingReportController) — guests + logged-in users, same anonymous-
-- capable + Turnstile-gated shape as comments above. Purely a moderation queue: resolving/
-- dismissing a report never touches the listing itself, an admin acts on it by hand.
CREATE TABLE IF NOT EXISTS listing_reports (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NULL COMMENT 'NULL for an anonymous report',
    reason      ENUM('closed_permanently', 'wrong_info', 'duplicate', 'inappropriate', 'spam', 'other') NOT NULL,
    message     TEXT NULL,
    status      ENUM('pending', 'resolved', 'dismissed') NOT NULL DEFAULT 'pending',
    ip          VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_listing_reports_listing (listing_id, status),
    KEY idx_listing_reports_status (status),
    CONSTRAINT fk_listing_reports_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_listing_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "This is my object" (OwnershipClaimController) — signed-in users only, requesting to become
-- a listing's owner (listings.user_id). Approving a claim (AdminController::
-- approveOwnershipClaim() -> OwnershipClaim::approve()) transfers user_id to the claimant and
-- auto-rejects any other still-pending claims on the same listing.
CREATE TABLE IF NOT EXISTS ownership_claims (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id       INT UNSIGNED NOT NULL,
    user_id          INT UNSIGNED NOT NULL COMMENT 'Claimant requesting to become owner',
    message          TEXT NULL,
    status           ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    rejection_reason VARCHAR(500) NULL,
    reviewed_at      DATETIME NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ownership_claims_listing (listing_id, status),
    KEY idx_ownership_claims_user (user_id, status),
    CONSTRAINT fk_ownership_claims_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_ownership_claims_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Groundwork for a later reviews/ratings phase — not wired into the UI yet.
CREATE TABLE IF NOT EXISTS reviews (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL COMMENT '1-5',
    comment     TEXT NULL,
    status      ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reviews_listing (listing_id),
    KEY idx_reviews_user (user_id),
    CONSTRAINT fk_reviews_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Seed data
-- ---------------------------------------------------------------------------

INSERT INTO cities (name, slug, lat, lng) VALUES
    ('Sofia', 'sofia', 42.6977, 23.3219)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO categories (name, slug, icon, sort_order) VALUES
    ('Restaurants & Cafes', 'restaurants-cafes', 'fa-utensils', 10),
    ('Shopping', 'shopping', 'fa-bag-shopping', 20),
    ('Health & Beauty', 'health-beauty', 'fa-shield-virus', 30),
    ('Nightlife', 'nightlife', 'fa-martini-glass', 40),
    ('Attractions & Sightseeing', 'attractions-sightseeing', 'fa-landmark', 50),
    ('Hotels & Accommodation', 'hotels-accommodation', 'fa-bed', 60),
    ('Services', 'services', 'fa-screwdriver-wrench', 70),
    ('Sports & Recreation', 'sports-recreation', 'fa-dumbbell', 80),
    ('Culture & Art', 'culture-art', 'fa-palette', 90),
    ('Automotive', 'automotive', 'fa-car', 100),
    -- Added for the sofiago-flutter POI migration (schools/libraries/kindergartens, cinemas,
    -- mineral springs — none of the 10 categories above fit): sort_order continues past 100 so
    -- they land after the original set in any sort_order-ordered listing.
    ('Education', 'education', 'fa-graduation-cap', 110),
    ('Cinemas', 'cinemas', 'fa-film', 120),
    ('Nature & Wellness', 'nature-wellness', 'fa-spa', 130)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO amenities (name, icon) VALUES
    ('Parking', 'fa-square-parking'),
    ('Wi-Fi', 'fa-wifi'),
    ('Wheelchair accessible', 'fa-wheelchair'),
    ('Outdoor seating', 'fa-umbrella-beach'),
    ('Card payment', 'fa-credit-card'),
    ('Pet friendly', 'fa-paw'),
    ('Delivery', 'fa-truck'),
    ('Air conditioning', 'fa-snowflake')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Fixed tag vocabulary — narrows within a category that groups several distinct sub-types (see
-- Listing::buildWhere()'s tag JOIN and poi_catalog.dart's doc comment for the mechanism this
-- reuses). Deliberately NOT free text: ListingManageController::syncAmenitiesAndTags() only
-- accepts ids from this table (see Tag::all()) — a listing owner picks from a fixed list rather
-- than typing their own, so "shopinggood"/"supermagazin"-style one-off tags can't happen and
-- every consumer (the mall-map floor filter, /explore's future tag filters) can rely on a known,
-- stable slug set. Extend this list (and Tag::KEYS + lang/*.json's tag.* keys) to add a new one —
-- never let user input create a tags row.
INSERT INTO tags (name, slug) VALUES
    -- Migrated with the sofiago-flutter POI toggles (bars/nightclubs under nightlife,
    -- schools/kindergartens/libraries under education, gallery/museum/temple/theatre under
    -- culture-art) — see poi_catalog.dart's tagSlug values.
    ('Bar', 'bar'),
    ('Nightclub', 'nightclub'),
    ('School', 'school'),
    ('Kindergarten', 'kindergarten'),
    ('Library', 'library'),
    ('Gallery', 'gallery'),
    ('Museum', 'museum'),
    ('Temple', 'temple'),
    ('Theatre', 'theatre'),
    -- Mall-unit sub-categories (see the mall-map research thread) — narrow within 'shopping' for
    -- most units, 'restaurants-cafes' for food-court units. Modelled on Sofia Ring Mall's own
    -- category list (sofiaring.bg/shops) rather than invented from scratch.
    ('Fashion', 'fashion'),
    ('Footwear, Bags & Leather Goods', 'footwear-leather'),
    ('Lingerie', 'lingerie'),
    ('Cosmetics & Pharmacy', 'cosmetics-pharmacy'),
    ('Kids & Toys', 'kids-toys'),
    ('Optics, Jewelry & Gifts', 'optics-jewelry-gifts'),
    ('Home & Furniture', 'home-furniture'),
    ('Sports Goods', 'sports-goods'),
    ('Electronics & Books', 'electronics-books'),
    ('Supermarket', 'supermarket'),
    ('Food Court', 'food-court'),
    ('Cafe', 'cafe'),
    ('Fast Food', 'fast-food')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Which category each tag above is offered under (see category_tags' own doc comment) — a few
-- tags straddle two categories on purpose (e.g. a food court is as much 'restaurants-cafes' as
-- it is 'shopping'; cosmetics-pharmacy fits both 'shopping' and 'health-beauty'). Categories not
-- listed here (hotels-accommodation, services, automotive, cinemas, nature-wellness) simply have
-- no tags yet — their listing form shows no tag checkboxes, which is a valid state, not a bug.
INSERT INTO category_tags (category_id, tag_id)
SELECT c.id, t.id FROM (
    SELECT 'nightlife' AS cat, 'bar' AS tag
    UNION ALL SELECT 'nightlife', 'nightclub'
    UNION ALL SELECT 'education', 'school'
    UNION ALL SELECT 'education', 'kindergarten'
    UNION ALL SELECT 'education', 'library'
    UNION ALL SELECT 'culture-art', 'library'
    UNION ALL SELECT 'culture-art', 'gallery'
    UNION ALL SELECT 'culture-art', 'museum'
    UNION ALL SELECT 'culture-art', 'theatre'
    UNION ALL SELECT 'attractions-sightseeing', 'gallery'
    UNION ALL SELECT 'attractions-sightseeing', 'museum'
    UNION ALL SELECT 'attractions-sightseeing', 'temple'
    UNION ALL SELECT 'shopping', 'fashion'
    UNION ALL SELECT 'shopping', 'footwear-leather'
    UNION ALL SELECT 'shopping', 'lingerie'
    UNION ALL SELECT 'shopping', 'cosmetics-pharmacy'
    UNION ALL SELECT 'health-beauty', 'cosmetics-pharmacy'
    UNION ALL SELECT 'shopping', 'kids-toys'
    UNION ALL SELECT 'shopping', 'optics-jewelry-gifts'
    UNION ALL SELECT 'shopping', 'home-furniture'
    UNION ALL SELECT 'shopping', 'sports-goods'
    UNION ALL SELECT 'sports-recreation', 'sports-goods'
    UNION ALL SELECT 'shopping', 'electronics-books'
    UNION ALL SELECT 'shopping', 'supermarket'
    UNION ALL SELECT 'shopping', 'food-court'
    UNION ALL SELECT 'restaurants-cafes', 'food-court'
    UNION ALL SELECT 'restaurants-cafes', 'cafe'
    UNION ALL SELECT 'restaurants-cafes', 'fast-food'
) x
JOIN categories c ON c.slug = x.cat
JOIN tags t ON t.slug = x.tag
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id);
