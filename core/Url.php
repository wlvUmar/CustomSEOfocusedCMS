<?php
// path: ./core/Url.php
// Canonical URL helpers extracted from core/helpers.php (god-module split).
// Pure string logic only - no DB, no HTML.

class Url {
    const NON_PROD_SENTINEL_BASE = 'http://invalid.localhost';

    /**
     * Resolve the absolute canonical site base URL used for SEO/meta and JSON-LD.
     *
     * Priority:
     *  1) BASE_URL env/constant (should be absolute in production, e.g. https://kuplyu-tashkent.uz)
     *  2) Current request host/proto as a fallback
     */
    public static function siteBaseUrl() {
        $baseUrl = defined('BASE_URL') ? (string)BASE_URL : '';
        $baseUrl = trim($baseUrl);

        // If already absolute, use it as-is — this is the only trusted production path.
        if ($baseUrl !== '' && strpos($baseUrl, '://') !== false) {
            return rtrim($baseUrl, '/');
        }

        // Non-production (or IS_PRODUCTION undefined): never emit the production host.
        // Returns an RFC 6761 reserved sentinel so canonical/OG/hreflang/JSON-LD values
        // are visibly wrong in dev instead of leaking production URLs.
        if (!defined('IS_PRODUCTION') || !IS_PRODUCTION) {
            error_log('[siteBaseUrl] non-production: BASE_URL missing or not absolute; returning sentinel');
            return self::NON_PROD_SENTINEL_BASE;
        }

        // Production: BASE_URL not configured as absolute — fail-closed to canonical production host.
        // Previous code fell back to $_SERVER['HTTP_HOST'] which enables host-header
        // poisoning (e.g. sitemap emitting http://192.168.100.7 or evil.com).
        if ($baseUrl === '') {
            error_log('[siteBaseUrl] BASE_URL empty, fallback to canonical https://kuplyu-tashkent.uz');
            return 'https://kuplyu-tashkent.uz';
        }

        // BASE_URL is a path prefix (e.g. /myapp) — still need a host, use canonical.
        if (strpos($baseUrl, '/') === 0) {
            return 'https://kuplyu-tashkent.uz' . rtrim($baseUrl, '/');
        }

        // Bare hostname without scheme
        return 'https://' . rtrim($baseUrl, '/');
    }

    public static function siteUrl($path = '') {
        $base = self::siteBaseUrl();
        $path = (string)($path ?? '');
        if ($path === '' || $path === '/') return $base . '/';
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Convert a possibly-relative URL into an absolute URL on the canonical site host.
     * Also rewrites absolute localhost/127.0.0.1 URLs to the canonical host.
     */
    public static function absoluteUrl($url, $baseUrl = null) {
        $url = trim((string)($url ?? ''));
        if ($url === '') return '';

        $baseUrl = $baseUrl ? rtrim((string)$baseUrl, '/') : self::siteBaseUrl();

        // Protocol-relative URL.
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }

        $parsed = @parse_url($url);
        if (is_array($parsed) && !empty($parsed['scheme'])) {
            $host = strtolower((string)($parsed['host'] ?? ''));
            if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                $path = $parsed['path'] ?? '';
                $query = isset($parsed['query']) ? ('?' . $parsed['query']) : '';
                $fragment = isset($parsed['fragment']) ? ('#' . $parsed['fragment']) : '';
                return $baseUrl . ($path ?: '') . $query . $fragment;
            }
            return $url;
        }

        if (strpos($url, '/') === 0) {
            return $baseUrl . $url;
        }

        return $baseUrl . '/' . $url;
    }

    public static function canonicalUrlForPage($slug, $lang) {
        $slug = (string)($slug ?? '');
        $lang = (string)($lang ?? DEFAULT_LANGUAGE);

        $isHome = in_array($slug, ['home', 'main', ''], true);
        if ($isHome) {
            return $lang !== DEFAULT_LANGUAGE ? self::siteUrl($lang) : self::siteUrl('/');
        }

        return $lang !== DEFAULT_LANGUAGE ? self::siteUrl($slug . '/' . $lang) : self::siteUrl($slug);
    }

    public static function canonicalUrlForArticle($id, $lang) {
        $id = (string)$id;
        $lang = (string)($lang ?? DEFAULT_LANGUAGE);

        return $lang !== DEFAULT_LANGUAGE ? self::siteUrl('articles/' . $id . '/' . $lang) : self::siteUrl('articles/' . $id);
    }

    public static function getPageSlugClass(?string $slug): string {
        $slug = strtolower(trim((string)($slug ?? '')));
        if ($slug === '') return 'page-generic';
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? 'page-' . $slug : 'page-generic';
    }
}
