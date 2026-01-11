<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="page-header">
    <h1><i data-feather="image"></i> Media Library</h1>
    <div class="page-actions">
        <button onclick="document.getElementById('upload-input').click()" class="btn btn-primary">
            <i data-feather="upload"></i> Upload Media
        </button>
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
        console.log(err)
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
