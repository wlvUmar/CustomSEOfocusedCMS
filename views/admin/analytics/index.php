<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="page-header">
    <h1><i data-feather="trending-up"></i> Analytics Dashboard</h1>

    <div class="header-actions">
        <select id="periodSelect" onchange="updateAnalyticsFilters()" class="btn">
            <optgroup label="Daily">
                <option value="range:today" <?= ($stats['range'] ?? '') === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="range:yesterday" <?= ($stats['range'] ?? '') === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                <option value="range:day_before" <?= ($stats['range'] ?? '') === 'day_before' ? 'selected' : '' ?>>Day Before Yesterday</option>
            </optgroup>
            <optgroup label="Weekly">
                <option value="range:last_week" <?= ($stats['range'] ?? '') === 'last_week' ? 'selected' : '' ?>>Last Week (Mon–Sun)</option>
            </optgroup>
            <optgroup label="Monthly">
                <option value="months:1" <?= empty($stats['range']) && $stats['months'] == 1 ? 'selected' : '' ?>>Last 1 Month</option>
                <option value="months:3" <?= empty($stats['range']) && $stats['months'] == 3 ? 'selected' : '' ?>>Last 3 Months</option>
                <option value="months:6" <?= empty($stats['range']) && $stats['months'] == 6 ? 'selected' : '' ?>>Last 6 Months</option>
                <option value="months:12" <?= empty($stats['range']) && $stats['months'] == 12 ? 'selected' : '' ?>>Last 12 Months</option>
            </optgroup>
        </select>

        <a href="<?= BASE_URL ?>/admin/analytics/export?months=<?= $stats['months'] ?><?= !empty($stats['range']) ? '&range=' . urlencode($stats['range']) : '' ?>"
           class="btn btn-secondary">
            <i data-feather="download"></i> Export CSV
        </a>
    </div>
</div>

<?php if (!empty($stats['range_label'])): ?>
<div style="margin: 0 0 16px; color: #64748b; font-size: 13px;">
    Showing: <strong><?= e($stats['range_label']) ?></strong>
</div>
<?php endif; ?>

<script>
function updateAnalyticsFilters() {
    const selection = document.getElementById('periodSelect').value;
    const params = new URLSearchParams(window.location.search);

    if (selection.startsWith('range:')) {
        params.set('range', selection.replace('range:', ''));
        params.delete('months');
    } else if (selection.startsWith('months:')) {
        params.set('months', selection.replace('months:', ''));
        params.delete('range');
    }

    window.location = `?${params.toString()}`;
}
</script>

<!-- Performance Scorecards (GSC Style) -->
<script>
    window.baseUrl = '<?= BASE_URL ?>';
</script>
<script>
    const visitsChartData = <?= json_encode($stats['visits_chart'] ?? []) ?>;
    const clicksChartData = <?= json_encode($stats['clicks_chart'] ?? []) ?>;
    const phonesChartData = <?= json_encode($stats['phone_calls_chart'] ?? []) ?>;

    window.performanceChartData = {
        labels: Object.keys(visitsChartData || {}),
        visits: Object.values(visitsChartData || {}),
        clicks: Object.values(clicksChartData || {}),
        phones: Object.values(phonesChartData || {})
    };
</script>
<script src="<?= BASE_URL ?>/js/admin/analytics.js"></script>

