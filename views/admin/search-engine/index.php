<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<link rel="stylesheet" href="<?= BASE_URL ?>/css/admin/features/search-engine.css">

<div class="page-header">
    <h1>
        <i data-feather="search"></i>
        Search Engine Indexing
    </h1>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/search-engine/submit" class="btn btn-primary">
            <i data-feather="send"></i> Submit Pages
        </a>
        <a href="<?= BASE_URL ?>/admin/search-engine/config" class="btn btn-secondary">
            <i data-feather="settings"></i> Configuration
        </a>
        <a href="<?= BASE_URL ?>/admin/search-engine/export" class="btn btn-secondary">
            <i data-feather="download"></i> Export History
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <?php foreach ($stats['by_engine'] ?? [] as $engine): 
        $engineName = $engine['engine'] ?? 'unknown';
        $icon = 'globe';
        if ($engineName === 'yandex') $icon = 'compass';
        elseif ($engineName === 'google') $icon = 'chrome';
        elseif ($engineName === 'bing') $icon = 'globe'; // redundancy for clarity
    ?>
    <div class="stat-card">
        <div class="card-header">
            <h3><?= ucfirst($engineName) ?></h3>
            <i data-feather="<?= $icon ?>"></i>
        </div>
        <div class="stat-number"><?= $engine['total_all_time'] ?? 0 ?></div>
        <div class="stat-details">
            <span class="success"><?= $engine['total_success'] ?? 0 ?> success</span>
            <span class="failed"><?= $engine['total_failed'] ?? 0 ?> failed</span>
        </div>
        <?php if (($engine['total_all_time'] ?? 0) > 0): ?>
        <div class="stat-progress">
            <div class="progress-bar">
                <div class="progress-fill success" style="width: <?= $engine['success_rate_percent'] ?? 0 ?>%"></div>
            </div>
            <span><?= $engine['success_rate_percent'] ?? 0 ?>% success rate</span>
        </div>
        <?php endif; ?>
        <span class="stat-meta">
            Today: <?= $engine['submissions_today'] ?? 0 ?> / <?= $engine['rate_limit_per_day'] ?? 0 ?>
        </span>
    </div>
    <?php endforeach; ?>
</div>

<!-- Quick Actions -->
<div class="quick-actions-section">
    <h2><i data-feather="zap"></i> Quick Actions</h2>
    
    <div class="action-grid">
        <?php if (!empty($stats['unsubmitted'])): ?>
        <div class="action-card">
            <div class="action-icon warning">
                <i data-feather="alert-circle"></i>
            </div>
            <h3><?= count($stats['unsubmitted']) ?></h3>
            <p>Unsubmitted Pages</p>
            <form method="POST" action="<?= BASE_URL ?>/admin/search-engine/submit-unsubmitted">
                <?= csrfField() ?>
                <input type="hidden" name="engine" value="bing">
                <button type="submit" class="btn btn-warning">
                    <i data-feather="send"></i> Submit All Now
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($stats['due_resubmit'])): ?>
        <div class="action-card">
            <div class="action-icon info">
                <i data-feather="clock"></i>
            </div>
            <h3><?= count($stats['due_resubmit']) ?></h3>
            <p>Pages Due for Resubmission</p>
            <a href="<?= BASE_URL ?>/admin/search-engine/submit" class="btn btn-info">
                <i data-feather="refresh-cw"></i> Review & Submit
            </a>
        </div>
        <?php endif; ?>
        
        <div class="action-card">
            <div class="action-icon success">
                <i data-feather="map"></i>
            </div>
            <h3>Sitemap</h3>
            <p>View or ping your sitemap</p>
            <a href="<?= BASE_URL ?>/sitemap.xml" class="btn btn-success" target="_blank">
                <i data-feather="external-link"></i> View Sitemap
            </a>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="recent-activity-section">
    <h2><i data-feather="activity"></i> Recent Submissions</h2>
    
    <?php if (empty($stats['recent'])): ?>
    <div class="empty-state">
        <i data-feather="inbox"></i>
        <p>No submissions yet</p>
        <p>Pages will appear here once they are submitted to search engines</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Engine</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['recent'] as $sub): ?>
                <tr>
                    <td>
                        <strong><?= e($sub['page_title'] ?? $sub['page_slug']) ?></strong>
                        <br>
                        <small class="text-muted"><?= e($sub['page_slug']) ?></small>
                    </td>
                    <td>
                        <span class="badge badge-<?= $sub['search_engine'] ?>">
                            <?= ucfirst($sub['search_engine']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?= $sub['submission_type'] ?>">
                            <?= ucfirst($sub['submission_type']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?= $sub['status'] ?>">
                            <?= $sub['status'] === 'success' ? '✓' : '✗' ?> <?= ucfirst($sub['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?= date('M d, H:i', strtotime($sub['submitted_at'])) ?>
                        <?php if (!empty($sub['duration_seconds'])): ?>
                        <br><small class="text-muted">(<?= $sub['duration_seconds'] ?>s)</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/search-engine/page/<?= e($sub['page_slug']) ?>" class="btn btn-sm btn-info">
                            <i data-feather="eye"></i> View History
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script src="<?= BASE_URL ?>/js/admin/features/search-engine.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
