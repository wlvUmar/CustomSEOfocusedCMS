<?php
$pageName = 'media/index';
require BASE_PATH . '/views/admin/layout/header.php';
?>

<div class="page-header">
    <h1><i data-feather="image"></i> Media Library</h1>
    <div class="page-actions">
        <button onclick="document.getElementById('upload-input').click()" class="btn btn-primary">
            <i data-feather="upload"></i> Upload Media
        </button>
    </div>
</div>

<!-- How to Use Info -->
<div class="info-banner">
    <div class="info-icon">✨</div>
    <div class="info-content">
        <strong>Automatic Image Display:</strong>
        <ul style="margin: 5px 0 0 20px; padding: 0;">
            <li><strong>Hero:</strong> Attached images appear at top of page automatically</li>
            <li><strong>Gallery:</strong> Creates automatic gallery section at bottom</li>
            <li><strong>Banner:</strong> Wide promotional image</li>
            <li><strong>Content:</strong> Can use <code>{{media:ID}}</code> in page editor for custom placement</li>
        </ul>
        <p style="margin-top: 8px; color: #666;">💡 Just click "Attach" → Select page & section → Done! No editing needed.</p>
    </div>
</div>

<!-- Filters -->
<div class="media-toolbar">
    <div class="filter-tabs">
        <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            All Media
        </a>
        <a href="?filter=used" class="filter-tab <?= $filter === 'used' ? 'active' : '' ?>">
            Used
        </a>
        <a href="?filter=unused" class="filter-tab <?= $filter === 'unused' ? 'active' : '' ?>">
            Unused
        </a>
    </div>

    <div class="search-box">
        <input type="text" id="search" placeholder="Search by filename..." onkeyup="filterMedia()">
    </div>
</div>

<!-- Upload Form (Hidden) -->
<form id="upload-form" style="display:none;">
    <input type="file" id="upload-input" accept="image/*" multiple onchange="uploadFiles()">
</form>

<!-- Media Grid -->
<div class="media-grid" id="media-grid">
    <?php if (empty($media)): ?>
        <div class="empty-state">
            <i data-feather="image" style="width:64px;height:64px;opacity:0.3;"></i>
            <h3>No Media Found</h3>
            <p>Upload your first image to get started</p>
            <button onclick="document.getElementById('upload-input').click()" class="btn btn-primary">
                Upload Media
            </button>
        </div>
    <?php else: ?>
        <?php foreach ($media as $item): ?>
            <div class="media-card" data-id="<?= $item['id'] ?>" data-name="<?= e($item['original_name']) ?>">
                <div class="media-thumbnail">
                    <img src="<?= UPLOAD_URL . e($item['filename']) ?>" alt="<?= e($item['original_name']) ?>" loading="lazy">
                    <div class="media-overlay">
                        <button class="btn-icon" onclick="viewMediaInfo(<?= $item['id'] ?>)" title="View Details">
                            <i data-feather="info"></i>
                        </button>
                        <button class="btn-icon" onclick="copyMediaId(<?= $item['id'] ?>)" title="Copy ID">
                            <i data-feather="hash"></i>
                        </button>
                    </div>
                </div>

                <div class="media-details">
                    <div class="media-id">ID: <?= $item['id'] ?></div>
                    <div class="media-filename" title="<?= e($item['original_name']) ?>">
                        <?= e(strlen($item['original_name']) > 30 ? substr($item['original_name'], 0, 27) . '...' : $item['original_name']) ?>
                    </div>
                    <div class="media-meta">
                        <?= number_format($item['file_size'] / 1024, 1) ?> KB
                        <?php if (isset($item['usage_count'])): ?>
                            • <span class="usage-badge <?= $item['usage_count'] > 0 ? 'used' : 'unused' ?>">
                                <?= $item['usage_count'] ?> page<?= $item['usage_count'] != 1 ? 's' : '' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="media-actions">
                    <button class="btn btn-sm btn-primary" onclick="showAttachModal(<?= $item['id'] ?>)" title="Attach to Page">
                        <i data-feather="link"></i> Attach
                    </button>
                    <button class="btn btn-sm" onclick="copyPlaceholder(<?= $item['id'] ?>)" title="Copy Placeholder">
                        <i data-feather="code"></i> {{media:<?= $item['id'] ?>}}
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteMedia(<?= $item['id'] ?>, <?= $item['usage_count'] ?? 0 ?>)" title="Delete">
                        <i data-feather="trash-2"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Attach Media Modal -->
