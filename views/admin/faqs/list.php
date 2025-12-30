<?php 
// path: ./views/admin/faqs/list.php
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<div class="page-header">
    <h1>FAQs</h1>
    <a href="<?= BASE_URL ?>/admin/faqs/new" class="btn btn-primary">Add New FAQ</a>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Page</th>
            <th>Question (RU)</th>
            <th>Order</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($faqs as $faq): ?>
        <tr>
            <td><?= $faq['id'] ?></td>
            <td><?= e($faq['page_slug']) ?></td>
            <td><?= e(substr($faq['question_ru'], 0, 60)) ?>...</td>
            <td><?= $faq['sort_order'] ?></td>
            <td>
                <span class="badge <?= $faq['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                    <?= $faq['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
            </td>
            <td>
                <a href="<?= BASE_URL ?>/admin/faqs/edit/<?= $faq['id'] ?>" class="btn btn-sm">Edit</a>
                <form method="POST" action="<?= BASE_URL ?>/admin/faqs/delete" style="display:inline;" 
                      onsubmit="return confirm('Delete this FAQ?')">
                    <input type="hidden" name="id" value="<?= $faq['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>

<!-- path: ./views/admin/faqs/edit.php -->
<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<h1><?= $faq ? 'Edit FAQ' : 'Create FAQ' ?></h1>

<form method="POST" action="<?= BASE_URL ?>/admin/faqs/save" class="admin-form">
    <?php if ($faq): ?>
    <input type="hidden" name="id" value="<?= $faq['id'] ?>">
    <?php endif; ?>
    
    <div class="form-group">
        <label>Page Slug*</label>
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
            <textarea name="question_ru" rows="2" required><?= $faq['question_ru'] ?? '' ?></textarea>
        </div>
        
        <div class="form-group">
            <label>Question (UZ)*</label>
            <textarea name="question_uz" rows="2" required><?= $faq['question_uz'] ?? '' ?></textarea>
        </div>
    </div>
    
    <div class="form-row">
        <div class="form-group">
            <label>Answer (RU)*</label>
            <textarea name="answer_ru" rows="4" required><?= $faq['answer_ru'] ?? '' ?></textarea>
        </div>
        
        <div class="form-group">
            <label>Answer (UZ)*</label>
            <textarea name="answer_uz" rows="4" required><?= $faq['answer_uz'] ?? '' ?></textarea>
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
        <button type="submit" class="btn btn-primary">Save FAQ</button>
        <a href="<?= BASE_URL ?>/admin/faqs" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>