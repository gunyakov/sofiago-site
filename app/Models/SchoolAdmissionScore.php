<?php

declare(strict_types=1);

namespace Sofiago\Models;

/**
 * Admission "класиране" (ranking round) score data for schools with profiled 7th-grade intake —
 * imported from RUO Sofia-grad's periodic "min/max bal по паралелки" .xlsx exports via
 * AdminSchoolAdmissionController. See school_paralelki/paralelka_scores' comments in schema.sql
 * for the two-table shape and why it's split this way.
 */
final class SchoolAdmissionScore
{
    /** RUO's blue text-color RGB for a quota-admission row — see XlsxReader::fontColors(). */
    private const QUOTA_FONT_COLOR = 'FF0070C0';

    /**
     * Upserts both tables from one file's rows: school_paralelki (the paralelka's identity —
     * which listing/school it belongs to, its current name) and paralelka_scores (this round's
     * boy/girl min/max). A row whose school code has no matching listing still gets its score
     * history stored (paralelka_scores doesn't need a listing), just no school_paralelki entry —
     * reported back as "unmatched" the same as before, and backfillable later without re-
     * importing old files once that school's ruo_school_code is set (see forListing()).
     *
     * @param array<int, array<int, mixed>> $rows Raw XlsxReader::rows() output for one file —
     *   header row position varies by a line or two between exports, and some drop the leading
     *   "№" row-number column entirely (shifting every other column left by one), so this scans
     *   for the first row that actually looks like data rather than assuming a fixed offset.
     * @param array<int, array<int, string>> $fontColors XlsxReader::fontColors() output for the
     *   same file — RUO marks a quota-admission paralelka by coloring its whole row's *text*
     *   blue (not a cell fill, despite the sheet's own "оцветените в синьо" note), so this reads
     *   the name cell's color for each row rather than needing a separate quota column.
     * @return array{imported: int, unmatched: array<int, array{code: int, name: string}>}
     */
    public static function importRows(array $rows, int $year, int $round, array $fontColors = []): array
    {
        $dataStart = null;
        $base = null; // Column index of the school-code cell: 1 (with a leading "№" column) or 0 (without).

        foreach ($rows as $i => $row) {
            if (isset($row[1]) && is_numeric($row[1]) && (int) $row[1] > 1000) {
                $dataStart = $i;
                $base = 1;
                break;
            }
            if (isset($row[0]) && is_numeric($row[0]) && (int) $row[0] > 1000) {
                $dataStart = $i;
                $base = 0;
                break;
            }
        }

        if ($dataStart === null) {
            return ['imported' => 0, 'unmatched' => []];
        }

        $imported = 0;
        $unmatched = [];
        $unmatchedCodes = [];

        for ($i = $dataStart; $i < count($rows); $i++) {
            $row = $rows[$i];

            $schoolNumber = isset($row[$base]) && is_numeric($row[$base]) ? (int) $row[$base] : null;
            $schoolName = isset($row[$base + 1]) ? trim((string) $row[$base + 1]) : '';
            $classNumber = isset($row[$base + 2]) ? trim((string) $row[$base + 2]) : '';
            // RUO's own export is inconsistent about zero-padding this code — the very same
            // paralelka has shown up as both "2561" and "02561" across different rounds of the
            // same year — so strip leading zeros whenever it's purely numeric, or two rows that
            // are really one paralelka end up as separate class_numbers.
            if ($classNumber !== '' && ctype_digit($classNumber)) {
                $classNumber = (string) (int) $classNumber;
            }
            $className = isset($row[$base + 3]) ? trim((string) $row[$base + 3]) : '';
            $minMale = isset($row[$base + 5]) && is_numeric($row[$base + 5]) ? (float) $row[$base + 5] : null;
            $minFemale = isset($row[$base + 6]) && is_numeric($row[$base + 6]) ? (float) $row[$base + 6] : null;
            $maxMale = isset($row[$base + 8]) && is_numeric($row[$base + 8]) ? (float) $row[$base + 8] : null;
            $maxFemale = isset($row[$base + 9]) && is_numeric($row[$base + 9]) ? (float) $row[$base + 9] : null;

            if ($schoolNumber === null || $classNumber === '') {
                continue; // Blank trailing row or footnote line.
            }

            $quote = ($fontColors[$i][$base + 3] ?? null) === self::QUOTA_FONT_COLOR;

            // Score history is independent of listing matching — always store it.
            db()->query(
                'INSERT INTO paralelka_scores (class_number, year, round, boy_min, boy_max, girl_min, girl_max)
                 VALUES (:class_number, :year, :round, :boy_min, :boy_max, :girl_min, :girl_max)
                 ON DUPLICATE KEY UPDATE
                    boy_min = VALUES(boy_min), boy_max = VALUES(boy_max),
                    girl_min = VALUES(girl_min), girl_max = VALUES(girl_max)',
                [
                    'class_number' => $classNumber,
                    'year' => $year,
                    'round' => $round,
                    'boy_min' => $minMale,
                    'boy_max' => $maxMale,
                    'girl_min' => $minFemale,
                    'girl_max' => $maxFemale,
                ]
            );
            $imported++;

            $listingId = db()->value('SELECT id FROM listings WHERE ruo_school_code = ?', [$schoolNumber]);

            if ($listingId === null) {
                if (!isset($unmatchedCodes[$schoolNumber])) {
                    $unmatchedCodes[$schoolNumber] = true;
                    $unmatched[] = ['code' => $schoolNumber, 'name' => $schoolName];
                }
                continue;
            }

            // "Latest name wins": only overwrite class_name if this row is from a (year, round)
            // at or after whatever this class_number's name currently came from, so importing an
            // older file after a newer one (any order) never regresses the displayed name.
            $existing = db()->one(
                'SELECT name_source_year, name_source_round FROM school_paralelki WHERE class_number = ?',
                [$classNumber]
            );
            $isNewer = $existing === null
                || $year > (int) $existing['name_source_year']
                || ($year === (int) $existing['name_source_year'] && $round >= (int) $existing['name_source_round']);

            if ($isNewer) {
                db()->query(
                    'INSERT INTO school_paralelki (listing_id, school_number, class_number, class_name, quote, name_source_year, name_source_round)
                     VALUES (:listing_id, :school_number, :class_number, :class_name, :quote, :year, :round)
                     ON DUPLICATE KEY UPDATE
                        listing_id = VALUES(listing_id), school_number = VALUES(school_number),
                        class_name = VALUES(class_name), quote = VALUES(quote),
                        name_source_year = VALUES(name_source_year), name_source_round = VALUES(name_source_round)',
                    [
                        'listing_id' => $listingId,
                        'school_number' => $schoolNumber,
                        'class_number' => $classNumber,
                        'class_name' => $className,
                        'quote' => $quote ? 1 : 0,
                        'year' => $year,
                        'round' => $round,
                    ]
                );
            }
        }

        return ['imported' => $imported, 'unmatched' => $unmatched];
    }

