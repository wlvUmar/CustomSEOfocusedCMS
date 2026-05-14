<?php 
require BASE_PATH . '/views/admin/layout/header.php';
require_once BASE_PATH . '/models/Analytics.php';
?>
 
<div class="page-header">
    <h1>Content Rotation Analytics</h1>
    <div class="header-actions">
        <select id="pageSelector" onchange="updateChartPage(this.value)" class="btn">
            <option value="">-- Select Page --</option>
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
        
        <select id="aggregationSelector" onchange="updateAggregation(this.value)" class="btn">
            <option value="daily" selected>Daily</option>
            <option value="weekly">Weekly</option>
            <option value="monthly">Monthly</option>
        </select>
        
        <select onchange="updateMonthsFilter(this.value)" class="btn">
            <option value="1" <?= $months == 1 ? 'selected' : '' ?>>Last Month</option>
            <option value="3" <?= $months == 3 ? 'selected' : '' ?>>Last 3 Months</option>
            <option value="6" <?= $months == 6 ? 'selected' : '' ?>>Last 6 Months</option>
            <option value="12" <?= $months == 12 ? 'selected' : '' ?>>Last 12 Months</option>
        </select>
    </div>
</div>

<div class="info-banner">
    <strong><i data-feather="bar-chart-2"></i> What This Shows:</strong> This page tracks which rotation content variations are actually displayed to visitors. 
    Use this data to identify your best-performing seasonal content and optimize your rotation strategy.
</div>

<?php if (empty($effectiveness)): ?>
    <div class="empty-state">
        <h2>No Rotation Data Yet</h2>
        <p>Start tracking rotation effectiveness by:</p>
        <ol>
            <li>Enabling rotation on pages</li>
            <li>Creating content for different months</li>
            <li>Waiting for visitors to view the rotated content</li>
        </ol>
        <a href="<?= BASE_URL ?>/admin/rotations/overview" class="btn btn-primary">
            <i data-feather="settings"></i> Set Up Rotations
        </a>
    </div>
<?php else: ?>

<!-- Main Chart Section -->
<div class="chart-card" style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <h3 style="margin-top: 0; margin-bottom: 20px;">Daily Performance Trend</h3>
    <div style="height: 400px; width: 100%; position: relative;">
        <canvas id="mainChart" style="width: 100% !important; height: 100% !important;"></canvas>
    </div>
</div>

<?php
// Get all pages and prepare initial data
$allPages = [];
$firstPageSlug = null;
foreach ($effectiveness as $row) {
    $slug = $row['page_slug'];
    if (!in_array($slug, $allPages)) {
        $allPages[] = $slug;
        if ($firstPageSlug === null) {
            $firstPageSlug = $slug;
        }
    }
}

// Get daily data for first page (or selected page from query param)
$selectedPageSlug = $_GET['page'] ?? $firstPageSlug;
$selectedPageData = $analyticsModel->getRotationDailyDataLast($selectedPageSlug, 30);

// Prepare chart data
$chartDates = [];
$chartVisits = [];
$chartCtr = [];

foreach ($selectedPageData as $day) {
    $chartDates[] = date('M d', strtotime($day['date']));
    $chartVisits[] = (int)($day['visits'] ?? 0);
    $visitsVal = (int)($day['visits'] ?? 0);
    $callsVal = (int)($day['phone_calls'] ?? 0);
    $ctrVal = $visitsVal > 0 ? round(($callsVal / $visitsVal) * 100, 2) : 0;
    $chartCtr[] = $ctrVal;
}
?>

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
                    <span class="current-badge">now</span>
                    <?php endif; ?>
                </div>
                
                <div class="rotation-metrics">
                    <div class="metric-row">
                        <span class="metric-label"><i data-feather="eye"></i> Times Shown:</span>
                        <span class="metric-value"><?= number_format($rotation['times_shown']) ?></span>
                    </div>
                    
                    <div class="metric-row">
                        <span class="metric-label"><i data-feather="calendar"></i> Unique Days:</span>
                        <span class="metric-value"><?= $rotation['unique_days'] ?></span>
                    </div>
                    
                    <div class="metric-row">
                        <span class="metric-label"><i data-feather="bar-chart"></i> Visits:</span>
                        <span class="metric-value"><?= number_format($total_visits) ?></span>
                    </div>
                    
                    <div class="metric-row">
                        <span class="metric-label"><i data-feather="phone"></i> Calls:</span>
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
                        <i data-feather="edit"></i> Edit Rotations
                    </a>
                </div>
            </div>
            
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php endforeach; ?>
</div>

<?php endif; ?>

<script>
// Store all pages data with slug and aggregation as key
let pagesData = {};
let currentAggregation = 'daily';
const months = <?= $months ?>;

// Helper function to format dates
function formatDate(dateStr, aggregation) {
    const date = new Date(dateStr);
    if (aggregation === 'daily') {
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    } else if (aggregation === 'weekly') {
        return 'W' + Math.ceil(date.getDate() / 7) + ' ' + date.toLocaleDateString('en-US', { month: 'short' });
    } else if (aggregation === 'monthly') {
        return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
    }
    return dateStr;
}

