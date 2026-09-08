<?php
/** @var string $supportEmail */
$updated = 'September 8, 2026';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 fs-16">
            <h1 class="mb-2">How to delete your account</h1>
            <p class="text-muted mb-4">Last updated: <?= e($updated) ?></p>

            <h2 class="h5 mt-4">1. Which account this covers</h2>
            <p>The same account is used both in the "Account" tab of the SofiaGO app and in the
                website dashboard (<a href="<?= e(url('/sign-in')) ?>">sofiago.eu/sign-in</a>) — the
                steps below delete both at once.</p>

            <h2 class="h5 mt-4">2. Steps to delete</h2>
            <ol>
                <li>Email <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a> from
                    the address you registered with, subject "Delete account".</li>
                <li>For security we may ask you to confirm from that same email before we process
                    the request.</li>
                <li>We delete the account manually, usually within a few business days, and confirm
                    by email once it's done.</li>
            </ol>
            <p>The app doesn't yet have a self-service "Delete account" button in the Account tab —
                until it does, an email request is the only way.</p>

            <h2 class="h5 mt-4">3. What gets deleted</h2>
            <ul>
                <li>your account — name, email, password (hash);</li>
                <li>every listing, photo and description you've added;</li>
                <li>your list of favorite places.</li>
            </ul>

            <h2 class="h5 mt-4">4. What may remain briefly</h2>
            <p>Technical logs (IP address, request time), used only to protect against abuse, expire
                automatically after a short period and are no longer linked to the deleted account —
                see also section 3 of our <a href="<?= e(url('/privacy')) ?>">privacy policy</a>.</p>

            <h2 class="h5 mt-4">5. Contact</h2>
            <p>Questions about deletion: <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>.</p>
        </div>
    </div>
</div>
