<?php
// path: ./views/admin/articles/list.php
require_once BASE_PATH . '/views/admin/layout/header.php';
?>

<div class="admin-content">
    <div class="page-header">
        <h1>Articles</h1>
        <a href="<?= BASE_URL ?>/admin/articles/new" class="btn btn-primary">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
            </svg>
            New Article
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

    <!-- Filters -->
    <div class="filters-panel">
        <form method="GET" action="<?= BASE_URL ?>/admin/articles" class="filters-form">
            <input type="text" 
                   name="search" 
                   placeholder="Search articles..." 
                   value="<?= htmlspecialchars($search) ?>"
                   class="filter-input">
            
            <select name="category" class="filter-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="status" class="filter-select">
                <option value="">All Status</option>
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
            </select>
            
            <button type="submit" class="btn btn-secondary">Filter</button>
            <a href="<?= BASE_URL ?>/admin/articles" class="btn btn-secondary">Clear</a>
        </form>
    </div>

    <!-- Articles Table -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($articles)): ?>
                    <tr>
                        <td colspan="8" class="text-center">No articles found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($articles as $article): ?>
                        <tr data-article-id="<?= $article['id'] ?>">
                            <td><?= $article['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($article['title_ru'] ?: $article['title_uz']) ?></strong>
                                <br>
                                <small class="text-muted">/articles/<?= $article['id'] ?></small>
                            </td>
                            <td><?= htmlspecialchars($article['category_ru'] ?: $article['category_uz'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($article['author']) ?></td>
                            <td>
                                <button class="status-badge <?= $article['is_published'] ? 'status-published' : 'status-draft' ?>"
                                        onclick="togglePublish(<?= $article['id'] ?>)">
                                    <?= $article['is_published'] ? 'Published' : 'Draft' ?>
                                </button>
                            </td>
                            <td><?= date('Y-m-d H:i', strtotime($article['created_at'])) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($article['updated_at'])) ?></td>
                            <td class="actions">
                                <a href="<?= BASE_URL ?>/articles/<?= $article['id'] ?>" 
                                   target="_blank" 
                                   class="btn-icon" 
                                   title="View">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                <a href="<?= BASE_URL ?>/admin/articles/edit/<?= $article['id'] ?>" 
                                   class="btn-icon" 
                                   title="Edit">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <button onclick="deleteArticle(<?= $article['id'] ?>)" 
                                        class="btn-icon btn-danger" 
                                        title="Delete">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function togglePublish(id) {
    if (!confirm('Toggle publish status?')) return;
    
    fetch('<?= BASE_URL ?>/admin/articles/toggle-publish', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => alert('Error: ' + err));
}

function deleteArticle(id) {
    if (!confirm('Are you sure you want to delete this article? This cannot be undone.')) return;
    
    fetch('<?= BASE_URL ?>/admin/articles/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => alert('Error: ' + err));
}
</script>

<style>
.filters-panel {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.filters-form {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.filter-input, .filter-select {
    padding: 0.5rem 1rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.filter-input {
    flex: 1;
    min-width: 200px;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: opacity 0.2s;
}

.status-badge:hover {
    opacity: 0.8;
}

.status-published {
    background: #10b981;
    color: white;
}

.status-draft {
    background: #6b7280;
    color: white;
}

.actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    padding: 0.5rem;
    border: none;
    background: transparent;
    cursor: pointer;
    color: #6b7280;
    transition: color 0.2s;
}

.btn-icon:hover {
    color: #111827;
}

.btn-icon.btn-danger:hover {
    color: #dc2626;
}
</style>

<?php require_once BASE_PATH . '/views/admin/layout/footer.php'; ?>
