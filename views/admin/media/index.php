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
    <?php 
    // Debug: show first item structure
    if (!empty($media)) {
        echo "<!-- DEBUG: First media item structure: " . json_encode($media[0]) . " -->";
        echo "<!-- DEBUG: Array keys: " . implode(", ", array_keys($media[0])) . " -->";
    }
    ?>
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
            <?php 
            // Get media ID - try multiple sources
            $mediaId = 0;
            if (isset($item['media_id']) && $item['media_id']) {
                $mediaId = (int)$item['media_id'];
            } else if (isset($item['id']) && $item['id']) {
                $mediaId = (int)$item['id'];
            }
            ?>
            <div class="media-card" data-id="<?= $mediaId ?>" data-name="<?= e($item['original_name']) ?>">
                <div class="media-thumbnail">
                    <img src="<?= UPLOAD_URL . e($item['filename']) ?>" alt="<?= e($item['original_name']) ?>" loading="lazy">
                </div>

                <div class="media-details">
                    <div class="media-id">ID: <?= $mediaId ?></div>
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
                    <?php if (!empty($item['pages'])): ?>
                        <div class="media-usage">
                            <div class="media-usage-title">Attached to:</div>
                            <?php foreach ($item['pages'] as $pageItem): ?>
                                <?php
                                $slug = $pageItem['slug'] ?? ($pageItem['title_ru'] ?? '');
                                $section = $pageItem['section'] ?? '';
                                $pageId = $pageItem['id'] ?? ($pageItem['page_id'] ?? null);
                                ?>
                                <div class="media-usage-item">
                                    <span class="media-usage-label">
                                        <?= e($slug) ?><?= $section ? ' (' . e($section) . ')' : '' ?>
                                    </span>
                                    <?php if (!empty($pageId)): ?>
                                        <button
                                            type="button"
                                            class="media-detach"
                                            data-media-id="<?= $mediaId ?>"
                                            data-page-id="<?= (int)$pageId ?>"
                                            data-section="<?= e($section) ?>"
                                        >
                                            Detach
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="media-actions">
                    <button class="btn btn-sm btn-primary" onclick="showAttachModal(<?= $mediaId ?>)" title="Attach to Page">
                        <i data-feather="link"></i> Attach
                    </button>
                    <button class="btn btn-sm btn-danger" data-media-id="<?= $mediaId ?>" onclick="deleteMedia(this.dataset.mediaId, <?= $item['usage_count'] ?? 0 ?>)" title="Delete">
                        <i data-feather="trash-2"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

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
                <input type="hidden" id="attach-page-id" required>
                <div id="attach-page-selected" class="muted">No page selected</div>
                <div id="attach-page-tree" class="page-tree">
                    <?php 
                    function renderPageHierarchy($pages, $parentId = 0) {
                        $output = '';
                        $childPages = array_filter($pages, function($p) use ($parentId) {
                            return ($p['parent_id'] ?? 0) == $parentId;
                        });
                        
                        usort($childPages, function($a, $b) {
                            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
                        });
                        
                        foreach ($childPages as $page) {
                            $label = e($page['slug'] ?? $page['title_ru'] ?? '');
                            $hasChildren = false;
                            foreach ($pages as $maybeChild) {
                                if (($maybeChild['parent_id'] ?? 0) == $page['id']) {
                                    $hasChildren = true;
                                    break;
                                }
                            }
                            
                            if ($hasChildren) {
                                $output .= '<details class="page-branch">';
                                $output .= '<summary>';
                                $output .= '<span class="page-label">' . $label . '</span>';
                                $output .= '<button type="button" class="page-pick" data-page-id="' . $page['id'] . '" data-page-label="' . $label . '">Select</button>';
                                $output .= '</summary>';
                                $output .= '<div class="page-children">';
                                $output .= renderPageHierarchy($pages, $page['id']);
                                $output .= '</div>';
                                $output .= '</details>';
                            } else {
                                $output .= '<button type="button" class="page-leaf page-pick" data-page-id="' . $page['id'] . '" data-page-label="' . $label . '">' . $label . '</button>';
                            }
                        }
                        return $output;
                    }
                    echo renderPageHierarchy($allPages);
                    ?>
                </div>
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

<style>
.page-tree {
    max-height: 260px;
    overflow: auto;
    padding: 8px 10px;
    border: 1px solid #e1e1e1;
    border-radius: 6px;
    background: #fafafa;
}
.page-branch summary {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 4px 0;
}
.page-label {
    flex: 1;
}
.page-children {
    padding-left: 16px;
    margin: 4px 0 8px;
}
.page-leaf {
    display: block;
    width: 100%;
    text-align: left;
    padding: 4px 6px;
    margin: 2px 0;
    border: 0;
    background: transparent;
    cursor: pointer;
}
.page-leaf:hover,
.page-pick:hover {
    background: #f0f0f0;
}
.page-pick {
    border: 0;
    background: #e9eef5;
    padding: 2px 6px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}
