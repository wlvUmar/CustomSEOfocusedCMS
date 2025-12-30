<?php 
// path: ./views/admin/analytics/index.php
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<div class="page-header">
    <h1>Analytics Dashboard</h1>
    <div class="header-actions">
        <select onchange="window.location='?months='+this.value" class="btn">
            <option value="3" <?= $stats['months'] == 3 ? 'selected' : '' ?>>Last 3 Months</option>
            <option value="6" <?= $stats['months'] == 6 ? 'selected' : '' ?>>Last 6 Months</option>
            <option value="12" <?= $stats['months'] == 12 ? 'selected' : '' ?>>Last 12 Months</option>
        </select>
        
        <a href="<?= BASE_URL ?>/admin/analytics/export?months=<?= $stats['months'] ?>" 
           class="btn btn-secondary">
            📊 Export CSV
        </a>
    </div>
</div>

<!-- Performance Trends -->
<div class="trend-cards">
    <div class="trend-card">
        <div class="trend-header">
            <span class="trend-label">Current Month Visits</span>
            <span class="trend-change <?= $stats['trends']['changes']['visits'] >= 0 ? 'positive' : 'negative' ?>">
                <?= $stats['trends']['changes']['visits'] >= 0 ? '↑' : '↓' ?>
                <?= abs($stats['trends']['changes']['visits']) ?>%
            </span>
        </div>
        <div class="trend-value"><?= number_format($stats['trends']['current']['visits'] ?? 0) ?></div>
        <div class="trend-comparison">
            vs <?= number_format($stats['trends']['previous']['visits'] ?? 0) ?> last month
        </div>
    </div>
    
    <div class="trend-card">
        <div class="trend-header">
            <span class="trend-label">Current Month Clicks</span>
            <span class="trend-change <?= $stats['trends']['changes']['clicks'] >= 0 ? 'positive' : 'negative' ?>">
                <?= $stats['trends']['changes']['clicks'] >= 0 ? '↑' : '↓' ?>
                <?= abs($stats['trends']['changes']['clicks']) ?>%
            </span>
        </div>
        <div class="trend-value"><?= number_format($stats['trends']['current']['clicks'] ?? 0) ?></div>
        <div class="trend-comparison">
            vs <?= number_format($stats['trends']['previous']['clicks'] ?? 0) ?> last month
        </div>
    </div>
    
    <div class="trend-card">
        <div class="trend-header">
            <span class="trend-label">Overall CTR</span>
        </div>
        <div class="trend-value">
            <?php 
            $totalVisits = $stats['total']['total_visits'] ?? 0;
            $totalClicks = $stats['total']['total_clicks'] ?? 0;
            $ctr = $totalVisits > 0 ? round(($totalClicks / $totalVisits) * 100, 2) : 0;
            echo $ctr;
            ?>%
        </div>
        <div class="trend-comparison">
            <?= number_format($totalClicks) ?> clicks / <?= number_format($totalVisits) ?> visits
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<div class="analytics-tabs">
    <a href="?view=overview&months=<?= $stats['months'] ?>" 
       class="tab-link <?= $stats['view'] === 'overview' ? 'active' : '' ?>">
        Overview
    </a>
    <a href="<?= BASE_URL ?>/admin/analytics/rotation" class="tab-link">
        Rotation Analysis
    </a>
    <a href="<?= BASE_URL ?>/admin/analytics/crawl" class="tab-link">
        Crawl Frequency
    </a>
</div>

<!-- Overview Content -->
<?php if ($stats['view'] === 'overview'): ?>

<!-- Top Performers -->
<div class="top-performers">
    <h2>Top Performing Pages</h2>
    <div class="performers-grid">
        <?php foreach (array_slice($stats['top_performers'], 0, 5) as $performer): ?>
        <div class="performer-card">
            <div class="performer-name">
                <a href="<?= BASE_URL ?>/admin/analytics/page/<?= e($performer['page_slug']) ?>">
                    <?= e($performer['page_slug']) ?>
                </a>
            </div>
            <div class="performer-stats">
                <div class="stat">
                    <span class="stat-label">CTR</span>
                    <span class="stat-value"><?= $performer['ctr'] ?>%</span>
                </div>
                <div class="stat">
                    <span class="stat-label">Visits</span>
                    <span class="stat-value"><?= number_format($performer['visits']) ?></span>
                </div>
                <div class="stat">
                    <span class="stat-label">Clicks</span>
                    <span class="stat-value"><?= number_format($performer['clicks']) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Language Statistics -->
<?php if (!empty($stats['language_stats'])): ?>
<div class="language-stats">
    <h2>Language Distribution</h2>
    <div class="lang-cards">
        <?php foreach ($stats['language_stats'] as $langStat): ?>
        <div class="lang-card">
            <div class="lang-name"><?= strtoupper($langStat['language']) ?></div>
            <div class="lang-metrics">
                <div class="metric">
                    <span class="metric-value"><?= number_format($langStat['visits']) ?></span>
                    <span class="metric-label">Visits</span>
                </div>
                <div class="metric">
                    <span class="metric-value"><?= number_format($langStat['clicks']) ?></span>
                    <span class="metric-label">Clicks</span>
                </div>
                <div class="metric">
                    <span class="metric-value"><?= $langStat['unique_pages'] ?></span>
                    <span class="metric-label">Pages</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Charts -->
