// FILE: public/js/admin/internal-links.js

// Toggle all checkboxes
function toggleAll(checkbox) {
    document.querySelectorAll('.page-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSelectedCount();
}

// Update selected count
function updateSelectedCount() {
    const count = document.querySelectorAll('.page-checkbox:checked').length;
    const countSpan = document.getElementById('selected-count');
    if (countSpan) {
        countSpan.textContent = count;
    }
}

// Add event listeners to checkboxes
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.page-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });
    updateSelectedCount();
});

// Auto-connect modal
function showAutoConnectModal() {
    document.getElementById('auto-connect-modal').style.display = 'flex';
    feather.replace();
}

function closeAutoConnectModal() {
    document.getElementById('auto-connect-modal').style.display = 'none';
}

// Bulk actions modal
function showBulkModal() {
    const selected = document.querySelectorAll('.page-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select at least one page');
        return;
    }
    
    // Collect selected IDs
    const ids = Array.from(selected).map(cb => cb.value);
    document.getElementById('bulk-page-ids').value = JSON.stringify(ids);
    
    document.getElementById('bulk-modal').style.display = 'flex';
    feather.replace();
}

function closeBulkModal() {
    document.getElementById('bulk-modal').style.display = 'none';
}

// Toggle target page field based on action
function toggleTargetField() {
    const action = document.getElementById('bulk-action').value;
    const targetGroup = document.getElementById('target-page-group');
    const targetSelect = document.getElementById('target-page');
    
    if (action === 'add-links' || action === 'remove-links') {
        targetGroup.style.display = 'block';
        targetSelect.required = true;
    } else {
        targetGroup.style.display = 'none';
        targetSelect.required = false;
    }
}

// Validate bulk form
function validateBulkForm() {
    const action = document.getElementById('bulk-action').value;
    const targetPage = document.getElementById('target-page').value;
    
    if (!action) {
        alert('Please select an action');
        return false;
    }
    
    if ((action === 'add-links' || action === 'remove-links') && !targetPage) {
        alert('Please select a target page');
        return false;
    }
    
    // Parse the page IDs from JSON
    const idsJson = document.getElementById('bulk-page-ids').value;
    const ids = JSON.parse(idsJson);
    
    // Create hidden inputs for each ID
    const form = document.querySelector('#bulk-modal form');
    ids.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'page_ids[]';
        input.value = id;
        form.appendChild(input);
    });
    
    return confirm(`Apply this action to ${ids.length} page(s)?`);
}

// Close modals on outside click
document.addEventListener('DOMContentLoaded', function() {
    const autoConnectModal = document.getElementById('auto-connect-modal');
    if (autoConnectModal) {
        autoConnectModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAutoConnectModal();
            }
        });
    }
    
    const bulkModal = document.getElementById('bulk-modal');
    if (bulkModal) {
        bulkModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeBulkModal();
            }
        });
    }
});