.muted {
    color: #777;
    margin-bottom: 6px;
}
.media-usage {
    color: #666;
    font-size: 12px;
    margin-top: 6px;
}
.media-usage-title {
    font-weight: 600;
    margin-bottom: 4px;
}
.media-usage-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 2px 0;
}
.media-usage-label {
    color: #555;
}
.media-detach {
    border: 0;
    background: #f4d6d6;
    color: #7a1f1f;
    padding: 2px 6px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
}
.media-detach:hover {
    background: #f0c4c4;
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
  const input = document.getElementById('upload-input');
  const files = input?.files;
  if (!files || !files.length) return;

  const formData = new FormData();
  Array.from(files).forEach(file => formData.append('files[]', file));

  fetch('<?= BASE_URL ?>/admin/media/bulk-upload', {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json'
    }
  })
  .then(async (r) => {
    const ct = r.headers.get('content-type') || '';
    const text = await r.text(); // read once

    // Non-2xx => show body snippet for debugging
    if (!r.ok) {
      console.error('Upload failed HTTP', r.status, r.statusText, 'CT:', ct, 'Body:', text);
      throw new Error(`HTTP ${r.status}. Response: ${text.slice(0, 300)}`);
    }

    // If server didn't return JSON, show what it returned
    if (!ct.includes('application/json')) {
      console.error('Expected JSON, got:', ct, text);
      throw new Error(`Expected JSON but got ${ct}. Body: ${text.slice(0, 300)}`);
    }

    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error('Invalid JSON:', text);
      throw new Error(`Invalid JSON. Body: ${text.slice(0, 300)}`);
    }

    return data;
  })
  .then((data) => {
    location.reload();
  })
  .catch((err) => {
    console.log(err);
    alert('Upload failed: ' + err.message);
  });
}

function resetAttachmentFields() {
    document.getElementById('attach-section').value = 'content';
    document.getElementById('attach-alt-ru').value = '';
    document.getElementById('attach-alt-uz').value = '';
    document.getElementById('attach-alignment').value = 'center';
    document.getElementById('attach-width').value = '';
}

function applyAttachmentFields(attachment) {
    if (!attachment) {
        resetAttachmentFields();
        return;
    }
    document.getElementById('attach-section').value = attachment.section || 'content';
    document.getElementById('attach-alt-ru').value = attachment.alt_text_ru || '';
    document.getElementById('attach-alt-uz').value = attachment.alt_text_uz || '';
    document.getElementById('attach-alignment').value = attachment.alignment || 'center';
    document.getElementById('attach-width').value = attachment.width || '';
}

function fetchAttachment(mediaId, pageId) {
    let url = '<?= BASE_URL ?>/admin/media/attachment?media_id=' + encodeURIComponent(mediaId);
    if (pageId) {
        url += '&page_id=' + encodeURIComponent(pageId);
    }
    return fetch(url, {
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .catch(() => null);
}

function showAttachModal(mediaId) {
    document.getElementById('attach-media-id').value = mediaId;
    document.getElementById('attach-page-id').value = '';
    document.getElementById('attach-page-selected').textContent = 'No page selected';
    resetAttachmentFields();
    document.getElementById('attach-modal').classList.add('active');
    
    fetchAttachment(mediaId)
        .then((data) => {
            if (!data || !data.success || !data.attachment) return;
            const attachment = data.attachment;
            applyAttachmentFields(attachment);
            if (attachment.page_id) {
                document.getElementById('attach-page-id').value = attachment.page_id;
                document.getElementById('attach-page-selected').textContent = attachment.page_slug || 'Selected page';
            }
        });
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
    // Debug: log what we're receiving
    console.log('Raw id:', id, 'Raw usageCount:', usageCount);
    
    // Parse ID safely
    const mediaId = parseInt(id, 10);
    
    console.log('Parsed mediaId:', mediaId, 'Type:', typeof mediaId);
    
    if (isNaN(mediaId) || mediaId <= 0) {
        console.error('Invalid mediaId detected:', mediaId);
        alert('Invalid media ID: ' + id);
        return;
    }
    
    const usage = parseInt(usageCount, 10) || 0;
    
    if (usage > 0) {
        if (!confirm(`This media is used on ${usage} page(s). Delete anyway?`)) {
            return;
        }
    } else {
        if (!confirm('Delete this media?')) return;
    }
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
    formData.append('id', mediaId);
    if (usage > 0) formData.append('force', '1');
    
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

document.addEventListener('click', (event) => {
    const target = event.target.closest('.page-pick');
    if (!target) return;
    event.preventDefault();
    event.stopPropagation();
    const pageId = target.getAttribute('data-page-id');
    const label = target.getAttribute('data-page-label') || '';
    document.getElementById('attach-page-id').value = pageId;
    document.getElementById('attach-page-selected').textContent = label || 'No page selected';
    const mediaId = document.getElementById('attach-media-id').value;
    if (mediaId && pageId) {
        fetchAttachment(mediaId, pageId)
            .then((data) => {
                if (!data || !data.success || !data.attachment) {
                    resetAttachmentFields();
                    return;
                }
                applyAttachmentFields(data.attachment);
            });
    }
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('.media-detach');
    if (!button) return;
    event.preventDefault();
    const mediaId = button.getAttribute('data-media-id');
    const pageId = button.getAttribute('data-page-id');
    const section = button.getAttribute('data-section');
    if (!mediaId || !pageId) return;
    if (!confirm('Detach this media from the page?')) return;
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
    formData.append('media_id', mediaId);
    formData.append('page_id', pageId);
    if (section) formData.append('section', section);
    
    fetch('<?= BASE_URL ?>/admin/media/detach', {
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
});
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
