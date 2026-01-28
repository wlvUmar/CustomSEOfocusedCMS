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

    function trackInternalLink(toSlug) {
        const fromSlug = getCurrentSlug();
        const lang = getCurrentLanguage();

        if (fromSlug === toSlug) return;

        const body = new URLSearchParams({
            from: fromSlug,
            to: toSlug,
            lang: lang
        });

        if (navigator.sendBeacon) {
            navigator.sendBeacon(window.baseUrl + '/track-internal-link', body);
            return;
        }

        fetch(window.baseUrl + '/track-internal-link', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body.toString(),
            keepalive: true
        }).catch(function (err) {
            console.debug('Link tracking failed:', err);
        });
    }

    function trackPhoneCall() {
        const slug = getCurrentSlug();
        const lang = getCurrentLanguage();
        const body = new URLSearchParams({ slug, lang });

        if (navigator.sendBeacon) {
            navigator.sendBeacon(window.baseUrl + '/track-phone-call', body);
        } else {
            fetch(window.baseUrl + '/track-phone-call', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                keepalive: true
            }).catch(e => console.debug('Phone tracking failed:', e));
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

            // Track phone calls
            if (link.href.startsWith('tel:')) {
                trackPhoneCall();
                return;
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
