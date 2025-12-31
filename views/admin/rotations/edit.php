<?php 
// path: ./views/admin/rotations/edit.php
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<h1><?= $rotation ? 'Edit Content Rotation' : 'Create Content Rotation' ?></h1>

<form method="POST" action="<?= BASE_URL ?>/admin/rotations/save" class="admin-form">
    <?php if ($rotation): ?>
        <input type="hidden" name="id" value="<?= $rotation['id'] ?>">
    <?php endif; ?>

    <input type="hidden" name="page_id" value="<?= $page['id'] ?>">

    <div class="form-group">
        <label>Page:</label>
        <input type="text" value="<?= e($page['title_ru']) ?> / <?= e($page['title_uz']) ?>" disabled>
    </div>

    <div class="form-group">
        <label>Active Month*</label>
        <select name="active_month" required>
            <option value="">Select Month</option>
            <?php foreach ($months as $num => $label): ?>
                <option value="<?= $num ?>" <?= ($rotation['active_month'] ?? '') == $num ? 'selected' : '' ?>>
                    <?= $label ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Content (RU)*</label>
            <textarea name="content_ru" class="tinymce" rows="6"><?= $rotation['content_ru'] ?? '' ?></textarea>
        </div>
        <div class="form-group">
            <label>Content (UZ)*</label>
            <textarea name="content_uz" class="tinymce" rows="6"><?= $rotation['content_uz'] ?? '' ?></textarea>
        </div>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="is_active" <?= ($rotation['is_active'] ?? 1) ? 'checked' : '' ?>>
            Active
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $rotation ? 'Update Rotation' : 'Create Rotation' ?></button>
        <a href="<?= BASE_URL ?>/admin/rotations/manage/<?= $page['id'] ?>" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.7.0/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '.tinymce',
    height: 300,
    menubar: false,
    plugins: 'lists link image code',
    toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code',
    content_style: 'body { font-family: sans-serif; font-size: 14px; }'
});
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
