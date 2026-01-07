function switchTab(tab, event) {
    if (event && event.currentTarget) {
        event.preventDefault();
    }
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

    const clickedBtn = event?.currentTarget || document.querySelector(`.tab-btn[data-tab="${tab}"]`);
    if (clickedBtn) clickedBtn.classList.add('active');

    const content = document.getElementById('tab-' + tab);
    if (content) content.classList.add('active');
}

// Auto-dismiss alerts after 5 seconds
function setupAlertDismissal() {
    document.querySelectorAll('.alert').forEach(alert => {
        scheduleDismissal(alert);
    });
}

function scheduleDismissal(alert) {
    setTimeout(() => {
        alert.classList.add('fade-out');
        setTimeout(() => {
            alert.remove();
        }, 300);
    }, 5000);
}

// Global alert function for AJAX/Dynamic use
window.showAlert = function(message, type = 'info') {
    // defined types: success, error, warning, info
    // remove existing to prevent stacking too many
    const existing = document.querySelector('.floating-alerts .alert');
    if (existing && document.querySelectorAll('.alert').length > 2) {
        document.querySelector('.floating-alerts').firstChild.remove();
    }

    const container = document.getElementById('floating-alerts') || createAlertContainer();
    
    const div = document.createElement('div');
    div.className = `alert alert-${type}`;
    
    // Icon mapping
    let iconName = 'info';
    if (type === 'success') iconName = 'check-circle';
    if (type === 'error') iconName = 'alert-circle';
    if (type === 'warning') iconName = 'alert-triangle';
    
    div.innerHTML = `<i data-feather="${iconName}"></i> ${message}`;
    
    container.appendChild(div);
    
    // Initialize icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    scheduleDismissal(div);
};

function createAlertContainer() {
    const div = document.createElement('div');
    div.id = 'floating-alerts';
    div.className = 'floating-alerts';
    document.body.appendChild(div);
    return div;
}

document.addEventListener('DOMContentLoaded', () => {

    const wrapper = document.querySelector('.admin-wrapper');
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.sidebar-toggle');

    if (!wrapper || !sidebar || !toggle) return;

    // Setup alert dismissal
    setupAlertDismissal();

    document.querySelectorAll('form[action*="delete"]').forEach(form => {
        form.addEventListener('submit', e => {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });


    function updateIcon() {
        const icon = toggle.querySelector('svg');
        if (icon) {
            icon.style.transform = wrapper.classList.contains('sidebar-collapsed') ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }

    function adjustToggleForScrollbar() {
        const style = getComputedStyle(toggle);
        const baseRight = parseFloat(style.right) || 0;
        const scrollbarWidth =
            window.innerWidth - document.documentElement.clientWidth;
        toggle.style.right = `${baseRight - scrollbarWidth}px`;
    }


    toggle.addEventListener('click', e => {
        e.stopPropagation();
        wrapper.classList.toggle('sidebar-collapsed');
        updateIcon();
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('.sidebar') && !e.target.closest('.sidebar-toggle')) {
            if (!wrapper.classList.contains('sidebar-collapsed')) {
                wrapper.classList.add('sidebar-collapsed');
                updateIcon();
            }
        }
    });

    updateIcon();
    adjustToggleForScrollbar();
});
