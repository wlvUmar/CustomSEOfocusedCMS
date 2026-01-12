<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<link rel="stylesheet" href="<?= BASE_URL ?>/css/admin/features/search-engine.css">

<div class="page-header">
    <h1>
        <i data-feather="globe"></i>
        Search Engine Integration
    </h1>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/search-engine/config" class="btn btn-secondary">
            <i data-feather="settings"></i> Configuration
        </a>
    </div>
</div>

<!-- Overview Section -->
<div class="alert alert-info" style="border-left: 4px solid #0ea5e9; padding: 15px 20px;">
    <h4 style="margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px;">
        <i data-feather="info"></i> How It Works
    </h4>
    <p style="margin: 0; font-size: 0.95rem; line-height: 1.6;">
        Your XML sitemap is <strong>automatically pinged</strong> to all enabled search engines whenever:
    </p>
    <ul style="margin: 8px 0 0 0; padding-left: 24px; font-size: 0.95rem;">
        <li>A new page is published</li>
        <li>An existing page is updated</li>
        <li>Content rotation is activated</li>
    </ul>
    <p style="margin: 8px 0 0 0; font-size: 0.95rem;">
        You can also manually ping your sitemap at any time using the button below.
    </p>
</div>

<!-- Enabled Engines -->
<div class="stats-grid">
    <?php 
    $engineInfo = [
        'bing' => ['icon' => 'globe', 'name' => 'Bing', 'color' => '#00A4EF'],
        'yandex' => ['icon' => 'compass', 'name' => 'Yandex', 'color' => '#FC3F1D'],
        'google' => ['icon' => 'chrome', 'name' => 'Google', 'color' => '#4285F4']
    ];
    
    foreach ($enabledEngines as $engine): 
        $info = $engineInfo[$engine] ?? ['icon' => 'globe', 'name' => ucfirst($engine)];
    ?>
    <div class="stat-card" style="border-top: 3px solid <?= $info['color'] ?? '#3b82f6' ?>;">
        <div class="card-header">
            <div>
                <h3><?= $info['name'] ?? ucfirst($engine) ?></h3>
                <small style="color: #64748b; display: block; margin-top: 4px;">Sitemap pinging</small>
            </div>
            <i data-feather="<?= $info['icon'] ?>" style="color: <?= $info['color'] ?? '#3b82f6' ?>; width: 28px; height: 28px;"></i>
        </div>
        <div style="padding: 16px 0; text-align: center; border-top: 1px solid #e2e8f0; margin-top: 12px;">
            <span style="display: inline-flex; align-items: center; gap: 6px; color: #10b981; font-weight: 500;">
                <i data-feather="check-circle" style="width: 18px; height: 18px;"></i> Active
            </span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($enabledEngines)): ?>
<div class="alert alert-warning" style="border-left: 4px solid #f59e0b;">
    <h4 style="margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px;">
        <i data-feather="alert-circle"></i> No Search Engines Enabled
    </h4>
    <p style="margin: 0;">
        Visit <a href="<?= BASE_URL ?>/admin/search-engine/config" style="color: #2563eb; font-weight: 600;">Configuration</a> to enable at least one search engine for automatic sitemap pinging.
    </p>
</div>
<?php else: ?>

<!-- Manual Ping Section -->
<div class="quick-actions-section">
    <h2 style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px;">
        <i data-feather="send"></i> Manual Sitemap Ping
    </h2>
    
    <div class="action-card" style="max-width: 700px; border: 1px solid #e2e8f0; background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%);">
        <div style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 8px 0; color: #0ea5e9;">Ping All Search Engines</h3>
            <p style="margin: 0; color: #64748b; font-size: 0.95rem;">
                Manually notify all enabled search engines about your sitemap updates
            </p>
        </div>
        
        <div style="background: white; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; border-left: 3px solid #0ea5e9; font-family: 'Courier New', monospace; font-size: 0.9rem; color: #1e293b; word-break: break-all;">
            <strong>Sitemap URL:</strong><br>
            <?= $sitemapUrl ?>
        </div>
        
        <form method="POST" action="<?= BASE_URL ?>/admin/search-engine/ping-now" style="display: inline;">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-success" style="display: inline-flex; align-items: center; gap: 8px;">
                <i data-feather="send" style="width: 18px; height: 18px;"></i>
                <span>Ping All Engines Now</span>
            </button>
            <p style="margin: 12px 0 0 0; font-size: 0.85rem; color: #64748b;">
                <i data-feather="info" style="width: 14px; height: 14px; display: inline; margin-right: 4px; vertical-align: text-bottom;"></i>
                This will notify Bing, Yandex, and Google to re-crawl your sitemap
            </p>
        </form>
    </div>
</div>

<?php endif; ?>

<!-- Info Box -->
<div style="background: #f8fafc; border-radius: 8px; padding: 20px; margin-top: 30px; border: 1px solid #e2e8f0;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <div>
            <h4 style="margin: 0 0 8px 0; color: #0ea5e9; display: flex; align-items: center; gap: 6px; font-size: 0.95rem;">
                <i data-feather="zap" style="width: 18px; height: 18px;"></i> Automatic Pinging
            </h4>
            <p style="margin: 0; color: #64748b; font-size: 0.9rem; line-height: 1.5;">
                Every page change triggers automatic sitemap pings without any manual action needed.
            </p>
        </div>
        <div>
            <h4 style="margin: 0 0 8px 0; color: #10b981; display: flex; align-items: center; gap: 6px; font-size: 0.95rem;">
                <i data-feather="check-circle" style="width: 18px; height: 18px;"></i> All Engines
            </h4>
            <p style="margin: 0; color: #64748b; font-size: 0.9rem; line-height: 1.5;">
                Supports Bing, Yandex, and Google simultaneously. Control which engines to use in Configuration.
            </p>
        </div>
        <div>
            <h4 style="margin: 0 0 8px 0; color: #f59e0b; display: flex; align-items: center; gap: 6px; font-size: 0.95rem;">
                <i data-feather="settings" style="width: 18px; height: 18px;"></i> Configurable
            </h4>
            <p style="margin: 0; color: #64748b; font-size: 0.9rem; line-height: 1.5;">
                Set daily rate limits per engine and enable/disable as needed.
            </p>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/js/admin/features/search-engine.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
