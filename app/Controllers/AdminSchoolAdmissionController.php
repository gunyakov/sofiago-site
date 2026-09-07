<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

use Sofiago\Core\XlsxReader;
use Sofiago\Middleware\Guards;
use Sofiago\Models\SchoolAdmissionScore;

/**
 * Admin-only importer for RUO Sofia-grad's "min/max bal по паралелки" school admission-score
 * exports (see school_admission_scores' comment in schema.sql). RUO publishes one of these after
 * each 7th-grade "класиране" (ranking round) — currently four a year — in the exact same column
 * layout every time, so this is meant to be reused indefinitely: an admin downloads whatever
 * RUO's site has posted, picks the year/round it's for, and uploads it here. Matching happens by
 * listings.ruo_school_code (backfilled once per school), not by parsing the school name out of
 * the file, so it keeps working unattended as long as that code stays set.
 */
final class AdminSchoolAdmissionController
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** GET /admin/school-scores */
    public function index(array $params): void
    {
        Guards::requireAdmin();

        echo view('admin/school-scores.tpl.php', [
            'title' => t('admin.school_scores_title'),
        ], 'layout/dashboard.tpl.php');
    }

    /** POST /admin/school-scores/import */
    public function import(array $params): void
    {
        Guards::requireAdmin();
        Guards::verifyCsrf();

        $year = (int) ($_POST['year'] ?? 0);
        $round = (int) ($_POST['round'] ?? 0);

        if ($year < 2000 || $year > 2100 || $round < 1 || $round > 4) {
            app()->session->flash('error', t('admin.school_scores_bad_params'));
            redirect('/admin/school-scores');
        }

        $file = $_FILES['file'] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            app()->session->flash('error', t('admin.school_scores_no_file'));
            redirect('/admin/school-scores');
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            app()->session->flash('error', t('admin.school_scores_upload_error'));
            redirect('/admin/school-scores');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            app()->session->flash('error', t('admin.school_scores_upload_error'));
            redirect('/admin/school-scores');
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            app()->session->flash('error', t('admin.school_scores_too_large'));
            redirect('/admin/school-scores');
        }

        if (!str_ends_with(strtolower((string) ($file['name'] ?? '')), '.xlsx')) {
            app()->session->flash('error', t('admin.school_scores_invalid_type'));
            redirect('/admin/school-scores');
        }

        try {
            $rows = XlsxReader::rows($tmpName);
            $fontColors = XlsxReader::fontColors($tmpName);
        } catch (\Throwable) {
            app()->session->flash('error', t('admin.school_scores_parse_error'));
            redirect('/admin/school-scores');
        }

        $result = SchoolAdmissionScore::importRows($rows, $year, $round, $fontColors);

        app()->session->flash('notice', t('admin.school_scores_import_done', [
            'imported' => (string) $result['imported'],
            'unmatched' => (string) count($result['unmatched']),
        ]));

        if ($result['unmatched'] !== []) {
            $names = array_map(
                static fn (array $u): string => $u['name'] . ' (' . $u['code'] . ')',
                $result['unmatched']
            );
            app()->session->flash('warning', t('admin.school_scores_unmatched_list') . ' ' . implode(', ', $names));
        }

        redirect('/admin/school-scores');
    }
}
