<?php
require BASE_PATH . '/views/admin/layout/header.php';
require_once BASE_PATH . '/models/Analytics.php';
?>

<div class="page-header">
    <h1>Аналитика ротации контента</h1>
    <div class="header-actions">
        <select id="pageSelector" class="btn">
            <option value="">-- Выберите страницу --</option>
            <?php
            $analyticsModel = new Analytics();
            $groupedData = [];
            foreach ($effectiveness as $row) {
                $pageSlug = $row['page_slug'];
                if (!isset($groupedData[$pageSlug])) {
                    $groupedData[$pageSlug] = [
                        'title' => $row['title_ru'],
                    ];
                }
            }
            foreach ($groupedData as $slug => $data):
            ?>
            <option value="<?= e($slug) ?>"><?= e($data['title']) ?></option>
            <?php endforeach; ?>
        </select>

        <select id="aggregationSelector" class="btn">
            <option value="daily" selected>По дням</option>
            <option value="weekly">По неделям</option>
            <option value="monthly">По месяцам</option>
        </select>

        <select id="monthsSelect" class="btn">
            <option value="1" <?= $months == 1 ? 'selected' : '' ?>>Последний месяц</option>
            <option value="3" <?= $months == 3 ? 'selected' : '' ?>>Последние 3 месяца</option>
            <option value="6" <?= $months == 6 ? 'selected' : '' ?>>Последние 6 месяцев</option>
            <option value="12" <?= $months == 12 ? 'selected' : '' ?>>Последние 12 месяцев</option>
        </select>
    </div>
</div>

<div class="info-banner">
    <strong><i data-feather="bar-chart-2"></i> Что здесь:</strong> какие варианты ротации реально показываются посетителям.
    Найдите лучшие сезонные материалы и улучшите стратегию ротации.
</div>

<?php if (empty($effectiveness)): ?>
    <div class="empty-state">
        <h2>Данных ротации пока нет</h2>
        <p>Чтобы начать отслеживание:</p>
        <ol>
            <li>Включите ротацию на страницах</li>
            <li>Создайте контент для разных месяцев</li>
            <li>Дождитесь визитов на ротируемый контент</li>
        </ol>
        <a href="<?= BASE_URL ?>/admin/rotations/overview" class="btn btn-primary">
            <i data-feather="settings"></i> Настроить ротации
        </a>
    </div>
<?php else: ?>

<!-- Main Chart Section -->
<div class="chart-card c-rot-card">
    <h3 class="c-rot-title">Дневной тренд</h3>
    <div class="c-rot-wrap">
        <canvas id="mainChart" class="c-rot-canvas"></canvas>
    </div>
</div>

<?php
$rotBoot = ['months' => $months, 'data' => ['daily' => [], 'weekly' => [], 'monthly' => []]];
$allSlugs = array_values(array_unique(array_map(function ($row) { return $row['page_slug']; }, $effectiveness)));
foreach (['daily', 'weekly', 'monthly'] as $agg) {
    $daysOrMonths = $agg === 'daily' ? ($months * 30) : $months;
    foreach ($allSlugs as $slug) {
        if ($agg === 'daily') {
            $rows = $analyticsModel->getRotationDailyDataLast($slug, $daysOrMonths);
        } elseif ($agg === 'weekly') {
            $rows = $analyticsModel->getRotationWeeklyDataLast($slug, $daysOrMonths);
        } else {
            $rows = $analyticsModel->getRotationMonthlyDataLast($slug, $daysOrMonths);
        }
        $entry = ['dates' => [], 'visits' => [], 'clicks' => [], 'calls' => [], 'ctr' => []];
        foreach ($rows as $r) {
            $dateKey = $agg === 'daily' ? $r['date'] : ($agg === 'weekly' ? $r['week_start'] : $r['month_start']);
            $entry['dates'][] = $dateKey;
            $entry['visits'][] = (int)($r['visits'] ?? 0);
            $entry['clicks'][] = (int)($r['clicks'] ?? 0);
            $entry['calls'][] = (int)($r['phone_calls'] ?? 0);
            $v = (int)($r['visits'] ?? 0);
            $c = (int)($r['phone_calls'] ?? 0);
            $entry['ctr'][] = $v > 0 ? round(($c / $v) * 100, 2) : 0;
        }
        $rotBoot['data'][$agg][$slug] = $entry;
    }
}
?>
<script id="rotationBoot" type="application/json"><?= json_encode($rotBoot) ?></script>
<script src="<?= BASE_URL ?>/js/admin/analytics.js?v=9"></script>

