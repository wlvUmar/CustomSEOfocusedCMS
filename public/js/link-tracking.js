// path: ./public/js/link-tracking.js
// Internal link tracking for analytics

(function() {
    'use strict';
    
    // Get current page slug from URL or data attribute
    function getCurrentSlug() {
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        // First part after domain is the slug (unless it's a language code)
        return pathParts[0] && pathParts[0].length > 2 ? pathParts[0] : 'home';
    }
    
    // Get current language
    function getCurrentLanguage() {
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        const lastPart = pathParts[pathParts.length - 1];
        return lastPart === 'uz' ? 'uz' : 'ru';
    }
    
    // Track internal link click
    function trackInternalLink(toSlug) {
        const fromSlug = getCurrentSlug();
        const lang = getCurrentLanguage();
        
        // Don't track if going to same page
        if (fromSlug === toSlug) return;
        
        // Send tracking request (non-blocking)
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
            // Silent fail - don't interrupt user experience
            console.debug('Link tracking failed:', err);
        });
    }
    
    // Extract slug from internal link href
    function extractSlugFromHref(href) {
        try {
            const url = new URL(href, window.location.origin);
            
            // Only track same-origin links
            if (url.origin !== window.location.origin) return null;
            
            const pathParts = url.pathname.split('/').filter(Boolean);
            
            // Get slug (first part that's not a language code)
            for (let i = 0; i < pathParts.length; i++) {
                const part = pathParts[i];
                if (part.length > 2) return part;
            }
            
            return 'home'; // Default to home if no slug found
        } catch (e) {
            return null;
        }
    }
    
    // Setup tracking on all internal links
    function setupLinkTracking() {
        document.addEventListener('click', function(e) {
            // Find closest anchor tag
            const link = e.target.closest('a');
            
            if (!link || !link.href) return;
            
            // Extract slug from href
            const toSlug = extractSlugFromHref(link.href);
            
            if (toSlug) {
                trackInternalLink(toSlug);
            }
        }, true); // Use capture phase to catch all clicks
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupLinkTracking);
    } else {
        setupLinkTracking();
    }
})();