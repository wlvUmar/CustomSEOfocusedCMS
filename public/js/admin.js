// ----------------------------
// Tabs
// ----------------------------
function switchTab(tab, event) {
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
        if (!toggle) return; // safety check
        const icon = toggle.querySelector('svg');
        if (!icon) return; // safety if SVG not yet in DOM

        // Add smooth transition if not already
        if (!icon.style.transition) {
            icon.style.transition = 'transform 0.3s ease';
        }

        // Rotate based on wrapper collapsed state
        const rotated = wrapper.classList.contains('sidebar-collapsed') ? 180 : 0;
        icon.style.transform = `rotate(${rotated}deg)`;
    }



    toggle.addEventListener('click', e => {
        e.stopPropagation();
        wrapper.classList.toggle('sidebar-collapsed');
        updateIcon();
    });

    // Click outside sidebar to collapse (only on mobile/tablet)
    document.addEventListener('click', e => {
        // Remove the screen size check
        if (!e.target.closest('.sidebar') && !e.target.closest('.sidebar-toggle')) {
            if (!wrapper.classList.contains('sidebar-collapsed')) {
                wrapper.classList.add('sidebar-collapsed');
                updateIcon();
            }
        }
    });


    // Start collapsed on mobile, expanded on desktop
    
    updateIcon();

});