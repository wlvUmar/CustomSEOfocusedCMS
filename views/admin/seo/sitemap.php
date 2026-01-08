<div class="sitemap-container">
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
            <h3><i data-feather="file-text"></i> Published Pages</h3>
            <p class="stat-number"><?= $totalPages ?></p>
            <div class="stat-subtext">Content ready for indexing</div>
        </div>
        
        <div class="stat-card">
            <h3><i data-feather="link"></i> Total URLs</h3>
            <p class="stat-number"><?= $totalUrls ?></p>
            <div class="stat-subtext">
                <i data-feather="info" style="width: 14px;"></i> <?= $totalPages ?> pages × 2 languages
            </div>
        </div>
        
        <div class="stat-card">
            <h3><i data-feather="layers"></i> Environment</h3>
            <p class="stat-number" style="font-size: 1.8rem;">
               <?= $isProduction 
                    ? '<span style="color: #10b981;"><i data-feather="globe"></i> Production</span>' 
                    : '<span style="color: #f59e0b;"><i data-feather="tool"></i> Development</span>' 
                ?>
            </p>
            <div class="stat-subtext">
                <?= $isProduction ? 'Publicly crawlable' : 'Crawlers blocked via robots.txt' ?>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Sitemap Info -->
        <div class="info-section">
            <h2><i data-feather="map"></i> Sitemap.xml Configuration</h2>
            
            <div class="url-box">
                <i data-feather="link"></i>
                <a href="<?= $sitemapUrl ?>" target="_blank"><?= $sitemapUrl ?></a>
            </div>
            
            <p style="margin-bottom: 20px; color: var(--secondary);">
                The sitemap is dynamically generated to ensure search engines always have the latest content.
            </p>
            
            <ul class="feature-list">
                <li><i data-feather="check-circle"></i> Multiple languages (hreflang) supported</li>
                <li><i data-feather="check-circle"></i> Automatic last-mod date updates</li>
                <li><i data-feather="check-circle"></i> Smart priority assignment (Home: 1.0, others: 0.8)</li>
                <li><i data-feather="check-circle"></i> Rotation-aware change frequencies</li>
            </ul>
            
            <div class="external-resources">
                <strong><i data-feather="book-open"></i> Submission Portals:</strong>
                <div class="resource-links">
                    <a href="https://search.google.com/search-console" target="_blank">
                        <i data-feather="external-link"></i> Google Console
                    </a>
                    <a href="https://www.bing.com/webmasters" target="_blank">
                        <i data-feather="external-link"></i> Bing Webmaster
                    </a>
                    <a href="https://webmaster.yandex.com" target="_blank">
                        <i data-feather="external-link"></i> Yandex Master
                    </a>
                </div>
            </div>
        </div>

        <!-- Robots.txt Info -->
        <div class="info-section">
            <h2><i data-feather="shield"></i> Robots.txt Directives</h2>
            
            <div class="url-box">
                <i data-feather="link"></i>
                <a href="<?= $robotsUrl ?>" target="_blank"><?= $robotsUrl ?></a>
            </div>
            
            <?php if ($isProduction): ?>
                <div class="status-banner success">
                    <i data-feather="check-circle"></i>
                    Production Mode: Crawling is fully enabled
                </div>
                <div class="config-preview">User-agent: *
Allow: /

Disallow: /admin/
Disallow: /config/
Disallow: /logs/

Sitemap: <?= $sitemapUrl ?></div>
            <?php else: ?>
                <div class="status-banner warning">
                    <i data-feather="alert-triangle"></i>
                    Development Mode: All bots are currently blocked
                </div>
                <div class="config-preview">User-agent: *
Disallow: /</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pages in Sitemap -->
    <div class="info-section">
        <h2><i data-feather="list"></i> Sitemap Inventory</h2>
        
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Page Title</th>
                        <th>Slug</th>
                        <th>Rotation</th>
                        <th>Frequency</th>
                        <th>Priority</th>
                        <th>Live Links</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                    <tr>
                        <td><strong><?= e($page['title_ru']) ?></strong></td>
                        <td><code><?= e($page['slug']) ?></code></td>
                        <td>
                            <?php if ($page['enable_rotation']): ?>
                                <span class="badge badge-success">Enabled</span>
                            <?php else: ?>
                                <span class="badge">Static</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code><?= $page['enable_rotation'] ? 'monthly' : 'yearly' ?></code>
                        </td>
                        <td>
                            <span style="font-weight: 600;"><?= $page['slug'] === 'home' ? '1.0' : '0.8' ?></span>
                        </td>
                        <td class="actions">
                            <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>" target="_blank" class="btn btn-sm" title="Russian Version">
                                <i data-feather="external-link"></i> RU
                            </a>
                            <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>/uz" target="_blank" class="btn btn-sm" title="Uzbek Version">
                                <i data-feather="external-link"></i> UZ
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>