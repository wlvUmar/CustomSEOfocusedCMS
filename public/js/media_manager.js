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

    function deleteMedia(id, usageCount) {
        console.log('deleteMedia called with id:', id, 'usageCount:', usageCount);
        
        // Convert to number and validate
        id = parseInt(id, 10);
        if (isNaN(id) || id <= 0) {
            alert('Error: Invalid media ID');
            return;
        }
        
        usageCount = parseInt(usageCount, 10) || 0;
        
        if (usageCount > 0) {
            if (!confirm(`This media is used on ${usageCount} page(s). Delete anyway?`)) {
                return;
            }
        } else {
            if (!confirm('Delete this image?')) return;
        }
        
        const formData = new FormData();
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
                alert('Delete failed: ' + data.message);
            }
        })
        .catch(e => alert('Error: ' + e.message));
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
            // Show all checkboxes
            document.querySelectorAll('.media-checkbox').forEach(cb => {
                cb.style.display = 'block';
            });
        } else {
            btn.classList.remove('btn-primary');
            // Hide checkboxes
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
        
        // Don't trigger if clicking on buttons
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
        if (selectedItems.size === 0) {
            alert('Please select images first');
            return;
        }
        
        let htmlCode = '';
        selectedItems.forEach(id => {
            const item = document.querySelector(`[data-id="${id}"]`);
            const filename = item.dataset.filename;
            const name = item.dataset.name;
            const url = '<?= UPLOAD_URL ?>' + filename;
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

    // Initialize - hide checkboxes by default
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.media-checkbox').forEach(cb => {
            cb.style.display = 'none';
        });
        feather.replace();
    });

    // Close modal on outside click
    document.getElementById('insert-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeInsertModal();
        }
    });