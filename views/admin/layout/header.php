<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <aside class="sidebar">
            <div class="logo">
                <h2>Admin Panel</h2>
            </div>
            <nav class="admin-nav">
                <a href="<?= BASE_URL ?>/admin/dashboard">Dashboard</a>
                <a href="<?= BASE_URL ?>/admin/pages">Pages</a>
                <a href="<?= BASE_URL ?>/admin/seo">SEO Settings</a>
                <a href="<?= BASE_URL ?>/admin/media">Media</a>
                <a href="<?= BASE_URL ?>/admin/logout">Logout</a>
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