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
            <div class="eyebrow">Карточка заявки</div>
            <h1>Заявка #<?= htmlspecialchars($request['id']) ?></h1>
            <p><?= nl2br(htmlspecialchars($request['description'] ?? '')) ?></p>
        </div>
        <div class="request-hero-stats">
            <div class="stat-card">
                <span class="stat-label">Статус</span>
                <strong class="status-strong <?= $statusClass ?>"><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></strong>
            </div>
            <div class="stat-card">
                <span class="stat-label">Фото</span>
                <strong><?= (int)($request['photo_count'] ?? 1) ?></strong>
            </div>
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
                            <a href="<?= htmlspecialchars($image['image_path']) ?>" target="_blank" class="gallery-item">
                                <img src="<?= htmlspecialchars($image['image_path']) ?>" alt="">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Информация</h2>
                </div>

                <div class="info-grid">
                    <div><span>ID</span><strong>#<?= htmlspecialchars($request['id']) ?></strong></div>
                    <div><span>Статус</span><strong><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></strong></div>
                    <div><span>Создано</span><strong><?= htmlspecialchars($request['created_at'] ?? '') ?></strong></div>
                    <div><span>Проверено</span><strong><?= htmlspecialchars($request['reviewed_at'] ?? '—') ?></strong></div>
                </div>
            </div>
        </section>

        <aside class="review-side">
            <div class="panel action-panel">
                <div class="panel-head">
                    <h2>Оценить</h2>
                </div>
                <form method="post" action="<?= BASE_URL ?>/admin/requests/approve">
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
                            <option value="+998947307704">+998947307704</option>
                        </select>
                    </div>
                    <button class="btn btn-success btn-block" type="submit">Отправить цену</button>
                </form>
            </div>

            <div class="panel action-panel danger">
                <div class="panel-head">
                    <h2>Нет оценки</h2>
                </div>
                <form method="post" action="<?= BASE_URL ?>/admin/requests/reject">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($request['id']) ?>">
                    <div class="form-group">
                        <label>Комментарий</label>
                        <textarea name="notes" class="form-control" rows="5" placeholder="Коротко и понятно"></textarea>
                    </div>
                    <button class="btn btn-danger btn-block" type="submit">Отправить ответ</button>
                </form>
            </div>

            <a class="back-link" href="<?= BASE_URL ?>/admin/requests">← Назад</a>
        </aside>
    </div>
</div>
<?php require_once BASE_PATH . '/views/admin/layout/footer.php'; ?>
