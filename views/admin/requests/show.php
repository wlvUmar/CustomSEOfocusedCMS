<?php require_once BASE_PATH . '/views/admin/layout/header.php'; ?>
<?php
$status = $request['status'] ?? 'pending';
$statusLabels = [
    'pending'   => 'Ожидает',
    'in_review' => 'В работе',
    'approved'  => 'Оценено',
    'rejected'  => 'Без оценки'
];
$statusIcons = [
    'pending'   => '⏳',
    'in_review' => '🔍',
    'approved'  => '✓',
    'rejected'  => '✕'
];
$isActionable = ($status === 'pending' || $status === 'in_review');
$imgCount = count($images ?? []);
?>

<div class="rp" id="rp">

    <!-- ░░ TOPBAR ░░ -->
    <div class="rp-topbar">
        <a href="<?= BASE_URL ?>/admin/requests" class="rp-back">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Назад
        </a>
        <div class="rp-topbar-id">
            <span class="rp-label">Заявка</span>
            <span class="rp-id">#<?= htmlspecialchars($request['id']) ?></span>
        </div>
        <span class="rp-status-chip rp-status-<?= $status ?>">
            <?= $statusIcons[$status] ?? '' ?> <?= htmlspecialchars($statusLabels[$status] ?? $status) ?>
        </span>
    </div>

    <!-- ░░ SPLIT LAYOUT ░░ -->
    <div class="rp-split">

        <!-- LEFT: Photo column -->
        <div class="rp-photo-col">

            <!-- Main viewer -->
            <div class="rp-viewer" id="rpViewer">
                <?php if (!empty($images)): ?>
                    <?php foreach ($images as $i => $img): ?>
                        <div class="rp-slide <?= $i === 0 ? 'active' : '' ?>" data-i="<?= $i ?>">
                            <img src="<?= htmlspecialchars($img['image_path']) ?>"
                                 alt="Фото <?= $i+1 ?>"
                                 class="rp-slide-img"
                                 onclick="openLightbox(this.src)">
                            <div class="rp-zoom-hint">нажмите для увеличения</div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($imgCount > 1): ?>
                        <button class="rp-arr rp-arr-l" onclick="slide(-1)">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M13 4l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <button class="rp-arr rp-arr-r" onclick="slide(1)">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M7 4l6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <div class="rp-counter"><span id="rpCurr">1</span> / <?= $imgCount ?></div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="rp-nophoto">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                        <span>Нет фото</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Thumbnail filmstrip -->
            <?php if ($imgCount > 1): ?>
                <div class="rp-strip" id="rpStrip">
                    <?php foreach ($images as $i => $img): ?>
                        <div class="rp-thumb <?= $i === 0 ? 'active' : '' ?>"
                             data-i="<?= $i ?>"
                             onclick="goTo(<?= $i ?>)">
                            <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Metadata cards (desktop: below photo) -->
            <div class="rp-meta-grid">
                <div class="rp-meta-card">
                    <div class="rp-meta-label">Телефон</div>
                    <div class="rp-meta-val"><?= htmlspecialchars($phone ?: '—') ?></div>
                </div>
                <div class="rp-meta-card">
                    <div class="rp-meta-label">Статус</div>
                    <div class="rp-meta-val"><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></div>
                </div>
                <div class="rp-meta-card">
                    <div class="rp-meta-label">Создано</div>
                    <div class="rp-meta-val"><?= htmlspecialchars($request['created_at'] ?? '—') ?></div>
                </div>
                <div class="rp-meta-card">
                    <div class="rp-meta-label">Проверено</div>
                    <div class="rp-meta-val"><?= htmlspecialchars($request['reviewed_at'] ?? '—') ?></div>
                </div>
            </div>

            <?php if (!empty($request['description'])): ?>
                <div class="rp-desc">
                    <div class="rp-desc-label">Описание</div>
                    <div class="rp-desc-text"><?= nl2br(htmlspecialchars($request['description'])) ?></div>
                </div>
            <?php endif; ?>

        </div><!-- /rp-photo-col -->

        <!-- RIGHT: Action column -->
        <div class="rp-action-col">

            <?php if ($isActionable): ?>

                <!-- APPROVE FORM -->
                <div class="rp-card rp-card-approve">
                    <div class="rp-card-head">
                        <div class="rp-card-icon rp-card-icon-green">
                            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3 9l4.5 4.5 7.5-9" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <h2>Оценить</h2>
                    </div>
                    <form method="post" action="<?= BASE_URL ?>/admin/requests/approve?token=<?= htmlspecialchars($token ?? '') ?>">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($request['id']) ?>">

                        <div class="rp-field">
                            <label>Цена</label>
                            <div class="rp-input-wrap">
                                <span class="rp-input-prefix">сум</span>
                                <input type="text" name="price" placeholder="150 000" class="rp-input rp-input-price">
                            </div>
                        </div>

                        <div class="rp-field">
                            <label>Комментарий</label>
                            <textarea name="notes" rows="4" placeholder="Коротко и понятно…" class="rp-input rp-textarea"></textarea>
                        </div>

                        <div class="rp-field">
                            <label>Телефон для звонка</label>
                            <select name="contact_phone" class="rp-input rp-select">
                                <option value="">Без контакта</option>
                                <option value="+998900069777" selected>+998900069777</option>
                                <option value="+998947307704">Abl</option>
                                <option value="+998704744047">Akosh</option>
                            </select>
                        </div>

                        <button type="submit" class="rp-btn rp-btn-green">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 8l4.5 4.5 7.5-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Отправить цену
                        </button>
                    </form>
                </div>

                <!-- DIVIDER -->
                <div class="rp-or">
                    <span>или</span>
                </div>

                <!-- REJECT FORM -->
                <div class="rp-card rp-card-reject">
                    <div class="rp-card-head">
                        <div class="rp-card-icon rp-card-icon-red">
                            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4 4l10 10M14 4L4 14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                        </div>
                        <h2>Нет оценки</h2>
                    </div>
                    <form method="post" action="<?= BASE_URL ?>/admin/requests/reject?token=<?= htmlspecialchars($token ?? '') ?>">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($request['id']) ?>">

                        <div class="rp-field">
                            <label>Причина</label>
                            <textarea name="notes" rows="3" placeholder="Коротко и понятно…" class="rp-input rp-textarea"></textarea>
                        </div>

                        <button type="submit" class="rp-btn rp-btn-red">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            Отклонить заявку
                        </button>
                    </form>
                </div>

            <?php else: ?>

                <!-- PROCESSED STATE -->
                <div class="rp-card rp-card-done rp-card-done-<?= $status ?>">
                    <div class="rp-done-badge rp-status-<?= $status ?>">
                        <?= $statusIcons[$status] ?? '' ?> <?= htmlspecialchars($statusLabels[$status] ?? $status) ?>
                    </div>
                    <h2 class="rp-done-title">Заявка обработана</h2>

                    <?php if (!empty($request['price'])): ?>
                        <div class="rp-done-price">
                            <div class="rp-done-price-label">Оценка</div>
                            <div class="rp-done-price-val"><?= htmlspecialchars($request['price']) ?> <span>сум</span></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($request['notes'])): ?>
                        <div class="rp-done-note">
                            <div class="rp-meta-label">Комментарий</div>
                            <p><?= nl2br(htmlspecialchars($request['notes'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </div><!-- /rp-action-col -->
    </div><!-- /rp-split -->
</div><!-- /rp -->

<!-- LIGHTBOX -->
<div class="rp-lb" id="rpLb" onclick="if(event.target===this||event.target.id==='rpLbWrap')closeLightbox()">
    <button class="rp-lb-close" onclick="closeLightbox()">
        <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M3 3l16 16M19 3L3 19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
    </button>
    <div class="rp-lb-controls">
        <button onclick="lbZoom(-0.25)">−</button>
        <button onclick="lbZoom(0.25)">+</button>
        <button onclick="lbReset()">↺</button>
    </div>
    <div class="rp-lb-wrap" id="rpLbWrap">
        <img id="rpLbImg" src="" alt="">
    </div>
</div>

<script>
// ── Carousel ────────────────────────────────────────────────
let cur = 0;
const slides = () => document.querySelectorAll('.rp-slide');
const thumbs = () => document.querySelectorAll('.rp-thumb');
const counter = document.getElementById('rpCurr');

function goTo(n) {
    const s = slides(), t = thumbs(), total = s.length;
    if (!total) return;
    s[cur].classList.remove('active');
    t[cur]?.classList.remove('active');
    cur = (n + total) % total;
    s[cur].classList.add('active');
    t[cur]?.classList.add('active');
    if (counter) counter.textContent = cur + 1;
    t[cur]?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
}
function slide(d) { goTo(cur + d); }

// Swipe on viewer
(function() {
    const v = document.getElementById('rpViewer');
    if (!v) return;
    let sx = 0, sy = 0;
    v.addEventListener('touchstart', e => { sx = e.touches[0].clientX; sy = e.touches[0].clientY; }, { passive: true });
    v.addEventListener('touchend', e => {
        const dx = e.changedTouches[0].clientX - sx;
        const dy = e.changedTouches[0].clientY - sy;
        if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 40) slide(dx < 0 ? 1 : -1);
    }, { passive: true });
})();

// Keyboard
document.addEventListener('keydown', e => {
    if (e.key === 'ArrowLeft') slide(-1);
    if (e.key === 'ArrowRight') slide(1);
});

// ── Lightbox ────────────────────────────────────────────────
let lbScale = 1, lbDragging = false, lbX = 0, lbY = 0, lbSX, lbSY;
const lb    = document.getElementById('rpLb');
const lbImg = document.getElementById('rpLbImg');
const lbWrap = document.getElementById('rpLbWrap');

function openLightbox(src) {
    lbImg.src = src; lbScale = 1; lbX = 0; lbY = 0;
    lbApply(); lb.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() { lb.classList.remove('open'); document.body.style.overflow = ''; }
function lbZoom(d)  { lbScale = Math.min(6, Math.max(0.5, lbScale + d)); lbApply(); }
function lbReset()  { lbScale = 1; lbX = 0; lbY = 0; lbApply(); }
function lbApply()  { lbImg.style.transform = `translate(${lbX}px,${lbY}px) scale(${lbScale})`; }

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
lbWrap.addEventListener('wheel', e => { e.preventDefault(); lbZoom(e.deltaY < 0 ? 0.15 : -0.15); }, { passive: false });
lbWrap.addEventListener('mousedown', e => { lbDragging = true; lbSX = e.clientX - lbX; lbSY = e.clientY - lbY; lbWrap.style.cursor = 'grabbing'; });
document.addEventListener('mousemove', e => { if (!lbDragging) return; lbX = e.clientX - lbSX; lbY = e.clientY - lbSY; lbApply(); });
document.addEventListener('mouseup', () => { lbDragging = false; lbWrap.style.cursor = 'grab'; });

let lbLastTap = 0;
lbWrap.addEventListener('touchstart', e => {
    const now = Date.now(); if (now - lbLastTap < 300) lbReset(); lbLastTap = now;
    if (e.touches.length === 1) { lbDragging = true; lbSX = e.touches[0].clientX - lbX; lbSY = e.touches[0].clientY - lbY; }
}, { passive: true });
lbWrap.addEventListener('touchmove', e => {
    if (lbDragging && e.touches.length === 1) { lbX = e.touches[0].clientX - lbSX; lbY = e.touches[0].clientY - lbSY; lbApply(); }
}, { passive: true });
lbWrap.addEventListener('touchend', () => { lbDragging = false; });
</script>

<?php require_once BASE_PATH . '/views/admin/layout/footer.php'; ?>