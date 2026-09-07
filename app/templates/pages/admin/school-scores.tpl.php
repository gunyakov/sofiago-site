<div class="container-xxl py-4">
    <h1 class="mb-2"><?= e(t('admin.school_scores_title')) ?></h1>
    <p class="text-muted mb-4"><?= e(t('admin.school_scores_hint')) ?></p>

    <div class="card p-4" style="max-width: 520px;">
        <form method="post" action="<?= e(url('/admin/school-scores/import')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label fw-medium"><?= e(t('admin.school_scores_year')) ?></label>
                <input type="number" name="year" class="form-control" min="2000" max="2100" value="<?= e((string) date('Y')) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-medium"><?= e(t('admin.school_scores_round')) ?></label>
                <select name="round" class="form-select" required>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label fw-medium"><?= e(t('admin.school_scores_file')) ?></label>
                <input type="file" name="file" accept=".xlsx" class="form-control" required>
                <div class="form-text"><?= e(t('admin.school_scores_file_hint')) ?></div>
            </div>

            <button type="submit" class="btn btn-primary"><?= e(t('admin.school_scores_submit')) ?></button>
        </form>
    </div>
</div>
