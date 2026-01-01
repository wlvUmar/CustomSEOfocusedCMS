// ----------------------------
// Tabs
// ----------------------------
function switchTab(tab, event) {
    // Handle both direct calls and event-based calls
    if (event && event.currentTarget) {
        event.preventDefault();
    }
    
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

    // Find and activate the button that was clicked
    const clickedBtn = event && event.currentTarget ? event.currentTarget : 
                       document.querySelector(`[onclick*="switchTab('${tab}')"]`);
    
    if (clickedBtn) {
        clickedBtn.classList.add('active');
    }
    
    const content = document.getElementById('tab-' + tab);
    if (content) content.classList.add('active');
}

// ----------------------------
// DOM Ready
// ----------------------------
document.addEventListener('DOMContentLoaded', () => {

    const wrapper = document.querySelector('.admin-wrapper');
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.sidebar-toggle');

    if (!wrapper || !sidebar || !toggle) return;

    // ----------------------------
    // Delete Confirmation
    // ----------------------------
    document.querySelectorAll('form[action*="delete"]').forEach(form => {
        form.addEventListener('submit', e => {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });

    // ----------------------------
    // Sidebar Toggle
    // ----------------------------
    function updateIcon() {
        // Just swap the chevron direction - CSS handles rotation
        toggle.innerHTML = '<i data-feather="chevron-left"></i>';
        feather.replace();
    }

    // Toggle click
    toggle.addEventListener('click', e => {
        e.stopPropagation();
        wrapper.classList.toggle('sidebar-collapsed');
        updateIcon();
    });

    // Click outside sidebar to collapse (only on mobile/tablet)
    document.addEventListener('click', e => {
        if (window.innerWidth <= 1024) { // Only on smaller screens
            if (!e.target.closest('.sidebar') && !e.target.closest('.sidebar-toggle')) {
                if (!wrapper.classList.contains('sidebar-collapsed')) {
                    wrapper.classList.add('sidebar-collapsed');
                    updateIcon();
                }
            }
        }
    });

    // Start collapsed on mobile, expanded on desktop
    if (window.innerWidth <= 1024) {
        wrapper.classList.add('sidebar-collapsed');
    } else {
        wrapper.classList.remove('sidebar-collapsed');
    }
    
    updateIcon();

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (window.innerWidth > 1024) {
                wrapper.classList.remove('sidebar-collapsed');
            } else {
                wrapper.classList.add('sidebar-collapsed');
            }
            updateIcon();
        }, 250);
    });
});