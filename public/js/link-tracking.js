// path: ./public/js/link-tracking.js
// Internal link and phone call tracking for analytics

(function () {
    'use strict';

    function getCurrentSlug() {
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        if (!pathParts.length) return 'main';
        // Handle /uz or /slug/uz pattern: first part may be language code
        if (pathParts[0] === 'uz' || pathParts[0] === 'ru') {
            return pathParts[1] || 'main';
        }
        // Normal slug
        const first = pathParts[0];
        // Filter out language suffix if present as last segment
        if (first === 'main') return 'main';
        return first;
    }

    function getCurrentLanguage() {
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        const lastPart = pathParts[pathParts.length - 1];
        return lastPart === 'uz' ? 'uz' : 'ru';
    }

    function postTracking(endpoint, params) {
        try {
            const body = new URLSearchParams(params);
            const blob = new Blob([body.toString()], { type: 'application/x-www-form-urlencoded' });

            if (navigator.sendBeacon) {
                // Use Blob for Safari compatibility (URLSearchParams may serialize as [object])
                navigator.sendBeacon((window.baseUrl || '') + endpoint, blob);
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

    function getReviewModal() {
        return document.getElementById('review-modal');
    }

    function getPendingReviewKey() {
        return 'pending_review_prompt';
    }

    function setPendingReview(callHref) {
        try {
            sessionStorage.setItem(getPendingReviewKey(), JSON.stringify({
                href: callHref,
                slug: getCurrentSlug(),
                lang: getCurrentLanguage(),
                ts: Date.now(),
                shown: false
            }));
        } catch (e) {}
    }

    function getPendingReview() {
        try {
            const raw = sessionStorage.getItem(getPendingReviewKey());
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function clearPendingReview() {
        try {
            sessionStorage.removeItem(getPendingReviewKey());
        } catch (e) {}
    }

    // Simple de-duplication helpers using sessionStorage with TTL
    function isRecentlyTracked(key, ttlMs) {
        try {
            const raw = sessionStorage.getItem(key);
            if (!raw) return false;
            const ts = parseInt(raw, 10);
            if (isNaN(ts)) return false;
            return (Date.now() - ts) < (ttlMs || 15000);
        } catch (e) {
            return false;
        }
    }

    function markTracked(key) {
        try {
            sessionStorage.setItem(key, String(Date.now()));
        } catch (e) {}
    }

    function openReviewModal(callHref) {
        const modal = getReviewModal();
        if (!modal) {
            return;
        }

        const callBtn = modal.querySelector('[data-review-call]');
        const reviewBtn = modal.querySelector('[data-review-open]');
        const message = modal.querySelector('[data-review-message]');

        if (message) {
            message.textContent = window.googleReviewPrompt || 'Thanks! Would you like to leave a review?';
        }
        if (callBtn) {
            callBtn.href = callHref;
        }
        if (reviewBtn) {
            reviewBtn.href = window.googleReviewUrl || '#';
            reviewBtn.hidden = !window.googleReviewUrl;
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('review-modal-open');
    }

    function closeReviewModal() {
        const modal = getReviewModal();
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('review-modal-open');
        clearPendingReview();
    }

    function maybeShowPendingReview() {
        const pending = getPendingReview();
        if (!pending || pending.shown) return;
        if (document.visibilityState !== 'visible') return;
        if ((Date.now() - pending.ts) < 1500) return;

        pending.shown = true;
        try {
            sessionStorage.setItem(getPendingReviewKey(), JSON.stringify(pending));
        } catch (e) {}
        openReviewModal(pending.href || 'tel:');
    }

    function trackInternalLink(toSlug) {
        const fromSlug = getCurrentSlug();
        const lang = getCurrentLanguage();

        if (fromSlug === toSlug) return;
        const key = 'tracked_internal_' + fromSlug + '_' + toSlug;
        if (isRecentlyTracked(key, 10000)) return;
        markTracked(key);
        postTracking('/track-internal-link', {
            from: fromSlug,
            to: toSlug,
            lang: lang
        });
    }

    function trackClick(href) {
        var key = 'tracked_click_' + (href || getCurrentSlug());
        if (isRecentlyTracked(key, 8000)) return;
        markTracked(key);
        
        // Get utm_source from sessionStorage if available
        var utmSource = '';
        try {
            utmSource = sessionStorage.getItem('persist_param_utm_source') || '';
        } catch (e) {}
        
        var params = {
            slug: getCurrentSlug(),
            lang: getCurrentLanguage()
        };
        
        if (utmSource) {
            params.utm_source = utmSource;
        }
        
        postTracking('/track-click', params);
    }

    function trackPhoneCall(href) {
        var key = 'tracked_phone_' + (href || getCurrentSlug());
        if (isRecentlyTracked(key, 15000)) return;
        markTracked(key);
        
        // Get utm_source from sessionStorage if available
        var utmSource = '';
        try {
            utmSource = sessionStorage.getItem('persist_param_utm_source') || '';
        } catch (e) {}
        
        var params = {
            slug: getCurrentSlug(),
            lang: getCurrentLanguage()
        };
        
        if (utmSource) {
            params.utm_source = utmSource;
        }
        
        postTracking('/track-phone-call', params);
    }

    function extractSlugFromHref(href) {
        try {
            const url = new URL(href, window.location.origin);
            if (url.origin !== window.location.origin) return null;
            const pathParts = url.pathname.split('/').filter(Boolean);
            // Skip language codes
            const filtered = pathParts.filter(p => p !== 'uz' && p !== 'ru');
            for (let i = 0; i < filtered.length; i++) {
                const part = filtered[i];
                if (part && part !== 'main') return part;
            }
            return 'main';
        } catch (e) {
            return null;
        }
    }

    function setupTracking() {
        // Use bubble phase (false) to avoid intercepting before app handlers (project-08#2)
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a');

            if (!link || !link.href) return;

            if (link.matches('[data-review-call]')) {
                return;
            }

            if (link.href.startsWith('tel:')) {
                trackPhoneCall(link.href);
                setPendingReview(link.href);
                return;
            }

            if (link.classList.contains('hero__cta') || link.matches('[data-track-click="1"]')) {
                trackClick(link.href);
            }

            // Track internal links
            const toSlug = extractSlugFromHref(link.href);
            if (toSlug) {
                trackInternalLink(toSlug);
            }
        }, false);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupTracking);
    } else {
        setupTracking();
    }

    document.addEventListener('visibilitychange', maybeShowPendingReview);
    window.addEventListener('focus', maybeShowPendingReview);
    window.addEventListener('pageshow', maybeShowPendingReview);

    document.addEventListener('click', function (e) {
        const modal = getReviewModal();
        if (!modal || modal.hidden) return;

        if (e.target.closest('[data-review-modal-close]')) {
            closeReviewModal();
            return;
        }

        if (e.target.closest('[data-review-open]')) {
            clearPendingReview();
            closeReviewModal();
            return;
        }

        if (e.target === modal) {
            closeReviewModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeReviewModal();
        }
    });
})();
