<?php

declare(strict_types=1);

namespace Sofiago\Core;

/**
 * Minimal, dependency-free .xlsx reader — just enough to turn a plain "one sheet, no formulas,
 * no merged cells" workbook into an array of rows. Written for RUO Sofia-grad's "min/max bal по
 * паралелки" school admission-score exports (see SchoolAdmissionScore) rather than as a general
 * spreadsheet library — the project has no PHP package manager dependency for this (composer.json
 * only carries phpmailer) and pulling in something like PhpSpreadsheet for "read some numbers out
 * of one sheet" would be a lot of surface area for what .xlsx actually is: a zip of small XML
 * parts. This just unzips the two parts that matter (the shared-strings table and sheet 1) and
 * reads cell values straight out of them.
 */
final class XlsxReader
{
    /**
     * @return array<int, array<int, string|float|null>> Rows in file order, 0-indexed both ways
     *   (row 0 = the sheet's first row, column 0 = column A). A cell with no value is null; a row
     *   is only as long as its last non-empty cell — short/blank rows are not padded out.
     */
    public static function rows(string $path): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Could not open as .xlsx (not a valid zip): ' . $path);
        }

        $sharedStrings = self::readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new \RuntimeException('xl/worksheets/sheet1.xml not found — not a single-sheet .xlsx workbook: ' . $path);
        }

        $doc = self::parseXml($sheetXml);
        $doc->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];

        foreach ($doc->xpath('//s:sheetData/s:row') as $rowEl) {
            $cells = [];

            foreach ($rowEl->c as $c) {
                $colIndex = self::columnIndex((string) $c['r']);
                $type = (string) $c['t'];
                $raw = isset($c->v) ? (string) $c->v : null;

                if ($raw === null) {
                    $value = null;
                } elseif ($type === 's') {
                    // Shared-string index — the cell's actual text lives in sharedStrings.xml, not here.
                    $value = $sharedStrings[(int) $raw] ?? '';
                } elseif ($type === 'str' || $type === 'inlineStr') {
                    $value = $raw;
                } else {
                    // No `t` attribute (or `t="n"`) means a plain number.
                    $value = is_numeric($raw) ? $raw + 0 : $raw;
                }

                $cells[$colIndex] = $value;
            }

            if ($cells === []) {
                $rows[] = [];
                continue;
            }

            $width = max(array_keys($cells)) + 1;
            $row = [];
            for ($i = 0; $i < $width; $i++) {
                $row[$i] = $cells[$i] ?? null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Font-color RGB (e.g. "FF0070C0") per cell, same 0-indexed row/column scheme as rows() —
     * written for RUO's "оцветените в синьо паралелки са с прием по квоти" note (quota-admission
     * rows are marked by blue *text*, not a cell fill, across the whole row). Cells with no
     * explicit color (inherited/default, i.e. plain black) are simply absent from the result.
     *
     * @return array<int, array<int, string>>
     */
    public static function fontColors(string $path): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Could not open as .xlsx (not a valid zip): ' . $path);
        }

        $stylesXml = $zip->getFromName('xl/styles.xml');
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($stylesXml === false || $sheetXml === false) {
            return [];
        }

        $fontColorByXf = self::readFontColorsByStyleIndex($stylesXml);

        $doc = self::parseXml($sheetXml);
        $doc->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $result = [];

        foreach ($doc->xpath('//s:sheetData/s:row') as $rowIndex => $rowEl) {
            foreach ($rowEl->c as $c) {
                $styleIndex = isset($c['s']) ? (int) $c['s'] : 0;
                $color = $fontColorByXf[$styleIndex] ?? null;

                if ($color !== null) {
                    $result[$rowIndex][self::columnIndex((string) $c['r'])] = $color;
                }
            }
        }

        return $result;
    }

    /** cellXfs index -> that style's font's RGB color (e.g. "FF0070C0"), only for styles that have an explicit rgb color. */
    private static function readFontColorsByStyleIndex(string $stylesXml): array
    {
        $doc = self::parseXml($stylesXml);
        $doc->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $fontColors = []; // font index -> rgb
        foreach ($doc->xpath('//s:fonts/s:font') as $i => $font) {
            if (isset($font->color) && isset($font->color['rgb'])) {
                $fontColors[$i] = (string) $font->color['rgb'];
            }
        }

        $byXf = []; // cellXfs index -> rgb
        foreach ($doc->xpath('//s:cellXfs/s:xf') as $i => $xf) {
            $fontId = isset($xf['fontId']) ? (int) $xf['fontId'] : 0;

            if (isset($fontColors[$fontId])) {
                $byXf[$i] = $fontColors[$fontId];
            }
        }

        return $byXf;
    }

    /** @return array<int, string> Shared-string-table index => text. */
    private static function readSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return []; // Workbook never used the shared-strings table (all-numeric sheet, or none written yet).
        }

        $doc = self::parseXml($xml);
        $doc->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $strings = [];

        foreach ($doc->xpath('//s:sst/s:si') as $si) {
            // A shared string is either one plain <t> or several <r><t> "rich text" runs — concatenate either way.
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
                continue;
            }

            // Rich-text run children don't reliably inherit the doc-level xpath namespace
            // registration from here, so walk them via namespaced children() instead of xpath().
            $text = '';
            $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            foreach ($si->children($ns)->r as $run) {
                $text .= (string) $run->children($ns)->t;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private static function parseXml(string $xml): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            throw new \RuntimeException('Malformed XML inside .xlsx');
        }

        return $doc;
    }

    /** "C7" -> 2 (zero-based column index); ignores the row-number part of the reference. */
    private static function columnIndex(string $cellRef): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($cellRef)) ?? '';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - ord('A') + 1);
        }

        return $index - 1;
    }
}
