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

<!-- Insight Cards -->
<div class="insight-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <?php
    // Rotation Impact Insights
    $rotationImpact = $this->getAnalyticsModel()->getRotationImpact($stats['months']);
    ?>
    <a href="<?= BASE_URL ?>/admin/analytics/rotation" style="text-decoration: none;">
        <div class="insight-card" style="background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%); padding: 20px; border-radius: 12px; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                <i data-feather="repeat" style="width: 24px; height: 24px; margin-right: 10px; stroke-width: 2.5;"></i>
                <h3 style="margin: 0; font-size: 16px; font-weight: 600;">Rotation Impact</h3>
            </div>
            <div style="font-size: 32px; font-weight: bold; margin-bottom: 10px;">
                <?= $rotationImpact['ctr_improvement'] >= 0 ? '+' : '' ?><?= $rotationImpact['ctr_improvement'] ?? '0' ?>%
            </div>
            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 12px;">
                CTR Change vs Non-Rotating
            </div>
            <div style="font-size: 13px; opacity: 0.85; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 12px;">
                <div><?= $rotationImpact['pages_with_rotation'] ?? 0 ?> pages use rotation</div>
                <div style="margin-top: 5px;">Click to view details →</div>
            </div>
        </div>
    </a>

    <?php
    // Navigation Insights  
    $navStats = $this->getAnalyticsModel()->getNavigationStats($stats['months']);
    ?>
    <a href="<?= BASE_URL ?>/admin/analytics/navigation" style="text-decoration: none;">
        <div class="insight-card" style="background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%); padding: 20px; border-radius: 12px; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                <i data-feather="git-branch" style="width: 24px; height: 24px; margin-right: 10px; stroke-width: 2.5;"></i>
                <h3 style="margin: 0; font-size: 16px; font-weight: 600;">Navigation Insights</h3>
            </div>
            <div style="font-size: 32px; font-weight: bold; margin-bottom: 10px;">
                <?= number_format($navStats['total_clicks'] ?? 0) ?>
            </div>
            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 12px;">
                Internal Link Clicks
            </div>
            <div style="font-size: 13px; opacity: 0.85; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 12px;">
                <div>Avg CTR: <strong><?= $navStats['avg_ctr'] ?? '0' ?>%</strong></div>
                <div style="margin-top: 5px;">Click to view details →</div>
            </div>
        </div>
    </a>

    <?php
    // Crawl Insights
    $crawlStats = $this->getAnalyticsModel()->getCrawlInsights(7);
    ?>
    <a href="<?= BASE_URL ?>/admin/analytics/crawl" style="text-decoration: none;">
        <div class="insight-card" style="background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%); padding: 20px; border-radius: 12px; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                <i data-feather="activity" style="width: 24px; height: 24px; margin-right: 10px; stroke-width: 2.5;"></i>
                <h3 style="margin: 0; font-size: 16px; font-weight: 600;">Crawl Insights</h3>
            </div>
            <div style="font-size: 32px; font-weight: bold; margin-bottom: 10px;">
                <?= $crawlStats['pages_crawled'] ?? 0 ?>
            </div>
            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 12px;">
                Pages Crawled (7 days)
            </div>
            <div style="font-size: 13px; opacity: 0.85; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 12px;">
                <div>Most active: <strong><?= $crawlStats['top_bot'] ?? 'N/A' ?></strong></div>
                <div style="margin-top: 5px;">Click to view details →</div>
            </div>
        </div>
    </a>
</div>

<!-- Performance Line Graph (GSC-style) -->
<div class="chart-box">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0;"><i data-feather="activity"></i> Performance Overview</h2>
        
        <div class="btn-group" style="display: flex; gap: 8px;">
            <button onclick="updatePerformanceChart('daily')" id="btn-daily" class="agg-toggle-btn" style="padding: 6px 14px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; color: #64748b; transition: all 0.2s;">
                Daily
            </button>
            <button onclick="updatePerformanceChart('weekly')" id="btn-weekly" class="agg-toggle-btn" style="padding: 6px 14px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; color: #64748b; transition: all 0.2s;">
                Weekly
            </button>
            <button onclick="updatePerformanceChart('monthly')" id="btn-monthly" class="agg-toggle-btn active" style="padding: 6px 14px; border: 1px solid #3b82f6; background: #3b82f6; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; color: white; transition: all 0.2s;">
                Monthly
            </button>
        </div>
    </div>
    <canvas id="performanceChart"></canvas>
</div>

