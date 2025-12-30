<?php 
// path: ./views/admin/faqs/edit.php
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<h1><?= $faq ? 'Edit FAQ' : 'Create FAQ' ?></h1>

<form method="POST" action="<?= BASE_URL ?>/admin/faqs/save" class="admin-form">
    <?php if ($faq): ?>
        <input type="hidden" name="id" value="<?= $faq['id'] ?>">
    <?php endif; ?>

    <div class="tabs">
        <button type="button" class="tab-btn active" onclick="switchTab('general')">General</button>
        <button type="button" class="tab-btn" onclick="switchTab('seo')">SEO & Meta</button>
    </div>

    <div id="tab-general" class="tab-content active">
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
                <textarea name="answer_ru" rows="4" class="tinymce"><?= $faq['answer_ru'] ?? '' ?></textarea>
            </div>
            <div class="form-group">
                <label>Answer (UZ)*</label>
                <textarea name="answer_uz" rows="4" class="tinymce"><?= $faq['answer_uz'] ?? '' ?></textarea>
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
    </div>

    <div id="tab-seo" class="tab-content">
        <p class="help-text">Optional SEO fields. Leave empty to use defaults.</p>

        <div class="form-row">
            <div class="form-group">
                <label>Meta Title (RU)</label>
                <input type="text" name="meta_title_ru" value="<?= $faq['meta_title_ru'] ?? '' ?>">
            </div>
            <div class="form-group">
                <label>Meta Title (UZ)</label>
                <input type="text" name="meta_title_uz" value="<?= $faq['meta_title_uz'] ?? '' ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Meta Keywords (RU)</label>
                <textarea name="meta_keywords_ru" rows="2"><?= $faq['meta_keywords_ru'] ?? '' ?></textarea>
            </div>
            <div class="form-group">
                <label>Meta Keywords (UZ)</label>
                <textarea name="meta_keywords_uz" rows="2"><?= $faq['meta_keywords_uz'] ?? '' ?></textarea>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Meta Description (RU)</label>
                <textarea name="meta_description_ru" rows="3"><?= $faq['meta_description_ru'] ?? '' ?></textarea>
            </div>
            <div class="form-group">
                <label>Meta Description (UZ)</label>
                <textarea name="meta_description_uz" rows="3"><?= $faq['meta_description_uz'] ?? '' ?></textarea>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save FAQ</button>
        <a href="<?= BASE_URL ?>/admin/faqs" class="btn btn-secondary">Cancel</a>
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

function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.tab-btn[onclick="switchTab('${tab}')"]`).classList.add('active');
}
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
