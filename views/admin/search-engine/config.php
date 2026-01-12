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

<div class="alert alert-info" style="border-left: 4px solid #0ea5e9;">
    <h4 style="margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px;">
        <i data-feather="info"></i> Sitemap Pinging
    </h4>
    <p style="margin: 0; font-size: 0.95rem;">
        Enable or disable sitemap pinging for each search engine. Your sitemap will be automatically pinged whenever pages are created, updated, or content is rotated.
    </p>
</div>

<form method="POST" action="<?= BASE_URL ?>/admin/search-engine/save-config">
    <?= csrfField() ?>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 30px;">
    <?php 
    $engineInfo = [
        'bing' => ['icon' => 'globe', 'name' => 'Bing', 'color' => '#00A4EF', 'description' => 'Bing Web Search & Bing News'],
        'yandex' => ['icon' => 'compass', 'name' => 'Yandex', 'color' => '#FC3F1D', 'description' => 'Yandex Search Engine'],
        'google' => ['icon' => 'chrome', 'name' => 'Google', 'color' => '#4285F4', 'description' => 'Google Search & Google News']
    ];
    
    foreach ($configs as $config): 
        $info = $engineInfo[$config['engine']] ?? ['icon' => 'globe', 'name' => ucfirst($config['engine']), 'color' => '#3b82f6'];
    ?>
    <div class="config-section" style="border-top: 4px solid <?= $info['color'] ?>;">
        <div class="config-header">
            <div>
                <h2 style="margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px;">
                    <i data-feather="<?= $info['icon'] ?>" style="color: <?= $info['color'] ?>; width: 24px; height: 24px;"></i>
                    <?= $info['name'] ?>
                </h2>
                <small style="color: #64748b; font-size: 0.9rem;"><?= $info['description'] ?></small>
            </div>
            <label class="toggle">
                <input type="checkbox" name="<?= $config['engine'] ?>_enabled" <?= $config['enabled'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
                <span class="toggle-label"><?= $config['enabled'] ? 'Enabled' : 'Disabled' ?></span>
            </label>
        </div>
        
        <div class="form-group" style="margin-top: 20px;">
            <label style="font-weight: 600; color: #1e293b; margin-bottom: 8px; display: block;">
                <i data-feather="key" style="width: 16px; height: 16px; display: inline; margin-right: 4px; vertical-align: text-bottom;"></i>
                API Key
            </label>
            <input type="text" 
                   name="<?= $config['engine'] ?>_api_key" 
                   class="form-control" 
                   value="<?= htmlspecialchars($config['api_key'] ?? '') ?>"
                   placeholder="Enter API key (if required)"
                   style="margin-bottom: 12px;">
            <small style="color: #64748b; display: block; margin-bottom: 16px;">
                <?php
                    $hints = [
                        'bing' => 'Bing IndexNow API key (optional for basic sitemap pinging)',
                        'yandex' => 'Yandex.Webmaster API key (optional for basic sitemap pinging)',
                        'google' => 'Google does not require an API key for sitemap discovery'
                    ];
                    echo $hints[$config['engine']] ?? 'API key for this search engine';
                ?>
            </small>
        </div>

        <div class="form-group" style="margin-top: 12px;">
            <label style="font-weight: 600; color: #1e293b; margin-bottom: 8px; display: block;">
                <i data-feather="link" style="width: 16px; height: 16px; display: inline; margin-right: 4px; vertical-align: text-bottom;"></i>
                Custom Endpoint
            </label>
            <input type="url" 
                   name="<?= $config['engine'] ?>_api_endpoint" 
                   class="form-control" 
                   value="<?= htmlspecialchars($config['api_endpoint'] ?? '') ?>"
                   placeholder="https://example.com/api/endpoint"
                   style="margin-bottom: 12px;">
            <small style="color: #64748b; display: block;">
                <?php
                    $endpoints = [
                        'bing' => 'Default: https://www.bing.com/ping',
                        'yandex' => 'Default: https://yandex.com/ping',
                        'google' => 'Default: https://www.google.com/ping'
                    ];
                    echo $endpoints[$config['engine']] ?? 'Leave empty for default endpoint';
                ?>
            </small>
        </div>
        
        <div class="form-group" style="margin-top: 20px;">
            <label style="font-weight: 600; color: #1e293b; margin-bottom: 8px; display: block;">
                <i data-feather="gauge" style="width: 16px; height: 16px; display: inline; margin-right: 4px; vertical-align: text-bottom;"></i>
                Daily Rate Limit
            </label>
            <div style="position: relative;">
                <input type="number" 
                       name="<?= $config['engine'] ?>_rate_limit" 
                       class="form-control" 
                       value="<?= $config['rate_limit_per_day'] ?>"
                       min="1"
                       max="999999"
                       style="padding-right: 40px;">
                <span style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 0.9rem; pointer-events: none;">pings/day</span>
            </div>
            <small style="color: #64748b; display: block; margin-top: 6px;">
                Maximum automatic pings per day (recommended: 10,000)
            </small>
        </div>

        <div style="background: #f0f9ff; border-left: 3px solid <?= $info['color'] ?>; padding: 12px 14px; border-radius: 4px; margin-top: 16px;">
            <p style="margin: 0; font-size: 0.85rem; color: #0369a1;">
                <strong>Status:</strong> 
                <span style="display: inline-block; margin-left: 4px;">
                    <?= $config['enabled'] ? 
                        '<span style="color: #10b981;">✓ Active and pinging</span>' : 
                        '<span style="color: #64748b;">○ Inactive</span>' 
                    ?>
                </span>
            </p>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    
    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 24px; border: 1px solid #e2e8f0;">
        <h3 style="margin: 0 0 12px 0; color: #1e293b; display: flex; align-items: center; gap: 8px;">
            <i data-feather="help-circle" style="width: 20px; height: 20px;"></i> Configuration Tips
        </h3>
        <ul style="margin: 0; padding-left: 28px; color: #64748b; font-size: 0.95rem; line-height: 1.8;">
            <li><strong>Bing:</strong> Supports up to 10,000 pings per day - recommended default</li>
            <li><strong>Yandex:</strong> Recommends 100 pings per day maximum for optimal performance</li>
            <li><strong>Google:</strong> Auto-discovers sitemaps via your robots.txt</li>
            <li>All engines are notified automatically when you create, update, or rotate content</li>
        </ul>
    </div>
    
    <div class="form-actions" style="display: flex; gap: 12px;">
        <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
            <i data-feather="save" style="width: 18px; height: 18px;"></i> Save Configuration
        </button>
        <a href="<?= BASE_URL ?>/admin/search-engine" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px;">
            <i data-feather="x" style="width: 18px; height: 18px;"></i> Cancel
        </a>
    </div>
</form>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
