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



// Call on load and on resize
window.addEventListener('resize', adjustToggleForScrollbar);
adjustToggleForScrollbar();
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
        const icon = toggle.querySelector('svg');
        if (icon) {
            icon.style.transform = wrapper.classList.contains('sidebar-collapsed') ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }

    function adjustToggleForScrollbar() {
        if (!toggle) return;
        const style = getComputedStyle(toggle);
        const currentRight = parseFloat(style.right);
        const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        toggle.style.right = `${currentRight + scrollbarWidth}px`;
    }
    
    toggle.addEventListener('click', e => {
        e.stopPropagation();
        wrapper.classList.toggle('sidebar-collapsed');
        updateIcon();
        adjustToggleForScrollbar();
    });

    // Click outside sidebar to collapse (only on mobile/tablet)
    document.addEventListener('click', e => {
        // Remove the screen size check
        if (!e.target.closest('.sidebar') && !e.target.closest('.sidebar-toggle')) {
            if (!wrapper.classList.contains('sidebar-collapsed')) {
                wrapper.classList.add('sidebar-collapsed');
                adjustToggleForScrollbar()
                updateIcon();
            }
        }
    });


    // Start collapsed on mobile, expanded on desktop
    
    updateIcon();
    adjustToggleForScrollbar();
    window.addEventListener('resize', adjustToggleForScrollbar);

});