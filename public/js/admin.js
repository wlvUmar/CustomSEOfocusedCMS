// Tab switching
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

// Confirm delete
document.addEventListener('DOMContentLoaded', function() {
    const deleteForms = document.querySelectorAll('form[action*="delete"]');
    deleteForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const main = document.querySelector('.admin-main');

    toggleBtn.addEventListener('click', () => {
        if (window.innerWidth < 769) {
            // Mobile: toggle active class
            sidebar.classList.toggle('active');
        } else {
            // Desktop: collapse sidebar
            sidebar.classList.toggle('collapsed');
            main.classList.toggle('sidebar-collapsed');
        }
    });
});
