<?php
/** @var string $supportEmail */
$updated = 'September 3, 2026';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 fs-16">
            <h1 class="mb-2">Terms of Use</h1>
            <p class="text-muted mb-4">Last updated: <?= e($updated) ?></p>

            <h2 class="h5 mt-4">1. General</h2>
            <p>SofiaGO ("the site", "the service") is a catalog of places in Sofia that lets users
                find and add points on the map: restaurants, cafes, shops, services and
                attractions. By using the site you agree to these terms. If you don't agree,
                please don't use the service.</p>

            <h2 class="h5 mt-4">2. Registration and account</h2>
            <p>An account is required to add places and save favorites. You must provide accurate
                information and keep your password secret — all activity under your account is
                considered yours. If you notice unauthorized access, please let us know.</p>

            <h2 class="h5 mt-4">3. Adding listings</h2>
            <p>By publishing a place on the map, you confirm that:</p>
            <ul>
                <li>the information (address, coordinates, description, photos) is accurate and refers to a real place;</li>
                <li>you have the right to use the photos you upload;</li>
                <li>the listing does not advertise illegal goods/services, spam, abuse, or misleading content.</li>
            </ul>
            <p>Every new listing goes through moderation. We may reject, edit or remove any listing
                that violates these terms without prior notice — with a reason shown in the
                author's dashboard.</p>

            <h2 class="h5 mt-4">4. Rights to content</h2>
            <p>By publishing text or photos, you keep the copyright to them, but grant SofiaGO a
                non-exclusive right to display that content on the site as part of the catalog
                (listing page, map, search).</p>

            <h2 class="h5 mt-4">5. Limitation of liability</h2>
            <p>Listings are added by users — we don't guarantee the absolute accuracy of opening
                hours, prices or availability of services at listed places. Check with the venue
                itself before visiting. The site is provided "as is", without any guarantee of
                uninterrupted operation.</p>

            <h2 class="h5 mt-4">6. Changes to these terms</h2>
            <p>We may update these terms as the service evolves. The date at the top of the page
                reflects the last change. By continuing to use the site after an update, you
                accept the new version.</p>

            <h2 class="h5 mt-4">7. Contact</h2>
            <p>For any questions about these terms, email us: <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>.</p>
        </div>
    </div>
</div>
