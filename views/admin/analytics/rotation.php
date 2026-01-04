<?php 
// path: ./views/admin/analytics/rotation.php
$pageName = 'analytics/rotation';
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

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
                $isCurrentMonth = $rotation['rotation_month'] == date('n');
                $total_visits = $rotation['total_visits'] ?? 0;
                $total_clicks = $rotation['total_clicks'] ?? 0;
                $ctr = $total_visits > 0 ? round(($total_clicks / $total_visits) * 100, 2) : 0;
            ?>
            
            <div class="rotation-item <?= $isCurrentMonth ? 'current' : '' ?>">
                <div class="rotation-month">
                    <?= $monthName ?>
                    <?php if ($isCurrentMonth): ?>
                    <span class="current-badge"></i> now</span>
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
        
        <div class="card-summary">
            <?php
            $totalShown = array_sum(array_column($data['rotations'], 'times_shown'));
            $avgPerMonth = count($data['rotations']) > 0 ? round($totalShown / count($data['rotations'])) : 0;
            $monthsCovered = count($data['rotations']);
            ?>
            <div class="summary-stat">
                <span class="stat-label"><i data-feather="eye"></i> Total Times Shown:</span>
                <span class="stat-value"><?= number_format($totalShown) ?></span>
            </div>
            <div class="summary-stat">
                <span class="stat-label"><i data-feather="bar-chart-2"></i> Avg per Month:</span>
                <span class="stat-value"><?= number_format($avgPerMonth) ?></span>
            </div>
            <div class="summary-stat">
                <span class="stat-label"><i data-feather="calendar"></i> Months Covered:</span>
                <span class="stat-value"><?= $monthsCovered ?>/12</span>
            </div>
        </div>
    </div>
    
    <?php endforeach; ?>
</div>

<?php endif; ?>


<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