<style>
    :root { --teal: #0e9f8a; }
    .c-rot-card { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .c-rot-title { margin-top: 0; margin-bottom: 20px; }
    .c-rot-wrap { height: 400px; width: 100%; position: relative; }
    .c-rot-canvas { width: 100% !important; height: 100% !important; }
</style>

<!-- Rotation Items Grid -->
<div class="rotation-effectiveness-grid">
    <?php
    $groupedData = [];
    foreach ($effectiveness as $row) {
        $pageSlug = $row['page_slug'];
        if (!isset($groupedData[$pageSlug])) {
            $groupedData[$pageSlug] = [
                'title' => $row['title_ru'],
                'rotations' => []
            ];
        }
        $groupedData[$pageSlug]['rotations'][] = $row;
    }

    foreach ($groupedData as $slug => $data):
    ?>

    <div class="effectiveness-card">
        <div class="card-header">
            <h2><?= e($data['title']) ?></h2>
            <span class="slug-badge"><?= e($slug) ?></span>
        </div>

        <div class="rotation-timeline">
            <?php foreach ($data['rotations'] as $rotation):
                $monthName = date('F', mktime(0, 0, 0, $rotation['rotation_month'], 1));
                $year = $rotation['year'];
                $currentMonth = date('n');
                $currentYear = date('Y');
                $isCurrentMonth = ($rotation['rotation_month'] == $currentMonth && $year == $currentYear);
                $total_visits = $rotation['total_visits'] ?? 0;
                $total_phones = $rotation['total_phone_calls'] ?? 0;
                $ctr = $total_visits > 0 ? round(($total_phones / $total_visits) * 100, 2) : 0;
            ?>

            <div class="rotation-item <?= $isCurrentMonth ? 'current' : '' ?>">
                <div class="rotation-month">
                    <?= $monthName ?> <?= $year ?>
                    <?php if ($isCurrentMonth): ?>
                    <span class="current-badge">сейчас</span>
                    <?php endif; ?>
                </div>

                <div class="rotation-metrics">
                    <div class="metric-row">
                        <span class="metric-label"><i data-feather="eye"></i> Показов:</span>
                        <span class="metric-value"><?= number_format($rotation['times_shown']) ?></span>
                    </div>

                    <div class="metric-row">
                        <span class="metric-label"><i data-feather="calendar"></i> Дней:</span>
                        <span class="metric-value"><?= $rotation['unique_days'] ?></span>
                    </div>

                    <div class="metric-row">
                        <span class="metric-label"><i data-feather="bar-chart"></i> Визиты:</span>
                        <span class="metric-value"><?= number_format($total_visits) ?></span>
                    </div>

                    <div class="metric-row">
                        <span class="metric-label"><i data-feather="phone"></i> Звонки:</span>
                        <span class="metric-value"><?= number_format($total_phones) ?></span>
                    </div>

                    <div class="metric-row">
                        <span class="metric-label"><i data-feather="percent"></i> CTR:</span>
                        <span class="metric-value highlight"><?= $ctr ?>%</span>
                    </div>
                </div>

                <div class="rotation-actions">
                    <a href="<?= BASE_URL ?>/admin/rotations/manage/<?= $rotation['page_id'] ?? '' ?>"
                       class="btn btn-sm">
                        <i data-feather="edit"></i> Изменить ротации
                    </a>
                </div>
            </div>

            <?php endforeach; ?>
        </div>
    </div>

    <?php endforeach; ?>
</div>

<?php endif; ?>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