<style>
    .performance-scorecards {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .performance-scorecard {
        padding: 20px;
        cursor: pointer;
        transition: all 0.2s;
        border-right: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        gap: 8px;
        position: relative;
    }
    .performance-scorecard:last-child { border-right: none; }
    .performance-scorecard:hover { background: #f8fafc; }
    
    .performance-scorecard.active.visits { border-top: 4px solid #3b82f6; padding-top: 16px; }
    .performance-scorecard.active.clicks { border-top: 4px solid #10b981; padding-top: 16px; }
    .performance-scorecard.active.phones { border-top: 4px solid #f59e0b; padding-top: 16px; }
    .performance-scorecard.active.ctr { border-top: 4px solid #8b5cf6; padding-top: 16px; }
    
    .performance-scorecard:not(.active) { opacity: 0.6; }
    
    .sc-label { color: #64748b; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 6px; }
    .sc-value { font-size: 24px; font-weight: 700; color: #1e293b; }
    .sc-change { font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 4px; }
    .sc-change.positive { color: #10b981; }
    .sc-change.negative { color: #ef4444; }
</style>

<div class="performance-scorecards">
    <?php
    $totalVisits = $stats['total']['total_visits'] ?? 0;
    $totalClicks = $stats['total']['total_clicks'] ?? 0;
    $totalPhones = $stats['total']['total_phone_calls'] ?? 0;
    $overallCtr = $totalVisits > 0 ? round((($totalClicks + $totalPhones) / $totalVisits) * 100, 2) : 0;
    
    $scorecards = [
        ['label' => 'Total Visits', 'icon' => 'eye', 'value' => $totalVisits, 'class' => 'visits', 'metric' => 'visits'],
        ['label' => 'Total Clicks', 'icon' => 'mouse-pointer', 'value' => $totalClicks, 'class' => 'clicks', 'metric' => 'clicks'],
        ['label' => 'Phone Calls', 'icon' => 'phone', 'value' => $totalPhones, 'class' => 'phones', 'metric' => 'phone_calls'],
        ['label' => 'Average CTR', 'icon' => 'percent', 'value' => $overallCtr . '%', 'class' => 'ctr', 'metric' => 'ctr']
    ];
    
    foreach ($scorecards as $sc): 
        $change = $stats['trends']['changes'][$sc['metric']] ?? 0;
    ?>
    <div class="performance-scorecard active <?= $sc['class'] ?>" data-metric="<?= $sc['metric'] ?>">
        <div class="sc-label">
            <i data-feather="<?= $sc['icon'] ?>" style="width: 14px; height: 14px;"></i>
            <?= $sc['label'] ?>
        </div>
        <div class="sc-value"><?= is_numeric($sc['value']) ? number_format($sc['value']) : $sc['value'] ?></div>
        <?php if (isset($stats['trends']['changes'][$sc['metric']])): ?>
        <div class="sc-change <?= $change >= 0 ? 'positive' : 'negative' ?>">
            <i data-feather="<?= $change >= 0 ? 'trending-up' : 'trending-down' ?>" style="width: 12px; height: 12px;"></i>
            <?= abs($change) ?>%
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Insight Mini-Cards -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px;">
    <?php
    $rotationImpact = $this->getAnalyticsModel()->getRotationImpact($stats['months']);
    $navStats = $this->getAnalyticsModel()->getNavigationStats($stats['months']);
    $crawlStats = $this->getAnalyticsModel()->getCrawlInsights(7);
    ?>
    <a href="<?= BASE_URL ?>/admin/analytics/rotation" class="mini-insight-card" style="display: flex; align-items: center; padding: 16px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; text-decoration: none; transition: all 0.2s;">
        <div style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: #fff7ed; color: #f59e0b; border-radius: 10px; margin-right: 12px;">
            <i data-feather="repeat"></i>
        </div>
        <div>
            <div style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;">Rotation</div>
            <div style="font-size: 15px; font-weight: 700; color: #1e293b;"><?= $rotationImpact['ctr_improvement'] > 0 ? '+' : '' ?><?= $rotationImpact['ctr_improvement'] ?>% CTR</div>
        </div>
    </a>
    
    <a href="<?= BASE_URL ?>/admin/analytics/navigation" class="mini-insight-card" style="display: flex; align-items: center; padding: 16px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; text-decoration: none; transition: all 0.2s;">
        <div style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: #e0e7ff; color: #3b82f6; border-radius: 10px; margin-right: 12px;">
            <i data-feather="git-branch"></i>
        </div>
        <div>
            <div style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;">Navigation</div>
            <div style="font-size: 15px; font-weight: 700; color: #1e293b;"><?= number_format($navStats['total_clicks'] ?? 0) ?> link clicks</div>
        </div>
    </a>
    
    <a href="<?= BASE_URL ?>/admin/analytics/crawl" class="mini-insight-card" style="display: flex; align-items: center; padding: 16px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; text-decoration: none; transition: all 0.2s;">
        <div style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: #d1fae5; color: #10b981; border-radius: 10px; margin-right: 12px;">
            <i data-feather="activity"></i>
        </div>
        <div>
            <div style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;">Crawl</div>
            <div style="font-size: 15px; font-weight: 700; color: #1e293b;"><?= $crawlStats['pages_crawled'] ?? 0 ?> pages (7d)</div>
        </div>
    </a>
</div>

<!-- Performance Line Graph (GSC-style) -->
<div class="chart-box">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0;"><i data-feather="activity"></i> Performance Overview</h2>
        
        <?php if (empty($stats['range'])): ?>
        <?php $activeAgg = $stats['aggregation'] ?? (($stats['months'] ?? 3) >= 3 ? 'monthly' : 'weekly'); ?>
        <div class="btn-group" style="display: flex; gap: 8px;">
            <?php if ((int)($stats['months'] ?? 3) >= 1): ?>
            <button onclick="updatePerformanceChart('daily')" id="btn-daily" class="agg-toggle-btn <?= $activeAgg === 'daily' ? 'active' : '' ?>" style="padding: 6px 14px; border: 1px solid <?= $activeAgg === 'daily' ? '#3b82f6' : '#e2e8f0' ?>; background: <?= $activeAgg === 'daily' ? '#3b82f6' : 'white' ?>; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; color: <?= $activeAgg === 'daily' ? 'white' : '#64748b' ?>; transition: all 0.2s;">
                Daily
            </button>
            <button onclick="updatePerformanceChart('weekly')" id="btn-weekly" class="agg-toggle-btn <?= $activeAgg === 'weekly' ? 'active' : '' ?>" style="padding: 6px 14px; border: 1px solid <?= $activeAgg === 'weekly' ? '#3b82f6' : '#e2e8f0' ?>; background: <?= $activeAgg === 'weekly' ? '#3b82f6' : 'white' ?>; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; color: <?= $activeAgg === 'weekly' ? 'white' : '#64748b' ?>; transition: all 0.2s;">
                Weekly
            </button>
            <?php endif; ?>
            <?php if ((int)($stats['months'] ?? 3) >= 3): ?>
            <button onclick="updatePerformanceChart('monthly')" id="btn-monthly" class="agg-toggle-btn <?= $activeAgg === 'monthly' ? 'active' : '' ?>" style="padding: 6px 14px; border: 1px solid <?= $activeAgg === 'monthly' ? '#3b82f6' : '#e2e8f0' ?>; background: <?= $activeAgg === 'monthly' ? '#3b82f6' : 'white' ?>; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; color: <?= $activeAgg === 'monthly' ? 'white' : '#64748b' ?>; transition: all 0.2s;">
                Monthly
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <canvas id="performanceChart" height="320"></canvas>
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
// Chart update function is now defined in analytics.js

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

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
