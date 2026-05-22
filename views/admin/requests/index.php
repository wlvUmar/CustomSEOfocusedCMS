<?php require_once BASE_PATH . '/views/admin/layout/header.php'; ?>
<?php
$pendingCount = count($requests ?? []);
$statusLabels = [
    'pending' => 'Ожидает',
    'in_review' => 'В работе',
    'approved' => 'Оценено',
    'rejected' => 'Без оценки'
];
?>
<div class="request-page">
    <div class="request-hero">
        <div>
            <div class="eyebrow">Проверка заявок</div>
            <h1>Заявки на оценку</h1>
            <p>Смотрите новые фото, быстро ставьте цену и отправляйте ответ клиенту.</p>
        </div>
        <div class="request-hero-stats">
            <div class="stat-card">
                <span class="stat-label">Ожидают</span>
                <strong><?= (int)$pendingCount ?></strong>
            </div>
        </div>
    </div>

    <?php if (!empty($requests)): ?>
        <div class="request-list">
            <?php foreach ($requests as $request): ?>
                <?php
                $status = $request['status'] ?? 'pending';
                $statusClass = 'is-pending';
                if ($status === 'approved') $statusClass = 'is-approved';
                elseif ($status === 'rejected') $statusClass = 'is-rejected';
                elseif ($status === 'in_review') $statusClass = 'is-review';
                ?>
                <article class="request-card">
                    <div class="request-thumb">
                        <?php if (!empty($request['image_path'])): ?>
                            <img src="<?= htmlspecialchars($request['image_path']) ?>" alt="">
                        <?php else: ?>
                            <div class="thumb-placeholder">No image</div>
                        <?php endif; ?>
                    </div>

                    <div class="request-content">
                        <div class="request-topline">
                            <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></span>
                            <span class="photo-badge"><?= (int)($request['photo_count'] ?? 1) ?> фото</span>
                        </div>

                        <h3>Заявка #<?= htmlspecialchars($request['id']) ?></h3>
                        <p><?= htmlspecialchars(substr($request['description'] ?? '', 0, 160)) ?></p>

                        <div class="request-meta">
                            <span>Создано: <?= htmlspecialchars($request['created_at'] ?? '') ?></span>
                        </div>
                    </div>

                    <div class="request-actions">
                        <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/requests/<?= htmlspecialchars($request['id']) ?>">Открыть</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>Заявок пока нет</h3>
            <p>Когда клиент отправит фото в Telegram, оно появится здесь.</p>
        </div>
    <?php endif; ?>
</div>
<?php require_once BASE_PATH . '/views/admin/layout/footer.php'; ?>
