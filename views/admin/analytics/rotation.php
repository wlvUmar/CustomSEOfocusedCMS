<?php 
// path: ./views/admin/analytics/rotation.php
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
        <a href="<?= BASE_URL ?>/admin/analytics" class="btn btn-secondary">Back to Analytics</a>
    </div>
</div>

<div class="info-banner">
    <strong>📊 What This Shows:</strong> This page tracks which rotation content variations are actually displayed to visitors. 
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
        <a href="<?= BASE_URL ?>/admin/rotations/overview" class="btn btn-primary">Set Up Rotations</a>
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
                    <span class="current-badge">NOW</span>
                    <?php endif; ?>
                </div>
                
                <div class="rotation-metrics">
                    <div class="metric-row">
                        <span class="metric-label">Times Shown:</span>
                        <span class="metric-value"><?= number_format($rotation['times_shown']) ?></span>
                    </div>
                    
                    <div class="metric-row">
                        <span class="metric-label">Unique Days:</span>
                        <span class="metric-value"><?= $rotation['unique_days'] ?></span>
                    </div>
                    
                    <?php if ($total_visits > 0): ?>
                    <div class="metric-row">
                        <span class="metric-label">Visits:</span>
                        <span class="metric-value"><?= number_format($total_visits) ?></span>
                    </div>
                    
                    <div class="metric-row">
                        <span class="metric-label">Clicks:</span>
                        <span class="metric-value"><?= number_format($total_clicks) ?></span>
                    </div>
                    
                    <div class="metric-row">
                        <span class="metric-label">CTR:</span>
                        <span class="metric-value highlight"><?= $ctr ?>%</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="rotation-actions">
                    <a href="<?= BASE_URL ?>/admin/rotations/manage/<?= $rotation['page_id'] ?? '' ?>" 
                       class="btn btn-sm">
                        Edit Rotations
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
                <span class="stat-label">Total Times Shown:</span>
                <span class="stat-value"><?= number_format($totalShown) ?></span>
            </div>
            <div class="summary-stat">
                <span class="stat-label">Avg per Month:</span>
                <span class="stat-value"><?= number_format($avgPerMonth) ?></span>
            </div>
            <div class="summary-stat">
                <span class="stat-label">Months Covered:</span>
                <span class="stat-value"><?= $monthsCovered ?>/12</span>
            </div>
        </div>
    </div>
    
    <?php endforeach; ?>
</div>

<?php endif; ?>

<style>
.info-banner {
    background: #eff6ff;
    border-left: 4px solid #3b82f6;
    padding: 16px;
    margin-bottom: 30px;
    border-radius: 4px;
    color: #1e3a8a;
}

.empty-state {
    background: white;
    padding: 60px 40px;
    text-align: center;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.empty-state h2 {
    margin-bottom: 20px;
    color: var(--text-dark);
}

.empty-state ol {
    text-align: left;
    max-width: 400px;
    margin: 20px auto;
}

.empty-state li {
    margin-bottom: 10px;
    color: var(--text-muted);
}

.rotation-effectiveness-grid {
    display: grid;
    gap: 30px;
}

.effectiveness-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--accent-light);
    border-bottom: 2px solid var(--primary-light);
}

.card-header h2 {
    margin: 0;
    font-size: 1.3em;
    color: var(--text-dark);
}

.slug-badge {
    background: var(--primary-dark);
    color: var(--primary-light);
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 0.85em;
    font-family: monospace;
}

.rotation-timeline {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1px;
    background: var(--accent-light);
    padding: 1px;
}

.rotation-item {
    background: white;
    padding: 16px;
    transition: all 0.2s;
}

.rotation-item:hover {
    background: var(--accent-light);
}

.rotation-item.current {
    background: #eff6ff;
    border-left: 3px solid #3b82f6;
}

.rotation-month {
    font-size: 1.1em;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.current-badge {
    background: #3b82f6;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 0.7em;
    font-weight: 700;
}

.rotation-metrics {
    margin-bottom: 12px;
}

.metric-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px solid var(--accent-light);
}

.metric-row:last-child {
    border-bottom: none;
}

.metric-label {
    font-size: 0.85em;
    color: var(--text-muted);
}

.metric-value {
    font-weight: 600;
    color: var(--text-dark);
}

.metric-value.highlight {
    color: var(--success);
}

.rotation-actions {
    padding-top: 12px;
    border-top: 1px solid var(--accent-light);
}

.card-summary {
    display: flex;
    justify-content: space-around;
    padding: 20px;
    background: var(--accent-light);
    border-top: 2px solid var(--primary-light);
}

.summary-stat {
    text-align: center;
}

.summary-stat .stat-label {
    display: block;
    font-size: 0.85em;
    color: var(--text-muted);
    margin-bottom: 4px;
}

.summary-stat .stat-value {
    display: block;
    font-size: 1.4em;
    font-weight: 600;
    color: var(--primary-dark);
}

@media (max-width: 768px) {
    .rotation-timeline {
        grid-template-columns: 1fr;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .card-summary {
        flex-direction: column;
        gap: 16px;
    }
}
</style>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>