<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<h1><?= $page ? 'Edit Page' : 'Create Page' ?></h1>

<form method="POST" action="<?= BASE_URL ?>/admin/pages/save" class="admin-form">
    <?php if ($page): ?>
    <input type="hidden" name="id" value="<?= $page['id'] ?>">
    <?php endif; ?>
    
    <div class="tabs">
        <button type="button" class="tab-btn active" onclick="switchTab('general')">General</button>
        <button type="button" class="tab-btn" onclick="switchTab('seo')">SEO</button>
    </div>
    
    <div id="tab-general" class="tab-content active">
        <div class="form-group">
            <label>Slug (URL)*</label>
            <input type="text" name="slug" value="<?= $page['slug'] ?? '' ?>" required>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Title (RU)*</label>
                <input type="text" name="title_ru" value="<?= $page['title_ru'] ?? '' ?>" required>
            </div>
            
            <div class="form-group">
                <label>Title (UZ)*</label>
                <input type="text" name="title_uz" value="<?= $page['title_uz'] ?? '' ?>" required>
            </div>
        </div>
        
        <div class="form-group">
            <label>Content (RU)</label>
            <textarea name="content_ru" class="tinymce"><?= $page['content_ru'] ?? '' ?></textarea>
        </div>
        
        <div class="form-group">
            <label>Content (UZ)</label>
            <textarea name="content_uz" class="tinymce"><?= $page['content_uz'] ?? '' ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Sort Order</label>
                <input type="number" name="sort_order" value="<?= $page['sort_order'] ?? 0 ?>">
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_published" <?= ($page['is_published'] ?? 1) ? 'checked' : '' ?>>
                    Published
                </label>
            </div>
        </div>
    </div>
    
    <div id="tab-seo" class="tab-content">
        <p class="help-text">Leave fields empty to use global defaults. Available variables: {{page.title}}, {{global.phone}}, {{global.email}}, {{global.address}}, {{global.working_hours}}, {{global.site_name}}</p>
        
        <div class="form-row">
            <div class="form-group">
                <label>Meta Title (RU)</label>
                <input type="text" name="meta_title_ru" value="<?= $page['meta_title_ru'] ?? '' ?>">
            </div>
            
            <div class="form-group">
                <label>Meta Title (UZ)</label>
                <input type="text" name="meta_title_uz" value="<?= $page['meta_title_uz'] ?? '' ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Meta Keywords (RU)</label>
                <textarea name="meta_keywords_ru" rows="2"><?= $page['meta_keywords_ru'] ?? '' ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Meta Keywords (UZ)</label>
                <textarea name="meta_keywords_uz" rows="2"><?= $page['meta_keywords_uz'] ?? '' ?></textarea>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Meta Description (RU)</label>
                <textarea name="meta_description_ru" rows="3"><?= $page['meta_description_ru'] ?? '' ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Meta Description (UZ)</label>
                <textarea name="meta_description_uz" rows="3"><?= $page['meta_description_uz'] ?? '' ?></textarea>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>JSON-LD Schema (RU)</label>
                <textarea name="jsonld_ru" rows="8" class="code"><?= $page['jsonld_ru'] ?? '' ?></textarea>
            </div>
            
            <div class="form-group">
                <label>JSON-LD Schema (UZ)</label>
                <textarea name="jsonld_uz" rows="8" class="code"><?= $page['jsonld_uz'] ?? '' ?></textarea>
            </div>
        </div>
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Page</button>
        <a href="<?= BASE_URL ?>/admin/pages" class="btn btn-secondary">Cancel</a>
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
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>