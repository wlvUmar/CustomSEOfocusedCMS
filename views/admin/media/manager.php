<?php 
// path: ./views/admin/media/manager.php
// IMPLEMENTATION: Replace the existing media/manager.php

require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<style>
.media-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.media-filters {
    display: flex;
    gap: 10px;
    align-items: center;
}

.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
}

.media-item {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.2s;
    cursor: pointer;
    position: relative;
}

.media-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.media-item.selected {
    outline: 3px solid #3b82f6;
    outline-offset: 2px;
}

.media-preview {
    width: 100%;
    height: 180px;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}

.media-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.media-checkbox {
    position: absolute;
    top: 10px;
    left: 10px;
    width: 20px;
    height: 20px;
    cursor: pointer;
    z-index: 10;
}

.media-info {
    padding: 12px;
}

.media-name {
    font-size: 0.85em;
    font-weight: 600;
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #303034;
}

.media-meta {
    font-size: 0.75em;
    color: #6b7280;
    margin-bottom: 8px;
}

.media-actions {
    display: flex;
    gap: 5px;
}

.media-actions button {
    flex: 1;
    padding: 6px;
    font-size: 0.8em;
}

.bulk-actions {
    display: none;
    background: #eff6ff;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    border-left: 3px solid #3b82f6;
}

.bulk-actions.active {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 8px;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    width: 90%;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
}

.insert-preview {
    max-width: 100%;
    border-radius: 6px;
    margin-bottom: 15px;
}

.insert-options {
    display: grid;
    gap: 15px;
}

.size-options {
    display: flex;
    gap: 10px;
}

.size-option {
    flex: 1;
    padding: 10px;
    border: 2px solid #e5e7eb;
    border-radius: 6px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
}

.size-option:hover,
.size-option.active {
    border-color: #3b82f6;
    background: #eff6ff;
}

.copy-field {
    display: flex;
    gap: 8px;
}

.copy-field input {
    flex: 1;
    padding: 8px;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.9em;
}
</style>

<div class="page-header">
    <h1><i data-feather="image"></i> Media Manager</h1>
</div>

<div class="media-controls">
    <div class="media-filters">
        <button onclick="document.getElementById('file-input').click()" class="btn btn-primary">
            <i data-feather="upload"></i> Upload Single
        </button>
        <button onclick="document.getElementById('bulk-file-input').click()" class="btn">
            <i data-feather="upload-cloud"></i> Bulk Upload
        </button>
        <button onclick="toggleSelectMode()" id="select-mode-btn" class="btn">
            <i data-feather="check-square"></i> Select Mode
        </button>
    </div>
    
    <div style="display: flex; gap: 10px; align-items: center;">
        <input type="text" id="search-input" placeholder="Search media..." 
               oninput="filterMedia()"
               style="padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px; width: 200px;">
        <select id="sort-select" onchange="sortMedia()" class="btn">
            <option value="newest">Newest First</option>
            <option value="oldest">Oldest First</option>
            <option value="name">Name A-Z</option>
            <option value="size">Size (Large-Small)</option>
        </select>
    </div>
</div>

<!-- Bulk Actions Bar -->
<div class="bulk-actions" id="bulk-actions">
    <div>
        <strong id="selected-count">0</strong> items selected
    </div>
    <div class="btn-group">
        <button onclick="insertSelected()" class="btn btn-primary">
            <i data-feather="plus"></i> Insert Selected
        </button>
        <button onclick="deleteSelected()" class="btn btn-danger">
            <i data-feather="trash-2"></i> Delete Selected
        </button>
        <button onclick="clearSelection()" class="btn">
            <i data-feather="x"></i> Clear
        </button>
    </div>
</div>

<!-- Upload Forms (Hidden) -->
<form id="upload-form" style="display:none;">
    <input type="file" id="file-input" accept="image/*" onchange="uploadFile()">
</form>

<form id="bulk-upload-form" method="POST" action="<?= BASE_URL ?>/admin/media/bulk-upload" 
      enctype="multipart/form-data" style="display:none;">
    <input type="file" id="bulk-file-input" name="files[]" accept="image/*" 
           multiple onchange="this.form.submit()">
</form>

