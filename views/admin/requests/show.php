<?php require_once BASE_PATH . '/views/admin/layout/header.php'; ?>
<?php
// Inline critical CSS as a fallback for in-app webviews (e.g., Telegram) that may block external stylesheets.
$cssFile = PUBLIC_PATH . '/css/admin/requests/show.css';
if (file_exists($cssFile)) {
    $css = file_get_contents($cssFile);
    // Echo inside a <style> so it applies even if external CSS is blocked.
    echo "<style>\n" . $css . "\n</style>\n";
}
?>
<?php
$status      = $request['status'] ?? 'pending';
$statusLabels = [
    'pending'   => 'Кутилмоқда',
    'in_review' => 'Кўрилмоқда',
    'approved'  => 'Баҳоланди',
    'rejected'  => 'Рад этилди'
];
$isActionable = ($status === 'pending' || $status === 'in_review');
$imgCount     = count($images ?? []);
?>

<div class="rd">

    <!-- ── Header ── -->
    <div class="rd-header">
        <a href="<?= BASE_URL ?>/admin/requests" class="rd-back" aria-label="Орқага">
            <svg width="16" height="16" viewBox="0 0 14 14" fill="none">
                <path d="M9 2L4 7l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
        <h1 class="rd-title">Сўров <span>#<?= htmlspecialchars($request['id']) ?></span></h1>
        <span class="rd-status rd-s-<?= $status ?>">
            <?= htmlspecialchars($statusLabels[$status] ?? $status) ?>
        </span>
    </div>

    <!-- ── Photo viewer (swipe only, no arrows/strip) ── -->
    <div class="rd-photo-card">
        <div class="rd-viewer" id="rdViewer" data-images='<?= htmlspecialchars(json_encode(array_map(fn($img) => $img['image_path'], $images ?? []))) ?>'>
            <?php if (!empty($images)): ?>
                <?php foreach ($images as $i => $img): ?>
                    <div class="rd-slide <?= $i === 0 ? 'active' : '' ?>">
                        <img src="<?= htmlspecialchars($img['image_path']) ?>"
                             alt="Расм <?= $i + 1 ?>"
                             data-lb-index="<?= $i ?>">
                    </div>
                <?php endforeach; ?>

                <?php if ($imgCount > 1): ?>
                    <div class="rd-counter-badge"><span id="rdCurr">1</span> / <?= $imgCount ?></div>
                    <div class="rd-dots" id="rdDots">
                        <?php foreach ($images as $i => $img): ?>
                            <span class="rd-dot <?= $i === 0 ? 'active' : '' ?>"></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="rd-no-photo">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">
                        <rect x="3" y="3" width="18" height="18" rx="3"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <path d="M21 15l-5-5L5 21"/>
                    </svg>
                    <span>Расм йўқ</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Phone — tappable call button ── -->
    <?php if (!empty($phone)): ?>
        <a href="tel:<?= htmlspecialchars($phone) ?>" class="rd-phone-btn">
            <span class="rd-phone-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </span>
            <span class="rd-phone-text">
                <span class="rd-phone-label">Телефон</span>
                <span class="rd-phone-val"><?= htmlspecialchars($phone) ?></span>
            </span>
            <span class="rd-phone-arrow">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
            </span>
        </a>
    <?php endif; ?>

    <!-- ── Secondary metadata — low-weight stamp tags ── -->
    <div class="rd-tags">
        <span class="rd-tag">Ҳолат: <b><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></b></span>
        <span class="rd-tag">Яратилди: <b><?= htmlspecialchars($request['created_at'] ?? '—') ?></b></span>
        <span class="rd-tag">Текширилди: <b><?= htmlspecialchars($request['reviewed_at'] ?? '—') ?></b></span>
    </div>

    <!-- ── Description — big, bold, readable (the page's main content) ── -->
    <?php if (!empty($request['description'])): ?>
        <div class="rd-desc-eyebrow">
            <span class="rd-desc-eyebrow-dot"></span>
            <span>Тавсиф</span>
        </div>
        <div class="rd-desc">
            <div class="rd-desc-text" id="rdDescText"><?= nl2br(htmlspecialchars($request['description'])) ?></div>
        </div>
    <?php endif; ?>

    <!-- ── Action card ── -->
    <div class="rd-action-card">

        <?php if ($isActionable): ?>

            <!-- Single form — two submit buttons with different formaction -->
            <form method="post" id="rdForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="id"         value="<?= htmlspecialchars($request['id']) ?>">

                <div class="rd-form-grid">

                    <!-- Price -->
                    <div class="rd-field rd-field-price">
                        <label for="rd-price">Нарх <span class="rd-field-hint">— рад этишда керак эмас</span></label>
                        <div class="rd-input-wrap">
                            <span class="rd-prefix">сум</span>
                            <input type="text" id="rd-price" name="price"
                                   class="rd-ctrl rd-ctrl-prefix rd-ctrl-price" placeholder="150 000"
                                   inputmode="numeric" autocomplete="off">
                        </div>
                    </div>

                    <!-- Contact phone -->
                    <div class="rd-field">
                        <label>Қайтиш рақами <span class="rd-field-hint">— рад этишда керак эмас</span></label>
                        <select name="contact_phone" class="rd-ctrl">
                            <option value="">Алоқасиз</option>
                            <option value="+998900069777" selected>+998900069777</option>
                            <option value="+998947307704">Abl</option>
                            <option value="+998704744047">Akosh</option>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div class="rd-field">
                        <label for="rd-notes">Изоҳ</label>
                        <textarea id="rd-notes" name="notes" class="rd-ctrl rd-textarea"
                                  rows="4" placeholder="Қисқа ва тушунарли…"></textarea>
                    </div>

                </div>

                <!-- Action buttons -->
                <div class="rd-btns">
                    <button type="submit"
                            id="rdApproveBtn"
                            class="rd-btn rd-btn-stamp-ok"
                            formaction="<?= BASE_URL ?>/admin/requests/approve?token=<?= htmlspecialchars($token ?? '') ?>">
                        <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M2 7.5l3.5 3.5 7.5-7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Нархни юбориш
                    </button>
                    <button type="submit"
                            class="rd-btn rd-btn-stamp-no"
                            formaction="<?= BASE_URL ?>/admin/requests/reject?token=<?= htmlspecialchars($token ?? '') ?>">
                        <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M3 3l9 9M12 3L3 12" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                        Рад этиш
                    </button>
                </div>

            </form>

        <?php else: ?>

            <!-- Processed state -->
            <div class="rd-done">
                <div class="rd-done-icon rd-done-icon-<?= $status === 'approved' ? 'ok' : 'no' ?>">
                    <?php if ($status === 'approved'): ?>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M4 12l6 6 10-10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <?php else: ?>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
                    <?php endif; ?>
                </div>
                <div class="rd-done-title"><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></div>

                <?php if (!empty($request['price'])): ?>
                    <div class="rd-done-price">
                        <div class="rd-action-label">Баҳо</div>
                        <div class="rd-done-price-val">
                            <?= htmlspecialchars($request['price']) ?>
                            <span>сум</span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($request['notes'])): ?>
                    <div class="rd-done-note">
                        <div class="rd-action-label">Изоҳ</div>
                        <p><?= nl2br(htmlspecialchars($request['notes'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>

</div><!-- /rd -->

<!-- ── Lightbox — no buttons, pinch-zoom + swipe + tap-to-close ── -->
<div class="rd-lb" id="rdLb">
    <div class="rd-lb-wrap" id="rdLbWrap">
        <img id="rdLbImg" src="" alt="">
    </div>
    <div class="rd-lb-dots" id="rdLbDots"></div>
</div>

<script src="<?= BASE_URL ?>/js/admin/requests-show.js"></script>


<?php require_once BASE_PATH . '/views/admin/layout/footer.php'; ?>
