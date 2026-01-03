// FILE: public/js/admin/rotation-manage.js
// Moved from inline scripts in views/admin/rotations/manage.php

// Preview modal functions
function showPreviewModal() {
    document.getElementById('preview-modal').style.display = 'flex';
    feather.replace();
}

function closePreviewModal() {
    document.getElementById('preview-modal').style.display = 'none';
}

function openPreview() {
    const month = document.getElementById('preview-month').value;
    const lang = document.getElementById('preview-lang').value;
    const pageId = document.querySelector('[name="page_id"]').value;
    const baseUrl = document.querySelector('input[name="page_id"]').closest('form').action.split('/admin/')[0];
    
    const url = baseUrl + '/admin/preview/' + pageId + '?month=' + month + '&lang=' + lang;
    window.open(url, '_blank', 'width=1200,height=800');
    closePreviewModal();
}

// Upload modal functions
function showUploadModal() {
    document.getElementById('upload-modal').style.display = 'flex';
    feather.replace();
}

function closeUploadModal() {
    document.getElementById('upload-modal').style.display = 'none';
}

// Bulk action functions
function toggleAll(checkbox) {
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

function confirmBulk(action) {
    const checked = document.querySelectorAll('.row-checkbox:checked').length;
    if (checked === 0) {
        alert('Please select at least one item');
        return false;
    }
    
    const messages = {
        'activate': `Activate ${checked} rotation(s)?`,
        'deactivate': `Deactivate ${checked} rotation(s)?`,
        'delete': `Delete ${checked} rotation(s)? This cannot be undone!`
    };
    
    return confirm(messages[action] || 'Continue?');
}

// Clone modal functions
function showCloneModal(sourceId, sourceName) {
    document.getElementById('clone-source-id').value = sourceId;
    document.getElementById('clone-source-name').textContent = sourceName;
    document.getElementById('clone-modal').style.display = 'flex';
    feather.replace();
}

function closeCloneModal() {
    document.getElementById('clone-modal').style.display = 'none';
}

// Close modals on outside click
document.addEventListener('DOMContentLoaded', function() {
    const previewModal = document.getElementById('preview-modal');
    if (previewModal) {
        previewModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closePreviewModal();
            }
        });
    }
    
    const cloneModal = document.getElementById('clone-modal');
    if (cloneModal) {
        cloneModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeCloneModal();
            }
        });
    }
    
    const uploadModal = document.getElementById('upload-modal');
    if (uploadModal) {
        uploadModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeUploadModal();
            }
        });
    }
});