// Extracted from views/admin/internal_links/index.php — hierarchy filter/toggle.
function filterPages(type) {
    const rows = document.querySelectorAll('.page-row');
    const buttons = document.querySelectorAll('.filter-btn');
    
    buttons.forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-filter="${type}"]`).classList.add('active');
    
    rows.forEach(row => {
        let show = false;
        
        switch(type) {
            case 'all':
                show = true;
                break;
            case 'root':
                show = row.dataset.isRoot === '1';
                break;
            case 'children':
                show = row.dataset.isChild === '1';
                break;
            case 'orphan':
                show = row.dataset.isOrphan === '1';
                break;
        }
        
        row.style.display = show ? '' : 'none';
        
        // Reset hierarchy visibility when filtering
        if (type !== 'all') {
            row.classList.remove('hidden');
        }
    });
}

function toggleChildren(pageId) {
    const btn = document.querySelector(`[data-page-id="${pageId}"] .toggle-children`);
    const allRows = document.querySelectorAll('.page-row');
    
    let foundCurrent = false;
    let currentDepth = -1;
    
    for (let row of allRows) {
        if (row.dataset.pageId == pageId) {
            foundCurrent = true;
            currentDepth = parseInt(row.className.match(/depth-(\d+)/)[1]);
            continue;
        }
        
        if (foundCurrent) {
            const rowDepthMatch = row.className.match(/depth-(\d+)/);
            if (!rowDepthMatch) continue;
            
            const rowDepth = parseInt(rowDepthMatch[1]);
            
            if (rowDepth <= currentDepth) {
                break;
            }
            
            // If we are collapsing, hide all descendants
            if (!btn.classList.contains('collapsed')) {
                row.classList.add('hidden');
                // Also collapse the children buttons of descendants to keep state consistent
                const childBtn = row.querySelector('.toggle-children');
                if (childBtn) childBtn.classList.add('collapsed');
            } else {
                // If we are expanding, only show direct children (depth + 1)
                // BUT we need to be careful. If we just show depth+1, we might leave their children hidden (correct).
                if (rowDepth === currentDepth + 1) {
                    row.classList.remove('hidden');
                    // Ensure the direct child is set to collapsed initially
                     const childBtn = row.querySelector('.toggle-children');
                     if (childBtn) childBtn.classList.add('collapsed');
                }
            }
        }
    }
    
    btn.classList.toggle('collapsed');
}

function expandAll() {
    document.querySelectorAll('.page-row').forEach(row => {
        row.classList.remove('hidden');
    });
    document.querySelectorAll('.toggle-children').forEach(btn => {
        btn.classList.remove('collapsed');
    });
}

function collapseAll() {
    document.querySelectorAll('.page-row').forEach(row => {
        const depthMatch = row.className.match(/depth-(\d+)/);
        if (depthMatch && parseInt(depthMatch[1]) > 0) {
            row.classList.add('hidden');
        }
    });
    document.querySelectorAll('.toggle-children').forEach(btn => {
        btn.classList.add('collapsed');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const type = this.dataset.filter;
            filterPages(type);
        });
    });

    // Replaces inline onclick=/onchange=/onsubmit= in internal_links/index.php.
    document.querySelectorAll('[data-action]').forEach(btn => {
        btn.addEventListener('click', function() {
            const action = this.dataset.action;
            if (action === 'expand' && typeof expandAll === 'function') expandAll();
            else if (action === 'collapse' && typeof collapseAll === 'function') collapseAll();
            else if (action === 'auto' && typeof showAutoConnectModal === 'function') showAutoConnectModal();
            else if (action === 'bulk' && typeof showBulkModal === 'function') showBulkModal();
            else if (action === 'close-auto' && typeof closeAutoConnectModal === 'function') closeAutoConnectModal();
            else if (action === 'close-bulk' && typeof closeBulkModal === 'function') closeBulkModal();
        });
    });

    const selectAll = document.getElementById('select-all');
    if (selectAll) selectAll.addEventListener('change', function() {
        if (typeof toggleAll === 'function') toggleAll(this);
    });

    document.querySelectorAll('[data-page-toggle]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (typeof toggleChildren === 'function') toggleChildren(this.dataset.pageToggle);
        });
    });

    const bulkAction = document.getElementById('bulk-action');
    if (bulkAction) bulkAction.addEventListener('change', function() {
        if (typeof toggleTargetField === 'function') toggleTargetField();
    });

    const bulkForm = document.getElementById('bulkForm');
    if (bulkForm) bulkForm.addEventListener('submit', function(e) {
        if (typeof validateBulkForm === 'function' && !validateBulkForm()) e.preventDefault();
    });

    // Hierarchy indent arrives via data-indent (keeps template free of style="").
    document.querySelectorAll('[data-indent]').forEach(el => {
        el.style.paddingLeft = el.dataset.indent + 'px';
    });
});