    /**
     * Grouped for display: year (newest first) => paralelki for that listing with scores in that
     * year => the 4 rounds' boy/girl min/max. Every round that has ANY score for that year is
     * included (not just the last one) — see AdminSchoolAdmissionController/schema.sql for why
     * collapsing to "latest round" was wrong. A paralelka only shows up under years it actually
     * has paralelka_scores rows for, so a school that renumbered its codes between years
     * naturally lists as separate rows per year (see school_paralelki's comment — codes aren't
     * guaranteed stable across school years, only within one year's 4 rounds).
     *
     * @return array<int, array<string, array{class_number: string, class_name: string, quote: bool,
     *   rounds: array<int, array{boy_min: ?float, boy_max: ?float, girl_min: ?float, girl_max: ?float}>}>>
     *   Outer key: year (desc). Inner key: class_number. rounds keyed 1-4, only present rounds included.
     */
    public static function forListing(int $listingId): array
    {
        $rows = db()->all(
            'SELECT sp.class_number, sp.class_name, sp.quote, ps.year, ps.round,
                    ps.boy_min, ps.boy_max, ps.girl_min, ps.girl_max
             FROM school_paralelki sp
             JOIN paralelka_scores ps ON ps.class_number = sp.class_number
             WHERE sp.listing_id = ?
             ORDER BY ps.year DESC, sp.class_number ASC, ps.round ASC',
            [$listingId]
        );

        $byYear = [];

        foreach ($rows as $row) {
            $year = (int) $row['year'];
            $code = $row['class_number'];

            if (!isset($byYear[$year][$code])) {
                $byYear[$year][$code] = [
                    'class_number' => $code,
                    'class_name' => $row['class_name'],
                    'quote' => (bool) $row['quote'],
                    'rounds' => [],
                ];
            }

            $byYear[$year][$code]['rounds'][(int) $row['round']] = [
                'boy_min' => $row['boy_min'] !== null ? (float) $row['boy_min'] : null,
                'boy_max' => $row['boy_max'] !== null ? (float) $row['boy_max'] : null,
                'girl_min' => $row['girl_min'] !== null ? (float) $row['girl_min'] : null,
                'girl_max' => $row['girl_max'] !== null ? (float) $row['girl_max'] : null,
            ];
        }

        krsort($byYear);

        return $byYear;
    }
}
