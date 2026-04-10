// path: ./public/js/link-tracking.js
// Internal link and phone call tracking for analytics

(function () {
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

    function postTracking(endpoint, params) {
        try {
            const body = new URLSearchParams(params);

            if (navigator.sendBeacon) {
                navigator.sendBeacon((window.baseUrl || '') + endpoint, body);
                return;
            }

            fetch((window.baseUrl || '') + endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: body.toString(),
                keepalive: true
            }).catch(function () {});
        } catch (e) {}
    }

    function trackInternalLink(toSlug) {
        const fromSlug = getCurrentSlug();
        const lang = getCurrentLanguage();

        if (fromSlug === toSlug) return;
        postTracking('/track-internal-link', {
            from: fromSlug,
            to: toSlug,
            lang: lang
        });
    }

    function trackClick() {
        postTracking('/track-click', {
            slug: getCurrentSlug(),
            lang: getCurrentLanguage()
        });
    }

    function trackPhoneCall() {
        postTracking('/track-phone-call', {
            slug: getCurrentSlug(),
            lang: getCurrentLanguage()
        });

        if (window.googleReviewUrl) {
            if (confirm(window.googleReviewPrompt || 'Thanks! Would you like to leave a review?')) {
                window.open(window.googleReviewUrl, '_blank', 'noopener,noreferrer');
            }
        }
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

    function setupTracking() {
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a');

            if (!link || !link.href) return;

            if (link.href.startsWith('tel:')) {
                trackPhoneCall();
                return;
            }

            if (link.classList.contains('floating-telegram') || link.matches('[data-track-click="1"]')) {
                trackClick();
            }

            // Track internal links
            const toSlug = extractSlugFromHref(link.href);
            if (toSlug) {
                trackInternalLink(toSlug);
            }
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupTracking);
    } else {
        setupTracking();
    }
})();
