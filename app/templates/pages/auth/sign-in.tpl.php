<div class="p-3 p-sm-5">
    <div class="row g-4 g-xl-5 justify-content-between">
        <div class="col-xl-5 d-flex justify-content-center">
            <div class="authentication-wrap overflow-hidden position-relative text-center text-sm-start my-5">
                <div class="mb-5">
                    <h2 class="display-6 fw-semibold mb-3"><?= t('auth.sign_in.heading') ?></h2>
                </div>

                <?= partial('form-errors.tpl.php') ?>

                <form method="post" action="<?= e(url('/sign-in')) ?>">
                    <?= csrf_field() ?>
                    <div class="form-group mb-4">
                        <label class="required"><?= e(t('auth.email')) ?></label>
                        <input type="email" name="email" class="form-control" value="<?= old('email') ?>" required>
                    </div>
                    <div class="form-group mb-4">
                        <label class="required"><?= e(t('auth.password')) ?></label>
                        <input type="password" name="password" class="form-control" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100"><?= e(t('auth.sign_in.submit')) ?></button>
                </form>

                <div class="bottom-text text-center mt-4">
                    <?= t('auth.sign_in.no_account', ['link' => '<a href="' . e(url('/sign-up')) . '" class="fw-medium text-decoration-underline">' . e(t('auth.sign_up.submit')) . '</a>']) ?>
                    <br><?= t('auth.sign_in.forgot', ['link' => '<a href="' . e(url('/forgot-password')) . '" class="fw-medium text-decoration-underline">' . e(t('auth.password_lowercase')) . '</a>']) ?>
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
