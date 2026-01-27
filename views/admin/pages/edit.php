<?php 
// path: ./views/admin/pages/edit.php
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<h1><?= $page ? 'Edit Page' : 'Create Page' ?></h1>

<form method="POST" action="<?= BASE_URL ?>/admin/pages/save" class="admin-form">
    <?= csrfField() ?>
    <?php if ($page): ?>
    <input type="hidden" name="id" value="<?= $page['id'] ?>">
    <?php endif; ?>
    
    <div class="tabs">
        <button type="button" class="tab-btn active" onclick="switchTab('general')">General</button>
        <button type="button" class="tab-btn" onclick="switchTab('seo')">SEO & Meta</button>
        <button type="button" class="tab-btn" onclick="switchTab('advanced')">Advanced SEO</button>
    </div>
    
    <div id="tab-general" class="tab-content active">
        <div class="help-text">
            <strong>Template Variables:</strong> Use {{variable}} syntax. Available: {{page.title}}, {{global.phone}}, {{global.email}}, {{global.address}}, {{global.working_hours}}, {{global.site_name}}, {{date.year}}, {{date.month}}
            <br><strong>Loops:</strong> {% for item in items %}...{% endfor %}
            <br><strong>Conditionals:</strong> {% if variable %}...{% else %}...{% endif %}
        </div>
        
        <div class="form-group">
            <label>Slug (URL)*</label>
            <input type="text" name="slug" value="<?= $page['slug'] ?? '' ?>" required>
        </div>
        
        <div class="form-group">
            <label>Parent Page (Optional - for hierarchy)</label>
            <select name="parent_id" class="form-control">
                <option value="">— Root Level (No Parent) —</option>
                <?php if (!empty($allPages)): ?>
                    <?php 
                    function renderParentPageOptions($pages, $currentPageId = null, $parentId = 0, $depth = 0, $maxDepth = 3) {
                        $output = '';
                        if ($depth > $maxDepth) return $output;
                        
                        $childPages = array_filter($pages, function($p) use ($parentId, $currentPageId) {
                            // Skip current page to prevent circular reference
                            if ($currentPageId && $p['id'] == $currentPageId) return false;
                            return ($p['parent_id'] ?? 0) == $parentId;
                        });
                        
                        usort($childPages, function($a, $b) {
                            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
                        });
                        
                        foreach ($childPages as $p) {
                            $indent = str_repeat('  ', $depth) . ($depth > 0 ? '└ ' : '');
                            $isSelected = $page && isset($page['parent_id']) && $page['parent_id'] == $p['id'];
                            $output .= sprintf(
                                '<option value="%d" %s>%s%s</option>' . "\n",
                                $p['id'],
                                $isSelected ? 'selected' : '',
                                $indent,
                                e($p['title_ru'] ?? $p['slug'])
                            );
                            $output .= renderParentPageOptions($pages, $currentPageId, $p['id'], $depth + 1, $maxDepth);
                        }
                        return $output;
                    }
                    echo renderParentPageOptions($allPages, $page['id'] ?? null);
                    ?>
                <?php endif; ?>
            </select>
            <small class="help-subtext">Create a page hierarchy. URLs remain flat, but breadcrumbs will show the path.</small>
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
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="enable_rotation" <?= ($page['enable_rotation'] ?? 0) ? 'checked' : '' ?>>
                    Enable Monthly Rotation
                </label>
            </div>
        </div>
        
        <?php if ($page): ?>
        <div class="form-actions form-actions-secondary">
            <a href="<?= BASE_URL ?>/admin/rotations/manage/<?= $page['id'] ?>" class="btn btn-secondary">Manage Content Rotations</a>
        </div>
        <?php endif; ?>
        
        <?php if ($page): ?>
        <a href="<?= BASE_URL ?>/admin/link-widget/manage/<?= $page['id'] ?>" class="btn btn-secondary">
            <i data-feather="link"></i> Manage Link Widget
        </a>
        <?php endif; ?>
    </div>
    
    <div id="tab-seo" class="tab-content">
        <p class="help-text">Leave fields empty to use global defaults. All template variables work here too.</p>
        
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
    </div>
    
    <div id="tab-advanced" class="tab-content">
        <p class="help-text">Advanced SEO options for social media and search engines.</p>
        
        <div class="form-row">
            <div class="form-group">
                <label>OG Title (RU) - For Facebook/Social</label>
                <input type="text" name="og_title_ru" value="<?= $page['og_title_ru'] ?? '' ?>">
            </div>
            
            <div class="form-group">
                <label>OG Title (UZ)</label>
                <input type="text" name="og_title_uz" value="<?= $page['og_title_uz'] ?? '' ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>OG Description (RU)</label>
                <textarea name="og_description_ru" rows="2"><?= $page['og_description_ru'] ?? '' ?></textarea>
            </div>
            
            <div class="form-group">
                <label>OG Description (UZ)</label>
                <textarea name="og_description_uz" rows="2"><?= $page['og_description_uz'] ?? '' ?></textarea>
            </div>
        </div>
        
        <div class="form-group">
            <label>OG Image URL (Full URL)</label>
            <input type="text" name="og_image" value="<?= $page['og_image'] ?? '' ?>">
        </div>
        
        <div class="form-group">
            <label>Canonical URL (Leave empty for auto)</label>
            <input type="text" name="canonical_url" value="<?= $page['canonical_url'] ?? '' ?>">
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
    plugins: 'fullscreen code',
    toolbar: 'fullscreen code',
    content_css: '<?= BASE_URL ?>/css/pages.css'
});
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>