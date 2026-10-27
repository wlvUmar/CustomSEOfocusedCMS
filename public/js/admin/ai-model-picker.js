// path: ./public/js/admin/ai-model-picker.js
// AI Studio model picker: a styled dropdown that drives the hidden #ai-model <select>.
// ai-studio.js keeps reading and writing the <select> (value, options, disabled), so this only mirrors it.
(function () {
    'use strict';

    var sel = document.getElementById('ai-model');
    var picker = document.getElementById('ai-model-picker');
    var btn = document.getElementById('ai-model-btn');
    var label = document.getElementById('ai-model-label');
    var menu = document.getElementById('ai-model-menu');
    if (!sel || !picker || !btn || !label || !menu) return;

    function render() {
        menu.innerHTML = '';
        Array.prototype.forEach.call(sel.options, function (o) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'ai-model__opt' + (o.selected ? ' is-active' : '');
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', o.selected ? 'true' : 'false');
            item.dataset.value = o.value;
            item.title = o.title || o.textContent;
            item.textContent = o.textContent;
            menu.appendChild(item);
        });
        var cur = sel.options[sel.selectedIndex];
        label.textContent = cur ? cur.textContent : 'Model';
        btn.disabled = sel.disabled;
    }

    function setOpen(open) {
        menu.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) render();
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(menu.hidden);
    });

    menu.addEventListener('click', function (e) {
        var item = e.target.closest('.ai-model__opt');
        if (!item) return;
        sel.value = item.dataset.value;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
        render();
        setOpen(false);
        btn.focus();
    });

    document.addEventListener('click', function (e) {
        if (!menu.hidden && !e.target.closest('#ai-model-picker')) setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !menu.hidden) { setOpen(false); btn.focus(); }
    });

    // ai-studio.js refills the options (live model list) and toggles `disabled` while a reply runs.
    new MutationObserver(render).observe(sel, { childList: true, attributes: true, attributeFilter: ['disabled'] });
    render();
})();
