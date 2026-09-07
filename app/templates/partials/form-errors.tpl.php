<?php if (($formErrors = errors()) !== []): ?>
<div class="alert alert-danger">
    <ul class="mb-0 ps-3">
        <?php foreach ($formErrors as $formError): ?>
        <li><?= e($formError) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
