<?php
// path: ./views/admin/articles/edit.php
require_once BASE_PATH . '/views/admin/layout/header.php';

$isEdit = !empty($article);
$title = $isEdit ? 'Edit Article' : 'New Article';
?>

<div class="admin-content">
    <div class="page-header">
        <h1><?= $title ?></h1>
        <a href="<?= BASE_URL ?>/admin/articles" class="btn btn-secondary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to List
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/admin/articles/save" enctype="multipart/form-data" class="article-form">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $article['id'] ?>">
        <?php endif; ?>

        <div class="form-card">
            <h2>Basic Information</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="title_ru">Title (Russian) *</label>
                    <input type="text" 
                           id="title_ru" 
                           name="title_ru" 
                           value="<?= htmlspecialchars($article['title_ru'] ?? '') ?>" 
                           required
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="title_uz">Title (Uzbek)</label>
                    <input type="text" 
                           id="title_uz" 
                           name="title_uz" 
                           value="<?= htmlspecialchars($article['title_uz'] ?? '') ?>"
                           class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label for="slug">URL Slug</label>
                <input type="text" 
                       id="slug" 
                       name="slug" 
                       value="<?= htmlspecialchars($article['slug'] ?? '') ?>"
                       placeholder="Leave empty to auto-generate from title"
                       class="form-control">
                <small class="form-hint">URL will be: /articles/<?= $article['id'] ?? '{id}' ?></small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category_ru">Category (Russian)</label>
                    <input type="text" 
                           id="category_ru" 
                           name="category_ru" 
                           value="<?= htmlspecialchars($article['category_ru'] ?? '') ?>"
                           list="categories_ru"
                           class="form-control">
                    <datalist id="categories_ru">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-group">
                    <label for="category_uz">Category (Uzbek)</label>
                    <input type="text" 
                           id="category_uz" 
                           name="category_uz" 
                           value="<?= htmlspecialchars($article['category_uz'] ?? '') ?>"
                           class="form-control">
                </div>
                </div>
            </div>

            <div class="form-group">
                <label for="related_page_id">Related Service Page</label>
                <select id="related_page_id" name="related_page_id" class="form-control">
                    <option value="">-- No Related Page --</option>
                    <?php if (isset($pages)): ?>
                        <?php foreach ($pages as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($article['related_page_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['title_ru'] ?? $p['title_uz'] ?? 'Page #' . $p['id']) ?> (<?= $p['slug'] ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <small class="form-hint">Links this article to a main service page (shown as a call-to-action)</small>
            </div>

            <div class="form-group">
                <label for="author">Author</label>
                <input type="text" 
                       id="author" 
                       name="author" 
                       value="<?= htmlspecialchars($article['author'] ?? 'Admin') ?>"
                       class="form-control">
            </div>
        </div>

        <div class="form-card">
            <h2>Content</h2>
            
            <div class="form-group">
                <label for="content_ru">Content (Russian) *</label>
                <textarea id="content_ru" 
                          name="content_ru" 
                          rows="15" 
                          required
                          class="form-control tinymce"><?= $article['content_ru'] ?? '' ?></textarea>
            </div>

            <div class="form-group">
                <label for="content_uz">Content (Uzbek)</label>
                <textarea id="content_uz" 
                          name="content_uz" 
                          rows="15"
                          class="form-control tinymce"><?= $article['content_uz'] ?? '' ?></textarea>
            </div>

            <div class="form-group">
                <label for="excerpt_ru">Excerpt (Russian)</label>
                <textarea id="excerpt_ru" 
                          name="excerpt_ru" 
                          rows="3"
                          placeholder="Leave empty to auto-generate from content"
                          class="form-control"><?= htmlspecialchars($article['excerpt_ru'] ?? '') ?></textarea>
                <small class="form-hint">Short summary for listings and meta description</small>
            </div>

            <div class="form-group">
                <label for="excerpt_uz">Excerpt (Uzbek)</label>
                <textarea id="excerpt_uz" 
                          name="excerpt_uz" 
                          rows="3"
                          placeholder="Leave empty to auto-generate from content"
                          class="form-control"><?= htmlspecialchars($article['excerpt_uz'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-card">
            <h2>Featured Image</h2>
            
            <?php if (!empty($article['image'])): ?>
                <div class="current-image">
                    <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($article['image']) ?>" 
                         alt="Current featured image"
                         style="max-width: 300px; border-radius: 8px;">
                    <input type="hidden" name="image" value="<?= htmlspecialchars($article['image']) ?>">
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="image_upload">Upload New Image</label>
                <input type="file" 
                       id="image_upload" 
                       name="image_upload" 
                       accept="image/*"
                       class="form-control">
            </div>
        </div>

        <div class="form-card">
            <h2>Publishing</h2>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" 
                           name="is_published" 
                           <?= !empty($article['is_published']) ? 'checked' : '' ?>>
                    <span>Publish this article</span>
                </label>
                <small class="form-hint">Unpublished articles are only visible to admins</small>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z"/>
                </svg>
                Save Article
            </button>
            
            <?php if ($isEdit): ?>
                <a href="<?= BASE_URL ?>/articles/<?= $article['id'] ?>" 
                   target="_blank" 
                   class="btn btn-secondary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Preview
                </a>
            <?php endif; ?>
        </div>
    </form>

    <div class="info-panel">
        <h3>ℹ️ SEO Auto-Generation</h3>
        <p>SEO metadata is automatically generated from your content:</p>
        <ul>
            <li><strong>Meta Title:</strong> Uses article title</li>
            <li><strong>Meta Description:</strong> Uses excerpt or first 160 characters of content</li>
            <li><strong>OpenGraph Tags:</strong> Auto-generated for social sharing</li>
            <li><strong>JSON-LD Schema:</strong> Article/BlogPosting schema with proper structure</li>
        </ul>
        <p>No manual SEO work required!</p>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.7.0/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '.tinymce',
    height: 420,
    menubar: false,
    plugins: 'fullscreen code link image lists',
    toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | code fullscreen',
    content_css: '<?= BASE_URL ?>/css/pages.css'
});
</script>

<?php require_once BASE_PATH . '/views/admin/layout/footer.php'; ?>
