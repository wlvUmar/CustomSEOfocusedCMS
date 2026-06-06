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
    'pending'   => 'Ожидает',
    'in_review' => 'В работе',
    'approved'  => 'Оценено',
    'rejected'  => 'Без оценки'
];
$isActionable = ($status === 'pending' || $status === 'in_review');
$imgCount     = count($images ?? []);
?>

<div class="rd">

    <!-- ── Header ── -->
    <div class="rd-header">
        <a href="<?= BASE_URL ?>/admin/requests" class="rd-back">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M9 2L4 7l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Назад
        </a>
        <h1 class="rd-title">Заявка <span>#<?= htmlspecialchars($request['id']) ?></span></h1>
        <span class="rd-status rd-s-<?= $status ?>">
            <?= htmlspecialchars($statusLabels[$status] ?? $status) ?>
        </span>
    </div>

    <!-- ── Photo card ── -->
    <div class="rd-photo-card">
        <div class="rd-viewer" id="rdViewer">
            <?php if (!empty($images)): ?>
                <?php foreach ($images as $i => $img): ?>
                    <div class="rd-slide <?= $i === 0 ? 'active' : '' ?>">
                        <img src="<?= htmlspecialchars($img['image_path']) ?>"
                             alt="Фото <?= $i + 1 ?>"
                             onclick="rdLbOpen(this.src)">
                    </div>
                <?php endforeach; ?>

                <?php if ($imgCount > 1): ?>
                    <button class="rd-arr rd-arr-l" onclick="rdSlide(-1)" type="button">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M11 3.5L6 9l5 5.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <button class="rd-arr rd-arr-r" onclick="rdSlide(1)" type="button">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M7 3.5l5 5.5-5 5.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div class="rd-counter"><span id="rdCurr">1</span> / <?= $imgCount ?></div>
                <?php endif; ?>

            <?php else: ?>
                <div class="rd-no-photo">
                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">
                        <rect x="3" y="3" width="18" height="18" rx="3"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <path d="M21 15l-5-5L5 21"/>
                    </svg>
                    <span>Нет фото</span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($imgCount > 1): ?>
            <div class="rd-strip">
                <?php foreach ($images as $i => $img): ?>
                    <div class="rd-thumb <?= $i === 0 ? 'active' : '' ?>"
                         onclick="rdGoTo(<?= $i ?>)">
                        <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Meta strip ── -->
    <div class="rd-meta-strip">
        <div class="rd-meta-cell">
            <div class="rd-meta-label">Телефон</div>
            <div class="rd-meta-val"><?= htmlspecialchars($phone ?: '—') ?></div>
        </div>
        <div class="rd-meta-cell">
            <div class="rd-meta-label">Статус</div>
            <div class="rd-meta-val"><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></div>
        </div>
        <div class="rd-meta-cell">
            <div class="rd-meta-label">Создано</div>
            <div class="rd-meta-val"><?= htmlspecialchars($request['created_at'] ?? '—') ?></div>
        </div>
        <div class="rd-meta-cell">
            <div class="rd-meta-label">Проверено</div>
            <div class="rd-meta-val"><?= htmlspecialchars($request['reviewed_at'] ?? '—') ?></div>
        </div>
    </div>

    <!-- ── Description ── -->
    <?php if (!empty($request['description'])): ?>
        <div class="rd-desc">
            <div class="rd-meta-label">Описание</div>
            <div class="rd-desc-text"><?= nl2br(htmlspecialchars($request['description'])) ?></div>
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
                    <div class="rd-field">
                        <label for="rd-price">Цена <span class="rd-field-hint">— не нужно при отказе</span></label>
                        <div class="rd-input-wrap">
                            <span class="rd-prefix">сум</span>
                            <input type="text" id="rd-price" name="price"
                                   class="rd-ctrl rd-ctrl-prefix" placeholder="150 000">
                        </div>
                    </div>

                    <!-- Contact phone -->
                    <div class="rd-field">
                        <label>Звонок <span class="rd-field-hint">— не нужно при отказе</span></label>
                        <select name="contact_phone" class="rd-ctrl">
                            <option value="">Без контакта</option>
                            <option value="+998900069777" selected>+998900069777</option>
                            <option value="+998947307704">Abl</option>
                            <option value="+998704744047">Akosh</option>
                        </select>
                    </div>

                    <!-- Notes — full width -->
                    <div class="rd-field rd-field-full">
                        <label for="rd-notes">Комментарий</label>
                        <textarea id="rd-notes" name="notes" class="rd-ctrl rd-textarea"
                                  rows="4" placeholder="Коротко и понятно…"></textarea>
                    </div>

                </div>

                <!-- Action buttons -->
                <div class="rd-btns">
                    <button type="submit"
                            id="rdApproveBtn"
                            class="rd-btn rd-btn-green"
                            formaction="<?= BASE_URL ?>/admin/requests/approve?token=<?= htmlspecialchars($token ?? '') ?>">
                        <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M2 7.5l3.5 3.5 7.5-7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Отправить цену
                    </button>
                    <button type="submit"
                            class="rd-btn rd-btn-red"
                            formaction="<?= BASE_URL ?>/admin/requests/reject?token=<?= htmlspecialchars($token ?? '') ?>">
                        <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M3 3l9 9M12 3L3 12" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                        Без оценки
                    </button>
                </div>

            </form>

        <?php else: ?>

            <!-- Processed state -->
            <div class="rd-done">
                <div class="rd-done-icon rd-done-icon-<?= $status === 'approved' ? 'ok' : 'no' ?>">
                    <?php if ($status === 'approved'): ?>
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M4 12l6 6 10-10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <?php else: ?>
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
                    <?php endif; ?>
                </div>
                <div class="rd-done-title"><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></div>

                <?php if (!empty($request['price'])): ?>
                    <div class="rd-done-price">
                        <div class="rd-meta-label">Оценка</div>
                        <div class="rd-done-price-val">
                            <?= htmlspecialchars($request['price']) ?>
                            <span>сум</span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($request['notes'])): ?>
                    <div class="rd-done-note">
                        <div class="rd-meta-label">Комментарий</div>
                        <p><?= nl2br(htmlspecialchars($request['notes'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>

</div><!-- /rd -->

<!-- ── Lightbox ── -->
<div class="rd-lb" id="rdLb">
    <button class="rd-lb-close" onclick="rdLbClose()" type="button">&times;</button>
    <div class="rd-lb-controls">
        <button onclick="rdLbZoom(-.25)" type="button">−</button>
        <button onclick="rdLbZoom(.25)"  type="button">+</button>
        <button onclick="rdLbReset()"    type="button">↺</button>
    </div>
    <div class="rd-lb-wrap" id="rdLbWrap">
        <img id="rdLbImg" src="" alt="">
    </div>
</div>

<script>
// ── Carousel ──────────────────────────────────────────
var rdCur = 0;
function rdGoTo(n) {
    var slides = document.querySelectorAll('.rd-slide');
    var thumbs = document.querySelectorAll('.rd-thumb');
    var counter = document.getElementById('rdCurr');
    if (!slides.length) return;
    slides[rdCur].classList.remove('active');
    if (thumbs[rdCur]) thumbs[rdCur].classList.remove('active');
    rdCur = (n + slides.length) % slides.length;
    slides[rdCur].classList.add('active');
    if (thumbs[rdCur]) {
        thumbs[rdCur].classList.add('active');
        thumbs[rdCur].scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }
    if (counter) counter.textContent = rdCur + 1;
}
function rdSlide(d) { rdGoTo(rdCur + d); }

(function () {
    var v = document.getElementById('rdViewer'), sx, sy;
    if (!v) return;
    v.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; sy = e.touches[0].clientY; }, { passive: true });
    v.addEventListener('touchend', function (e) {
        var dx = e.changedTouches[0].clientX - sx;
        var dy = e.changedTouches[0].clientY - sy;
        if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 40) rdSlide(dx < 0 ? 1 : -1);
    }, { passive: true });
})();

