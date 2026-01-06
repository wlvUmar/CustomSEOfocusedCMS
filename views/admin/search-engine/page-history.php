<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<link rel="stylesheet" href="<?= BASE_URL ?>/css/admin/search-engine.css">

<div class="page-header">
    <h1>
        <i data-feather="file-text"></i>
        Search Engine History: <?= e($page['title_ru']) ?>
    </h1>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/search-engine" class="btn btn-secondary">
            <i data-feather="arrow-left"></i> Back
        </a>
        <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>" class="btn btn-info">
            <i data-feather="edit"></i> Edit Page
        </a>
        <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>" class="btn btn-success" target="_blank">
            <i data-feather="external-link"></i> View Page
        </a>
    </div>
</div>

<!-- Current Status Cards -->
<div class="status-grid">
    <?php
    $engines = ['bing', 'yandex', 'google'];
    foreach ($engines as $engine):
        $engineStatus = $status[$engine] ?? null;
    ?>
    <div class="status-card <?= $engineStatus ? 'has-submissions' : 'no-submissions' ?>">
        <div class="status-header">
            <h3>
                <i data-feather="<?= $engine === 'bing' ? 'globe' : ($engine === 'yandex' ? 'compass' : 'chrome') ?>"></i>
                <?= ucfirst($engine) ?>
            </h3>
            <?php if ($engineStatus): ?>
            <span class="badge badge-<?= $engineStatus['last_status'] ?>">
                <?= $engineStatus['last_status'] === 'success' ? '✓' : '✗' ?> <?= ucfirst($engineStatus['last_status']) ?>
            </span>
            <?php else: ?>
            <span class="badge badge-secondary">Never Submitted</span>
            <?php endif; ?>
        </div>
        
        <?php if ($engineStatus): ?>
        <div class="status-body">
            <div class="status-row">
                <span class="label">Last Submitted:</span>
                <span class="value">
                    <?= $engineStatus['last_submitted_at'] ? date('M d, Y H:i', strtotime($engineStatus['last_submitted_at'])) : 'Never' ?>
                </span>
            </div>
            <div class="status-row">
                <span class="label">Last Success:</span>
                <span class="value">
                    <?= $engineStatus['last_success_at'] ? date('M d, Y H:i', strtotime($engineStatus['last_success_at'])) : 'Never' ?>
                </span>
            </div>
            <div class="status-row">
                <span class="label">Total Submissions:</span>
                <span class="value"><?= $engineStatus['total_submissions'] ?></span>
            </div>
            <div class="status-row">
                <span class="label">Success Rate:</span>
                <span class="value">
                    <?php 
                    $successRate = $engineStatus['total_submissions'] > 0 
                        ? round(($engineStatus['successful_submissions'] / $engineStatus['total_submissions']) * 100, 1) 
                        : 0;
                    ?>
                    <?= $successRate ?>% (<?= $engineStatus['successful_submissions'] ?>/<?= $engineStatus['total_submissions'] ?>)
                </span>
            </div>
        </div>
        <?php else: ?>
        <div class="status-body">
            <p class="text-muted">This page has never been submitted to <?= ucfirst($engine) ?></p>
        </div>
        <?php endif; ?>
        
        <div class="status-actions">
            <?php if ($engineStatus && $engineStatus['can_resubmit_at'] > date('Y-m-d H:i:s')): ?>
            <button class="btn btn-sm btn-secondary" disabled>
                <i data-feather="clock"></i> 
                Cooldown (<?= max(1, floor((strtotime($engineStatus['can_resubmit_at']) - time()) / 60)) ?> min)
            </button>
            <?php else: ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/search-engine/submit-page" style="width: 100%;">
                <?= csrfField() ?>
                <input type="hidden" name="slug" value="<?= e($page['slug']) ?>">
                <input type="hidden" name="engines[]" value="<?= $engine ?>">
                <button type="submit" class="btn btn-sm btn-primary" style="width: 100%;">
                    <i data-feather="send"></i> Submit to <?= ucfirst($engine) ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Submission History Timeline -->
<div class="history-section">
    <h2><i data-feather="clock"></i> Submission History</h2>
    
    <?php if (empty($history)): ?>
    <div class="empty-state">
        <i data-feather="inbox"></i>
        <p>No submission history yet</p>
        <p class="empty-hint">Submit this page to search engines to start tracking</p>
    </div>
    <?php else: ?>
    <div class="timeline">
        <?php foreach ($history as $entry): ?>
        <div class="timeline-item status-<?= $entry['status'] ?>">
            <div class="timeline-marker">
                <?php if ($entry['status'] === 'success'): ?>
                <i data-feather="check-circle"></i>
                <?php elseif ($entry['status'] === 'failed'): ?>
                <i data-feather="x-circle"></i>
                <?php else: ?>
                <i data-feather="clock"></i>
                <?php endif; ?>
            </div>
            
            <div class="timeline-content">
                <div class="timeline-header">
                    <div>
                        <h4>
                            <?= ucfirst($entry['search_engine']) ?> 
                            <span class="badge badge-<?= $entry['submission_type'] ?>">
                                <?= ucfirst($entry['submission_type']) ?>
                            </span>
                        </h4>
                        <span class="timeline-date">
                            <?= date('M d, Y - H:i:s', strtotime($entry['submitted_at'])) ?>
                        </span>
                    </div>
                    <span class="badge badge-<?= $entry['status'] ?>">
                        <?= $entry['status'] === 'success' ? '✓' : ($entry['status'] === 'failed' ? '✗' : '⏱') ?> 
                        <?= ucfirst($entry['status']) ?>
                    </span>
                </div>
                
                <div class="timeline-details">
                    <?php if ($entry['response_code']): ?>
                    <span class="detail-item">
                        <strong>Response Code:</strong> <?= $entry['response_code'] ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($entry['response_message']): ?>
                    <span class="detail-item">
                        <strong>Message:</strong> <?= e($entry['response_message']) ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($entry['rotation_month']): ?>
                    <span class="detail-item">
                        <strong>Rotation Month:</strong> <?= date('F', mktime(0, 0, 0, $entry['rotation_month'], 1)) ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($entry['user_id']): ?>
                    <span class="detail-item">
                        <strong>Submitted by:</strong> Admin User #<?= $entry['user_id'] ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script src="<?= BASE_URL ?>/js/admin/search-engine.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
