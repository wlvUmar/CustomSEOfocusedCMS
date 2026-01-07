<?php
// FIXED: views/admin/layout/header.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/css/favicon.ico">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/admin/core/layout.css">
    <?php
    if (!empty($pageName)) {
        // Check for feature-specific CSS in new structure
        $featureCss = "admin/features/{$pageName}.css";
        if (file_exists(BASE_PATH . "/public/css/{$featureCss}")) {
             echo '<link rel="stylesheet" href="' . BASE_URL . "/css/{$featureCss}" . '">';
        }

        // Keep support for legacy/other paths if needed, or remove 
        // fallback to old location
        $oldCss = "admin/{$pageName}.css";
        if (file_exists(BASE_PATH . "/public/css/{$oldCss}")) {
            echo '<link rel="stylesheet" href="' . BASE_URL . "/css/{$oldCss}" . '">';
        }
    }
    ?>
</head>
<body>
    <?php /* Floating alerts container - stacked to top-right */ ?>
    <div id="floating-alerts" class="floating-alerts">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i data-feather="check-circle"></i>
                <?= e($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php endif; ?>

        <?php if (isset($_SESSION['warning'])): ?>
            <div class="alert alert-warning">
                <i data-feather="alert-triangle"></i>
                <?= e($_SESSION['warning']) ?>
            </div>
            <?php unset($_SESSION['warning']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['info'])): ?>
            <div class="alert alert-info">
                <i data-feather="info"></i>
                <?= e($_SESSION['info']) ?>
            </div>
            <?php unset($_SESSION['info']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i data-feather="alert-circle"></i>
                <?= e($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </div>

    <div class="admin-wrapper">
        <aside class="sidebar">
           
            <div class="logo">
                <h2>Admin Panel</h2>
            </div>
            <nav class="admin-nav">
                <a href="<?= BASE_URL ?>/admin/dashboard" class="<?= strpos($_SERVER['REQUEST_URI'], '/dashboard') !== false ? 'active' : '' ?>">
                    <i data-feather="bar-chart-2"></i> Dashboard
                </a>
                
                <div class="nav-section">
                    <div class="nav-section-title">Content</div>
                    <a href="<?= BASE_URL ?>/admin/pages" class="<?= strpos($_SERVER['REQUEST_URI'], '/pages') !== false ? 'active' : '' ?>">
                        <i data-feather="file-text"></i> Pages
                    </a>
                    <a href="<?= BASE_URL ?>/admin/rotations/overview" class="<?= strpos($_SERVER['REQUEST_URI'], '/rotations') !== false ? 'active' : '' ?>">
                        <i data-feather="repeat"></i> Content Rotation
                    </a>
                    <a href="<?= BASE_URL ?>/admin/internal-links" class="<?= strpos($_SERVER['REQUEST_URI'], '/internal-links') !== false ? 'active' : '' ?>">
                        <i data-feather="link"></i> Internal Links
                    </a>
                    <a href="<?= BASE_URL ?>/admin/faqs" class="<?= strpos($_SERVER['REQUEST_URI'], '/faqs') !== false ? 'active' : '' ?>">
                        <i data-feather="help-circle"></i> FAQs
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Analytics</div>
                    <a href="<?= BASE_URL ?>/admin/analytics" class="<?= strpos($_SERVER['REQUEST_URI'], '/analytics') !== false && strpos($_SERVER['REQUEST_URI'], '/rotation') === false && strpos($_SERVER['REQUEST_URI'], '/crawl') === false && strpos($_SERVER['REQUEST_URI'], '/navigation') === false ? 'active' : '' ?>">
                        <i data-feather="trending-up"></i> Overview
                    </a>
                    <a href="<?= BASE_URL ?>/admin/analytics/rotation" class="<?= strpos($_SERVER['REQUEST_URI'], '/analytics/rotation') !== false ? 'active' : '' ?>">
                        <i data-feather="bar-chart"></i> Rotation Stats
                    </a>
                    <a href="<?= BASE_URL ?>/admin/analytics/navigation" class="<?= strpos($_SERVER['REQUEST_URI'], '/analytics/navigation') !== false ? 'active' : '' ?>">
                        <i data-feather="git-branch"></i> Navigation Flow
                    </a>
                    <a href="<?= BASE_URL ?>/admin/analytics/crawl" class="<?= strpos($_SERVER['REQUEST_URI'], '/analytics/crawl') !== false ? 'active' : '' ?>">
                        <i data-feather="activity"></i> Crawl Analysis
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">SEO & Indexing</div>
                    <a href="<?= BASE_URL ?>/admin/seo" class="<?= strpos($_SERVER['REQUEST_URI'], '/seo') !== false && strpos($_SERVER['REQUEST_URI'], '/sitemap') === false ? 'active' : '' ?>">
                        <i data-feather="search"></i> SEO Settings
                    </a>
                    <a href="<?= BASE_URL ?>/admin/search-engine" class="<?= strpos($_SERVER['REQUEST_URI'], '/search-engine') !== false ? 'active' : '' ?>">
                        <i data-feather="send"></i> Search Engines
                    </a>
                    <a href="<?= BASE_URL ?>/admin/seo/sitemap" class="<?= strpos($_SERVER['REQUEST_URI'], '/seo/sitemap') !== false ? 'active' : '' ?>">
                        <i data-feather="map"></i> Sitemap & Robots
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Settings</div>
                    <a href="<?= BASE_URL ?>/admin/media" class="<?= strpos($_SERVER['REQUEST_URI'], '/media') !== false ? 'active' : '' ?>">
                        <i data-feather="image"></i> Media
                    </a>
                
                    <a href="<?= BASE_URL ?>/deploy.php" class="<?= strpos($_SERVER['REQUEST_URI'], '/deploy.php') !== false ? 'active' : '' ?>">
                        <i data-feather="upload-cloud"></i> Deploy
                    </a>

                    <a href="<?= BASE_URL ?>/admin/logout" style="margin-top: 20px; color: #dc3545;">
                        <i data-feather="log-out"></i> Logout
                    </a>
                </div>
            </nav>
        </aside>
        <button class="sidebar-toggle" aria-label="Toggle sidebar">
            <i data-feather="chevron-left"></i>
        </button>
        <main class="admin-main">
            <div class="admin-content">