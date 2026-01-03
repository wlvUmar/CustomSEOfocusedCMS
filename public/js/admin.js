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


document.addEventListener('DOMContentLoaded', () => {

    const wrapper = document.querySelector('.admin-wrapper');
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.sidebar-toggle');

    if (!wrapper || !sidebar || !toggle) return;


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
        toggle.style.right = `${baseRight + scrollbarWidth}px`;
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
