<div class="p-3 p-sm-5">
    <div class="row g-4 g-xl-5 justify-content-between">
        <div class="col-xl-5 d-flex justify-content-center">
            <div class="authentication-wrap overflow-hidden position-relative text-center text-sm-start my-5">
                <div class="mb-5">
                    <h2 class="display-6 fw-semibold mb-3"><?= t('auth.forgot.heading') ?></h2>
                    <p class="mb-0"><?= e(t('auth.forgot.subtitle')) ?></p>
                </div>

                <?= partial('form-errors.tpl.php') ?>

                <form method="post" action="<?= e(url('/forgot-password')) ?>">
                    <?= csrf_field() ?>
                    <div class="form-group mb-4">
                        <label class="required"><?= e(t('auth.email')) ?></label>
                        <input type="email" name="email" class="form-control" value="<?= old('email') ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100"><?= e(t('auth.forgot.submit')) ?></button>
                </form>

                <div class="bottom-text text-center mt-4">
                    <?= t('auth.forgot.remember', ['link' => '<a href="' . e(url('/sign-in')) . '" class="fw-medium text-decoration-underline">' . e(t('nav.sign_in')) . '</a>']) ?>
                </div>
            </div>
        </div>
        <div class="col-xl-7 d-none d-xl-block">
            <div class="background-image bg-light d-flex flex-column h-100 justify-content-center p-5 rounded-4">
                <div class="py-5 text-center">
                    <img src="<?= e(asset('theme/images/png-img/forgot-password.png')) ?>" alt="" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
</div>
