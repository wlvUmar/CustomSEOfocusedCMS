// FILE: public/js/admin/internal-links-manage.js

// Initialize sortable for drag and drop
document.addEventListener('DOMContentLoaded', function() {
    const sortableList = document.getElementById('sortable-links');
    
    if (sortableList && typeof Sortable !== 'undefined') {
        // support both templates: .drag-handle (internal_links) and .link-drag-handle (link_widget)
        const handleEl = sortableList.querySelector('.drag-handle, .link-drag-handle');
        new Sortable(sortableList, {
            animation: 150,
            handle: handleEl ? '.drag-handle, .link-drag-handle' : undefined,
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                saveOrder();
            }
        });
    }
});

// Save new order via AJAX — instant save on drag end
function saveOrder() {
    const items = document.querySelectorAll('#sortable-links .link-item');
    const linkIds = Array.from(items).map(item => item.getAttribute('data-id'));
    // page_id from data attribute / URL is most reliable, fallback to hidden input
    let pageId = document.getElementById('sortable-links')?.getAttribute('data-page-id') || null;
    if (!pageId) {
        const m = window.location.pathname.match(/\/manage\/(\d+)/);
        if (m) pageId = m[1];
    }
    if (!pageId) {
        const el = document.querySelector('input[name="page_id"]');
        if (el) pageId = el.value;
    }
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken || document.querySelector('input[name="csrf_token"]')?.value || '';
    // optimistic UI: add saving state
    const list = document.getElementById('sortable-links');
    if (list) list.style.opacity = '0.6';
    const fd = new FormData();
    fd.append('csrf_token', csrf);
    fd.append('page_id', pageId || '');
    linkIds.forEach(id => fd.append('link_ids[]', id));
    const reorderUrl = list?.getAttribute('data-reorder-url') || ((window.baseUrl || window.location.origin) + '/admin/link-widget/reorder');
    
    fetch(reorderUrl, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-Token': csrf
        },
        body: fd
    })
    .then(async response => {
        const ct = response.headers.get('content-type') || '';
        const text = await response.text();
        let data = {};
        try { data = ct.includes('application/json') ? JSON.parse(text) : {success: response.ok}; } catch(e){ data = {success:false, message: text.slice(0,200)}; }
        if (!response.ok || data.success === false) throw new Error(data.message || 'HTTP '+response.status);
        return data;
    })
    .then(data => {
        const list2 = document.getElementById('sortable-links');
        if (list2) list2.style.opacity = '1';
        showToast('Order saved ✓');
    })
    .catch(error => {
        console.error('Error saving order:', error);
        const list2 = document.getElementById('sortable-links');
        if (list2) list2.style.opacity = '1';
        showToast('Error saving order: ' + (error.message||error), 'error');
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
