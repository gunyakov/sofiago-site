<div class="p-3 p-sm-5">
    <div class="row g-4 g-xl-5 justify-content-between">
        <div class="col-xl-5 d-flex justify-content-center">
            <div class="authentication-wrap overflow-hidden position-relative text-center text-sm-start my-5">
                <div class="mb-5">
                    <h2 class="display-6 fw-semibold mb-3"><?= t('auth.reset.heading') ?></h2>
                </div>

                <?= partial('form-errors.tpl.php') ?>

                <form method="post" action="<?= e(url('/reset-password')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="form-group mb-4">
                        <label class="required"><?= e(t('auth.reset.new_password')) ?></label>
                        <input type="password" name="password" class="form-control" autocomplete="new-password" required minlength="8">
                    </div>
                    <div class="form-group mb-4">
                        <label class="required"><?= e(t('auth.password_confirm')) ?></label>
                        <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100"><?= e(t('auth.reset.submit')) ?></button>
                </form>
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
