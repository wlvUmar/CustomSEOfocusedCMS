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

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
