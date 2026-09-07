<?php
/**
 * @var string $name
 * @var string $link
 * @var string $locale The recipient's own stored locale (users.locale), not the sending
 *   request's — see Lang::getFor()/t_for().
 */
?>
<div style="font-family: system-ui, sans-serif; max-width: 480px; margin: 0 auto; color: #1e242d;">
    <h2 style="color: #ff5a1f;">SofiaGO</h2>
    <p><?= e(t_for($locale, 'email.greeting', ['name' => $name])) ?></p>
    <p><?= e(t_for($locale, 'email.verify.body')) ?></p>
    <p style="margin: 24px 0;">
        <a href="<?= e($link) ?>" style="background: #ff5a1f; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; display: inline-block;"><?= e(t_for($locale, 'email.verify.cta')) ?></a>
    </p>
    <p style="font-size: 13px; color: #666;"><?= e(t_for($locale, 'email.verify.footer')) ?></p>
</div>
