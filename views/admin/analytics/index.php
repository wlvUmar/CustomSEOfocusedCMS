<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

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

<!-- Top Pages Performance -->
<div class="chart-box">
    <h2><i data-feather="trending-up"></i> Top Performing Pages</h2>
    <canvas id="topPagesChart"></canvas>
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


<?php if (!IS_PRODUCTION): ?>
<!-- DEBUG PANEL -->
<div style="background: #1e293b; color: #e2e8f0; padding: 20px; border-radius: 8px; margin-top: 30px; font-family: monospace; font-size: 12px; border: 2px solid #f59e0b;">
    <div style="margin-bottom: 10px; color: #f59e0b; font-weight: bold;">🔧 DEBUG PANEL</div>
    
    <div style="margin-bottom: 10px;">
        <strong>Chart.js Loaded:</strong> <span id="debug-chartjs">checking...</span>
    </div>
    
    <div style="margin-bottom: 10px;">
        <strong>Visits Data:</strong> <span id="debug-visits">checking...</span>
    </div>
    
    <div style="margin-bottom: 10px;">
        <strong>Clicks Data:</strong> <span id="debug-clicks">checking...</span>
    </div>
    
    <div style="margin-bottom: 10px;">
        <strong>Canvas Elements:</strong> <span id="debug-canvas">checking...</span>
    </div>
    
    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #475569;">
        <strong style="color: #10b981;">✓ Check browser Console (F12) for detailed logs</strong>
    </div>
</div>
<?php endif; ?>

<script>
// Pass chart data from PHP to JavaScript
const visitsChartData = <?= json_encode($stats['visits_chart'] ?? null) ?>;
const clicksChartData = <?= json_encode($stats['clicks_chart'] ?? null) ?>;

// Prepare top pages data
const topPerformers = <?= json_encode($stats['top_performers'] ?? []) ?>;
let topPagesData = {
    labels: [],
    visits: [],
    clicks: []
};

if (topPerformers && Array.isArray(topPerformers) && topPerformers.length > 0) {
    topPerformers.forEach(p => {
        // Use page_slug as fallback label - format it nicely
        const slug = p.page_slug || 'Unknown';
        const label = slug.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ').substring(0, 30);
        const visits = parseInt(p.visits) || 0;
        const clicks = parseInt(p.clicks) || 0;
        
        if (visits > 0) {  // Only add if there's actual data
            topPagesData.labels.push(label);
            topPagesData.visits.push(visits);
            topPagesData.clicks.push(clicks);
        }
    });
}

window.topPagesChartData = topPagesData;

if (window.DEBUG) {
    console.log('=== ANALYTICS DATA DEBUG ===');
    console.log('Stats object received:', typeof stats !== 'undefined' ? 'YES' : 'NO');
    console.log('Raw visitsChartData from PHP:', visitsChartData);
    console.log('Raw clicksChartData from PHP:', clicksChartData);
    console.log('Top performers data:', topPerformers);
    console.log('Processed topPagesData:', topPagesData);
}

// Validate and assign to window
if (visitsChartData && typeof visitsChartData === 'object') {
    window.visitsChartData = {
        labels: Object.keys(visitsChartData),
        data: Object.values(visitsChartData)
    };
    if (window.DEBUG) console.log('✓ window.visitsChartData set:', window.visitsChartData);
} else {
    if (window.DEBUG) console.warn('✗ visitsChartData invalid or null:', visitsChartData);
    window.visitsChartData = { labels: [], data: [] };
}

if (clicksChartData && typeof clicksChartData === 'object') {
    window.clicksChartData = {
        labels: Object.keys(clicksChartData),
        data: Object.values(clicksChartData)
    };
    if (window.DEBUG) console.log('✓ window.clicksChartData set:', window.clicksChartData);
} else {
    if (window.DEBUG) console.warn('✗ clicksChartData invalid or null:', clicksChartData);
    window.clicksChartData = { labels: [], data: [] };
}

if (window.DEBUG) console.log('=== END DATA DEBUG ===');

// Update debug panel (only if DEBUG enabled)
if (window.DEBUG) {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            const chartJsEl = document.getElementById('debug-chartjs');
            const visitsEl = document.getElementById('debug-visits');
            const clicksEl = document.getElementById('debug-clicks');
            const canvasEl = document.getElementById('debug-canvas');
            
            if (chartJsEl) chartJsEl.textContent = (typeof Chart !== 'undefined') ? '✓ YES' : '✗ NO';
            if (visitsEl) visitsEl.textContent = (window.visitsChartData && window.visitsChartData.data.length > 0) 
                ? '✓ ' + window.visitsChartData.data.length + ' data points'
                : '✗ No data';
            if (clicksEl) clicksEl.textContent = (window.clicksChartData && window.clicksChartData.data.length > 0)
                ? '✓ ' + window.clicksChartData.data.length + ' data points'
                : '✗ No data';
            
            const visitsCanvas = !!document.getElementById('visitsChart');
            const clicksCanvas = !!document.getElementById('clicksChart');
            if (canvasEl) canvasEl.textContent = (visitsCanvas && clicksCanvas) ? '✓ Both found' : '✗ Missing: ' + 
                (!visitsCanvas ? 'visits' : '') + 
                (!clicksCanvas ? ' clicks' : '');
        }, 100);
    });
}
</script>

<script src="<?= BASE_URL ?>/js/admin/analytics.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
