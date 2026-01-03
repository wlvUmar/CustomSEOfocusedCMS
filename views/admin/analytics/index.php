<?php
$pageName = 'analytics/index';
require BASE_PATH . '/views/admin/layout/header.php';
?>

<div class="page-header">
    <h1><i data-feather="trending-up"></i> Analytics Dashboard</h1>

    <div class="header-actions">
        <select onchange="window.location='?months='+this.value" class="btn">
            <option value="3" <?= $stats['months'] == 3 ? 'selected' : '' ?>>Last 3 Months</option>
            <option value="6" <?= $stats['months'] == 6 ? 'selected' : '' ?>>Last 6 Months</option>
            <option value="12" <?= $stats['months'] == 12 ? 'selected' : '' ?>>Last 12 Months</option>
        </select>

        <a href="<?= BASE_URL ?>/admin/analytics/export?months=<?= $stats['months'] ?>"
           class="btn btn-secondary">
            <i data-feather="download"></i> Export CSV
        </a>
    </div>
</div>

<!-- Trend Cards -->
<div class="trend-cards">
    <?php
    $metrics = [
        [
            'label' => 'Current Month Visits',
            'icon'  => 'eye',
            'key'   => 'visits'
        ],
        [
            'label' => 'Current Month Clicks',
            'icon'  => 'mouse-pointer',
            'key'   => 'clicks'
        ]
    ];

    foreach ($metrics as $metric):
        $change = $stats['trends']['changes'][$metric['key']] ?? 0;
    ?>
    <div class="trend-card">
        <div class="trend-header">
            <span class="trend-label">
                <i data-feather="<?= $metric['icon'] ?>"></i> <?= $metric['label'] ?>
            </span>

            <span class="trend-change <?= $change >= 0 ? 'positive' : 'negative' ?>">
                <i data-feather="<?= $change >= 0 ? 'trending-up' : 'trending-down' ?>"></i>
                <?= abs($change) ?>%
            </span>
        </div>

        <div class="trend-value">
            <?= number_format($stats['trends']['current'][$metric['key']] ?? 0) ?>
        </div>

        <div class="trend-comparison">
            vs <?= number_format($stats['trends']['previous'][$metric['key']] ?? 0) ?> last month
        </div>
    </div>
    <?php endforeach; ?>

    <div class="trend-card">
        <div class="trend-header">
            <span class="trend-label">
                <i data-feather="percent"></i> Overall CTR
            </span>
        </div>

        <?php
        $totalVisits = $stats['total']['total_visits'] ?? 0;
        $totalClicks = $stats['total']['total_clicks'] ?? 0;
        $ctr = $totalVisits > 0 ? round(($totalClicks / $totalVisits) * 100, 2) : 0;
        ?>

        <div class="trend-value"><?= $ctr ?>%</div>

        <div class="trend-comparison">
            <?= number_format($totalClicks) ?> clicks / <?= number_format($totalVisits) ?> visits
        </div>
    </div>
</div>

<!-- Quick Access -->
<div class="quick-access-cards">
    <a href="<?= BASE_URL ?>/admin/analytics/navigation" class="quick-card primary">
        <div class="quick-card-header">
            <div class="quick-card-icon primary">
                <i data-feather="git-branch"></i>
            </div>
            <h3>Navigation Flow</h3>
        </div>

        <p>Track internal link clicks, popular paths, and user journeys.</p>

        <div class="quick-card-footer">
            View Analytics <i data-feather="arrow-right"></i>
        </div>
    </a>

    <a href="<?= BASE_URL ?>/admin/analytics/rotation" class="quick-card success">
        <div class="quick-card-header">
            <div class="quick-card-icon success">
                <i data-feather="repeat"></i>
            </div>
            <h3>Rotation Stats</h3>
        </div>

        <p>See which content variations are shown and how they perform.</p>

        <div class="quick-card-footer">
            View Analytics <i data-feather="arrow-right"></i>
        </div>
    </a>

    <a href="<?= BASE_URL ?>/admin/analytics/crawl" class="quick-card warning">
        <div class="quick-card-header">
            <div class="quick-card-icon warning">
                <i data-feather="activity"></i>
            </div>
            <h3>Crawl Analysis</h3>
        </div>

        <p>Monitor search engine crawl frequency and stale content.</p>

        <div class="quick-card-footer">
            View Analytics <i data-feather="arrow-right"></i>
        </div>
    </a>
</div>

<?php
$hourlyData = $this->getAnalyticsModel()->getHourlyActivity(7);
$conversionFunnel = $this->getAnalyticsModel()->getConversionFunnel($stats['months']);
?>

<!-- Heatmap -->
<div class="chart-box">
    <h2><i data-feather="clock"></i> Activity Heatmap – Last 7 Days</h2>
    <canvas id="heatmapChart"></canvas>
</div>

<!-- Funnel -->
<div class="chart-box">
    <h2><i data-feather="filter"></i> Conversion Funnel</h2>

    <div class="funnel-container">
        <?php
        $steps = [
            ['label' => 'Page Visits', 'value' => (int)($stats['total']['total_visits'] ?? 0)],
            ['label' => 'Engaged (2+ pages)', 'value' => is_array($conversionFunnel['engaged'] ?? null) ? 0 : (int)($conversionFunnel['engaged'] ?? 0)],
            ['label' => 'Actions / Clicks', 'value' => (int)($stats['total']['total_clicks'] ?? 0)]
        ];

        $max = max(array_column($steps, 'value'), 1); // avoid division by 0
        $totalProgress = 0;
        ?>

        <?php foreach ($steps as $i => $step):
            $widthPercent = max(round(($step['value'] / $max) * 100), 10);
            $totalProgress += $widthPercent;
        ?>
        <div class="funnel-step" style="flex: <?= $widthPercent ?>;">
            <div class="funnel-bar">
                <span class="funnel-label"><?= htmlspecialchars($step['label']) ?></span>
                <span class="funnel-value"><?= number_format($step['value']) ?></span>
                <div class="funnel-progress"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>



<!-- Charts -->
<div class="chart-container">
    <div class="chart-box">
        <h2><i data-feather="bar-chart-2"></i> Monthly Visits</h2>
        <canvas id="visitsChart"></canvas>
    </div>

    <div class="chart-box">
        <h2><i data-feather="mouse-pointer"></i> Monthly Clicks</h2>
        <canvas id="clicksChart"></canvas>
    </div>
</div>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
