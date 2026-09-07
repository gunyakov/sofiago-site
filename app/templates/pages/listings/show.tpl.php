<div class="container-xxl py-4">
    <nav class="mb-3"><a href="<?= e(url('/explore')) ?>" class="text-decoration-none"><i class="fa-solid fa-arrow-left me-1"></i><?= e(t('listing.back_to_catalog')) ?></a></nav>

    <?php if ($media !== []): ?>
    <div class="row g-2 mb-1">
        <?php foreach (array_slice($media, 0, 4) as $i => $item): ?>
        <div class="col-6 col-md-3">
            <img src="<?= e($item['path']) ?>" alt="<?= e($listing['title']) ?>" class="img-fluid rounded-3 w-100" style="height:180px; object-fit:cover;">
        </div>
        <?php endforeach; ?>
    </div>
    <?php
        // Credit line for any shown photo pulled from a source that requires attribution
        // (e.g. Wikimedia Commons CC-BY-SA) — Upload::storeImage() never sets these, so this
        // is empty for ordinary owner-uploaded photos. See listing_media.credit/source_url.
        $creditedShown = array_filter(array_slice($media, 0, 4), static fn (array $m): bool => !empty($m['credit']));
    ?>
    <?php if ($creditedShown !== []): ?>
    <div class="small text-muted mb-4">
        <?php foreach ($creditedShown as $item): ?>
        <div><?= e(t('listing.photo_credit')) ?>: <?php if (!empty($item['source_url'])): ?><a href="<?= e($item['source_url']) ?>" target="_blank" rel="noopener noreferrer nofollow" class="text-muted"><?= e($item['credit']) ?></a><?php else: ?><?= e($item['credit']) ?><?php endif; ?></div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="mb-4"></div>
    <?php endif; ?>
    <?php else: ?>
    <div class="d-flex align-items-center justify-content-center bg-light rounded-4 mb-4" style="height:240px;">
        <i class="fa-solid <?= e($listing['category_icon'] ?: 'fa-map-pin') ?> fs-1 text-primary opacity-50"></i>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="small text-muted mb-2"><?= e(\Sofiago\Models\Category::label($listing)) ?><?= !empty($listing['district']) ? ' · ' . e($listing['district']) : '' ?></div>
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h1 class="mb-0"><?= e($listing['title']) ?></h1>
                <?php if (!empty($currentUser)): ?>
                <?= partial('favorite-button.tpl.php', ['listingId' => (int) $listing['id'], 'isFavorited' => $isFavorited]) ?>
                <?php endif; ?>
            </div>
            <p class="fs-16"><?= nl2br(e(\Sofiago\Models\Listing::descriptionFor($listing))) ?></p>

            <?php if ($amenities !== []): ?>
            <div class="mt-4">
                <h5 class="mb-3"><?= e(t('listing.amenities')) ?></h5>
                <div class="row g-3">
                    <?php foreach ($amenities as $amenity): ?>
                    <div class="col-auto col-lg-3">
                        <div class="d-flex align-items-center text-dark">
                            <div class="flex-shrink-0">
                                <i class="fa-solid <?= e($amenity['icon'] ?: 'fa-check') ?> fs-18"></i>
                            </div>
                            <div class="flex-grow-1 fs-16 fw-medium ms-3"><?= e(\Sofiago\Models\Amenity::label($amenity)) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($admissionScores !== []): ?>
            <?php
                // N/A means "no data" (this round hasn't been imported yet, or this specific
                // cell was blank/non-numeric in the source) — a real recorded 0 (e.g. no girls
                // admitted that round) is a genuine value and must print as "0", not N/A; the
                // two are not interchangeable, so this deliberately does NOT collapse them.
                $fmtScore = static function (?float $v): string {
                    if ($v === null) {
                        return t('listing.admission_scores_na');
                    }
                    return rtrim(rtrim(number_format($v, 2), '0'), '.');
                };
            ?>
            <div class="mt-4">
                <h5 class="mb-1"><?= e(t('listing.admission_scores_title')) ?></h5>
                <p class="small text-muted mb-3"><?= e(t('listing.admission_scores_hint')) ?></p>
                <?php foreach ($admissionScores as $year => $paralelki): ?>
                <h6 class="fw-semibold mt-3 mb-2"><?= e((string) $year) ?></h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered align-middle text-center fs-13">
                        <thead>
                            <tr>
                                <th rowspan="3" class="align-middle text-start"><?= e(t('listing.admission_scores_col_class_number')) ?></th>
                                <th rowspan="3" class="align-middle text-start"><?= e(t('listing.admission_scores_col_paralelka')) ?></th>
                                <?php for ($round = 1; $round <= 4; $round++): ?>
                                <th colspan="4"><?= e(t('listing.admission_scores_round', ['n' => $round])) ?></th>
                                <?php endfor; ?>
                            </tr>
                            <tr>
                                <?php for ($round = 1; $round <= 4; $round++): ?>
                                <th colspan="2" class="fw-normal small"><?= e(t('listing.admission_scores_boy')) ?></th>
                                <th colspan="2" class="fw-normal small"><?= e(t('listing.admission_scores_girl')) ?></th>
                                <?php endfor; ?>
                            </tr>
                            <tr>
                                <?php for ($round = 1; $round <= 4; $round++): ?>
                                <th class="fw-normal small"><?= e(t('listing.admission_scores_max')) ?></th>
                                <th class="fw-normal small"><?= e(t('listing.admission_scores_min')) ?></th>
                                <th class="fw-normal small"><?= e(t('listing.admission_scores_max')) ?></th>
                                <th class="fw-normal small"><?= e(t('listing.admission_scores_min')) ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paralelki as $p): ?>
                            <tr>
                                <td class="text-start"><?= e($p['class_number']) ?><?php if ($p['quote']): ?> <span class="badge bg-info-subtle text-info" title="<?= e(t('listing.admission_scores_quote_hint')) ?>"><?= e(t('listing.admission_scores_quote_badge')) ?></span><?php endif; ?></td>
                                <td class="text-start"><?= e($p['class_name']) ?></td>
                                <?php for ($round = 1; $round <= 4; $round++): ?>
                                    <?php $r = $p['rounds'][$round] ?? ['boy_max' => null, 'boy_min' => null, 'girl_max' => null, 'girl_min' => null]; ?>
                                    <td><?= e($fmtScore($r['boy_max'])) ?></td>
                                    <td><?= e($fmtScore($r['boy_min'])) ?></td>
                                    <td><?= e($fmtScore($r['girl_max'])) ?></td>
                                    <td><?= e($fmtScore($r['girl_min'])) ?></td>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <hr class="my-5">

            <!-- start comments section -->
            <div id="comment-form">
                <h4 class="fw-semibold fs-3 mb-4"><?= e(t('comment.section_title')) ?> <span class="font-caveat text-primary">(<?= (int) count($comments) ?>)</span></h4>

                <?php if ($comments === []): ?>
                <p class="text-muted"><?= e(t('comment.empty')) ?></p>
                <?php else: ?>
                <?php foreach ($comments as $i => $comment): ?>
                <div class="d-flex mb-4 <?= $i < count($comments) - 1 ? 'border-bottom pb-4' : '' ?>">
                    <div class="flex-shrink-0">
                        <div class="d-flex align-items-center justify-content-center bg-light rounded-circle text-primary" style="width:56px;height:56px;">
                            <i class="fa-solid fa-user fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="comment-header d-flex flex-wrap gap-2 mb-2">
                            <div>
                                <h4 class="fs-18 mb-0"><?= e($comment['display_name']) ?></h4>
                                <div class="comment-datetime fs-12 text-muted"><?= e(date('d.m.Y H:i', strtotime((string) $comment['created_at']))) ?></div>
                            </div>
                            <div class="d-flex align-items-center text-primary rating-stars ms-auto">
                                <?php for ($star = 1; $star <= 5; $star++): ?>
                                <i class="fa-star-icon <?= $star > (int) $comment['rating'] ? 'none' : '' ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="fs-15"><?= nl2br(e($comment['body'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($turnstileSiteKey): ?>
                <hr class="my-4">
                <h4 class="fw-semibold fs-3 mb-4"><?= e(t('comment.leave_a')) ?> <span class="font-caveat text-primary"><?= e(t('comment.comment_word')) ?></span></h4>
                <?= partial('form-errors.tpl.php') ?>
                <form class="row g-4" method="post" action="<?= e(url('/listings/' . $listing['slug'] . '/comments')) ?>">
                    <?= csrf_field() ?>
                    <?php if (!auth()->check()): ?>
                    <div class="col-sm-6">
                        <label class="required fw-medium mb-2"><?= e(t('comment.full_name')) ?></label>
                        <input type="text" name="name" class="form-control" value="<?= old('name') ?>" placeholder="<?= e(t('comment.full_name_placeholder')) ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="fw-medium mb-2"><?= e(t('comment.email_address')) ?></label>
                        <input type="email" name="email" class="form-control" value="<?= old('email') ?>" placeholder="<?= e(t('comment.email_placeholder')) ?>">
                    </div>
                    <?php endif; ?>
                    <div class="col-sm-12">
                        <label class="required fw-medium mb-2"><?= e(t('comment.rating')) ?></label>
                        <div class="star-rating-input">
                            <?php for ($star = 5; $star >= 1; $star--): ?>
                            <input type="radio" name="rating" id="comment-rating-<?= $star ?>" value="<?= $star ?>" <?= old('rating') === (string) $star ? 'checked' : '' ?> required>
                            <label for="comment-rating-<?= $star ?>" title="<?= $star ?>"><i class="fa-solid fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="col-sm-12">
                        <label class="required fw-medium mb-2"><?= e(t('comment.comment_word')) ?></label>
                        <textarea name="body" class="form-control" rows="5" placeholder="<?= e(t('comment.body_placeholder')) ?>" required><?= old('body') ?></textarea>
                    </div>
                    <div class="col-sm-12">
                        <?= \Sofiago\Core\Turnstile::widget() ?>
                    </div>
                    <div class="col-sm-12 text-end">
                        <button type="submit" class="btn btn-primary"><?= e(t('comment.submit')) ?></button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            <!-- end /. comments section -->
        </div>

        <div class="col-lg-4">
            <div class="card p-4 mb-4">
                <h5 class="mb-3"><?= e(t('listing.contacts')) ?></h5>
                <?php if (!empty($listing['address'])): ?>
                <div class="mb-2"><i class="fa-solid fa-location-dot me-2 text-primary"></i><?= e($listing['address']) ?></div>
                <?php endif; ?>
                <?php if (!empty($listing['phone'])): ?>
                <div class="mb-2"><i class="fa-solid fa-phone me-2 text-primary"></i><a href="tel:<?= e($listing['phone']) ?>" class="text-dark text-decoration-none"><?= e($listing['phone']) ?></a></div>
                <?php endif; ?>
                <?php if (!empty($listing['website'])): ?>
                <div class="mb-2"><i class="fa-solid fa-globe me-2 text-primary"></i><a href="<?= e($listing['website']) ?>" target="_blank" rel="noopener" class="text-dark text-decoration-none"><?= e($listing['website']) ?></a></div>
                <?php endif; ?>
                <?php if (!empty($listing['email'])): ?>
                <div class="mb-2"><i class="fa-solid fa-envelope me-2 text-primary"></i><a href="mailto:<?= e($listing['email']) ?>" class="text-dark text-decoration-none"><?= e($listing['email']) ?></a></div>
                <?php endif; ?>

                <?php
                    $socials = [
                        'facebook_url' => 'fa-brands fa-facebook-f',
                        'instagram_url' => 'fa-brands fa-instagram',
                        'twitter_url' => 'fa-brands fa-twitter',
                        'linkedin_url' => 'fa-brands fa-linkedin-in',
                    ];
                    $hasSocials = false;
                    foreach ($socials as $field => $icon) {
                        if (!empty($listing[$field])) { $hasSocials = true; break; }
                    }
                ?>
                <?php if ($hasSocials): ?>
                <div class="d-flex gap-2 mt-3">
                    <?php foreach ($socials as $field => $icon): ?>
                        <?php if (!empty($listing[$field])): ?>
                        <a href="<?= e($listing[$field]) ?>" target="_blank" rel="noopener" class="btn-icon d-flex align-items-center justify-content-center rounded-circle bg-light text-primary"><i class="<?= e($icon) ?>"></i></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($hours !== []): ?>
            <div class="border p-4 rounded-4 mb-4">
                <div class="align-items-center d-flex justify-content-between mb-4">
                    <h4 class="w-semibold mb-0"><?= e(t('listing.opening_hours_kicker')) ?> <span class="font-caveat text-primary"><?= e(t('listing.opening_hours_word')) ?></span></h4>
                    <i class="fa-solid fa-clock fs-3 text-primary"></i>
                </div>
                <?php foreach (\Sofiago\Models\Listing::DAYS as $day): ?>
                <div class="align-items-center d-flex justify-content-between mb-3">
                    <span class="fw-semibold"><?= e(t('days.' . $day)) ?></span>
                    <?php if (isset($hours[$day])): ?>
                    <span class="fs-14"><?= e(date('g:i a', strtotime($hours[$day]['open']))) ?> - <?= e(date('g:i a', strtotime($hours[$day]['close']))) ?></span>
                    <?php else: ?>
                    <span class="fw-medium text-danger"><?= e(t('listing.closed')) ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($listing['lat'] && $listing['lng']): ?>
            <div id="map-listing" class="rounded-4" style="height: 260px;" data-lat="<?= e($listing['lat']) ?>" data-lng="<?= e($listing['lng']) ?>" data-title="<?= e($listing['title']) ?>" data-icon="<?= e($listing['map_icon_path'] ?? '') ?>" data-category="<?= e($listing['category_slug']) ?>"></div>
            <?php endif; ?>

            <?php if ($objectMap !== null): ?>
            <!-- Only rendered once the venue's own map is admin-published (see
                 ListingController::show()'s $objectMap / Venue::objectMapFor()) — a shop inside
                 an in-progress venue never gets this button, so nobody stumbles onto an
                 unfinished floor plan. -->
            <a href="<?= e(url('/venues/' . $objectMap['venue_listing_id'] . '?floor=' . $objectMap['floor_id'] . '&unit=' . $listing['indoor_unit_id'])) ?>" class="btn btn-outline-primary w-100 mt-3">
                <i class="fa-solid fa-map-location-dot me-2"></i><?= e(t('listing.view_on_map')) ?>
            </a>
            <?php endif; ?>

            <!-- "This is my object" trigger — hidden entirely for the current owner, a disabled
                 note if they already have a request pending, opens #claimModal for any other
                 signed-in user, a plain sign-in link for a guest (the modal itself needs an
                 account to submit into, so a guest goes straight to /sign-in instead of opening
                 an empty modal they can't use). -->
            <?php if (!$isOwner): ?>
            <div class="border p-3 rounded-4 mt-3 text-center">
                <?php if ($hasPendingOwnershipClaim): ?>
                <div class="small text-muted"><i class="fa-solid fa-clock me-1"></i><?= e(t('listing.claim_pending')) ?></div>
                <?php elseif (!empty($currentUser)): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#claimModal">
                    <i class="fa-solid fa-hand-holding me-2"></i><?= e(t('listing.claim_ownership')) ?>
                </button>
                <?php else: ?>
                <a href="<?= e(url('/sign-in')) ?>" class="btn btn-outline-secondary btn-sm w-100"><i class="fa-solid fa-hand-holding me-2"></i><?= e(t('listing.claim_ownership')) ?></a>
                <div class="small text-muted mt-2"><?= e(t('listing.claim_sign_in_prompt')) ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- "Report a problem" trigger — anonymous-capable, opens #reportModal. -->
            <div class="text-center mt-3">
                <button type="button" class="btn btn-link btn-sm text-muted text-decoration-none" data-bs-toggle="modal" data-bs-target="#reportModal">
                    <i class="fa-solid fa-flag me-1"></i><?= e(t('report.link_label')) ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Both forms below are modals rather than inline sections — on a narrow screen an inline
         dropdown+textarea buried in the sidebar was awkward to reach/fill in (2026-09-08
         feedback); a modal gets the whole viewport instead. Markup sits outside .row/.col-lg-4
         on purpose (a modal backdrop/dialog shouldn't be constrained by the sidebar column's
         own width). Each one reopens itself after a failed submit via the small script at the
         bottom — see $reportReopened/$claimReopened below. -->
    <?php $reportReopened = old('reason') !== '' || old('message') !== ''; ?>
    <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= e(t('report.title')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($reportReopened): ?><?= partial('form-errors.tpl.php') ?><?php endif; ?>
                    <form method="post" action="<?= e(url('/listings/' . $listing['slug'] . '/report')) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="required fw-medium mb-1 small"><?= e(t('report.reason_label')) ?></label>
                            <select name="reason" class="form-select" required>
                                <option value=""><?= e(t('report.reason_select')) ?></option>
                                <?php foreach (['closed_permanently', 'wrong_info', 'duplicate', 'inappropriate', 'spam', 'other'] as $reason): ?>
                                <option value="<?= e($reason) ?>" <?= old('reason') === $reason ? 'selected' : '' ?>><?= e(t('report.reason_' . $reason)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="fw-medium mb-1 small"><?= e(t('report.message_label')) ?></label>
                            <textarea name="message" class="form-control" rows="3" placeholder="<?= e(t('report.message_placeholder')) ?>"><?= old('message') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <?= \Sofiago\Core\Turnstile::widget() ?>
                        </div>
                        <button type="submit" class="btn btn-outline-danger w-100"><?= e(t('report.submit')) ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$isOwner && !$hasPendingOwnershipClaim && !empty($currentUser)): ?>
    <?php $claimReopened = old('claim_message') !== ''; ?>
    <div class="modal fade" id="claimModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= e(t('listing.claim_ownership')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted"><?= e(t('listing.claim_hint')) ?></p>
                    <?php if ($claimReopened): ?><?= partial('form-errors.tpl.php') ?><?php endif; ?>
                    <form method="post" action="<?= e(url('/listings/' . $listing['slug'] . '/claim-ownership')) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="required fw-medium mb-1 small"><?= e(t('listing.claim_message_label')) ?></label>
                            <textarea name="claim_message" class="form-control" rows="4" placeholder="<?= e(t('listing.claim_message_placeholder')) ?>" required><?= old('claim_message') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <?= \Sofiago\Core\Turnstile::widget() ?>
                        </div>
                        <button type="submit" class="btn btn-outline-secondary w-100"><?= e(t('listing.claim_ownership')) ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($reportReopened || (isset($claimReopened) && $claimReopened)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($reportReopened): ?>new bootstrap.Modal(document.getElementById('reportModal')).show();<?php endif; ?>
            <?php if (isset($claimReopened) && $claimReopened): ?>new bootstrap.Modal(document.getElementById('claimModal')).show();<?php endif; ?>
        });
    </script>
    <?php endif; ?>
</div>
