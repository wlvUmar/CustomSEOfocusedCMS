
<?php 
// path: ./views/admin/rotations/overview.php
$pageName = 'rotations/overview';
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<div class="page-header">
    <h1>Content Rotation Overview</h1>
    <a href="<?= BASE_URL ?>/admin/pages" class="btn btn-secondary">Back to Pages</a>
</div>

<?php if (!empty($incompletePages)): ?>
<div class="alert alert-error">
    <strong><i data-feather="alert-triangle"></i> Attention:</strong> <?= count($incompletePages) ?> page(s) with rotation enabled have incomplete month coverage.
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

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>