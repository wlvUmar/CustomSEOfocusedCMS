<?php require_once BASE_PATH . '/views/admin/layout/header.php'; ?>
<?php
$status = $request['status'] ?? 'pending';
$statusLabels = [
    'pending' => 'Ожидает',
    'in_review' => 'В работе',
    'approved' => 'Оценено',
    'rejected' => 'Без оценки'
];
$statusClass = 'is-pending';
if ($status === 'approved') $statusClass = 'is-approved';
elseif ($status === 'rejected') $statusClass = 'is-rejected';
elseif ($status === 'in_review') $statusClass = 'is-review';
?>
<div class="request-page request-page-detail">
    <div class="request-hero">
        <div>
            <h1>Заявка <?= htmlspecialchars($request['id']) ?></h1>
        </div>

    </div>

    <div class="review-layout">
        <section class="review-main">
            <div class="panel">
                <div class="panel-head">
                    <h2>Фото</h2>
                    <span class="muted">Галерея клиента</span>
                </div>

                <div class="hero-image">
                    <?php if (!empty($images)): ?>
                            <img src="<?= htmlspecialchars($images[0]['image_path']) ?>" alt="">
                    <?php else: ?>
                        <div class="hero-placeholder">Нет фото</div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($images) && count($images) > 1): ?>
                    <div class="gallery-grid">
                        <?php foreach (array_slice($images, 1) as $image): ?>
                            <div class="gallery-item">
                                <img src="<?= htmlspecialchars($image['image_path']) ?>" alt="">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="panel-head">
                </div>

                <div class="info-grid">
                    <div><strong><?= nl2br(htmlspecialchars($request['description'] ?? '--')) ?></strong></div>
                    <div><span>Статус</span><strong><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></strong></div>
                    <div><span>Телефон</span><strong><?= htmlspecialchars($phone ?: '—') ?></strong></div>
                    <div><span>Создано</span><strong><?= htmlspecialchars($request['created_at'] ?? '') ?></strong></div>
                    <div><span>Проверено</span><strong><?= htmlspecialchars($request['reviewed_at'] ?? '—') ?></strong></div>
                </div>
            </div>
        </section>

        <aside class="review-side">
            <?php if ($status === 'pending' || $status === 'in_review'): ?>
                <div class="panel action-panel">
                    <div class="panel-head">
                        <h2>Оценить</h2>
                    </div>
                    <form method="post" action="<?= BASE_URL ?>/admin/requests/approve?token=<?= htmlspecialchars($token ?? '') ?>">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($request['id']) ?>">
                        <div class="form-group">
                            <label>Цена</label>
                            <input type="text" name="price" class="form-control" placeholder="Например: 150000">
                        </div>
                        <div class="form-group">
                            <label>Комментарий</label>
                            <textarea name="notes" class="form-control" rows="5" placeholder="Коротко и понятно"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Телефон для звонка</label>
                            <select name="contact_phone" class="form-control">
                                <option value="">Без контакта</option>
                                <option value="+998900069777" selected>+998900069777</option>
                                <option value="+998947307704">Abl</option>
                                <option value="+998704744047">Akosh</option>
                            </select>
                        </div>
                        <button class="btn btn-success btn-block" type="submit">Отправить цену</button>
                    </form>
                </div>

                <div class="panel action-panel danger">
                    <div class="panel-head">
                        <h2>Нет оценки</h2>
                    </div>
                    <form method="post" action="<?= BASE_URL ?>/admin/requests/reject?token=<?= htmlspecialchars($token ?? '') ?>">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($request['id']) ?>">
                        <div class="form-group">
                            <label>Комментарий</label>
                            <textarea name="notes" class="form-control" rows="5" placeholder="Коротко и понятно"></textarea>
                        </div>
                        <button class="btn btn-danger btn-block" type="submit">Отправить ответ</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="panel action-panel">
                    <div class="panel-head">
                        <h2>Заявка обработана</h2>
                    </div>
                    <p class="muted">Статус: <strong><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></strong></p>
                    <?php if (!empty($request['price'])): ?>
                        <p>Цена: <strong><?= htmlspecialchars($request['price']) ?></strong></p>
                    <?php endif; ?>
                    <?php if (!empty($request['notes'])): ?>
                        <p>Комментарий: <strong><?= htmlspecialchars($request['notes']) ?></strong></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <a class="back-link btn" href="<?= BASE_URL ?>/admin/requests">← Назад</a>
        </aside>
    </div>
