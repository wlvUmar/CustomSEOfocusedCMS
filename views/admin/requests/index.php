<?php require_once BASE_PATH . '/views/admin/layout/header.php'; ?>

<?php
$statusLabels = [
    'pending'   => 'Ожидает',
    'in_review' => 'В работе',
    'approved'  => 'Оценено',
    'rejected'  => 'Без оценки'
];

// Count per status
$counts = ['all' => count($requests ?? [])];
foreach ($statusLabels as $key => $_) {
    $counts[$key] = count(array_filter($requests ?? [], fn($r) => ($r['status'] ?? 'pending') === $key));
}

// Active filter from query string
$activeFilter = $_GET['status'] ?? 'all';
?>

<div class="request-page">
    <div class="request-hero">
        <div>
            <h1>Заявки</h1>
        </div>
        <div class="request-hero-stats">
            <div class="stat-card">
                <span class="stat-label">Всего</span>
                <strong><?= $counts['all'] ?></strong>
            </div>
            <div class="stat-card">
                <span class="stat-label">Ожидают</span>
                <strong><?= $counts['pending'] ?></strong>
            </div>
            <div class="stat-card">
                <span class="stat-label">Оценено</span>
                <strong><?= $counts['approved'] ?></strong>
            </div>
        </div>
    </div>

    <!-- Status filter tabs -->
    <div class="filter-tabs">
        <?php
        $filters = ['all' => 'Все'] + $statusLabels;
        foreach ($filters as $key => $label):
            $isActive = $activeFilter === $key;
            $url = BASE_URL . '/admin/requests' . ($key !== 'all' ? '?status=' . $key : '');
        ?>
            <a href="<?= $url ?>"
               class="filter-tab <?= $isActive ? 'active' : '' ?> <?= $key !== 'all' ? 'status-tab-' . $key : '' ?>">
                <?= $label ?>
                <span class="filter-count"><?= $counts[$key] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php
    // Filter client-side if controller passes all, or trust controller filtered
    $filtered = array_filter($requests ?? [], function($r) use ($activeFilter) {
        if ($activeFilter === 'all') return true;
        return ($r['status'] ?? 'pending') === $activeFilter;
    });
    ?>

    <?php if (!empty($filtered)): ?>
        <div class="request-list">
            <?php foreach ($filtered as $request): ?>
                <?php
                $status = $request['status'] ?? 'pending';
                $statusClass = match($status) {
                    'approved'  => 'is-approved',
                    'rejected'  => 'is-rejected',
                    'in_review' => 'is-review',
                    default     => 'is-pending'
                };
                ?>
                <article class="request-card">
                    <div class="request-thumb">
                        <?php if (!empty($request['image_path'])): ?>
                            <img src="<?= htmlspecialchars($request['image_path']) ?>" alt="">
                        <?php else: ?>
                            <div class="thumb-placeholder">Нет фото</div>
                        <?php endif; ?>
                    </div>

                    <div class="request-content">
                        <div class="request-topline">
                            <span class="status-badge <?= $statusClass ?>">
                                <?= htmlspecialchars($statusLabels[$status] ?? $status) ?>
                            </span>
                            <span class="photo-badge"><?= (int)($request['photo_count'] ?? 1) ?> фото</span>
                        </div>
                        <h3>Заявка #<?= htmlspecialchars($request['id']) ?></h3>
                        <p><?= htmlspecialchars(substr($request['description'] ?? '', 0, 160)) ?></p>
                        <div class="request-meta">
                            <span>Создано: <?= htmlspecialchars($request['created_at'] ?? '') ?></span>
                        </div>
                    </div>

                    <div class="request-actions">
                        <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/admin/requests/<?= htmlspecialchars($request['id']) ?>">
                            Открыть
                        </a>
                        <form method="post" action="<?= BASE_URL ?>/admin/requests/delete" style="margin:0" data-no-confirm>
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($request['id']) ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Удалить</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>Заявок нет</h3>
            <p>По выбранному фильтру ничего не найдено.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once BASE_PATH . '/views/admin/layout/footer.php'; ?>