document.addEventListener('keydown', function (e) {
    if (document.getElementById('rdLb').classList.contains('open')) {
        if (e.key === 'Escape') rdLbClose();
        return;
    }
    if (e.key === 'ArrowLeft')  rdSlide(-1);
    if (e.key === 'ArrowRight') rdSlide(1);
});

// ── Lightbox ──────────────────────────────────────────
var lbScale = 1, lbX = 0, lbY = 0;
var lbDragging = false, lbDragSX, lbDragSY;
var pinchStartDist = 0, pinchStartScale = 1;
var rdLbEl   = document.getElementById('rdLb');
var rdLbImg  = document.getElementById('rdLbImg');
var rdLbWrap = document.getElementById('rdLbWrap');

function lbApply() {
    rdLbImg.style.transform = 'translate(' + lbX + 'px,' + lbY + 'px) scale(' + lbScale + ')';
}

function lbClampPan() {
    if (lbScale <= 1) { lbX = 0; lbY = 0; return; }
    var maxX = Math.max(0, (rdLbImg.offsetWidth  * lbScale - rdLbWrap.offsetWidth)  / 2);
    var maxY = Math.max(0, (rdLbImg.offsetHeight * lbScale - rdLbWrap.offsetHeight) / 2);
    lbX = Math.max(-maxX, Math.min(maxX, lbX));
    lbY = Math.max(-maxY, Math.min(maxY, lbY));
}

