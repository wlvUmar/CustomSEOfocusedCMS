<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<link rel="stylesheet" href="<?= BASE_URL ?>/css/admin/search-engine.css">

<div class="page-header">
    <h1>
        <i data-feather="settings"></i>
        Search Engine Configuration
    </h1>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/search-engine" class="btn btn-secondary">
            <i data-feather="arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/admin/search-engine/save-config">
    <?= csrfField() ?>
    
    <?php foreach ($configs as $config): ?>
    <div class="config-section">
        <div class="config-header">
            <h2>
                <i data-feather="<?= $config['engine'] === 'bing' ? 'globe' : ($config['engine'] === 'yandex' ? 'compass' : 'chrome') ?>"></i>
                <?= ucfirst($config['engine']) ?>
            </h2>
            <label class="toggle">
                <input type="checkbox" name="<?= $config['engine'] ?>_enabled" <?= $config['enabled'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
                <span class="toggle-label">Enabled</span>
            </label>
        </div>
        
        <div class="form-group">
            <label>API Key <?= $config['engine'] === 'bing' ? '(auto-generated)' : '(optional)' ?></label>
            <input type="text" 
                   name="<?= $config['engine'] ?>_api_key" 
                   class="form-control" 
                   value="<?= e($config['api_key'] ?? '') ?>"
                   <?= $config['engine'] === 'bing' ? 'readonly' : '' ?>>
            <?php if ($config['engine'] === 'bing'): ?>
            <small class="form-text text-muted">
                Bing API key is auto-generated. A verification file will be created in /public/
            </small>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label>Daily Rate Limit</label>
            <input type="number" 
                   name="<?= $config['engine'] ?>_rate_limit" 
                   class="form-control" 
                   value="<?= $config['rate_limit_per_day'] ?>"
                   min="1"
                   max="999999">
            <small class="form-text text-muted">
                Maximum submissions per day (Bing: 10,000 recommended, Yandex: 100)
            </small>
        </div>
        
        <div class="usage-stats">
            <span><strong>Today's Usage:</strong> <?= $config['submissions_today'] ?> / <?= $config['rate_limit_per_day'] ?></span>
            <span><strong>Last Reset:</strong> <?= $config['last_reset_date'] ?? 'Never' ?></span>
        </div>
        
        <div class="form-group">
            <h3>Auto-Submission Options</h3>
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" 
                           name="<?= $config['engine'] ?>_auto_create" 
                           <?= $config['auto_submit_on_create'] ? 'checked' : '' ?>>
                    Submit when pages are created
                </label>
                <label>
                    <input type="checkbox" 
                           name="<?= $config['engine'] ?>_auto_update" 
                           <?= $config['auto_submit_on_update'] ? 'checked' : '' ?>>
                    Submit when pages are updated
                </label>
                <label>
                    <input type="checkbox" 
                           name="<?= $config['engine'] ?>_auto_rotation" 
                           <?= $config['auto_submit_on_rotation'] ? 'checked' : '' ?>>
                    Submit when content is rotated
                </label>
                <label>
                    <input type="checkbox" 
                           name="<?= $config['engine'] ?>_ping_sitemap" 
                           <?= $config['ping_sitemap'] ? 'checked' : '' ?>>
                    Ping sitemap on changes
                </label>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <i data-feather="save"></i> Save Configuration
        </button>
        <a href="<?= BASE_URL ?>/admin/search-engine" class="btn btn-secondary">
            Cancel
        </a>
    </div>
</form>

<script src="<?= BASE_URL ?>/js/admin/search-engine.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
