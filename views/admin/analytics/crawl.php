<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="page-header">
    <h1><i data-feather="activity"></i> Анализ обхода поисковиками</h1>
    <div class="header-actions">
        <select id="crawlDaysSelect" class="btn">
            <option value="7" <?= $days == 7 ? 'selected' : '' ?>>Последние 7 дней</option>
            <option value="14" <?= $days == 14 ? 'selected' : '' ?>>Последние 14 дней</option>
            <option value="30" <?= $days == 30 ? 'selected' : '' ?>>Последние 30 дней</option>
            <option value="90" <?= $days == 90 ? 'selected' : '' ?>>Последние 90 дней</option>
        </select>
    </div>
</div>

<div class="info-banner">
    <strong><i data-feather="info"></i> Об отчёте:</strong> здесь только визиты ботов/краулеров.
    Визиты обычных пользователей исключены. Частый обход — признак важности контента для поисковиков.
</div>

<?php if (empty($crawl_frequency)): ?>
    <div class="empty-state">
        <h2>Данных обхода пока нет</h2>
        <p>Трекинг покажет данные, когда боты поисковиков начнут заходить на страницы.</p>
    </div>
<?php else: ?>

<!-- Charts Row -->
<div class="c-crawl-grid">
    <!-- Bot Activity Over Time -->
    <div class="chart-box c-crawl-box">
        <h2><i data-feather="trending-up"></i> Активность ботов (визиты по дням)</h2>
        <canvas id="botActivityChart" height="250"></canvas>
    </div>

    <!-- Bot Distribution -->
    <div class="chart-box c-crawl-box">
        <h2><i data-feather="pie-chart"></i> Распределение ботов</h2>
        <canvas id="botDistributionChart" height="250"></canvas>
    </div>
</div>

<!-- Top Crawled Pages -->
<div class="chart-box">
    <h2><i data-feather="bar-chart-2"></i> Топ-10 обходимых страниц</h2>
    <canvas id="topPagesChart" height="100"></canvas>
</div>

<!-- Summary Stats Cards -->
<div class="crawl-summary-cards">
    <?php
    $dailyPages = 0;
    $weeklyPages = 0;
    $monthlyPages = 0;
    $rarePages = 0;

    foreach ($crawl_frequency as $page) {
        $daysSince = (strtotime('today') - strtotime($page['last_visit'])) / 86400;
        if ($daysSince <= 1) $dailyPages++;
        elseif ($daysSince <= 7) $weeklyPages++;
        elseif ($daysSince <= 30) $monthlyPages++;
        else $rarePages++;
    }
    ?>

    <div class="summary-card daily">
        <div class="card-icon"><i data-feather="zap"></i></div>
        <div class="card-content">
            <div class="card-number"><?= $dailyPages ?></div>
            <div class="card-label">Ежедневный обход</div>
            <div class="card-desc">Визит &lt; 24ч назад</div>
        </div>
    </div>

    <div class="summary-card weekly">
        <div class="card-icon"><i data-feather="calendar"></i></div>
        <div class="card-content">
            <div class="card-number"><?= $weeklyPages ?></div>
            <div class="card-label">Еженедельный обход</div>
            <div class="card-desc">Визит &lt; 7 дней назад</div>
        </div>
    </div>

    <div class="summary-card monthly">
        <div class="card-icon"><i data-feather="clock"></i></div>
        <div class="card-content">
            <div class="card-number"><?= $monthlyPages ?></div>
            <div class="card-label">Ежемесячный обход</div>
            <div class="card-desc">Визит &lt; 30 дней назад</div>
        </div>
    </div>

    <div class="summary-card rare">
        <div class="card-icon"><i data-feather="alert-triangle"></i></div>
        <div class="card-content">
            <div class="card-number"><?= $rarePages ?></div>
            <div class="card-label">Редко/устарели</div>
            <div class="card-desc">Требуют внимания</div>
        </div>
    </div>
</div>

<!-- Detailed Data (Collapsible) -->
<div class="chart-box">
    <div class="c-crawl-toggle" id="crawlDetailsToggle" role="button" tabindex="0" aria-expanded="false">
        <h2><i data-feather="list"></i> Детальный лог обхода (<?= count($crawl_frequency) ?> стр.)</h2>
        <i data-feather="chevron-down" id="details-arrow"></i>
    </div>

    <div id="detailed-data" class="c-crawl-details c-hidden">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Страница</th>
                    <th>Бот</th>
                    <th>Визиты</th>
                    <th>Последний визит</th>
                    <th>Частота</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($crawl_frequency as $page):
                    $daysSince = round((strtotime('today') - strtotime($page['last_visit'])) / 86400);
                    $status = $daysSince <= 1 ? 'Ежедневно' : ($daysSince <= 7 ? 'Еженедельно' : ($daysSince <= 30 ? 'Ежемесячно' : 'Редко'));
                    $statusCls = $daysSince <= 1 ? 'c-st-daily' : ($daysSince <= 7 ? 'c-st-weekly' : ($daysSince <= 30 ? 'c-st-monthly' : 'c-st-rare'));
                ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>/<?= e($page['page_slug']) ?>" target="_blank" class="c-crawl-link">
                            /<?= e($page['page_slug']) ?>
                        </a>
                    </td>
                    <td><span class="badge badge-<?= strtolower($page['bot_type']) ?>"><?= ucfirst(e($page['bot_type'])) ?></span></td>
                    <td><?= number_format($page['total_visits']) ?></td>
                    <td><?= date('M d', strtotime($page['last_visit'])) ?> <span class="c-crawl-ago">(<?= $daysSince ?>д назад)</span></td>
                    <td><span class="<?= $statusCls ?>"><?= $status ?></span></td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/analytics/page/<?= e($page['page_slug']) ?>" class="btn btn-sm">Статистика</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$crawlBoot = [
    'dailyStats' => $daily_stats ?? [],
    'botSummary' => $bot_summary ?? [],
    'topPages' => array_slice($crawl_frequency ?? [], 0, 10),
];
?>
<script id="crawlBoot" type="application/json"><?= json_encode($crawlBoot) ?></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script src="<?= BASE_URL ?>/js/admin/analytics.js?v=9"></script>

<style>
    :root { --teal: #0e9f8a; }
    .c-hidden { display: none !important; }
    .c-crawl-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
    .c-crawl-box { margin: 0; }
    .c-crawl-toggle { display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
    .c-crawl-details { margin-top: 20px; }
    .c-crawl-link { font-weight: 500; }
    .c-crawl-ago { font-size: 11px; color: #6b7280; }
    .c-st-daily, .c-st-weekly, .c-st-monthly, .c-st-rare { font-weight: 600; }
    .c-st-daily { color: var(--teal); }
    .c-st-weekly { color: #3b82f6; }
    .c-st-monthly { color: #f59e0b; }
    .c-st-rare { color: #ef4444; }
</style>

<?php endif; ?>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
