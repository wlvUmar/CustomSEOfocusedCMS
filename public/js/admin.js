// ----------------------------
// Tabs
// ----------------------------
function switchTab(tab, event) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

    event.currentTarget.classList.add('active'); // safer than event.target
    const content = document.getElementById('tab-' + tab);
    if (content) content.classList.add('active');
}

// ----------------------------
// DOM Ready
// ----------------------------
document.addEventListener('DOMContentLoaded', () => {

    // Confirm before delete
    document.querySelectorAll('form[action*="delete"]').forEach(form => {
        form.addEventListener('submit', e => {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });

    const wrapper = document.querySelector('.admin-wrapper');
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.sidebar-toggle');

    if (!wrapper || !sidebar || !toggle) return;

    // Update toggle icon
    function updateIcon() {
        toggle.innerHTML = wrapper.classList.contains('sidebar-collapsed')
            ? '<i data-feather="chevron-right"></i>'
            : '<i data-feather="chevron-left"></i>';
        feather.replace();  // render feather icons
    }

    // Toggle sidebar
    toggle.addEventListener('click', () => {
        wrapper.classList.toggle('sidebar-collapsed');
        updateIcon();
    });

    // Click outside sidebar to collapse
    document.addEventListener('click', e => {
        if (!e.target.closest('.sidebar') && !e.target.closest('.sidebar-toggle')) {
            if (!wrapper.classList.contains('sidebar-collapsed')) {
                wrapper.classList.add('sidebar-collapsed');
                updateIcon();
            }
        }
    });

    // Initial state
    wrapper.classList.add('sidebar-collapsed');
    updateIcon();
});
