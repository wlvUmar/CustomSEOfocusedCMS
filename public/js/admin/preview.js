// FILE: public/js/admin/preview.js
// Moved from inline script in views/templates/preview.php

// Get page ID from the page (set by PHP)
let pageId = window.previewPageId || 0;
let baseUrl = window.baseUrl || '';

function changeMonth() {
    const month = document.getElementById('month-selector').value;
    const lang = document.getElementById('lang-selector').value;
    window.location.href = `${baseUrl}/admin/preview/${pageId}?month=${month}&lang=${lang}`;
}

function changeLang() {
    const month = document.getElementById('month-selector').value;
    const lang = document.getElementById('lang-selector').value;
    window.location.href = `${baseUrl}/admin/preview/${pageId}?month=${month}&lang=${lang}`;
}

// Initialize feather icons when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});