</div>
<div id="img-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.92);align-items:center;justify-content:center;flex-direction:column;">
    <button onclick="closeModal()" style="position:absolute;top:16px;right:20px;background:none;border:none;color:#fff;font-size:32px;cursor:pointer;line-height:1;">&times;</button>
    <div style="position:absolute;top:16px;left:50%;transform:translateX(-50%);display:flex;gap:8px;">
        <button onclick="zoom(-0.25)" style="background:rgba(255,255,255,0.15);border:none;color:#fff;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:18px;">−</button>
        <button onclick="zoom(0.25)" style="background:rgba(255,255,255,0.15);border:none;color:#fff;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:18px;">+</button>
        <button onclick="resetZoom()" style="background:rgba(255,255,255,0.15);border:none;color:#fff;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;">сброс</button>
    </div>
    <div id="modal-img-wrap" style="overflow:hidden;width:100vw;height:100vh;display:flex;align-items:center;justify-content:center;cursor:grab;">
        <img id="modal-img" src="" style="max-width:90vw;max-height:85vh;object-fit:contain;transform-origin:center;transition:transform 0.15s;user-select:none;">
    </div>
</div>

<script>
let _scale = 1, _dragging = false, _startX, _startY, _tx = 0, _ty = 0;
const modal = document.getElementById('img-modal');
const img = document.getElementById('modal-img');
const wrap = document.getElementById('modal-img-wrap');

document.querySelectorAll('.hero-image img, .gallery-item img').forEach(el => {
    el.style.cursor = 'zoom-in';
    el.addEventListener('click', function(e) {
        e.preventDefault();
        openModal(this.src);
    });
});

function openModal(src) {
    img.src = src;
    _scale = 1; _tx = 0; _ty = 0;
    applyTransform();
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    modal.style.display = 'none';
    document.body.style.overflow = '';
}
function zoom(delta) {
    _scale = Math.min(5, Math.max(0.5, _scale + delta));
    applyTransform();
}
function resetZoom() { _scale = 1; _tx = 0; _ty = 0; applyTransform(); }
function applyTransform() {
    img.style.transform = `translate(${_tx}px, ${_ty}px) scale(${_scale})`;
}

modal.addEventListener('click', function(e) { if (e.target === modal || e.target === wrap) closeModal(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });

wrap.addEventListener('wheel', function(e) {
    e.preventDefault();
    zoom(e.deltaY < 0 ? 0.15 : -0.15);
}, { passive: false });

wrap.addEventListener('mousedown', function(e) {
    _dragging = true; _startX = e.clientX - _tx; _startY = e.clientY - _ty;
    wrap.style.cursor = 'grabbing';
});
document.addEventListener('mousemove', function(e) {
    if (!_dragging) return;
    _tx = e.clientX - _startX; _ty = e.clientY - _startY;
    applyTransform();
});
document.addEventListener('mouseup', function() { _dragging = false; wrap.style.cursor = 'grab'; });

let _lastTap = 0;
wrap.addEventListener('touchstart', function(e) {
    const now = Date.now();
    if (now - _lastTap < 300) { resetZoom(); }
    _lastTap = now;
    if (e.touches.length === 1) {
        _dragging = true;
        _startX = e.touches[0].clientX - _tx;
        _startY = e.touches[0].clientY - _ty;
    }
}, { passive: true });
wrap.addEventListener('touchmove', function(e) {
    if (_dragging && e.touches.length === 1) {
        _tx = e.touches[0].clientX - _startX;
        _ty = e.touches[0].clientY - _startY;
        applyTransform();
    }
}, { passive: true });
wrap.addEventListener('touchend', function() { _dragging = false; });
</script>
<?php require_once BASE_PATH . '/views/admin/layout/footer.php'; ?>

