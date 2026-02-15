<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="page-header">
    <h1>Content Rotation Analytics</h1>
    <div class="header-actions">
        <select onchange="window.location='?months='+this.value" class="btn">
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
                    
                    <?php if ($total_visits > 0): ?>
                    <div class="metric-row">
                        <span class="metric-label"><i data-feather="bar-chart"></i> Visits:</span>
                        <span class="metric-value"><?= number_format($total_visits) ?></span>
                    </div>
                    
                    <div class="metric-row">
                        <span class="metric-label"><i data-feather="mouse-pointer"></i> Clicks:</span>
                        <span class="metric-value"><?= number_format($total_clicks) ?></span>
                    </div>
                    
                    <div class="metric-row">
                        <span class="metric-label"><i data-feather="percent"></i> CTR:</span>
                        <span class="metric-value highlight"><?= $ctr ?>%</span>
                    </div>
                    <?php endif; ?>
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
        
        <div class="card-summary" style="position: relative; min-height: 500px;">
            <div style="position: absolute; top: 0; right: 0; cursor: pointer; padding: 12px 16px; background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb; z-index: 10;" onclick="toggleChart(this)">
                <i data-feather="chevron-down" style="width: 18px; height: 18px;"></i>
            </div>
            <?php
            // Prepare chart data
            $chartMonths = [];
            $chartVisits = [];
            $chartCtr = [];
            foreach ($data['rotations'] as $rot) {
                $chartMonths[] = date('M', mktime(0, 0, 0, $rot['rotation_month'], 1));
                $chartVisits[] = (int)($rot['total_visits'] ?? 0);
                $visitsVal = (int)($rot['total_visits'] ?? 0);
                $phonesVal = (int)($rot['total_phone_calls'] ?? 0);
                $ctrVal = $visitsVal > 0 ? round(($phonesVal / $visitsVal) * 100, 2) : 0;
                $chartCtr[] = $ctrVal;
            }
            $chartId = 'chart_' . str_replace(' ', '_', $slug);
            ?>
            <div class="chart-container" style="display: none; width: 100%; height: 450px; padding-top: 40px;">
                <canvas id="<?= $chartId ?>" style="width: 100% !important; height: 100% !important;"></canvas>
            </div>
        </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('<?= $chartId ?>');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: <?= json_encode($chartMonths) ?>,
                            datasets: [
                                {
                                    label: 'Visits',
                                    data: <?= json_encode($chartVisits) ?>,
                                    borderColor: '#3b82f6',
                                    backgroundColor: '#3b82f610',
                                    tension: 0.4,
                                    yAxisID: 'y',
                                    fill: true
                                },
                                {
                                    label: 'Phone Call CTR %',
                                    data: <?= json_encode($chartCtr) ?>,
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
                            maintainAspectRatio: true,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { 
                                    display: true,
                                    position: 'top',
                                    labels: { font: { size: 11 }, boxWidth: 12 }
                                }
                            },
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    title: { display: true, text: 'Visits', font: { size: 11 } }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    title: { display: true, text: 'CTR %', font: { size: 11 } },
                                    grid: { drawOnChartArea: false }
                                }
                            }
                        }
                    });
                }
            });
            </script>
        </div>
    </div>
    
    <?php endforeach; ?>
</div>

<?php endif; ?>

<script>
function toggleChart(element) {
    const container = element.parentElement.querySelector('.chart-container');
    const icon = element.querySelector('i');
    
    if (container.style.display === 'none') {
        container.style.display = 'block';
        icon.style.transform = 'rotate(0deg)';
        // Trigger chart resize after display change
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 100);
    } else {
        container.style.display = 'none';
        icon.style.transform = 'rotate(-90deg)';
    }
}

// Add smooth transition to icon
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.card-summary > div[onclick="toggleChart(this)"] i').forEach(icon => {
        icon.style.transition = 'transform 0.3s ease';
        icon.style.transform = 'rotate(-90deg)';
    });
});
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
