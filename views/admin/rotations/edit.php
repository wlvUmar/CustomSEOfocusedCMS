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

    <div class="tabs">
        <button type="button" class="tab-btn active" onclick="switchTab('content')">Content</button>
        <button type="button" class="tab-btn" onclick="switchTab('seo')">SEO & Meta</button>
        <button type="button" class="tab-btn" onclick="switchTab('advanced')">Advanced SEO</button>
    </div>

    <!-- CONTENT TAB -->
    <div id="tab-content" class="tab-content active">
        <div class="help-text">
            <strong>Template Variables:</strong> All page template variables work here: {{page.title}}, {{global.phone}}, {{date.year}}, etc.
            <br><strong>Note:</strong> SEO fields also support template variables!
        </div>

        <div class="form-group">
            <label>Page:</label>
            <input type="text" value="<?= e($page['title_ru']) ?> / <?= e($page['title_uz']) ?>" disabled>
        </div>

        <div class="form-group">
            <label>Active Month*</label>
            <select name="active_month" required>
                <option value="">Select Month</option>
                <?php foreach ($months as $num => $label): ?>
                    <option value="<?= $num ?>" 
                            <?= ($rotation['active_month'] ?? $suggestedMonth ?? '') == $num ? 'selected' : '' ?>>
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
    </div>

    <!-- SEO TAB -->
    <div id="tab-seo" class="tab-content">
        <p class="help-text">
            Override page SEO for this rotation. Leave empty to use base page SEO. 
            Template variables work here too!
        </p>

        <div class="form-row">
            <div class="form-group">
                <label>Meta Title (RU)</label>
                <input type="text" name="meta_title_ru" 
                       value="<?= $rotation['meta_title_ru'] ?? '' ?>"
                       placeholder="Leave empty to use page default">
            </div>
            
            <div class="form-group">
                <label>Meta Title (UZ)</label>
                <input type="text" name="meta_title_uz" 
                       value="<?= $rotation['meta_title_uz'] ?? '' ?>"
                       placeholder="Leave empty to use page default">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Meta Keywords (RU)</label>
                <textarea name="meta_keywords_ru" rows="2"
                          placeholder="Leave empty to use page default"><?= $rotation['meta_keywords_ru'] ?? '' ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Meta Keywords (UZ)</label>
                <textarea name="meta_keywords_uz" rows="2"
                          placeholder="Leave empty to use page default"><?= $rotation['meta_keywords_uz'] ?? '' ?></textarea>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Meta Description (RU)</label>
                <textarea name="meta_description_ru" rows="3"
                          placeholder="Leave empty to use page default"><?= $rotation['meta_description_ru'] ?? '' ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Meta Description (UZ)</label>
                <textarea name="meta_description_uz" rows="3"
                          placeholder="Leave empty to use page default"><?= $rotation['meta_description_uz'] ?? '' ?></textarea>
            </div>
        </div>
    </div>

    <!-- ADVANCED SEO TAB -->
    <div id="tab-advanced" class="tab-content">
        <p class="help-text">Advanced SEO for social media and structured data.</p>

        <div class="form-row">
            <div class="form-group">
                <label>OG Title (RU) - For Facebook/Social</label>
                <input type="text" name="og_title_ru" 
                       value="<?= $rotation['og_title_ru'] ?? '' ?>"
                       placeholder="Leave empty to use page default">
            </div>
            
            <div class="form-group">
                <label>OG Title (UZ)</label>
                <input type="text" name="og_title_uz" 
                       value="<?= $rotation['og_title_uz'] ?? '' ?>"
                       placeholder="Leave empty to use page default">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>OG Description (RU)</label>
                <textarea name="og_description_ru" rows="2"
                          placeholder="Leave empty to use page default"><?= $rotation['og_description_ru'] ?? '' ?></textarea>
            </div>
            
            <div class="form-group">
                <label>OG Description (UZ)</label>
                <textarea name="og_description_uz" rows="2"
                          placeholder="Leave empty to use page default"><?= $rotation['og_description_uz'] ?? '' ?></textarea>
            </div>
        </div>

        <div class="form-group">
            <label>OG Image URL (Full URL)</label>
            <input type="text" name="og_image" 
                   value="<?= $rotation['og_image'] ?? '' ?>"
                   placeholder="Leave empty to use page default">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>JSON-LD Schema (RU)</label>
                <textarea name="jsonld_ru" rows="8" class="code"
                          placeholder="Leave empty to use page default"><?= $rotation['jsonld_ru'] ?? '' ?></textarea>
            </div>
            
            <div class="form-group">
                <label>JSON-LD Schema (UZ)</label>
                <textarea name="jsonld_uz" rows="8" class="code"
                          placeholder="Leave empty to use page default"><?= $rotation['jsonld_uz'] ?? '' ?></textarea>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <?= $rotation ? 'Update Rotation' : 'Create Rotation' ?>
        </button>
        <a href="<?= BASE_URL ?>/admin/rotations/manage/<?= $page['id'] ?>" class="btn btn-secondary">Cancel</a>
        
        <?php if (!$rotation): ?>
        <button type="submit" name="default_from_page" value="1" class="btn" 
                style="background: #3b82f6; color: white;">
            Create with Page Defaults
        </button>
        <?php endif; ?>
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
    content_css: '<?= BASE_URL ?>/css/pages.css'
});
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>