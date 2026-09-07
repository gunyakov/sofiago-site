<?php
/** @var string $supportEmail */
$updated = 'September 3, 2026';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 fs-16">
            <h1 class="mb-2">Privacy Policy</h1>
            <p class="text-muted mb-4">Last updated: <?= e($updated) ?></p>

            <h2 class="h5 mt-4">1. What data we collect</h2>
            <ul>
                <li>on registration — name, email, password (stored only as a hash; we never see or store it in plain text);</li>
                <li>when adding a place — address, coordinates, description, contact details, photos you upload yourself;</li>
                <li>technical data — IP address and request timing, to protect against password guessing and abuse (see also the cookies section).</li>
            </ul>

            <h2 class="h5 mt-4">2. What we use the data for</h2>
            <p>To run your account and dashboard, confirm your email and reset your password via a
                link, show your listings and favorites, and protect the site from spam and
                brute-force login attempts.</p>

            <h2 class="h5 mt-4">3. Cookies and similar technologies</h2>
            <p>The site uses two required cookies for signing in (session and "remember me") —
                without them the dashboard won't work. The site does not set any third-party
                advertising or tracking cookies. The map on the site loads tiles and fonts from a
                third-party mapping service — this is a technical request for map imagery and is
                not tied to your account.</p>

            <h2 class="h5 mt-4">4. Storage and data protection</h2>
            <p>Passwords are stored only as an irreversible hash (bcrypt). The connection to the
                site is protected by HTTPS. Only the service administrator has access to the
                database.</p>

            <h2 class="h5 mt-4">5. Who we share data with</h2>
            <p>We do not sell or share your personal data with third parties for advertising
                purposes. Data may be processed by technical contractors necessary to run the
                service — the hosting provider and (once connected) the mail server used to send
                confirmation/password-reset emails.</p>

            <h2 class="h5 mt-4">6. Your rights</h2>
            <p>You can request a copy of your data at any time, ask us to correct it, or delete
                your account entirely along with all listings and data — email
                <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a> and we'll
                handle the request manually.</p>

            <h2 class="h5 mt-4">7. Changes to this policy</h2>
            <p>We may update this policy as the service evolves — the date at the top of the page
                reflects the last change.</p>

            <h2 class="h5 mt-4">8. Contact</h2>
            <p>For questions about personal data processing: <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>.</p>
        </div>
    </div>
</div>
