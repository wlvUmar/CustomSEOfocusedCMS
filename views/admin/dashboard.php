<?php require 'layout/header.php'; ?>

<h1>Dashboard</h1>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Total Pages</h3>
        <p class="stat-number"><?= $stats['total_pages'] ?></p>
    </div>
    
    <div class="stat-card">
        <h3>Published Pages</h3>
        <p class="stat-number"><?= $stats['published_pages'] ?></p>
    </div>
    
    <div class="stat-card">
        <h3>Media Files</h3>
        <p class="stat-number"><?= $stats['total_media'] ?></p>
    </div>
</div>

<div style="margin-top: 30px;">
    <h2>Quick Actions</h2>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/pages/new" class="btn btn-primary">Create New Page</a>
        <a href="<?= BASE_URL ?>/admin/seo" class="btn btn-secondary">Edit SEO Settings</a>
        <a href="<?= BASE_URL ?>/" class="btn btn-secondary" target="_blank">View Website</a>
    </div>
</div>

<?php require 'layout/footer.php'; ?>