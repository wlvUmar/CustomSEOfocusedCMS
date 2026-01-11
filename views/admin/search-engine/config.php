<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

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
    
    <?php 
    $indexNowEngines = ['bing', 'yandex'];
    foreach ($configs as $config): 
        $isIndexNow = in_array($config['engine'], $indexNowEngines);
        $engineIcons = [
            'bing' => 'globe',
            'yandex' => 'compass',
            'google' => 'chrome'
        ];
        $icon = $engineIcons[$config['engine']] ?? 'globe';
    ?>
    <div class="config-section">
        <div class="config-header">
            <h2>
                <i data-feather="<?= $icon ?>"></i>
                <?= ucfirst($config['engine']) ?>
                <?php if ($isIndexNow): ?>
                    <span class="badge badge-primary" style="font-size: 0.7em; margin-left: 10px;">IndexNow</span>
                <?php endif; ?>
            </h2>
            <label class="toggle">
                <input type="checkbox" name="<?= $config['engine'] ?>_enabled" <?= $config['enabled'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
                <span class="toggle-label">Enabled</span>
            </label>
        </div>
        
        
        
        <div class="form-group">
            <label>API Key <?php if (in_array($config['engine'], ['bing', 'yandex'])): ?>(shared IndexNow key)<?php else: ?>(optional)<?php endif; ?></label>
            <input type="text" 
                   name="<?= $config['engine'] ?>_api_key" 
                   class="form-control" 
                   value="<?= e($config['api_key'] ?? '') ?>"
                   <?= in_array($config['engine'], ['bing', 'yandex']) ? 'readonly' : '' ?>>
            <?php if ($config['engine'] === 'bing' && !empty($config['api_key'])): ?>
            <small class="form-text text-muted">
                <strong>Verification file:</strong> 
                <a href="<?= BASE_URL ?>/<?= $config['api_key'] ?>.txt" target="_blank">
                    <?= BASE_URL ?>/<?= $config['api_key'] ?>.txt
                </a>
                <button type="button" class="btn btn-sm btn-secondary" onclick="verifyKeyFile()">
                    <i data-feather="check-circle"></i> Verify Accessible
                </button>
            </small>
            <form method="POST" action="<?= BASE_URL ?>/admin/search-engine/regenerate-api-key" style="display: inline-block; margin-top: 5px;">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Are you sure? This will generate a new key and the old one will stop working.')">
                    <i data-feather="refresh-cw"></i> Regenerate Key
                </button>
            </form>
            <?php elseif ($config['engine'] === 'bing'): ?>
            <small class="form-text text-muted">
                Bing API key will be auto-generated on first submission. A verification file will be created in /public/
            </small>
            <?php elseif (in_array($config['engine'], ['yandex'])): ?>
            <small class="form-text text-muted">
                This engine uses the same IndexNow API key as Bing. The key is shared across all IndexNow engines.
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

<script>
function verifyKeyFile() {
    fetch('<?= BASE_URL ?>/admin/search-engine/verify-api-key-file')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✓ ' + data.message + '\n\nURL: ' + data.url + '\nContent: ' + data.content);
            } else {
                alert('✗ ' + data.message + '\n\nURL: ' + data.url);
            }
        })
        .catch(error => {
            alert('Error verifying key file: ' + error);
        });
}
</script>

<script src="<?= BASE_URL ?>/js/admin/search-engine.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
