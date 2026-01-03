// path: ./public/js/link-tracking.js
// Internal link tracking for analytics

(function() {
    'use strict';
    
    function getCurrentSlug() {
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        return pathParts[0] && pathParts[0].length > 2 ? pathParts[0] : 'home';
    }
    
    function getCurrentLanguage() {
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        const lastPart = pathParts[pathParts.length - 1];
        return lastPart === 'uz' ? 'uz' : 'ru';
    }

    
    function trackInternalLink(toSlug) {
        const fromSlug = getCurrentSlug();
        const lang = getCurrentLanguage();
        
        if (fromSlug === toSlug) return;
        
        fetch(window.baseUrl + '/track-internal-link', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded' 
            },
            body: 'from=' + encodeURIComponent(fromSlug) + 
                  '&to=' + encodeURIComponent(toSlug) + 
                  '&lang=' + encodeURIComponent(lang),
            keepalive: true // Ensure request completes even if page unloads
        }).catch(function(err) {
            console.debug('Link tracking failed:', err);
        });
    }
    
    function extractSlugFromHref(href) {
        try {
            const url = new URL(href, window.location.origin);
            if (url.origin !== window.location.origin) return null;
            const pathParts = url.pathname.split('/').filter(Boolean);
            for (let i = 0; i < pathParts.length; i++) {
                const part = pathParts[i];
                if (part.length > 2) return part;
            }
            
            return 'home'; 
        } catch (e) {
            return null;
        }
    }
    
    function setupLinkTracking() {
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            
            if (!link || !link.href) return;
            const toSlug = extractSlugFromHref(link.href);
            
            if (toSlug) {
                trackInternalLink(toSlug);
            }
        }, true); 
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupLinkTracking);
    } else {
        setupLinkTracking();
    }
    document.querySelectorAll('main a[href^="/"], main a[href^="' + window.baseUrl + '"]').forEach(link => {
        link.addEventListener('click', function() {
            const linkText = this.textContent;
            const toSlug = extractSlugFromHref(this.href);
            
            navigator.sendBeacon(window.baseUrl + '/track-link-click', new URLSearchParams({
                from: getCurrentSlug(),
                to: toSlug,
                text: linkText,
                lang: getCurrentLanguage()
            }));
        });
    });
})();