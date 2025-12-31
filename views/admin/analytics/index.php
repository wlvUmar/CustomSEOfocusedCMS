<?php 
// path: ./views/admin/analytics/index.php
$pageName = 'analytics/index';
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
            <i data-feather="download"></i> Export CSV
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
        <i data-feather="trending-up"></i> Overview
    </a>
    <a href="<?= BASE_URL ?>/admin/analytics/rotation" class="tab-link">
        <i data-feather="repeat"></i> Rotation Analysis
    </a>
    <a href="<?= BASE_URL ?>/admin/analytics/navigation" class="tab-link">
        <i data-feather="git-branch"></i> Navigation Flow
    </a>
    <a href="<?= BASE_URL ?>/admin/analytics/crawl" class="tab-link">
        <i data-feather="zap"></i> Crawl Frequency
    </a>
</div>

<!-- Overview Content -->
<?php if ($stats['view'] === 'overview'): ?>

<!-- Quick Access Cards -->
<div class="quick-access-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 40px;">
    <a href="<?= BASE_URL ?>/admin/analytics/navigation" class="quick-card" style="text-decoration: none; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #3b82f6; transition: all 0.2s;">
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 12px;">
            <div style="width: 48px; height: 48px; background: #eff6ff; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                <i data-feather="git-branch" style="color: #3b82f6; width: 24px; height: 24px;"></i>
            </div>
            <h3 style="margin: 0; font-size: 1.1em; color: var(--text-dark);">Navigation Flow</h3>
        </div>
        <p style="margin: 0; color: var(--text-muted); font-size: 0.9em; line-height: 1.5;">
            See how users navigate between pages. Track internal link clicks, popular paths, and user journeys.
        </p>
        <div style="margin-top: 15px; color: #3b82f6; font-weight: 600; font-size: 0.9em; display: flex; align-items: center; gap: 5px;">
            View Navigation Analytics <i data-feather="arrow-right" style="width: 16px; height: 16px;"></i>
        </div>
    </a>
    
    <a href="<?= BASE_URL ?>/admin/analytics/rotation" class="quick-card" style="text-decoration: none; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #059669; transition: all 0.2s;">
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 12px;">
            <div style="width: 48px; height: 48px; background: #d1f4e0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                <i data-feather="repeat" style="color: #059669; width: 24px; height: 24px;"></i>
            </div>
            <h3 style="margin: 0; font-size: 1.1em; color: var(--text-dark);">Rotation Stats</h3>
        </div>
        <p style="margin: 0; color: var(--text-muted); font-size: 0.9em; line-height: 1.5;">
            Track which content variations are shown and how they perform each month.
        </p>
        <div style="margin-top: 15px; color: #059669; font-weight: 600; font-size: 0.9em; display: flex; align-items: center; gap: 5px;">
            View Rotation Analytics <i data-feather="arrow-right" style="width: 16px; height: 16px;"></i>
        </div>
    </a>
    
    <a href="<?= BASE_URL ?>/admin/analytics/crawl" class="quick-card" style="text-decoration: none; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #f59e0b; transition: all 0.2s;">
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 12px;">
            <div style="width: 48px; height: 48px; background: #fef3c7; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                <i data-feather="zap" style="color: #f59e0b; width: 24px; height: 24px;"></i>
            </div>
            <h3 style="margin: 0; font-size: 1.1em; color: var(--text-dark);">Crawl Analysis</h3>
        </div>
        <p style="margin: 0; color: var(--text-muted); font-size: 0.9em; line-height: 1.5;">
            Monitor how often search engines crawl your pages and identify stale content.
        </p>
        <div style="margin-top: 15px; color: #f59e0b; font-weight: 600; font-size: 0.9em; display: flex; align-items: center; gap: 5px;">
            View Crawl Analytics <i data-feather="arrow-right" style="width: 16px; height: 16px;"></i>
        </div>
    </a>
</div>

<style>
.quick-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12) !important;
}
</style>

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
                        <i data-feather="eye"></i> View Details
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
<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>