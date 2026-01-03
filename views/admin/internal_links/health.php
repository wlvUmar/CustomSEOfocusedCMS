<?php
// PRODUCTION READY: views/admin/internal_links/health.php
// Removed BETA badge, improved logic

require BASE_PATH . '/views/admin/layout/header.php';
?>

<div class="page-header">
    <h1><i data-feather="shield"></i> Link Health Checker</h1>
    <div class="btn-group">
        <button onclick="location.reload()" class="btn">
            <i data-feather="refresh-cw"></i> Refresh
        </button>
        <a href="<?= BASE_URL ?>/admin/internal-links" class="btn btn-secondary">
            <i data-feather="arrow-left"></i> Back to Links
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="stat-card" style="border-left: 4px solid <?= $summary['total_broken'] > 0 ? '#dc3545' : '#10b981' ?>;">
        <h3><i data-feather="alert-triangle"></i> Broken Links</h3>
        <p class="stat-number" style="color: <?= $summary['total_broken'] > 0 ? '#dc3545' : '#10b981' ?>;">
            <?= $summary['total_broken'] ?>
        </p>
    </div>
    
    <div class="stat-card">
        <h3><i data-feather="file-text"></i> Pages Affected</h3>
        <p class="stat-number"><?= $summary['pages_affected'] ?></p>
    </div>
    
    <div class="stat-card" style="border-left: 4px solid #3b82f6;">
        <h3><i data-feather="shield"></i> Health Status</h3>
        <p class="stat-number" style="font-size: 1.5em;">
            <?php if ($summary['total_broken'] === 0): ?>
                <span style="color: #10b981; display: flex; align-items: center; gap: 10px;">
                    <i data-feather="check-circle" style="width: 36px; height: 36px;"></i> Healthy
                </span>
            <?php elseif ($summary['total_broken'] < 5): ?>
                <span style="color: #f59e0b; display: flex; align-items: center; gap: 10px;">
                    <i data-feather="alert-circle" style="width: 36px; height: 36px;"></i> Warning
                </span>
            <?php else: ?>
                <span style="color: #dc3545; display: flex; align-items: center; gap: 10px;">
                    <i data-feather="x-circle" style="width: 36px; height: 36px;"></i> Critical
                </span>
            <?php endif; ?>
        </p>
    </div>
    
    <div class="stat-card">
        <h3><i data-feather="clock"></i> Last Checked</h3>
        <p class="stat-number" style="font-size: 1.2em; color: #6b7280;">
            <?= date('M d, H:i') ?>
        </p>
    </div>
</div>

<?php if (empty($brokenLinks)): ?>
    <!-- Success State -->
    <div style="background: white; padding: 60px; text-align: center; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <div style="font-size: 4em; margin-bottom: 20px;">
            <i data-feather="check-circle" style="color: #10b981; width: 80px; height: 80px;"></i>
        </div>
        <h2 style="color: #10b981; margin-bottom: 10px;">All Links Healthy!</h2>
        <p style="color: #6b7280; font-size: 1.1em;">
            No broken internal links detected. Your site's link structure is in great shape.
        </p>
        <div style="margin-top: 30px;">
            <a href="<?= BASE_URL ?>/admin/internal-links" class="btn btn-primary">
                <i data-feather="link"></i> Back to Link Manager
            </a>
        </div>
    </div>
<?php else: ?>

    <div class="info-banner" style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; margin-bottom: 30px; border-radius: 4px;">
        <strong><i data-feather="alert-triangle"></i> Found <?= count($brokenLinks) ?> broken link(s)</strong><br>
        These links point to pages that don't exist or aren't published. Click "Fix" to remove them automatically.
    </div>

    <!-- Broken Links by Page -->
    <?php foreach ($byPage as $pageId => $data): 
        $page = $data['page'];
        $links = $data['broken_links'];
    ?>
    
    <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #dc3545;">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h3 style="margin: 0 0 5px 0; font-size: 1.2em;">
                    <i data-feather="file-text"></i> <?= e($page['title_ru']) ?>
                </h3>
                <code style="background: #f3f4f6; padding: 3px 8px; border-radius: 4px; font-size: 0.9em;">
                    <?= e($page['slug']) ?>
                </code>
                <span class="badge" style="background: #dc3545; margin-left: 10px; color: white; padding: 4px 10px; border-radius: 4px; font-size: 0.85em;">
                    <i data-feather="alert-circle" style="width: 14px; height: 14px; vertical-align: middle;"></i>
                    <?= count($links) ?> broken
                </span>
            </div>
            
            <div style="display: flex; gap: 8px;">
                <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>" 
                   class="btn btn-sm" title="Edit page">
                    <i data-feather="edit"></i> Edit Page
                </a>
            </div>
        </div>
        
        <table class="data-table" style="margin-top: 15px;">
            <thead>
                <tr>
                    <th><i data-feather="link"></i> Broken URL</th>
                    <th><i data-feather="type"></i> Link Text</th>
                    <th style="width: 80px;"><i data-feather="globe"></i> Lang</th>
                    <th style="width: 100px;"><i data-feather="tool"></i> Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($links as $link): ?>
                <tr>
                    <td>
                        <code style="color: #dc3545; font-size: 0.9em;">
                            <?= e($link['broken_url']) ?>
                        </code>
                    </td>
                    <td><?= e($link['link_text']) ?></td>
                    <td>
                        <span class="badge"><?= strtoupper($link['language']) ?></span>
                    </td>
                    <td>
                        <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/fix-broken" 
                              style="display: inline;"
                              onsubmit="return confirm('Remove this broken link? The link text will remain as plain text.')">
                            <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                            <input type="hidden" name="language" value="<?= $link['language'] ?>">
                            <button type="submit" class="btn btn-sm btn-primary" title="Fix broken link">
                                <i data-feather="tool"></i> Fix
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php endforeach; ?>

<?php endif; ?>

<style>
.stat-card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.stat-card h3 {
    font-size: 0.9em;
    color: #6b7280;
    margin-bottom: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.stat-number {
    font-size: 2.5em;
    font-weight: bold;
    color: #303034;
    line-height: 1;
    margin: 0;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.85em;
    font-weight: 600;
}

.info-banner {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.info-banner i {
    margin-top: 2px;
    flex-shrink: 0;
}
</style>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>  