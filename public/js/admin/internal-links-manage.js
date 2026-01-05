// FILE: public/js/admin/internal-links-manage.js

// Initialize sortable for drag and drop
document.addEventListener('DOMContentLoaded', function() {
    const sortableList = document.getElementById('sortable-links');
    
    if (sortableList && typeof Sortable !== 'undefined') {
        new Sortable(sortableList, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                saveOrder();
            }
        });
    }
});

// Save new order via AJAX
function saveOrder() {
    const items = document.querySelectorAll('#sortable-links .link-item');
    const linkIds = Array.from(items).map(item => item.getAttribute('data-id'));
    const pageId = document.querySelector('input[name="page_id"]').value;
    
    fetch(window.location.origin + '/admin/link-widget/reorder', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            page_id: pageId,
            link_ids: linkIds
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show brief success indicator
            showToast('Order saved');
        }
    })
    .catch(error => {
        console.error('Error saving order:', error);
        showToast('Error saving order', 'error');
    });
}

// Filter available pages
function filterPages() {
    const searchTerm = document.getElementById('search-pages').value.toLowerCase();
    const items = document.querySelectorAll('.available-page-item');
    
    items.forEach(item => {
        const title = item.getAttribute('data-title');
        if (title.includes(searchTerm)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

// Simple toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<i data-feather="${type === 'success' ? 'check' : 'alert-circle'}"></i> ${message}`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        padding: 12px 20px;
        border-radius: 6px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 8px;
    `;
    
    document.body.appendChild(toast);
    feather.replace();
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}
