<?php 
// path: ./views/admin/rotations/manage.php
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<div class="page-header">
    <h1>Content Rotations: <?= e($page['title_ru']) ?></h1>
    <div>
        <a href="<?= BASE_URL ?>/admin/rotations/new/<?= $page['id'] ?>" class="btn btn-primary">Add Month Content</a>
        <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>" class="btn btn-secondary">Back to Page</a>
    </div>
</div>

<div class="help-text">
    <strong>Current Month:</strong> <?= $months[date('n')] ?>. 
    This page will automatically show the content for the current month if rotation is enabled.
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Month</th>
            <th>Preview (RU)</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $hasContent = [];
        foreach ($rotations as $r) {
            $hasContent[$r['active_month']] = $r;
        }
        
        foreach ($months as $num => $name): 
            $rotation = $hasContent[$num] ?? null;
        ?>
        <tr>
            <td><strong><?= $name ?></strong></td>
            <td>
                <?php if ($rotation): ?>
                    <?= e(substr(strip_tags($rotation['content_ru']), 0, 80)) ?>...
                <?php else: ?>
                    <em>No content</em>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($rotation): ?>
                    <span class="badge <?= $rotation['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                        <?= $rotation['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                    <?php if ($num == date('n')): ?>
                        <span class="badge" style="background: #3b82f6;">Current</span>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($rotation): ?>
                    <a href="<?= BASE_URL ?>/admin/rotations/edit/<?= $rotation['id'] ?>" class="btn btn-sm">Edit</a>
                    <form method="POST" action="<?= BASE_URL ?>/admin/rotations/delete" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $rotation['id'] ?>">
                        <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</button>
                    </form>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/admin/rotations/new/<?= $page['id'] ?>?month=<?= $num ?>" class="btn btn-sm">Add</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>

<!-- path: ./views/admin/rotations/edit.php -->
<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<h1><?= $rotation ? 'Edit' : 'Create' ?> Content Rotation</h1>

<form method="POST" action="<?= BASE_URL ?>/admin/rotations/save" class="admin-form">
    <?php if ($rotation): ?>
    <input type="hidden" name="id" value="<?= $rotation['id'] ?>">
    <?php endif; ?>
    <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
    
    <div class="help-text">
        <strong>Page:</strong> <?= e($page['title_ru']) ?> (<?= e($page['slug']) ?>)
        <br><strong>Remember:</strong> Enable "Monthly Rotation" on the page for this to take effect!
    </div>
    
    <div class="form-group">
        <label>Active Month*</label>
        <select name="active_month" required>
            <?php 
            $selectedMonth = $rotation['active_month'] ?? ($_GET['month'] ?? date('n'));
            foreach ($months as $num => $name): 
            ?>
            <option value="<?= $num ?>" <?= $selectedMonth == $num ? 'selected' : '' ?>>
                <?= $name ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="form-group">
        <label>Content (RU)*</label>
        <textarea name="content_ru" class="tinymce" required><?= $rotation['content_ru'] ?? '' ?></textarea>
    </div>
    
    <div class="form-group">
        <label>Content (UZ)*</label>
        <textarea name="content_uz" class="tinymce" required><?= $rotation['content_uz'] ?? '' ?></textarea>
    </div>
    
    <div class="form-group">
        <label>
            <input type="checkbox" name="is_active" <?= ($rotation['is_active'] ?? 1) ? 'checked' : '' ?>>
            Active
        </label>
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Rotation</button>
        <a href="<?= BASE_URL ?>/admin/rotations/manage/<?= $page['id'] ?>" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.7.0/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '.tinymce',
    height: 400,
    menubar: false,
    plugins: 'lists link image code',
    toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code',
    content_style: 'body { font-family: sans-serif; font-size: 14px; }'
});
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>