// Load data for all aggregation levels
<?php
$allSlugs = array_unique(array_map(function($row) { return $row['page_slug']; }, $effectiveness));
foreach (['daily', 'weekly', 'monthly'] as $agg):
    $daysOrMonths = $agg === 'daily' ? ($months * 30) : $months;
?>
pagesData['<?= $agg ?>'] = <?= json_encode(
    array_reduce(
        $allSlugs,
        function($carry, $slug) use ($analyticsModel, $agg, $daysOrMonths) {
            if ($agg === 'daily') {
                $data = $analyticsModel->getRotationDailyDataLast($slug, $daysOrMonths);
            } elseif ($agg === 'weekly') {
                $data = $analyticsModel->getRotationWeeklyDataLast($slug, $daysOrMonths);
            } else {
                $data = $analyticsModel->getRotationMonthlyDataLast($slug, $daysOrMonths);
            }
            
            $dates = [];
            $visits = [];
            $clicks = [];
            $calls = [];
            $ctr = [];
            
            foreach ($data as $row) {
                $dateKey = $agg === 'daily' ? $row['date'] : ($agg === 'weekly' ? $row['week_start'] : $row['month_start']);
                $dates[] = $dateKey;
                $visits[] = (int)($row['visits'] ?? 0);
                $clicks[] = (int)($row['clicks'] ?? 0);
                $calls[] = (int)($row['phone_calls'] ?? 0);
                $visitsVal = (int)($row['visits'] ?? 0);
                $callsVal = (int)($row['phone_calls'] ?? 0);
                $ctrVal = $visitsVal > 0 ? round(($callsVal / $visitsVal) * 100, 2) : 0;
                $ctr[] = $ctrVal;
            }
            
            $carry[$slug] = ['dates' => $dates, 'visits' => $visits, 'clicks' => $clicks, 'calls' => $calls, 'ctr' => $ctr];
            return $carry;
        },
        []
    )
) ?>;
<?php endforeach; ?>

let mainChart = null;

function updateChartPage(pageSlug) {
    if (!pageSlug || !pagesData[currentAggregation][pageSlug]) {
        console.warn('Page slug not found:', pageSlug);
        return;
    }
    
    const data = pagesData[currentAggregation][pageSlug];
    
    if (mainChart) {
        // Format dates based on aggregation
        const formattedDates = data.dates.map(d => formatDate(d, currentAggregation));
        mainChart.data.labels = formattedDates;
        mainChart.data.datasets[0].data = data.visits;
        mainChart.data.datasets[1].data = data.clicks;
        mainChart.data.datasets[2].data = data.calls;
        mainChart.data.datasets[3].data = data.ctr;
        mainChart.update();
    }
}

function updateAggregation(aggregation) {
    currentAggregation = aggregation;
    
    // Get currently selected page
    const pageSelector = document.getElementById('pageSelector');
    const selectedPage = pageSelector.value;
    
    if (selectedPage) {
        updateChartPage(selectedPage);
    } else {
        // Update with first page
        const firstPage = Object.keys(pagesData[aggregation])[0];
        if (firstPage) {
            pageSelector.value = firstPage;
            updateChartPage(firstPage);
        }
    }
}

function updateMonthsFilter(months) {
    // Simple string-based URL update
    const basePath = window.location.pathname;
    window.location.href = basePath + '?months=' + encodeURIComponent(months);
}

document.addEventListener('DOMContentLoaded', function() {
    const firstPageKey = Object.keys(pagesData['daily'])[0];
    const firstData = pagesData['daily'][firstPageKey];
    
    if (!firstData) return;
    
    // Format dates for display
    const formattedDates = firstData.dates.map(d => formatDate(d, 'daily'));
    
    const ctx = document.getElementById('mainChart');
    if (ctx && firstData) {
        mainChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: formattedDates,
                datasets: [
                    {
                        label: 'Visits',
                        data: firstData.visits,
                        borderColor: '#3b82f6',
                        backgroundColor: '#3b82f610',
                        tension: 0.4,
                        yAxisID: 'y',
                        fill: true
                    },
                    {
                        label: 'Clicks',
                        data: firstData.clicks,
                        borderColor: '#10b981',
                        backgroundColor: '#10b98110',
                        tension: 0.4,
                        yAxisID: 'y',
                        fill: false,
                        borderWidth: 2
                    },
                    {
                        label: 'Phone Calls',
                        data: firstData.calls,
                        borderColor: '#ef4444',
                        backgroundColor: '#ef444410',
                        tension: 0.4,
                        yAxisID: 'y',
                        fill: false,
                        borderWidth: 2
                    },
                    {
                        label: 'CTR %',
                        data: firstData.ctr,
                        borderColor: '#f59e0b',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.4,
                        yAxisID: 'y1',
                        pointRadius: 4,
                        pointBackgroundColor: '#f59e0b'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { 
                        display: true,
                        position: 'top',
                        labels: { font: { size: 12 }, boxWidth: 14 }
                    }
                },
                scales: {
                    x: {
                        ticks: { maxRotation: 45, minRotation: 0 }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Count', font: { size: 12 } }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'CTR %', font: { size: 12 } },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
        
        // Set first page as selected
        const pageSelector = document.getElementById('pageSelector');
        pageSelector.value = firstPageKey;
    }
});
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
