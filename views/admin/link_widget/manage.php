<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="page-header">
    <h1><i data-feather="link"></i> Link Widget: <?= e($page['title_ru']) ?></h1>

    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/pages" class="btn btn-secondary">
            <i data-feather="arrow-left"></i> Back
        </a>
        <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>" target="_blank" class="btn">
            <i data-feather="eye"></i> Preview
        </a>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2><i data-feather="settings"></i> Widget Settings</h2>

        <form method="POST" action="<?= BASE_URL ?>/admin/link-widget/toggle" class="inline-form">
            <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
            <input type="hidden" name="show" value="<?= $page['show_link_widget'] ? '0' : '1' ?>">

            <button class="btn <?= $page['show_link_widget'] ? 'btn-danger' : 'btn-primary' ?>">
                <i data-feather="<?= $page['show_link_widget'] ? 'eye-off' : 'eye' ?>"></i>
                <?= $page['show_link_widget'] ? 'Hide Widget' : 'Show Widget' ?>
            </button>
        </form>
    </div>
</div>

<div class="link-widget-grid">

    <!-- Active Links -->
    <div class="panel">
        <h2 class="panel-title">
            <i data-feather="check-circle"></i>
            Active Links (<?= count($currentLinks) ?>)
        </h2>

        <?php if (empty($currentLinks)): ?>
            <div class="empty-state">
                <i data-feather="info"></i>
                <p>No links added yet. Add some from the right.</p>
            </div>
        <?php else: ?>
            <div
                id="sortable-links"
                data-page-id="<?= $page['id'] ?>"
                data-reorder-url="<?= BASE_URL ?>/admin/link-widget/reorder"
            >
                <?php foreach ($currentLinks as $link): ?>
                    <div class="link-item" data-id="<?= $link['id'] ?>">
                        <div class="link-drag-handle">
                            <i data-feather="menu"></i>
                        </div>

                        <div class="link-info">
                            <strong><?= e($link['title_ru']) ?></strong>
                            <small><?= e($link['slug']) ?></small>
                        </div>

                        <form method="POST"
                              action="<?= BASE_URL ?>/admin/link-widget/remove"
                              class="inline-form">
                            <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                            <input type="hidden" name="link_to_page_id" value="<?= $link['link_to_page_id'] ?>">

                            <button class="btn btn-sm btn-danger" title="Remove">
                                <i data-feather="x"></i>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Available Pages -->
    <div class="panel">
        <h2 class="panel-title">
            <i data-feather="plus-circle"></i>
            Add Links (<?= count($availablePages) ?>)
        </h2>

        <?php if (empty($availablePages)): ?>
            <div class="empty-state">
                <p>All pages are already linked.</p>
            </div>
        <?php else: ?>
            <div class="available-pages-list">
                <?php foreach ($availablePages as $availPage): ?>
                    <div class="available-page-item">
                        <div>
                            <strong><?= e($availPage['title_ru']) ?></strong>
                            <small><?= e($availPage['slug']) ?></small>
                        </div>

                        <form method="POST" action="<?= BASE_URL ?>/admin/link-widget/add">
                            <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                            <input type="hidden" name="link_to_page_id" value="<?= $availPage['id'] ?>">

                            <button class="btn btn-sm btn-primary">
                                <i data-feather="plus"></i> Add
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
