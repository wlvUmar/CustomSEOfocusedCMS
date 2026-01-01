// Tab switching
// 

function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

// Confirm delete
document.addEventListener('DOMContentLoaded', function() {
    const deleteForms = document.querySelectorAll('form[action*="delete"]');
    const wrapper = document.querySelector('.admin-wrapper');
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.sidebar-toggle');
    deleteForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });


    function updateIcon() {
        toggle.innerHTML = wrapper.classList.contains('sidebar-collapsed')
            ? '<i data-feather="chevron-right"></i>'
            : '<i data-feather="chevron-left"></i>';
        feather.replace();
    }

    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        wrapper.classList.toggle('sidebar-collapsed');
        updateIcon();
    });

    document.addEventListener('click', (e) => {
        if (
            e.target.closest('.sidebar') ||
            e.target.closest('.sidebar-toggle')
        ) return;

        if (!wrapper.classList.contains('sidebar-collapsed')) {
            wrapper.classList.add('sidebar-collapsed');
            updateIcon();
        }
    });

    updateIcon();
});
});