<!-- Media Grid -->
<div class="media-grid" id="media-grid">
    <?php foreach ($media as $item): ?>
    <div class="media-item" 
         data-id="<?= $item['id'] ?>"
         data-filename="<?= e($item['filename']) ?>"
         data-name="<?= e($item['original_name']) ?>"
         data-size="<?= $item['file_size'] ?>"
         data-date="<?= strtotime($item['uploaded_at']) ?>"
         onclick="selectMedia(this, event)">
        
        <input type="checkbox" class="media-checkbox" onclick="event.stopPropagation(); toggleMediaSelection(this)">
        
        <div class="media-preview">
            <img src="<?= UPLOAD_URL . e($item['filename']) ?>" 
                 alt="<?= e($item['original_name']) ?>"
                 loading="lazy">
        </div>
        
        <div class="media-info">
            <div class="media-name" title="<?= e($item['original_name']) ?>">
                <?= e($item['original_name']) ?>
            </div>
            <div class="media-meta">
                <?= number_format($item['file_size'] / 1024, 1) ?> KB
                <span style="margin: 0 5px;">•</span>
                <?= date('M d, Y', strtotime($item['uploaded_at'])) ?>
            </div>
            <div class="media-actions">
                <button onclick="event.stopPropagation(); insertSingle(<?= $item['id'] ?>)" 
                        class="btn btn-sm btn-primary" title="Insert">
                    <i data-feather="plus"></i>
                </button>
                <button onclick="event.stopPropagation(); copyUrl('<?= UPLOAD_URL . e($item['filename']) ?>')" 
                        class="btn btn-sm" title="Copy URL">
                    <i data-feather="copy"></i>
                </button>
                <button onclick="event.stopPropagation(); deleteMedia(<?= $item['id'] ?>)" 
                        class="btn btn-sm btn-danger" title="Delete">
                    <i data-feather="trash-2"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Insert Modal -->
<div class="modal" id="insert-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i data-feather="plus-circle"></i> Insert Image</h2>
            <button onclick="closeInsertModal()" class="close-btn">
                <i data-feather="x"></i>
            </button>
        </div>
        <div class="modal-body">
            <img id="insert-preview" class="insert-preview" src="" alt="Preview">
            
            <div class="insert-options">
                <div>
                    <label><strong>Size:</strong></label>
                    <div class="size-options">
                        <div class="size-option active" data-size="full" onclick="selectSize(this)">
                            <div><i data-feather="maximize"></i></div>
                            <div style="font-size: 0.85em; margin-top: 5px;">Full Size</div>
                        </div>
                        <div class="size-option" data-size="medium" onclick="selectSize(this)">
                            <div><i data-feather="square"></i></div>
                            <div style="font-size: 0.85em; margin-top: 5px;">Medium</div>
                        </div>
                        <div class="size-option" data-size="thumbnail" onclick="selectSize(this)">
                            <div><i data-feather="minimize"></i></div>
                            <div style="font-size: 0.85em; margin-top: 5px;">Thumbnail</div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label><strong>Image URL:</strong></label>
                    <div class="copy-field">
                        <input type="text" id="image-url" readonly>
                        <button onclick="copyField('image-url')" class="btn">
                            <i data-feather="copy"></i>
                        </button>
                    </div>
                </div>
                
                <div>
                    <label><strong>HTML Code:</strong></label>
                    <div class="copy-field">
                        <input type="text" id="html-code" readonly>
                        <button onclick="copyField('html-code')" class="btn">
                            <i data-feather="copy"></i>
                        </button>
                    </div>
                </div>
                
                <div>
                    <label><strong>Markdown:</strong></label>
                    <div class="copy-field">
                        <input type="text" id="markdown-code" readonly>
                        <button onclick="copyField('markdown-code')" class="btn">
                            <i data-feather="copy"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <button onclick="copyField('html-code')" class="btn btn-primary" style="width: 100%;">
                    <i data-feather="clipboard"></i> Copy HTML & Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let selectMode = false;
let selectedItems = new Set();
let currentInsertUrl = '';
let currentInsertName = '';

function uploadFile() {
    const input = document.getElementById('file-input');
    const file = input.files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('file', file);
    
    fetch('<?= BASE_URL ?>/admin/media/upload', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(e => alert('Upload failed'));
}

function deleteMedia(id) {
    if (!confirm('Delete this image?')) return;
    
    const formData = new FormData();
    formData.append('id', id);
    
    fetch('<?= BASE_URL ?>/admin/media/delete', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelector(`[data-id="${id}"]`).remove();
        } else {
            alert('Delete failed');
        }
    });
}

function copyUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert('URL copied to clipboard');
    });
}

function toggleSelectMode() {
    selectMode = !selectMode;
    document.getElementById('select-mode-btn').classList.toggle('btn-primary');
    
    if (!selectMode) {
        clearSelection();
    }
}

