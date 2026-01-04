<?php require 'layout/header.php'; ?>

<div class="dashboard-header">
    <h1>Dashboard</h1>
    <p class="dashboard-subtitle">Welcome back! Here's an overview of your system</p>
</div>

<div class="dashboard-grid">
    <!-- Content Section -->
    <div class="dashboard-section">
        <h2 class="section-title">
            <i data-feather="file-text"></i> Content
        </h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="card-header">
                    <h3>Total Pages</h3>
                    <i data-feather="file-text"></i>
                </div>
                <p class="stat-number"><?= $stats['total_pages'] ?></p>
                <p class="stat-label">All pages in system</p>
            </div>
            
            <div class="stat-card accent-success">
                <div class="card-header">
                    <h3>Published Pages</h3>
                    <i data-feather="check-circle"></i>
                </div>
                <p class="stat-number"><?= $stats['published_pages'] ?></p>
                <p class="stat-label">Live on website</p>
            </div>
            
            <div class="stat-card">
                <div class="card-header">
                    <h3>FAQs</h3>
                    <i data-feather="help-circle"></i>
                </div>
                <p class="stat-number"><?= $stats['total_faqs'] ?></p>
                <p class="stat-label">Total questions</p>
            </div>
            
            <div class="stat-card">
                <div class="card-header">
                    <h3>Media Files</h3>
                    <i data-feather="image"></i>
                </div>
                <p class="stat-number"><?= $stats['total_media'] ?></p>
                <p class="stat-label">Images & files</p>
            </div>
        </div>
        
        <div class="quick-links">
            <a href="<?= BASE_URL ?>/admin/pages/new" class="btn btn-primary btn-sm">
                <i data-feather="plus"></i> New Page
            </a>
            <a href="<?= BASE_URL ?>/admin/faqs/new" class="btn btn-info btn-sm">
                <i data-feather="plus"></i> New FAQ
            </a>
            <a href="<?= BASE_URL ?>/admin/media" class="btn btn-success btn-sm">
                <i data-feather="upload"></i> Upload Media
            </a>
        </div>
    </div>

    <!-- Content Rotation Section -->
    <div class="dashboard-section">
        <h2 class="section-title">
            <i data-feather="repeat"></i> Content Rotation
        </h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="card-header">
                    <h3>Pages with Rotation</h3>
                    <i data-feather="repeat"></i>
                </div>
                <p class="stat-number"><?= $stats['total_pages_with_rotation'] ?></p>
                <p class="stat-label">Rotation enabled</p>
            </div>
            
            <div class="stat-card">
                <div class="card-header">
                    <h3>Total Rotations</h3>
                    <i data-feather="layers"></i>
                </div>
                <p class="stat-number"><?= $stats['total_rotations'] ?></p>
                <p class="stat-label">Content variants</p>
            </div>
            
            <div class="stat-card accent-success">
                <div class="card-header">
                    <h3>Active Rotations</h3>
                    <i data-feather="power"></i>
                </div>
                <p class="stat-number"><?= $stats['active_rotations'] ?></p>
                <p class="stat-label">Currently active</p>
            </div>
        </div>
        
        <div class="quick-links">
            <a href="<?= BASE_URL ?>/admin/rotations/overview" class="btn btn-primary btn-sm">
                <i data-feather="eye"></i> View Overview
            </a>
        </div>
    </div>

    <!-- Analytics Section -->
    <div class="dashboard-section">
        <h2 class="section-title">
            <i data-feather="trending-up"></i> Analytics
        </h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="card-header">
                    <h3>Today's Visits</h3>
                    <i data-feather="eye"></i>
                </div>
                <p class="stat-number"><?= number_format($stats['today_visits']) ?></p>
                <p class="stat-label">Page views today</p>
            </div>
            
            <div class="stat-card">
                <div class="card-header">
                    <h3>Today's Clicks</h3>
                    <i data-feather="mouse-pointer"></i>
                </div>
                <p class="stat-number"><?= number_format($stats['today_clicks']) ?></p>
                <p class="stat-label">User interactions</p>
            </div>
            
            <div class="stat-card accent-info">
                <div class="card-header">
                    <h3>This Week's Visits</h3>
                    <i data-feather="calendar"></i>
                </div>
                <p class="stat-number"><?= number_format($stats['week_visits']) ?></p>
                <p class="stat-label">Last 7 days</p>
            </div>
        </div>
        
        <div class="quick-links">
            <a href="<?= BASE_URL ?>/admin/analytics" class="btn btn-primary btn-sm">
                <i data-feather="bar-chart-2"></i> Full Analytics
            </a>
            <a href="<?= BASE_URL ?>/admin/analytics/rotation" class="btn btn-info btn-sm">
                <i data-feather="bar-chart"></i> Rotation Stats
            </a>
        </div>
    </div>
</div>

<div class="dashboard-footer">
    <h2>Quick Actions</h2>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/pages/new" class="btn btn-primary">
            <i data-feather="file-plus"></i> Create New Page
        </a>
        <a href="<?= BASE_URL ?>/admin/seo" class="btn btn-secondary">
            <i data-feather="search"></i> Edit SEO Settings
        </a>
        <a href="<?= BASE_URL ?>/" class="btn btn-info" target="_blank">
            <i data-feather="external-link"></i> View Website
        </a>
        <a href="<?= BASE_URL ?>/admin/analytics" class="btn btn-success">
            <i data-feather="trending-up"></i> View Analytics
        </a>
    </div>
</div>

<?php require 'layout/footer.php'; ?>