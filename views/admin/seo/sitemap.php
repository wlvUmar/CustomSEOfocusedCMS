<?php 
// path: ./views/admin/seo/sitemap.php
// IMPLEMENTATION: Place in views/admin/seo/sitemap.php

require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<div class="page-header">
    <h1><i data-feather="map"></i> Sitemap & Robots.txt</h1>
    <div class="btn-group">
        <a href="<?= $sitemapUrl ?>" target="_blank" class="btn">
            <i data-feather="eye"></i> View Sitemap
        </a>
        <a href="<?= $robotsUrl ?>" target="_blank" class="btn">
            <i data-feather="file-text"></i> View Robots.txt
        </a>
        <?php if ($isProduction): ?>
        <form method="POST" action="<?= BASE_URL ?>/admin/seo/sitemap/ping" style="display: inline;">
            <button type="submit" class="btn btn-primary" 
                    onclick="return confirm('Ping Google and Bing about sitemap updates?')">
                <i data-feather="zap"></i> Ping Search Engines
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Overview Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <h3>Published Pages</h3>
        <p class="stat-number"><?= $totalPages ?></p>
    </div>
    
    <div class="stat-card">
        <h3>Total URLs</h3>
        <p class="stat-number"><?= $totalUrls ?></p>
        <p style="font-size: 0.85em; color: #6b7280; margin-top: 5px;">
            (<?= $totalPages ?> pages × 2 languages)
        </p>
    </div>
    
    <div class="stat-card">
        <h3>Environment</h3>
        <p class="stat-number" style="font-size: 1.5em;">
            <?= $isProduction ? '🌐 Production' : '🔧 Development' ?>
        </p>
    </div>
</div>

<!-- Sitemap Info -->
<div class="form-section">
    <h3><i data-feather="map"></i> Sitemap.xml</h3>
    
    <div class="help-text" style="margin-bottom: 15px;">
        <strong>URL:</strong> <a href="<?= $sitemapUrl ?>" target="_blank"><?= $sitemapUrl ?></a>
    </div>
    
    <p style="margin-bottom: 15px; line-height: 1.6;">
        The sitemap is automatically generated from your published pages and includes:
    </p>
    
    <ul style="margin-bottom: 15px; padding-left: 25px; line-height: 1.8;">
        <li>✓ All published pages in both Russian and Uzbek</li>
        <li>✓ Alternate language links (hreflang tags)</li>
        <li>✓ Last modification dates</li>
        <li>✓ Priority and change frequency hints</li>
        <li>✓ Pages with rotation = monthly update frequency</li>
        <li>✓ Pages without rotation = yearly update frequency</li>
    </ul>
    
    <div style="background: #f9fafb; padding: 15px; border-radius: 6px; border-left: 3px solid #3b82f6;">
        <strong>📋 Submit to Search Engines:</strong>
        <ul style="margin-top: 10px; padding-left: 20px;">
            <li><a href="https://search.google.com/search-console" target="_blank">Google Search Console</a></li>
            <li><a href="https://www.bing.com/webmasters" target="_blank">Bing Webmaster Tools</a></li>
            <li><a href="https://webmaster.yandex.com" target="_blank">Yandex Webmaster</a></li>
        </ul>
    </div>
</div>

<!-- Robots.txt Info -->
<div class="form-section">
    <h3><i data-feather="file-text"></i> Robots.txt</h3>
    
    <div class="help-text" style="margin-bottom: 15px;">
        <strong>URL:</strong> <a href="<?= $robotsUrl ?>" target="_blank"><?= $robotsUrl ?></a>
    </div>
    
    <?php if ($isProduction): ?>
        <div style="background: #d1f4e0; padding: 15px; border-radius: 6px; border-left: 3px solid #059669; margin-bottom: 15px;">
            <strong>✅ Production Mode:</strong> All pages are crawlable
        </div>
        
        <p style="margin-bottom: 10px;"><strong>Current Configuration:</strong></p>
        <pre style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 6px; overflow-x: auto; font-family: monospace; font-size: 13px;">User-agent: *
Allow: /

Disallow: /admin/
Disallow: /config/
Disallow: /logs/
Disallow: /database/

Sitemap: <?= $sitemapUrl ?></pre>
    <?php else: ?>
        <div style="background: #fef3c7; padding: 15px; border-radius: 6px; border-left: 3px solid #f59e0b; margin-bottom: 15px;">
            <strong>⚠️ Development Mode:</strong> All crawlers are blocked
        </div>
        
        <p style="margin-bottom: 10px;"><strong>Current Configuration:</strong></p>
        <pre style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 6px; overflow-x: auto; font-family: monospace; font-size: 13px;">User-agent: *
Disallow: /</pre>
    <?php endif; ?>
</div>

<!-- Pages in Sitemap -->
<div class="form-section">
    <h3><i data-feather="list"></i> Pages in Sitemap</h3>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Page</th>
                <th>Slug</th>
                <th>Rotation</th>
                <th>Change Freq</th>
                <th>Priority</th>
                <th>URLs</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pages as $page): ?>
            <tr>
                <td><?= e($page['title_ru']) ?></td>
                <td><code><?= e($page['slug']) ?></code></td>
                <td>
                    <?php if ($page['enable_rotation']): ?>
                        <span class="badge badge-success">Enabled</span>
                    <?php else: ?>
                        <span class="badge">Disabled</span>
                    <?php endif; ?>
                </td>
                <td>
                    <code><?= $page['enable_rotation'] ? 'monthly' : 'yearly' ?></code>
                </td>
                <td>
                    <code><?= $page['slug'] === 'home' ? '1.0' : '0.8' ?></code>
                </td>
                <td style="font-size: 0.85em;">
                    <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>" target="_blank">
                        <i data-feather="external-link"></i> RU
                    </a>
                    <span style="margin: 0 5px;">•</span>
                    <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>/uz" target="_blank">
                        <i data-feather="external-link"></i> UZ
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

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
}

.stat-number {
    font-size: 2.5em;
    font-weight: bold;
    color: #303034;
    line-height: 1;
    margin: 0;
}

pre {
    white-space: pre-wrap;
    word-wrap: break-word;
}
</style>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>