function toggleMediaSelection(checkbox) {
    const item = checkbox.closest('.media-item');
    const id = item.dataset.id;
    
    if (checkbox.checked) {
        selectedItems.add(id);
        item.classList.add('selected');
    } else {
        selectedItems.delete(id);
        item.classList.remove('selected');
    }
    
    updateBulkActions();
}

function selectMedia(element, event) {
    if (!selectMode) return;
    
    const checkbox = element.querySelector('.media-checkbox');
    checkbox.checked = !checkbox.checked;
    toggleMediaSelection(checkbox);
}

function updateBulkActions() {
    const bulkActions = document.getElementById('bulk-actions');
    const count = document.getElementById('selected-count');
    
    count.textContent = selectedItems.size;
    
    if (selectedItems.size > 0) {
        bulkActions.classList.add('active');
    } else {
        bulkActions.classList.remove('active');
    }
}

function clearSelection() {
    selectedItems.clear();
    document.querySelectorAll('.media-checkbox').forEach(cb => cb.checked = false);
    document.querySelectorAll('.media-item').forEach(item => item.classList.remove('selected'));
    updateBulkActions();
}

function deleteSelected() {
    if (!confirm(`Delete ${selectedItems.size} selected images?`)) return;
    
    const promises = Array.from(selectedItems).map(id => {
        const formData = new FormData();
        formData.append('id', id);
        return fetch('<?= BASE_URL ?>/admin/media/delete', {
            method: 'POST',
            body: formData
        });
    });
    
    Promise.all(promises).then(() => {
        location.reload();
    });
}

function insertSingle(id) {
    const item = document.querySelector(`[data-id="${id}"]`);
    const filename = item.dataset.filename;
    const name = item.dataset.name;
    
    currentInsertUrl = '<?= UPLOAD_URL ?>' + filename;
    currentInsertName = name;
    
    showInsertModal();
}

function insertSelected() {
    alert('This will insert ' + selectedItems.size + ' images. Feature coming soon!');
}

function showInsertModal() {
    document.getElementById('insert-preview').src = currentInsertUrl;
    updateInsertCodes('full');
    document.getElementById('insert-modal').classList.add('active');
    feather.replace();
}

function closeInsertModal() {
    document.getElementById('insert-modal').classList.remove('active');
}

function selectSize(element) {
    document.querySelectorAll('.size-option').forEach(opt => opt.classList.remove('active'));
    element.classList.add('active');
    
    const size = element.dataset.size;
    updateInsertCodes(size);
}

function updateInsertCodes(size) {
    let sizeClass = '';
    let sizeStyle = '';
    
    switch(size) {
        case 'medium':
            sizeClass = ' class="img-medium"';
            sizeStyle = ' style="max-width: 600px;"';
            break;
        case 'thumbnail':
            sizeClass = ' class="img-thumbnail"';
            sizeStyle = ' style="max-width: 300px;"';
            break;
        default:
            sizeClass = '';
            sizeStyle = '';
    }
    
    document.getElementById('image-url').value = currentInsertUrl;
    document.getElementById('html-code').value = `<img src="${currentInsertUrl}" alt="${currentInsertName}"${sizeStyle}>`;
    document.getElementById('markdown-code').value = `![${currentInsertName}](${currentInsertUrl})`;
}

function copyField(fieldId) {
    const field = document.getElementById(fieldId);
    field.select();
    navigator.clipboard.writeText(field.value).then(() => {
        alert('Copied to clipboard!');
        if (fieldId === 'html-code') {
            closeInsertModal();
        }
    });
}

function filterMedia() {
    const search = document.getElementById('search-input').value.toLowerCase();
    document.querySelectorAll('.media-item').forEach(item => {
        const name = item.dataset.name.toLowerCase();
        item.style.display = name.includes(search) ? '' : 'none';
    });
}

function sortMedia() {
    const sortBy = document.getElementById('sort-select').value;
    const grid = document.getElementById('media-grid');
    const items = Array.from(grid.children);
    
    items.sort((a, b) => {
        switch(sortBy) {
            case 'newest':
                return parseInt(b.dataset.date) - parseInt(a.dataset.date);
            case 'oldest':
                return parseInt(a.dataset.date) - parseInt(b.dataset.date);
            case 'name':
                return a.dataset.name.localeCompare(b.dataset.name);
            case 'size':
                return parseInt(b.dataset.size) - parseInt(a.dataset.size);
        }
    });
    
    items.forEach(item => grid.appendChild(item));
}

// Close modal on outside click
document.getElementById('insert-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeInsertModal();
    }
});
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>