<div id="attach-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Attach Media to Page</h3>
            <button onclick="closeAttachModal()" class="btn-close">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="attach-media-id">
            
            <div class="form-group">
                <label>Select Page:</label>
                <select id="attach-page-id" class="form-control" required>
                    <option value="">-- Select Page --</option>
                    <?php foreach ($allPages as $page): ?>
                        <option value="<?= $page['id'] ?>"><?= e($page['title_ru']) ?> (<?= $page['slug'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Section:</label>
                <select id="attach-section" class="form-control">
                    <option value="content">Content (inline)</option>
                    <option value="hero">Hero Banner</option>
                    <option value="gallery">Gallery</option>
                    <option value="banner">Banner</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Alt Text (RU):</label>
                    <input type="text" id="attach-alt-ru" class="form-control" placeholder="Описание изображения">
                </div>
                <div class="form-group">
                    <label>Alt Text (UZ):</label>
                    <input type="text" id="attach-alt-uz" class="form-control" placeholder="Rasm tavsifi">
                </div>
            </div>

            <div class="form-group">
                <label>Alignment:</label>
                <select id="attach-alignment" class="form-control">
                    <option value="center">Center</option>
                    <option value="left">Left</option>
                    <option value="right">Right</option>
                    <option value="full">Full Width</option>
                </select>
            </div>

            <div class="form-group">
                <label>Max Width (px):</label>
                <input type="number" id="attach-width" class="form-control" placeholder="Leave empty for auto">
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeAttachModal()" class="btn">Cancel</button>
            <button onclick="attachMedia()" class="btn btn-primary">Attach Media</button>
        </div>
    </div>
</div>

<!-- Media Info Modal -->
<div id="info-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Media Details</h3>
            <button onclick="closeInfoModal()" class="btn-close">&times;</button>
        </div>
        <div class="modal-body" id="info-modal-body">
            Loading...
        </div>
    </div>
</div>

<style>
.media-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
    gap: 20px;
}

.filter-tabs {
    display: flex;
    gap: 10px;
}

.filter-tab {
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    color: #666;
    transition: all 0.2s;
}

.filter-tab:hover {
    background: #f5f5f5;
    color: #333;
}

.filter-tab.active {
    background: #007bff;
    color: white;
}

.info-banner {
    background: #e3f2fd;
    border: 1px solid #90caf9;
    border-radius: 8px;
    padding: 15px 20px;
    margin: 15px 0;
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.info-icon {
    font-size: 24px;
    flex-shrink: 0;
}

.info-content {
    flex: 1;
    line-height: 1.6;
}

.info-content code {
    background: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    color: #d32f2f;
    font-size: 13px;
}

.search-box input {
    padding: 8px 16px;
    border: 1px solid #ddd;
    border-radius: 6px;
    width: 300px;
}

.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.media-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    background: white;
    transition: all 0.3s;
}

.media-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.media-thumbnail {
    position: relative;
    width: 100%;
    height: 200px;
    background: #f5f5f5;
    overflow: hidden;
}

.media-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.media-overlay {
    position: absolute;
    top: 0;
    right: 0;
    display: flex;
    gap: 5px;
    padding: 8px;
    opacity: 0;
    transition: opacity 0.2s;
}

.media-card:hover .media-overlay {
    opacity: 1;
}

.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background: rgba(255,255,255,0.9);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:hover {
    background: white;
}

.media-details {
    padding: 12px;
}

.media-id {
    font-size: 11px;
    color: #999;
    font-weight: 600;
    margin-bottom: 4px;
}

.media-filename {
    font-weight: 500;
    color: #333;
    margin-bottom: 6px;
}

.media-meta {
    font-size: 12px;
    color: #666;
}

.usage-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 500;
    font-size: 11px;
}

.usage-badge.used {
    background: #e3f2fd;
    color: #1976d2;
}

.usage-badge.unused {
    background: #fff3e0;
    color: #f57c00;
}

.media-actions {
    padding: 12px;
    display: flex;
    gap: 8px;
    border-top: 1px solid #f0f0f0;
}

