// path: ./public/js/param-persistence.js
// URL parameter persistence across page navigation using sessionStorage

(function () {
    'use strict';

    // Session storage keys
    const STORAGE_KEYS = {
        phone: 'persist_param_phone',
        instagram: 'persist_param_instagram',
        telegram: 'persist_param_telegram',
        utm_source: 'persist_param_utm_source'
    };

    // Parameters to persist across navigation
    const PARAMS_TO_PERSIST = ['phone', 'instagram', 'telegram', 'utm_source'];

    /**
     * Get current query parameters from URL
     */
    function getUrlParams() {
        const params = {};
        const searchParams = new URLSearchParams(window.location.search);
        
        PARAMS_TO_PERSIST.forEach(key => {
            const value = searchParams.get(key);
            if (value) {
                params[key] = value;
            }
        });

        return params;
    }

    /**
     * Store parameters in sessionStorage
     */
    function storeParams(params) {
        try {
            PARAMS_TO_PERSIST.forEach(key => {
                if (params[key]) {
                    sessionStorage.setItem(STORAGE_KEYS[key], params[key]);
                }
            });
        } catch (e) {
            // Silently handle storage errors
        }
    }

    /**
     * Retrieve parameters from sessionStorage
     */
    function retrieveParams() {
        const params = {};
        try {
            PARAMS_TO_PERSIST.forEach(key => {
                const value = sessionStorage.getItem(STORAGE_KEYS[key]);
                if (value) {
                    params[key] = value;
                }
            });
        } catch (e) {
            // Silently handle storage errors
        }
        return params;
    }

    /**
     * Build query string from params object
     */
    function buildQueryString(params) {
        const pairs = [];
        Object.keys(params).forEach(key => {
            if (params[key]) {
                pairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(params[key]));
            }
        });
        return pairs.length > 0 ? '?' + pairs.join('&') : '';
    }

    /**
     * Add persistent params to internal links
     */
    function addParamsToInternalLinks() {
        const storedParams = retrieveParams();
        
        if (Object.keys(storedParams).length === 0) {
            return; // No params to add
        }

        const queryString = buildQueryString(storedParams);
        const baseUrl = window.location.origin;
        const currentUrl = window.location.href.split('?')[0]; // Without query string

        document.querySelectorAll('a[href]').forEach(link => {
            const href = link.getAttribute('href');
            
            if (!href) return;

            // Skip external links, anchors, and non-standard URLs
            if (href.startsWith('http') && !href.startsWith(baseUrl)) return;
            if (href.startsWith('#')) return;
            if (href.startsWith('tel:') || href.startsWith('mailto:') || href.startsWith('javascript:')) return;
            if (href.startsWith('data:')) return;

            // Handle relative URLs
            try {
                const linkUrl = new URL(href, baseUrl);
                
                // Only modify same-domain links
                if (linkUrl.origin !== baseUrl) return;

                // Build the new URL with persistent params
                const pathname = linkUrl.pathname;
                const existingParams = new URLSearchParams(linkUrl.search);
                
                // Merge stored params with existing ones (existing ones take precedence)
                const mergedParams = { ...storedParams };
                existingParams.forEach((value, key) => {
                    mergedParams[key] = value;
                });

                const newQueryString = buildQueryString(mergedParams);
                const newHref = pathname + newQueryString;

                link.setAttribute('href', newHref);
            } catch (e) {
                // Invalid URL, skip
            }
        });
    }

    /**
     * Initialize param persistence on page load
     */
    function initializeParamPersistence() {
        // 1. Check for params in current URL
        const urlParams = getUrlParams();
        
        // 2. Store them if present
        if (Object.keys(urlParams).length > 0) {
            storeParams(urlParams);
        }

        // 3. Add stored params to internal links
        addParamsToInternalLinks();
    }

    // Run initialization when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeParamPersistence);
    } else {
        initializeParamPersistence();
    }

    // Re-apply params to dynamically added links (e.g., AJAX)
    // Using a simple observer approach
    if (window.MutationObserver) {
        try {
            const observer = new MutationObserver(function(mutations) {
                let hasNewLinks = false;
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length > 0) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) { // Element node
                                if (node.tagName === 'A' || node.querySelector('a')) {
                                    hasNewLinks = true;
                                }
                            }
                        });
                    }
                });
                
                if (hasNewLinks) {
                    addParamsToInternalLinks();
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        } catch (e) {
            // Silently handle observer errors
        }
    }
})();
