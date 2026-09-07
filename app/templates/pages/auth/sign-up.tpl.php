<div class="p-3 p-sm-5">
    <div class="row g-4 g-xl-5 justify-content-between">
        <div class="col-xl-5 d-flex justify-content-center">
            <div class="authentication-wrap overflow-hidden position-relative text-center text-sm-start my-5">
                <div class="mb-5">
                    <h2 class="display-6 fw-semibold mb-3"><?= t('auth.sign_up.heading') ?></h2>
                    <p class="mb-0"><?= e(t('auth.sign_up.subtitle')) ?></p>
                </div>

                <?= partial('form-errors.tpl.php') ?>

                <form method="post" action="<?= e(url('/sign-up')) ?>">
                    <?= csrf_field() ?>
                    <div class="form-group mb-4">
                        <label class="required"><?= e(t('auth.name')) ?></label>
                        <input type="text" name="name" class="form-control" value="<?= old('name') ?>" required>
                    </div>
                    <div class="form-group mb-4">
                        <label class="required"><?= e(t('auth.email')) ?></label>
                        <input type="email" name="email" class="form-control" value="<?= old('email') ?>" required>
                    </div>
                    <div class="form-group mb-4">
                        <label class="required"><?= e(t('auth.password')) ?></label>
                        <input type="password" name="password" class="form-control" autocomplete="new-password" required minlength="8">
                    </div>
                    <div class="form-group mb-4">
                        <label class="required"><?= e(t('auth.password_confirm')) ?></label>
                        <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100"><?= e(t('auth.sign_up.submit')) ?></button>

                    <p class="small text-muted mt-3 mb-0">
                        <?= t('auth.sign_up.consent', [
                            'terms' => '<a href="' . e(url('/terms')) . '" target="_blank">' . e(t('common.terms_of_use')) . '</a>',
                            'privacy' => '<a href="' . e(url('/privacy')) . '" target="_blank">' . e(t('common.privacy_policy')) . '</a>',
                        ]) ?>
                    </p>
                </form>

                <div class="bottom-text text-center mt-4">
                    <?= t('auth.sign_up.already_account', ['link' => '<a href="' . e(url('/sign-in')) . '" class="fw-medium text-decoration-underline">' . e(t('nav.sign_in')) . '</a>']) ?>
                </div>
            </div>
        </div>
        <div class="col-xl-7 d-none d-xl-block">
            <div class="background-image bg-light d-flex flex-column h-100 justify-content-center p-5 rounded-4">
                <div class="py-5 text-center">
                    <div class="mb-5">
                        <h2 class="fw-semibold"><?= e(t('auth.side_title')) ?></h2>
                        <p><?= e(t('auth.side_text')) ?></p>
                    </div>
                    <img src="<?= e(asset('theme/images/png-img/login.png')) ?>" alt="" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
</div>