<div class="chart-container">
    <div class="chart-box">
        <h2>Monthly Visits</h2>
        <canvas id="visitsChart"></canvas>
    </div>
    
    <div class="chart-box">
        <h2>Monthly Clicks</h2>
        <canvas id="clicksChart"></canvas>
    </div>
</div>

<!-- Detailed Page Stats Table -->
<div style="margin-top: 40px;">
    <h2>Page Statistics (Last <?= $stats['months'] ?> Months)</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Page</th>
                <th>Language</th>
                <th>Visits</th>
                <th>Clicks</th>
                <th>CTR</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stats['page_stats'] as $page): 
                $pageCtr = $page['visits'] > 0 ? number_format(($page['clicks'] / $page['visits']) * 100, 2) : 0;
            ?>
            <tr>
                <td>
                    <a href="<?= BASE_URL ?>/admin/analytics/page/<?= e($page['page_slug']) ?>">
                        <?= e($page['page_slug']) ?>
                    </a>
                </td>
                <td><span class="badge"><?= strtoupper($page['language']) ?></span></td>
                <td><?= number_format($page['visits']) ?></td>
                <td><?= number_format($page['clicks']) ?></td>
                <td>
                    <span class="ctr-badge <?= $pageCtr > 5 ? 'high' : ($pageCtr > 2 ? 'medium' : 'low') ?>">
                        <?= $pageCtr ?>%
                    </span>
                </td>
                <td>
                    <a href="<?= BASE_URL ?>/admin/analytics/page/<?= e($page['page_slug']) ?>" 
                       class="btn btn-sm">
                        View Details
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const visitsData = <?= json_encode($stats['visits_chart']) ?>;
const clicksData = <?= json_encode($stats['clicks_chart']) ?>;

// Visits Chart
new Chart(document.getElementById('visitsChart'), {
    type: 'line',
    data: {
        labels: visitsData.labels,
        datasets: [{
            label: 'Visits',
            data: visitsData.values,
            borderColor: '#303034',
            backgroundColor: 'rgba(48, 48, 52, 0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Clicks Chart
new Chart(document.getElementById('clicksChart'), {
    type: 'line',
    data: {
        labels: clicksData.labels,
        datasets: [{
            label: 'Clicks',
            data: clicksData.values,
            borderColor: '#059669',
            backgroundColor: 'rgba(5, 150, 105, 0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

<style>
.header-actions {
    display: flex;
    gap: 10px;
}

.trend-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.trend-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.trend-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.trend-label {
    font-size: 0.9em;
    color: var(--text-muted);
}

.trend-change {
    font-size: 0.9em;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 4px;
}

.trend-change.positive {
    color: #059669;
    background: #d1f4e0;
}

.trend-change.negative {
    color: #dc3545;
    background: #f8d7da;
}

.trend-value {
    font-size: 2em;
    font-weight: bold;
    color: var(--text-dark);
    margin-bottom: 4px;
}

.trend-comparison {
    font-size: 0.85em;
    color: var(--text-muted);
}

.analytics-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 30px;
    border-bottom: 2px solid var(--accent-light);
}

.tab-link {
    padding: 12px 20px;
    text-decoration: none;
    color: var(--text-muted);
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
}

.tab-link:hover {
    color: var(--text-dark);
}

.tab-link.active {
    color: var(--primary-dark);
    border-bottom-color: var(--primary-dark);
}

.top-performers {
    margin-bottom: 40px;
}

.performers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.performer-card {
    background: white;
    padding: 16px;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.performer-name {
    font-weight: 600;
    margin-bottom: 12px;
    color: var(--text-dark);
}

.performer-name a {
    color: var(--text-dark);
    text-decoration: none;
}

.performer-stats {
    display: flex;
    gap: 12px;
}

.performer-stats .stat {
    flex: 1;
    text-align: center;
}

.performer-stats .stat-label {
    display: block;
    font-size: 0.75em;
    color: var(--text-muted);
    margin-bottom: 2px;
}

.performer-stats .stat-value {
    display: block;
    font-size: 1.1em;
    font-weight: 600;
    color: var(--primary-dark);
}

.language-stats {
    margin-bottom: 40px;
}

.lang-cards {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.lang-card {
    flex: 1;
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.lang-name {
    font-size: 1.5em;
    font-weight: bold;
    color: var(--primary-dark);
    margin-bottom: 16px;
}

.lang-metrics {
    display: flex;
    gap: 20px;
}

.metric {
    flex: 1;
    text-align: center;
}

.metric-value {
    display: block;
    font-size: 1.3em;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 4px;
}

.metric-label {
    display: block;
    font-size: 0.85em;
    color: var(--text-muted);
}

.chart-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.chart-box {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.chart-box h2 {
    margin-bottom: 20px;
    font-size: 1.1em;
}

.chart-box canvas {
    height: 250px !important;
}

.ctr-badge {
    padding: 4px 10px;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.9em;
}

.ctr-badge.high {
    background: #d1f4e0;
    color: #059669;
}

.ctr-badge.medium {
    background: #fef3c7;
    color: #f59e0b;
}

.ctr-badge.low {
    background: #f3f4f6;
    color: #6b7280;
}
</style>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>