<!-- Top Pages Performance -->
<div class="chart-box">
    <h2><i data-feather="trending-up"></i> Top 10 Performing Pages</h2>
    
    <?php
    $topPerformers = array_slice($stats['top_performers'] ?? [], 0, 10);
    if (!empty($topPerformers)):
        // Find max values for scaling
        $maxVisits = max(array_column($topPerformers, 'visits'));
        $maxClicks = max(array_column($topPerformers, 'clicks'));
    ?>
    
    <table style="width: 100%; border-collapse: separate; border-spacing: 0 8px;">
        <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
            <tr>
                <th style="padding: 12px; text-align: left; font-size: 13px; color: #64748b; font-weight: 600;">#</th>
                <th style="padding: 12px; text-align: left; font-size: 13px; color: #64748b; font-weight: 600;">Page</th>
                <th style="padding: 12px; text-align: center; font-size: 13px; color: #64748b; font-weight: 600;">Visits</th>
                <th style="padding: 12px; text-align: center; font-size: 13px; color: #64748b; font-weight: 600;">Clicks</th>
                <th style="padding: 12px; text-align: center; font-size: 13px; color: #64748b; font-weight: 600;">CTR</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($topPerformers as $index => $page):
                $visits = (int)($page['visits'] ?? 0);
                $clicks = (int)($page['clicks'] ?? 0);
                $ctr = $visits > 0 ? round(($clicks / $visits) * 100, 2) : 0;
                $visitsWidth = $maxVisits > 0 ? ($visits / $maxVisits) * 100 : 0;
                $clicksWidth = $maxClicks > 0 ? ($clicks / $maxClicks) * 100 : 0;
            ?>
            <tr style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; border-left: 1px solid #f1f5f9; border-top-left-radius: 8px; border-bottom-left-radius: 8px; font-weight: 600; color: #94a3b8; font-size: 14px;">
                    <?= $index + 1 ?>
                </td>
                <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #1e293b; font-weight: 500;">
                    <div style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($page['page_slug']) ?>">
                        <?= htmlspecialchars($page['page_slug']) ?>
                    </div>
                </td>
                <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;">
                    <div style="text-align: right; margin-bottom: 4px; font-size: 14px; font-weight: 600; color: #3b82f6;">
                        <?= number_format($visits) ?>
                    </div>
                    <div style="background: #e0e7ff; height: 6px; border-radius: 3px; overflow: hidden;">
                        <div style="background: #3b82f6; height: 100%; width: <?= $visitsWidth ?>%; border-radius: 3px;"></div>
                    </div>
                </td>
                <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;">
                    <div style="text-align: right; margin-bottom: 4px; font-size: 14px; font-weight: 600; color: #10b981;">
                        <?= number_format($clicks) ?>
                    </div>
                    <div style="background: #d1fae5; height: 6px; border-radius: 3px; overflow: hidden;">
                        <div style="background: #10b981; height: 100%; width: <?= $clicksWidth ?>%; border-radius: 3px;"></div>
                    </div>
                </td>
                <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; border-top-right-radius: 8px; border-bottom-right-radius: 8px; text-align: center;">
                    <span style="display: inline-block; padding: 4px 12px; background: <?= $ctr >= 5 ? '#d1fae5' : ($ctr >= 2 ? '#fef3c7' : '#fee2e2') ?>; color: <?= $ctr >= 5 ? '#059669' : ($ctr >= 2 ? '#d97706' : '#dc2626') ?>; border-radius: 12px; font-size: 13px; font-weight: 600;">
                        <?= $ctr ?>%
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php else: ?>
    <div style="padding: 40px; text-align: center; color: #94a3b8;">
        <i data-feather="inbox" style="width: 48px; height: 48px; margin-bottom: 16px;"></i>
        <div>No data available yet</div>
    </div>
    <?php endif; ?>
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

// Combine visits and clicks for performance chart
window.performanceChartData = {
    labels: visitsChartData ? Object.keys(visitsChartData) : [],
    visits: visitsChartData ? Object.values(visitsChartData) : [],
    clicks: clicksChartData ? Object.values(clicksChartData) : []
};

// Function to update performance chart with different aggregation
async function updatePerformanceChart(aggregation) {
    // Update button styles
    document.querySelectorAll('.agg-toggle-btn').forEach(btn => {
        btn.style.background = 'white';
        btn.style.color = '#64748b';
        btn.style.borderColor = '#e2e8f0';
    });
    
    const activeBtn = document.getElementById(`btn-${aggregation}`);
    activeBtn.style.background = '#3b82f6';
    activeBtn.style.color = 'white';
    activeBtn.style.borderColor = '#3b82f6';
    
    // Fetch new data
    try {
        const months = <?= $stats['months'] ?>;
        const response = await fetch(`<?= BASE_URL ?>/admin/analytics/getData?months=${months}&aggregation=${aggregation}`);
        const data = await response.json();
        
        // Update chart
        if (performanceChartInstance && data.visits && data.clicks) {
            performanceChartInstance.data.labels = Object.keys(data.visits);
            performanceChartInstance.data.datasets[0].data = Object.values(data.visits);
            performanceChartInstance.data.datasets[1].data = Object.values(data.clicks);
            performanceChartInstance.update();
        }
    } catch (error) {
        console.error('Error fetching chart data:', error);
    }
}

if (window.DEBUG) {
    console.log('=== ANALYTICS DATA DEBUG ===');
    console.log('Performance data:', window.performanceChartData);
    console.log('=== END DATA DEBUG ===');
}

// Update debug panel (only if DEBUG enabled)
if (window.DEBUG) {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            const chartJsEl = document.getElementById('debug-chartjs');
            const performanceEl = document.getElementById('debug-visits');
            const canvasEl = document.getElementById('debug-canvas');
            
            if (chartJsEl) chartJsEl.textContent = (typeof Chart !== 'undefined') ? '✓ YES' : '✗ NO';
            if (performanceEl) {
                performanceEl.parentElement.querySelector('strong').textContent = 'Performance Data:';
                performanceEl.textContent = (window.performanceChartData && window.performanceChartData.labels.length > 0) 
                    ? '✓ ' + window.performanceChartData.labels.length + ' data points'
                    : '✗ No data';
            }
            
            const clicksEl = document.getElementById('debug-clicks');
            if (clicksEl) clicksEl.parentElement.style.display = 'none'; // Hide old clicks check
            
            const performanceCanvas = !!document.getElementById('performanceChart');
            const topPagesCanvas = !!document.getElementById('topPagesChart');
            if (canvasEl) canvasEl.textContent = (performanceCanvas && topPagesCanvas) ? '✓ Both found' : '✗ Missing: ' + 
                (!performanceCanvas ? 'performance' : '') + 
                (!topPagesCanvas ? ' topPages' : '');
        }, 100);
    });
}
</script>

<script src="<?= BASE_URL ?>/js/admin/analytics.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
