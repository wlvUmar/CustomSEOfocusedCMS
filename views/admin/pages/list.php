<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="page-header">
    <h1>Pages</h1>
    <a href="<?= BASE_URL ?>/admin/pages/new" class="btn btn-primary">Add New Page</a>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Slug</th>
            <th>Title (RU)</th>
            <th>Title (UZ)</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($pages as $page): ?>
        <tr>
            <td><?= $page['id'] ?></td>
            <td><?= e($page['slug']) ?></td>
            <td><?= e($page['title_ru']) ?></td>
            <td><?= e($page['title_uz']) ?></td>
            <td>
                <span class="badge <?= $page['is_published'] ? 'badge-success' : 'badge-danger' ?>">
                    <?= $page['is_published'] ? 'Published' : 'Draft' ?>
                </span>
            </td>
            <td>
                <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>" class="btn btn-sm">Edit</a>
                <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>" class="btn btn-sm" target="_blank">View</a>
                <form method="POST" action="<?= BASE_URL ?>/admin/pages/delete" style="display:inline;" 
                      onsubmit="return confirm('Delete this page?')">
                    <input type="hidden" name="id" value="<?= $page['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>