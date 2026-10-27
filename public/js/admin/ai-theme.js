// path: ./public/js/admin/ai-theme.js
// AI Studio theme: dark (default) or light. The choice is remembered in this browser only.
(function () {
    'use strict';

    var root = document.querySelector('.ai-studio--app');
    var btn = document.getElementById('ai-theme-toggle');
    if (!root || !btn) return;

    function apply(theme) {
        root.setAttribute('data-theme', theme);
        var toLight = theme !== 'light';
        var text = toLight ? 'Light theme' : 'Dark theme';
        btn.setAttribute('aria-pressed', theme === 'light' ? 'true' : 'false');
        btn.setAttribute('aria-label', 'Switch to ' + (toLight ? 'light' : 'dark') + ' theme');
        btn.title = btn.getAttribute('aria-label');
        var span = btn.querySelector('span');
        if (span) span.textContent = text;
    }

    var saved = 'dark';
    try { saved = localStorage.getItem('aiStudioTheme') === 'light' ? 'light' : 'dark'; } catch (e) { /* storage unavailable */ }
    apply(saved);

    btn.addEventListener('click', function () {
        var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        try { localStorage.setItem('aiStudioTheme', next); } catch (e) { /* storage unavailable */ }
        apply(next);
    });
})();
