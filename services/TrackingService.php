<?php
// path: ./services/TrackingService.php
// Tracking helpers extracted from core/helpers.php (god-module split).
// Rate-limit + rollup bumps + visitor identity. DB-touching calls delegate
// to Analytics (models own SQL). No HTML here.

class TrackingService {
    public static function shouldSkipTracking(): bool
    {
        if (!empty($_COOKIE['no_track']) && $_COOKIE['no_track'] === '1') {
            return true;
        }

        // Skip tracking for logged-in admins browsing the front-end
        // Visitor requests run under PHPSESSID, not ADMINSESSID — check admin cookie as proxy to avoid polluting analytics.
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
            return true;
        }
        if (isset($_COOKIE['ADMINSESSID']) && $_COOKIE['ADMINSESSID'] !== '') {
            return true;
        }

        // No Accept-Language header → not a real browser → skip tracking
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return true;
        }

        $clientIp = self::getClientIp();
        $skipIps = [
            '144.124.192.237',
        ];

        if ($clientIp && in_array($clientIp, $skipIps, true)) {
            return true;
        }

        return false;
    }

    public static function normalizeTrackingLanguage($lang): string
    {
        $lang = strtolower(trim((string)($lang ?? '')));
        if (!in_array($lang, SUPPORTED_LANGUAGES, true)) {
            return DEFAULT_LANGUAGE;
        }
        return $lang;
    }

    public static function isValidAnalyticsSlug($slug): bool
    {
        $slug = (string)($slug ?? '');
        if ($slug === '' || strlen($slug) > 120) return false;

        // Accept page slugs like "televizor" and synthetic slugs like "article-123".
        return (bool)preg_match('/^[a-z0-9][a-z0-9\-_]*$/', $slug);
    }

    public static function isValidAnalyticsInternalLinkSlug($slug): bool
    {
        // Slightly looser: allow article-* too (for future use) but still ASCII-safe.
        return self::isValidAnalyticsSlug($slug);
    }

    public static function getReferrerSource(): string {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer === '') return '';
        $host = parse_url($referer, PHP_URL_HOST);
        if (!$host) return '';
        $host = preg_replace('/^www\./i', '', $host);
        if (str_ends_with($host, 'kuplyu-tashkent.uz')) return '';
        return $host;
    }

    public static function getClientIp(): ?string
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    public static function getGaClientId(): ?string
    {
        $gaCookie = $_COOKIE['_ga'] ?? null;
        if (!$gaCookie) {
            return null;
        }
        if (preg_match('/^GA\d+\.\d+\.(.+)$/', $gaCookie, $matches)) {
            return str_replace('.', '_', $matches[1]);
        }
        return null;
    }

    public static function setTrackingCookie(string $name, string $value, int $ttlSeconds): void
    {
        $options = [
            'expires' => time() + max(60, $ttlSeconds),
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie($name, $value, $options);
        $_COOKIE[$name] = $value;
    }

    public static function getOrCreateTrackingVisitorId(): string
    {
        $cookieName = 'trk_visitor_id';
        $oneDay = 86400;

        // 1) Try existing cookie first — refresh its TTL so it lives 1 day from now
        $visitorId = trim((string)($_COOKIE[$cookieName] ?? ''));
        if ($visitorId !== '') {
            self::setTrackingCookie($cookieName, $visitorId, $oneDay);
            return $visitorId;
        }

        // 2) Try Google Analytics client ID as stable cross-session identifier
        $gaId = self::getGaClientId();
        if ($gaId !== null) {
            $visitorId = 'ga_' . $gaId;
            self::setTrackingCookie($cookieName, $visitorId, $oneDay);
            return $visitorId;
        }

        // 3) Fall back to IP-stable ID (same IP = same visitor, prevents bot dedup bypass)
        $ip = self::getClientIp() ?: '0.0.0.0';
        $visitorId = 'ip_' . hash('sha256', $ip);
        self::setTrackingCookie($cookieName, $visitorId, $oneDay);
        return $visitorId;
    }

    public static function trackingRateLimit(string $action, int $maxAttempts, int $windowSeconds): bool
    {
        return (new Analytics())->trackingRateLimit($action, $maxAttempts, $windowSeconds);
    }

    public static function shouldCountClickThisSession(string $slug, string $language, int $cooldownSeconds = 5): bool
    {
        return (new Analytics())->shouldCountClickThisSession($slug, $language, $cooldownSeconds);
    }

    public static function trackSiteVisit($language) {
        (new Analytics())->trackSiteVisit($language);
    }

    public static function trackVisit($slug, $language) {
        (new Analytics())->trackVisit($slug, $language);
    }

    public static function trackBotVisit($slug, $language) {
        (new Analytics())->trackBotVisit($slug, $language);
    }

    public static function trackClick($slug, $language, $utmSource = '') {
        (new Analytics())->trackClick($slug, $language, $utmSource);
    }

    public static function trackPhoneCall($slug, $language, $utmSource = '') {
        (new Analytics())->trackPhoneCall($slug, $language, $utmSource);
    }

    public static function trackInternalLink($fromSlug, $toSlug, $language) {
        (new Analytics())->trackInternalLink($fromSlug, $toSlug, $language);
    }

    public static function bumpMonthlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $isNewDay, $utmSource = ''): void
    {
        (new Analytics())->bumpMonthlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $isNewDay, $utmSource);
    }

    public static function bumpHourlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $utmSource = ''): void
    {
        (new Analytics())->bumpHourlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $utmSource);
    }

    public static function bumpInternalLinkMonthlySummary($fromSlug, $toSlug, $language, $deltaClicks, $isNewDay): void
    {
        (new Analytics())->bumpInternalLinkMonthlySummary($fromSlug, $toSlug, $language, $deltaClicks, $isNewDay);
    }

    /**
     * @deprecated Legacy recompute retained for admin repair scripts; prefer bumpInternalLinkMonthlySummary.
     */
    public static function updateInternalLinkMonthlySummary($fromSlug, $toSlug, $language) {
        (new Analytics())->updateInternalLinkMonthlySummary($fromSlug, $toSlug, $language);
    }

    public static function cleanupDedupTables(): void
    {
        (new Analytics())->cleanupDedupTables();
    }
}