function rdLbOpen(src) {
    rdLbImg.src = src;
    lbScale = 1; lbX = 0; lbY = 0;
    lbApply();
    rdLbEl.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function rdLbClose() {
    rdLbEl.classList.remove('open');
    document.body.style.overflow = '';
}

rdLbEl.addEventListener('click', function (e) {
    if (e.target === rdLbEl || e.target === rdLbWrap) rdLbClose();
});

// Scroll wheel zoom (desktop)
rdLbWrap.addEventListener('wheel', function (e) {
    e.preventDefault();
    lbScale = Math.min(6, Math.max(1, lbScale + (e.deltaY < 0 ? 0.15 : -0.15)));
    lbClampPan();
    lbApply();
}, { passive: false });

// Double-click to toggle zoom
rdLbWrap.addEventListener('dblclick', function () {
    lbScale = lbScale > 1.5 ? 1 : 2.5;
    lbX = 0; lbY = 0;
    lbApply();
});

// Mouse drag (desktop)
rdLbWrap.addEventListener('mousedown', function (e) {
    e.preventDefault();
    lbDragging = true;
    lbDragSX = e.clientX - lbX;
    lbDragSY = e.clientY - lbY;
    rdLbWrap.style.cursor = 'grabbing';
});
document.addEventListener('mousemove', function (e) {
    if (!lbDragging) return;
    lbX = e.clientX - lbDragSX;
    lbY = e.clientY - lbDragSY;
    lbClampPan();
    lbApply();
});
document.addEventListener('mouseup', function () {
    lbDragging = false;
    rdLbWrap.style.cursor = 'grab';
});

// Touch: pinch to zoom + single-finger pan
rdLbWrap.addEventListener('touchstart', function (e) {
    if (e.touches.length === 1) {
        lbDragging = true;
        lbDragSX = e.touches[0].clientX - lbX;
        lbDragSY = e.touches[0].clientY - lbY;
    } else if (e.touches.length === 2) {
        lbDragging = false;
        var dx = e.touches[0].clientX - e.touches[1].clientX;
        var dy = e.touches[0].clientY - e.touches[1].clientY;
        pinchStartDist  = Math.sqrt(dx * dx + dy * dy);
        pinchStartScale = lbScale;
    }
}, { passive: true });

rdLbWrap.addEventListener('touchmove', function (e) {
    e.preventDefault();
    if (e.touches.length === 2) {
        var dx   = e.touches[0].clientX - e.touches[1].clientX;
        var dy   = e.touches[0].clientY - e.touches[1].clientY;
        var dist = Math.sqrt(dx * dx + dy * dy);
        lbScale  = Math.min(6, Math.max(1, pinchStartScale * dist / pinchStartDist));
        lbClampPan();
        lbApply();
    } else if (lbDragging && e.touches.length === 1) {
        lbX = e.touches[0].clientX - lbDragSX;
        lbY = e.touches[0].clientY - lbDragSY;
        lbClampPan();
        lbApply();
    }
}, { passive: false });

rdLbWrap.addEventListener('touchend', function (e) {
    if (e.touches.length < 2) pinchStartDist = 0;
    if (e.touches.length === 0) lbDragging = false;
}, { passive: true });
</script>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var approveBtn = document.getElementById('rdApproveBtn');
    var form = document.getElementById('rdForm');
    if (approveBtn && form) {
        approveBtn.addEventListener('click', function (e) {
            var priceEl = document.getElementById('rd-price');
            var price = priceEl ? (priceEl.value || '').trim() : '';
            if (price === '') {
                e.preventDefault();
                alert('Пожалуйста, укажите цену перед отправкой.');
                priceEl && priceEl.focus();
                return false;
            }
        });
    }
});
</script>

<?php require_once BASE_PATH . '/views/admin/layout/footer.php'; ?>