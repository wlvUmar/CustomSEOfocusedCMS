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

// Auto-connect modal (index.php hides via .c-hidden{display:none!important} — toggle the class, inline style alone loses to !important)
function showAutoConnectModal() {
    var m = document.getElementById('auto-connect-modal');
    if (!m) return;
    m.classList.remove('c-hidden');
    m.style.display = 'flex';
    if (window.feather) feather.replace();
}

function closeAutoConnectModal() {
    var m = document.getElementById('auto-connect-modal');
    if (!m) return;
    m.classList.add('c-hidden');
    m.style.display = 'none';
}

// Bulk actions modal
function showBulkModal() {
    const selected = document.querySelectorAll('.page-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select at least one page');
        return;
    }
    
    // Collect selected IDs into staging input (no name= so it never submits as a string)
    const ids = Array.from(selected).map(cb => cb.value);
    document.getElementById('bulk-page-ids').value = JSON.stringify(ids);
    
    var m = document.getElementById('bulk-modal');
    if (!m) return;
    m.classList.remove('c-hidden');
    m.style.display = 'flex';
    if (window.feather) feather.replace();
}

function closeBulkModal() {
    var m = document.getElementById('bulk-modal');
    if (!m) return;
    m.classList.add('c-hidden');
    m.style.display = 'none';
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
    
    // Create hidden inputs for each ID (clear stale ones from a previous submit attempt)
    const form = document.querySelector('#bulk-modal form');
    form.querySelectorAll('input[name="page_ids[]"]').forEach(el => el.remove());
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
