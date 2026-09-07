<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="mb-4"><?= e($heading ?? $title ?? '') ?></h1>
            <div class="fs-16"><?= nl2br(e($body ?? '')) ?></div>
        </div>
    </div>
</div>
