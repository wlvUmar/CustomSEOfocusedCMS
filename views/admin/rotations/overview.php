<?php 
// path: ./views/admin/rotations/overview.php
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<div class="page-header">
    <h1>Content Rotation Overview</h1>
    <a href="<?= BASE_URL ?>/admin/pages" class="btn btn-secondary">Back to Pages</a>
</div>

<?php if (!empty($incompletePages)): ?>
<div class="alert alert-error">
    <strong>⚠️ Attention:</strong> <?= count($incompletePages) ?> page(s) with rotation enabled have incomplete month coverage.
</div>
<?php endif; ?>

<div class="rotation-summary">
    <h2>Pages with Content Rotation</h2>
    
    <?php if (empty($rotationStatus)): ?>
        <p>No pages have content rotation enabled.</p>
    <?php else: ?>
        
        <?php foreach ($rotationStatus as $item): 
            $page = $item['page'];
            $stats = $item['stats'];
            $completionPercent = round(($stats['covered_months'] / 12) * 100);
            $statusClass = $completionPercent == 100 ? 'status-complete' : 'status-incomplete';
        ?>
        
        <div class="rotation-card <?= $statusClass ?>">
            <div class="rotation-header">
                <div>
                    <h3>
                        <a href="<?= BASE_URL ?>/admin/rotations/manage/<?= $page['id'] ?>">
                            <?= e($page['title_ru']) ?>
                        </a>
                    </h3>
                    <p class="slug"><?= e($page['slug']) ?></p>
                </div>
                <div class="rotation-stats">
                    <div class="stat-badge">
                        <span class="stat-number"><?= $stats['covered_months'] ?>/12</span>
                        <span class="stat-label">Months</span>
                    </div>
                </div>
            </div>
            
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $completionPercent ?>%"></div>
            </div>
            
            <div class="rotation-details">
                <div class="detail-row">
                    <span class="label">Active:</span>
                    <span class="value"><?= count($stats['active_months']) ?> months</span>
                </div>
                
                <?php if (!empty($stats['missing_months'])): ?>
                <div class="detail-row">
                    <span class="label">Missing:</span>
                    <span class="value missing">
                        <?php 
                        $missingNames = array_map(function($m) {
                            return date('M', mktime(0, 0, 0, $m, 1));
                        }, $stats['missing_months']);
                        echo implode(', ', $missingNames);
                        ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($stats['inactive_months'])): ?>
                <div class="detail-row">
                    <span class="label">Inactive:</span>
                    <span class="value inactive"><?= count($stats['inactive_months']) ?> months</span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="rotation-actions">
                <a href="<?= BASE_URL ?>/admin/rotations/manage/<?= $page['id'] ?>" class="btn btn-sm btn-primary">
                    Manage Rotations
                </a>
                <?php if ($completionPercent < 100): ?>
                <a href="<?= BASE_URL ?>/admin/rotations/new/<?= $page['id'] ?>" class="btn btn-sm">
                    Add Missing Months
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php endforeach; ?>
        
    <?php endif; ?>
</div>

<style>
.rotation-summary {
    margin-top: 30px;
}

.rotation-card {
    background: white;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #059669;
}

.rotation-card.status-incomplete {
    border-left-color: #f59e0b;
}

.rotation-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 16px;
}

.rotation-header h3 {
    margin: 0 0 4px 0;
    font-size: 1.2em;
}

.rotation-header h3 a {
    color: var(--text-dark);
    text-decoration: none;
}

.rotation-header h3 a:hover {
    color: var(--primary-dark);
}

.slug {
    color: var(--text-muted);
    font-size: 0.9em;
    margin: 0;
}

.rotation-stats {
    display: flex;
    gap: 16px;
}

.stat-badge {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 1.5em;
    font-weight: bold;
    color: var(--primary-dark);
}

.stat-label {
    display: block;
    font-size: 0.85em;
    color: var(--text-muted);
}

.progress-bar {
    height: 8px;
    background: var(--accent-light);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 16px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #059669, #10b981);
    transition: width 0.3s;
}

.status-incomplete .progress-fill {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
}

.rotation-details {
    margin-bottom: 16px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid var(--accent-light);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row .label {
    font-weight: 500;
    color: var(--text-muted);
}

.detail-row .value {
    color: var(--text-dark);
}

.detail-row .value.missing {
    color: #f59e0b;
    font-weight: 500;
}

.detail-row .value.inactive {
    color: #dc3545;
}

.rotation-actions {
    display: flex;
    gap: 10px;
    padding-top: 16px;
    border-top: 1px solid var(--accent-light);
}
</style>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>