<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="page-header">
    <h1>Internal Links: <?= e($page['title_ru']) ?></h1>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/internal-links" class="btn btn-secondary">
            <i data-feather="arrow-left"></i> Back to Overview
        </a>
        <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>" class="btn btn-secondary">
            <i data-feather="edit"></i> Edit Page
        </a>
    </div>
</div>

<div class="links-stats-bar">
    <div class="stat-item">
        <span class="stat-label"><i data-feather="arrow-right"></i> Outgoing Links:</span>
        <span class="stat-value"><?= count($currentLinks) ?></span>
    </div>
    <div class="stat-item">
        <span class="stat-label"><i data-feather="arrow-left"></i> Incoming Links:</span>
        <span class="stat-value"><?= count($incomingLinks) ?></span>
    </div>
    <div class="stat-item">
        <span class="stat-label"><i data-feather="eye"></i> Widget Status:</span>
        <span class="stat-value <?= $page['show_link_widget'] ? 'text-success' : 'text-danger' ?>">
            <?= $page['show_link_widget'] ? 'Enabled' : 'Disabled' ?>
        </span>
    </div>
</div>

<!-- Widget Toggle -->
<form method="POST" action="<?= BASE_URL ?>/admin/link-widget/toggle" style="margin-bottom: 30px;">
    <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
    <input type="hidden" name="show" value="<?= $page['show_link_widget'] ? '0' : '1' ?>">
    <button type="submit" class="btn <?= $page['show_link_widget'] ? 'btn-danger' : 'btn-primary' ?>">
        <i data-feather="<?= $page['show_link_widget'] ? 'eye-off' : 'eye' ?>"></i>
        <?= $page['show_link_widget'] ? 'Disable' : 'Enable' ?> Link Widget on This Page
    </button>
</form>

<div class="two-column-layout">
    <!-- Current Outgoing Links -->
    <div class="column">
        <h2><i data-feather="arrow-right"></i> Outgoing Links (<?= count($currentLinks) ?>)</h2>
        
        <?php if (empty($currentLinks)): ?>
        <div class="empty-state">
            <i data-feather="link-2"></i>
            <p>No outgoing links yet</p>
            <small>Add links from the "Available Pages" section</small>
        </div>
        <?php else: ?>
        <div class="links-list sortable" id="sortable-links">
            <?php foreach ($currentLinks as $link): ?>
            <div class="link-item" data-id="<?= $link['id'] ?>">
                <div class="drag-handle">
                    <i data-feather="menu"></i>
                </div>
                <div class="link-info">
                    <strong><?= e($link['title_ru']) ?></strong>
                    <small><?= e($link['slug']) ?></small>
                </div>
                <form method="POST" action="<?= BASE_URL ?>/admin/link-widget/remove" style="display:inline;">
                    <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                    <input type="hidden" name="link_to_page_id" value="<?= $link['link_to_page_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger" title="Remove link">
                        <i data-feather="trash-2"></i>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <small style="color: #6b7280; display: block; margin-top: 10px;">
            <i data-feather="move"></i> Drag to reorder links
        </small>
        <?php endif; ?>
    </div>

    <!-- Available Pages to Link -->
    <div class="column">
        <h2><i data-feather="plus-square"></i> Available Pages</h2>
        
        <?php if (empty($availablePages)): ?>
        <div class="empty-state">
            <i data-feather="check-circle"></i>
            <p>All pages are already linked!</p>
        </div>
        <?php else: ?>
        <div class="search-box">
            <i data-feather="search"></i>
            <input type="text" id="search-pages" placeholder="Search pages..." onkeyup="filterPages()">
        </div>
        
        <div class="links-list" id="available-pages-list">
            <?php foreach ($availablePages as $availPage): ?>
            <div class="link-item available-page-item" data-title="<?= e(strtolower($availPage['title_ru'])) ?>">
                <div class="link-info">
                    <strong><?= e($availPage['title_ru']) ?></strong>
                    <small><?= e($availPage['slug']) ?></small>
                </div>
                <form method="POST" action="<?= BASE_URL ?>/admin/link-widget/add">
                    <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                    <input type="hidden" name="link_to_page_id" value="<?= $availPage['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-primary" title="Add link">
                        <i data-feather="plus"></i> Add
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Incoming Links Section -->
<div style="margin-top: 40px;">
    <h2><i data-feather="arrow-left"></i> Pages Linking to This Page (<?= count($incomingLinks) ?>)</h2>
    
    <?php if (empty($incomingLinks)): ?>
    <div class="empty-state">
        <i data-feather="alert-circle"></i>
        <p>No incoming links</p>
        <small>This page is not linked from any other pages yet</small>
    </div>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Page</th>
                <th>Slug</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($incomingLinks as $link): ?>
            <tr>
                <td><strong><?= e($link['title_ru']) ?></strong></td>
                <td><code><?= e($link['slug']) ?></code></td>
                <td>
                    <a href="<?= BASE_URL ?>/admin/internal-links/manage/<?= $link['page_id'] ?>" 
                       class="btn btn-sm">
                        <i data-feather="external-link"></i> Manage
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="<?= BASE_URL ?>/js/admin/internal-links-manage.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
