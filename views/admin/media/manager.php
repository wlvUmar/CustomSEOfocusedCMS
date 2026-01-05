<?php
// path: ./views/admin/media/manager.php
$pageName = 'media/manager';
require BASE_PATH . '/views/admin/layout/header.php';
?>

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

    <div class="media-search-sort">
        <input
            type="text"
            id="search-input"
            placeholder="Search media..."
            oninput="filterMedia()"
            class="media-search"
        >
        <select id="sort-select" onchange="sortMedia()" class="btn">
            <option value="newest">Newest First</option>
            <option value="oldest">Oldest First</option>
            <option value="name">Name A–Z</option>
            <option value="size">Size (Large–Small)</option>
        </select>
    </div>
</div>

<!-- Bulk Actions -->
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

<!-- Upload Forms -->
<form id="upload-form" hidden>
    <input type="file" id="file-input" accept="image/*" onchange="uploadFile()">
</form>

<form
    id="bulk-upload-form"
    method="POST"
    action="<?= BASE_URL ?>/admin/media/bulk-upload"
    enctype="multipart/form-data"
    hidden
>
    <input
        type="file"
        id="bulk-file-input"
        name="files[]"
        accept="image/*"
        multiple
        onchange="this.form.submit()"
    >
</form>

<!-- Media Grid -->
<div class="media-grid" id="media-grid">
    <?php foreach ($media as $item): ?>
        <div
            class="media-item"
            data-id="<?= $item['id'] ?>"
            data-filename="<?= e($item['filename']) ?>"
            data-name="<?= e($item['original_name']) ?>"
            data-size="<?= $item['file_size'] ?>"
            data-date="<?= strtotime($item['uploaded_at']) ?>"
            onclick="selectMedia(this, event)"
        >
            <input
                type="checkbox"
                class="media-checkbox"
                onclick="event.stopPropagation(); toggleMediaSelection(this)"
            >

            <div class="media-preview">
                <img
                    src="<?= UPLOAD_URL . e($item['filename']) ?>"
                    alt="<?= e($item['original_name']) ?>"
                    loading="lazy"
                >
            </div>

            <div class="media-info">
                <div class="media-name" title="<?= e($item['original_name']) ?>">
                    <?= e($item['original_name']) ?>
                </div>

                <div class="media-meta">
                    <?= number_format($item['file_size'] / 1024, 1) ?> KB
                    • <?= date('M d, Y', strtotime($item['uploaded_at'])) ?>
                </div>

                <div class="media-actions">
                    <button
                        class="btn btn-sm btn-primary"
                        title="Insert"
                        onclick="event.stopPropagation(); insertSingle(<?= $item['id'] ?>)"
                    >
                        <i data-feather="plus"></i>
                    </button>

                    <button
                        class="btn btn-sm"
                        title="Copy URL"
                        onclick="event.stopPropagation(); copyUrl('<?= UPLOAD_URL . e($item['filename']) ?>')"
                    >
                        <i data-feather="copy"></i>
                    </button>

                    <button
                        class="btn btn-sm btn-danger"
                        title="Delete"
                        onclick="event.stopPropagation(); deleteMedia(<?= $item['id'] ?>)"
                    >
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
            <button class="close-btn" onclick="closeInsertModal()">
                <i data-feather="x"></i>
            </button>
        </div>

        <div class="modal-body">
            <img id="insert-preview" class="insert-preview" alt="Preview">

            <div class="insert-options">
                <div>
                    <label><strong>Size:</strong></label>
                    <div class="size-options">
                        <div class="size-option active" data-size="full" onclick="selectSize(this)">
                            <i data-feather="maximize"></i>
                            <div>Full Size</div>
                        </div>
                        <div class="size-option" data-size="medium" onclick="selectSize(this)">
                            <i data-feather="square"></i>
                            <div>Medium</div>
                        </div>
                        <div class="size-option" data-size="thumbnail" onclick="selectSize(this)">
                            <i data-feather="minimize"></i>
                            <div>Thumbnail</div>
                        </div>
                    </div>
                </div>

                <div>
                    <label><strong>Image URL:</strong></label>
                    <div class="copy-field">
                        <input type="text" id="image-url" readonly>
                        <button class="btn" onclick="copyField('image-url')">
                            <i data-feather="copy"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label><strong>HTML Code:</strong></label>
                    <div class="copy-field">
                        <input type="text" id="html-code" readonly>
                        <button class="btn" onclick="copyField('html-code')">
                            <i data-feather="copy"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label><strong>Markdown:</strong></label>
                    <div class="copy-field">
                        <input type="text" id="markdown-code" readonly>
                        <button class="btn" onclick="copyField('markdown-code')">
                            <i data-feather="copy"></i>
                        </button>
                    </div>
                </div>
            </div>

            <button class="btn btn-primary insert-confirm" onclick="copyField('html-code')">
                <i data-feather="clipboard"></i> Copy HTML & Close
            </button>
        </div>
    </div>
</div>

<script>
    const BASE_URL = '<?= BASE_URL ?>';
    const UPLOAD_URL = '<?= UPLOAD_URL ?>';
    
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
        
        fetch(BASE_URL + '/admin/media/upload', {
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
        
        fetch(BASE_URL + '/admin/media/delete', {
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
        const btn = document.getElementById('select-mode-btn');
        
        if (selectMode) {
            btn.classList.add('btn-primary');
            document.querySelectorAll('.media-checkbox').forEach(cb => {
                cb.style.display = 'block';
            });
        } else {
            btn.classList.remove('btn-primary');
            document.querySelectorAll('.media-checkbox').forEach(cb => {
                cb.style.display = 'none';
            });
            clearSelection();
        }
        
        feather.replace();
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
        
        if (event.target.closest('button')) return;
        
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
        document.querySelectorAll('.media-checkbox').forEach(cb => {
            cb.checked = false;
            cb.style.display = 'none';
        });
        document.querySelectorAll('.media-item').forEach(item => {
            item.classList.remove('selected');
        });
        updateBulkActions();
    }

    function deleteSelected() {
        if (!confirm(`Delete ${selectedItems.size} selected images?`)) return;
        
        const promises = Array.from(selectedItems).map(id => {
            const formData = new FormData();
            formData.append('id', id);
            return fetch(BASE_URL + '/admin/media/delete', {
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
        
        currentInsertUrl = UPLOAD_URL + filename;
        currentInsertName = name;
        
        showInsertModal();
    }

    function insertSelected() {
        if (selectedItems.size === 0) {
            alert('Please select images first');
            return;
        }
        
        let htmlCode = '';
        selectedItems.forEach(id => {
            const item = document.querySelector(`[data-id="${id}"]`);
            const filename = item.dataset.filename;
            const name = item.dataset.name;
            const url = UPLOAD_URL + filename;
            htmlCode += `<img src="${url}" alt="${name}">\n`;
        });
        
        navigator.clipboard.writeText(htmlCode).then(() => {
            alert(`HTML code for ${selectedItems.size} images copied to clipboard!`);
        });
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
        let sizeStyle = '';
        
        switch(size) {
            case 'medium':
                sizeStyle = ' style="max-width: 600px;"';
                break;
            case 'thumbnail':
                sizeStyle = ' style="max-width: 300px;"';
                break;
            default:
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

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.media-checkbox').forEach(cb => {
            cb.style.display = 'none';
        });
        feather.replace();
    });

    document.getElementById('insert-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeInsertModal();
        }
    });
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
