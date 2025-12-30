<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/admin.css">
    <?php
    if (!empty($pageName)) {
        $cssFile = "admin/{$pageName}.css"; // include 'admin' folder in URL and path
        $cssPath = BASE_PATH . "/public/css/{$cssFile}";

        // debug
        echo "<!-- Debug: Trying CSS path: {$cssPath} -->";

        if (file_exists($cssPath)) {
            echo "<!-- Debug: CSS file exists, linking it -->";
            echo '<link rel="stylesheet" href="' . BASE_URL . "/css/{$cssFile}" . '">';
        } else {
            echo "<!-- Debug: CSS file NOT found -->";
        }
    }
    ?>



</head>
<body>
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
                    <a href="<?= BASE_URL ?>/admin/faqs" class="<?= strpos($_SERVER['REQUEST_URI'], '/faqs') !== false ? 'active' : '' ?>">
                        <i data-feather="help-circle"></i> FAQs
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Analytics</div>
                    <a href="<?= BASE_URL ?>/admin/analytics" class="<?= strpos($_SERVER['REQUEST_URI'], '/analytics') !== false && strpos($_SERVER['REQUEST_URI'], '/rotation') === false && strpos($_SERVER['REQUEST_URI'], '/crawl') === false ? 'active' : '' ?>">
                        <i data-feather="trending-up"></i> Overview
                    </a>
                    <a href="<?= BASE_URL ?>/admin/analytics/rotation" class="<?= strpos($_SERVER['REQUEST_URI'], '/analytics/rotation') !== false ? 'active' : '' ?>">
                        <i data-feather="bar-chart"></i> Rotation Stats
                    </a>
                    <a href="<?= BASE_URL ?>/admin/analytics/crawl" class="<?= strpos($_SERVER['REQUEST_URI'], '/analytics/crawl') !== false ? 'active' : '' ?>">
                        <i data-feather="zap"></i> Crawl Analysis
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Settings</div>
                    <a href="<?= BASE_URL ?>/admin/seo" class="<?= strpos($_SERVER['REQUEST_URI'], '/seo') !== false ? 'active' : '' ?>">
                        <i data-feather="search"></i> SEO Settings
                    </a>
                    <a href="<?= BASE_URL ?>/admin/media" class="<?= strpos($_SERVER['REQUEST_URI'], '/media') !== false ? 'active' : '' ?>">
                        <i data-feather="image"></i> Media
                    </a>
                </div>
                
                <a href="<?= BASE_URL ?>/admin/logout" style="margin-top: 20px; color: #dc3545;">
                    <i data-feather="log-out"></i> Logout
                </a>
            </nav>
        </aside>
        
        <main class="admin-main">
            <div class="admin-content">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= e($_SESSION['success']) ?></div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error"><?= e($_SESSION['error']) ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

<style>
.nav-section {
    margin: 15px 0;
}

.nav-section-title {
    padding: 8px 20px;
    font-size: 0.75em;
    font-weight: 600;
    text-transform: uppercase;
    color: rgba(237, 235, 215, 0.5);
    letter-spacing: 0.5px;
}

.admin-nav a.active {
    background: var(--accent-dark);
    border-left: 3px solid var(--primary-light);
}
</style>