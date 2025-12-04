<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="page-header">
    <h1>Media Manager</h1>
    <button onclick="document.getElementById('file-input').click()" class="btn btn-primary">Upload Image</button>
</div>

<form id="upload-form" style="display:none;">
    <input type="file" id="file-input" accept="image/*" onchange="uploadFile()">
</form>

<div class="media-grid">
    <?php foreach ($media as $item): ?>
    <div class="media-item" data-id="<?= $item['id'] ?>">
        <img src="<?= UPLOAD_URL . e($item['filename']) ?>" alt="<?= e($item['original_name']) ?>">
        <div class="media-info">
            <p><?= e($item['original_name']) ?></p>
            <p class="media-meta"><?= number_format($item['file_size'] / 1024, 1) ?> KB</p>
            <div class="media-actions">
                <button onclick="copyUrl('<?= UPLOAD_URL . e($item['filename']) ?>')" class="btn btn-sm">Copy URL</button>
                <button onclick="deleteMedia(<?= $item['id'] ?>)" class="btn btn-sm btn-danger">Delete</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
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
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>