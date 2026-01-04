<?php 
// path: ./views/admin/faqs/edit.php
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<h1><?= $faq ? 'Edit FAQ' : 'Create FAQ' ?></h1>

<form method="POST" action="<?= BASE_URL ?>/admin/faqs/save" class="admin-form">
    <?= csrfField() ?>
    <?php if ($faq): ?>
        <input type="hidden" name="id" value="<?= $faq['id'] ?>">
    <?php endif; ?>

    <div class="form-group">
        <label>Page*</label>
        <select name="page_slug" required>
            <option value="">Select Page</option>
            <?php foreach ($pages as $page): ?>
            <option value="<?= e($page['slug']) ?>" <?= ($faq['page_slug'] ?? '') === $page['slug'] ? 'selected' : '' ?>>
                <?= e($page['slug']) ?> - <?= e($page['title_ru']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Question (RU)*</label>
            <input type="text" name="question_ru" value="<?= $faq['question_ru'] ?? '' ?>" required>
        </div>
        <div class="form-group">
            <label>Question (UZ)*</label>
            <input type="text" name="question_uz" value="<?= $faq['question_uz'] ?? '' ?>" required>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Answer (RU)*</label>
            <textarea name="answer_ru" rows="3" required><?= $faq['answer_ru'] ?? '' ?></textarea>
        </div>
        <div class="form-group">
            <label>Answer (UZ)*</label>
            <textarea name="answer_uz" rows="3" required><?= $faq['answer_uz'] ?? '' ?></textarea>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Sort Order</label>
            <input type="number" name="sort_order" value="<?= $faq['sort_order'] ?? 0 ?>">
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" <?= ($faq['is_active'] ?? 1) ? 'checked' : '' ?>>
                Active
            </label>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $faq ? 'Update FAQ' : 'Create FAQ' ?></button>
        <a href="<?= BASE_URL ?>/admin/faqs" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