.media-actions .btn {
    flex: 1;
    font-size: 12px;
    padding: 6px 8px;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state h3 {
    margin: 20px 0 10px;
    color: #666;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
</style>

<script>
function filterMedia() {
    const search = document.getElementById('search').value.toLowerCase();
    const cards = document.querySelectorAll('.media-card');
    
    cards.forEach(card => {
        const name = card.dataset.name.toLowerCase();
        card.style.display = name.includes(search) ? '' : 'none';
    });
}

function uploadFiles() {
    const files = document.getElementById('upload-input').files;
    if (!files.length) return;
    
    const formData = new FormData();
    Array.from(files).forEach(file => {
        formData.append('files[]', file);
    });
    
    fetch('<?= BASE_URL ?>/admin/media/bulk-upload', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        location.reload();
    })
    .catch(err => {
        alert('Upload failed: ' + err.message);
    });
}

function showAttachModal(mediaId) {
    document.getElementById('attach-media-id').value = mediaId;
    document.getElementById('attach-modal').classList.add('active');
}

function closeAttachModal() {
    document.getElementById('attach-modal').classList.remove('active');
}

function attachMedia() {
    const mediaId = document.getElementById('attach-media-id').value;
    const pageId = document.getElementById('attach-page-id').value;
    const section = document.getElementById('attach-section').value;
    const altRu = document.getElementById('attach-alt-ru').value;
    const altUz = document.getElementById('attach-alt-uz').value;
    const alignment = document.getElementById('attach-alignment').value;
    const width = document.getElementById('attach-width').value;
    
    if (!pageId) {
        alert('Please select a page');
        return;
    }
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
    formData.append('media_id', mediaId);
    formData.append('page_id', pageId);
    formData.append('section', section);
    formData.append('alt_text_ru', altRu);
    formData.append('alt_text_uz', altUz);
    formData.append('alignment', alignment);
    if (width) formData.append('width', width);
    
    fetch('<?= BASE_URL ?>/admin/media/attach', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeAttachModal();
            const sectionMessages = {
                'hero': '✅ Media attached to HERO section!\n\nIt will automatically appear at the top of the page.',
                'gallery': '✅ Media attached to GALLERY section!\n\nIt will automatically appear in the gallery grid.',
                'banner': '✅ Media attached to BANNER section!\n\nIt will automatically appear as a banner.',
                'content': '✅ Media attached to CONTENT section!\n\nYou can now use:\n{{media:' + mediaId + '}}\n\nOr it will appear with other content media.'
            };
            alert(sectionMessages[section] || 'Media attached successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
    });
}

function copyMediaId(id) {
    navigator.clipboard.writeText(id);
    alert('Media ID copied: ' + id);
}

function copyPlaceholder(id) {
    const placeholder = '{{media:' + id + '}}';
    navigator.clipboard.writeText(placeholder);
    alert('Copied: ' + placeholder);
}

function deleteMedia(id, usageCount) {
    if (usageCount > 0) {
        if (!confirm(`This media is used on ${usageCount} page(s). Delete anyway?`)) {
            return;
        }
    } else {
        if (!confirm('Delete this media?')) return;
    }
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
    formData.append('id', id);
    if (usageCount > 0) formData.append('force', '1');
    
    fetch('<?= BASE_URL ?>/admin/media/delete', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
    });
}

function viewMediaInfo(id) {
    document.getElementById('info-modal').classList.add('active');
    document.getElementById('info-modal-body').innerHTML = 'Loading...';
    
    fetch('<?= BASE_URL ?>/admin/media/info?id=' + id)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const info = data.media;
            const pages = data.pages || [];
            
            let html = `
                <div><strong>ID:</strong> ${info.id}</div>
                <div><strong>Filename:</strong> ${info.filename}</div>
                <div><strong>Size:</strong> ${(info.file_size / 1024).toFixed(1)} KB</div>
                <div><strong>Uploaded:</strong> ${info.uploaded_at}</div>
                <hr>
                <h4>Used on ${pages.length} page(s):</h4>
            `;
            
            if (pages.length > 0) {
                html += '<ul>';
                pages.forEach(p => {
                    html += `<li>${p.title_ru} (${p.slug}) - Section: ${p.section}</li>`;
                });
                html += '</ul>';
            } else {
                html += '<p><em>Not used on any pages yet</em></p>';
            }
            
            document.getElementById('info-modal-body').innerHTML = html;
        }
    });
}

function closeInfoModal() {
    document.getElementById('info-modal').classList.remove('active');
}
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
