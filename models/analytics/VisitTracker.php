<?php
// models/analytics/VisitTracker.php
// Write path: visit/click/call/link/bot tracking. Guards + rollups live in TrackingStore.

class VisitTracker {
    private $db;
    private $store;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->store = new TrackingStore();
    }

    public function trackSiteVisit($language) {
        $auditCtx = ['slug' => AnalyticsQuery::SITE_SLUG, 'language' => $language];

        if (shouldSkipTracking()) {
            logTrackingAudit('site_visit', array_merge($auditCtx, ['action' => 'skipped']));
            return;
        }

        $language = normalizeTrackingLanguage($language);
        $auditCtx['language'] = $language;

        if (isBot()) {
            logTrackingAudit('site_visit', array_merge($auditCtx, ['action' => 'bot']));
            $this->trackBotVisit(AnalyticsQuery::SITE_SLUG, $language);
            return;
        }

        if (!$this->store->trackingRateLimit('site_visit', 15, 60)) {
            logTrackingAudit('site_visit', array_merge($auditCtx, ['action' => 'rate_limited']));
            return;
        }

        $visitorId = getOrCreateTrackingVisitorId();
        $date = date('Y-m-d');

        try {
            $dedup = $this->db->query(
                "INSERT IGNORE INTO analytics_dedup_site_visits (visitor_id, view_date) VALUES (?, ?)",
                [$visitorId, $date]
            );
            if ($dedup->rowCount() === 0) {
                logTrackingAudit('site_visit', array_merge($auditCtx, ['action' => 'dedup_skipped', 'visitor_id' => $visitorId, 'date' => $date]));
                return;
            }

            $hour = (int)date('G');
            $sql = "INSERT INTO analytics_hourly (page_slug, language, date, hour, visits, clicks, phone_calls)
                    VALUES ('" . AnalyticsQuery::SITE_SLUG . "', ?, ?, ?, 1, 0, 0)
                    ON DUPLICATE KEY UPDATE visits = visits + 1";

            $this->db->query($sql, [$language, $date, $hour]);
            logTrackingAudit('site_visit', array_merge($auditCtx, ['action' => 'counted', 'visitor_id' => $visitorId, 'date' => $date, 'hour' => $hour]));
        } catch (Exception $e) {
            error_log("Site visit tracking error: " . $e->getMessage());
        }
    }

    public function trackVisit($slug, $language) {
        $auditCtx = ['slug' => $slug, 'language' => $language];

        if (shouldSkipTracking()) {
            logTrackingAudit('visit', array_merge($auditCtx, ['action' => 'skipped']));
            return;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        if (!IS_PRODUCTION && !empty($_GET['debug_ua']) && $_GET['debug_ua'] === '1') {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            error_log("[DEBUG_UA] method=$method uri=$uri slug=$slug lang=$language ua=$userAgent");
        }

        $language = normalizeTrackingLanguage($language);
        $auditCtx['language'] = $language;
        if (!isValidAnalyticsSlug($slug)) {
            logTrackingAudit('visit', array_merge($auditCtx, ['action' => 'invalid_slug']));
            return;
        }

        if (isBot()) {
            logTrackingAudit('visit', array_merge($auditCtx, ['action' => 'bot']));
            $this->trackBotVisit($slug, $language);
            return;
        }

        if (!$this->store->trackingRateLimit('visit', 15, 60)) {
            logTrackingAudit('visit', array_merge($auditCtx, ['action' => 'rate_limited']));
            return;
        }

        $visitorId = getOrCreateTrackingVisitorId();
        $date = date('Y-m-d');
        $utmSource = trim((string)($_GET['utm_source'] ?? ''));
        if ($utmSource === '') {
            $utmSource = getReferrerSource();
        }

        try {
            $dedup = $this->db->query(
                "INSERT IGNORE INTO analytics_dedup_visits (visitor_id, page_slug, language, view_date, utm_source) VALUES (?, ?, ?, ?, ?)",
                [$visitorId, $slug, $language, $date, $utmSource]
            );
            if ($dedup->rowCount() === 0) {
                logTrackingAudit('visit', array_merge($auditCtx, ['action' => 'dedup_skipped', 'visitor_id' => $visitorId, 'date' => $date, 'utm_source' => $utmSource]));
                return;
            }
        } catch (Exception $e) {
            error_log("Visit dedup error: " . $e->getMessage());
        }

        $this->store->cleanupDedupTables();

        try {
            $sql = "INSERT INTO analytics (page_slug, language, visits, clicks, utm_source, date)
                    VALUES (?, ?, 1, 0, ?, ?)
                    ON DUPLICATE KEY UPDATE visits = visits + 1";

            $stmt = $this->db->query($sql, [$slug, $language, $utmSource, $date]);
            $isNewDay = ($stmt && $stmt->rowCount() === 1);
            $this->store->bumpMonthlySummary($slug, $language, 1, 0, 0, $isNewDay, $utmSource);
            $this->store->bumpHourlySummary($slug, $language, 1, 0, 0, $utmSource);
            logTrackingAudit('visit', array_merge($auditCtx, ['action' => 'counted', 'visitor_id' => $visitorId, 'date' => $date, 'utm_source' => $utmSource, 'is_new_day' => $isNewDay]));
        } catch (Exception $e) {
            error_log("Analytics error: " . $e->getMessage());
        }
    }

    public function trackBotVisit($slug, $language) {
        logDebug("[TRACK_BOT_VISIT] Called for slug: $slug, language: $language");

        try {
            $date = date('Y-m-d');
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

            $botType = 'unknown';
            $userAgentLower = strtolower($userAgent);

            $uaMap = [
                'googlebot' => 'googlebot',
                'google-inspectiontool' => 'googlebot',
                'google-structured-data-testing-tool' => 'googlebot',
                'google-pagerenderer' => 'googlebot',
                'google page renderer' => 'googlebot',
                'googleother' => 'googlebot',
                'google-extended' => 'googlebot',
                'adsbot-google' => 'googlebot',
                'mediapartners-google' => 'googlebot',
                'apis-google' => 'googlebot',
                'bingbot' => 'bingbot',
                'yandexbot' => 'yandexbot',
                'baiduspider' => 'baiduspider',
                'slurp' => 'yahoo',
                'duckduckbot' => 'duckduckgo',
            ];
            foreach ($uaMap as $needle => $type) {
                if (strpos($userAgentLower, $needle) !== false) {
                    $botType = $type;
                    break;
                }
            }
            if ($botType === 'unknown') {
                if (preg_match('/([a-zA-Z0-9_\-]+bot|spider|crawler|scraper)/i', $userAgent, $matches)) {
                    $botType = strtolower($matches[1]);
                } elseif (preg_match('/(facebook|twitter|slack|discord|whatsapp|telegram|pinterest|linkedin)externalhit/i', $userAgent, $matches)) {
                    $botType = strtolower($matches[1]);
                } else {
                    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
                    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
                    if ($acceptLang === '') {
                        $botType = 'no_accept_language';
                    } elseif ($accept === '') {
                        $botType = 'no_accept';
                    } else {
                        $botType = 'other';
                    }
                }
            }

            logDebug("[TRACK_BOT_VISIT] Bot type: $botType, Date: $date");

            $sql = "INSERT INTO analytics_bot_visits
                    (page_slug, language, bot_type, user_agent, visit_date, visits)
                    VALUES (?, ?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE visits = visits + 1, last_visit = NOW()";

            $this->db->query($sql, [$slug, $language, $botType, substr($userAgent, 0, 255), $date]);

            logDebug("[TRACK_BOT_VISIT] Successfully inserted/updated bot visit record");
        } catch (Exception $e) {
            error_log("[TRACK_BOT_VISIT] ERROR: " . $e->getMessage());
        }
    }

    public function trackClick($slug, $language, $utmSource = '') {
        if (shouldSkipTracking()) return;
        if (isBot()) return;

        if (!$this->store->shouldCountClickThisSession($slug, $language)) {
            return;
        }

        $language = normalizeTrackingLanguage($language);
        if (!isValidAnalyticsSlug($slug)) return;

        $visitorId = getOrCreateTrackingVisitorId();
        $date = date('Y-m-d');
        $utmSource = trim((string)$utmSource);

        try {
            $dedup = $this->db->query(
                "INSERT IGNORE INTO analytics_dedup_clicks (visitor_id, page_slug, language, view_date, utm_source) VALUES (?, ?, ?, ?, ?)",
                [$visitorId, $slug, $language, $date, $utmSource]
            );
            if ($dedup->rowCount() === 0) {
                return;
            }
        } catch (Exception $e) {
            error_log("Click dedup error: " . $e->getMessage());
        }

        try {
            $sql = "INSERT INTO analytics (page_slug, language, visits, clicks, phone_calls, utm_source, date)
                    VALUES (?, ?, 0, 1, 0, ?, ?)
                    ON DUPLICATE KEY UPDATE clicks = clicks + 1";

            $stmt = $this->db->query($sql, [$slug, $language, $utmSource, $date]);
            $isNewDay = ($stmt && $stmt->rowCount() === 1);
            $this->store->bumpMonthlySummary($slug, $language, 0, 1, 0, $isNewDay, $utmSource);
            $this->store->bumpHourlySummary($slug, $language, 0, 1, 0, $utmSource);
        } catch (Exception $e) {
            error_log("Click tracking error: " . $e->getMessage());
        }
    }

    public function trackPhoneCall($slug, $language, $utmSource = '') {
        if (shouldSkipTracking()) return;
        if (isBot()) return;

        $language = normalizeTrackingLanguage($language);
        if (!isValidAnalyticsSlug($slug)) return;

        $visitorId = getOrCreateTrackingVisitorId();
        $utmSource = trim((string)$utmSource);

        try {
            $dedup = $this->db->query(
                "INSERT IGNORE INTO analytics_dedup_phone_calls (visitor_id, page_slug, language, utm_source) VALUES (?, ?, ?, ?)",
                [$visitorId, $slug, $language, $utmSource]
            );
            if ($dedup->rowCount() === 0) {
                return;
            }
        } catch (Exception $e) {
            error_log("Phone call dedup error: " . $e->getMessage());
        }

        try {
            $date = date('Y-m-d');

            $sql = "INSERT INTO analytics (page_slug, language, visits, clicks, phone_calls, utm_source, date)
                    VALUES (?, ?, 0, 0, 1, ?, ?)
                    ON DUPLICATE KEY UPDATE phone_calls = phone_calls + 1";

            $stmt = $this->db->query($sql, [$slug, $language, $utmSource, $date]);
            $isNewDay = ($stmt && $stmt->rowCount() === 1);
            $this->store->bumpMonthlySummary($slug, $language, 0, 0, 1, $isNewDay, $utmSource);
            $this->store->bumpHourlySummary($slug, $language, 0, 0, 1, $utmSource);
        } catch (Exception $e) {
            error_log("Phone call tracking error: " . $e->getMessage());
        }
    }

    public function trackInternalLink($fromSlug, $toSlug, $language) {
        if (shouldSkipTracking()) return;
        if (isBot()) return;

        $language = normalizeTrackingLanguage($language);
        if (!isValidAnalyticsInternalLinkSlug($fromSlug)) return;
        if (!isValidAnalyticsInternalLinkSlug($toSlug)) return;
        if ($fromSlug === $toSlug) return;

        $visitorId = getOrCreateTrackingVisitorId();
        $date = date('Y-m-d');

        try {
            $dedup = $this->db->query(
                "INSERT IGNORE INTO analytics_dedup_internal_links (visitor_id, from_slug, to_slug, view_date) VALUES (?, ?, ?, ?)",
                [$visitorId, $fromSlug, $toSlug, $date]
            );
            if ($dedup->rowCount() === 0) {
                return;
            }
        } catch (Exception $e) {
            error_log("Internal link dedup error: " . $e->getMessage());
        }

        try {
            $sql = "INSERT INTO analytics_internal_links (from_slug, to_slug, language, clicks, date)
                    VALUES (?, ?, ?, 1, ?)
                    ON DUPLICATE KEY UPDATE clicks = clicks + 1";

            $stmt = $this->db->query($sql, [$fromSlug, $toSlug, $language, $date]);
            $isNewDay = ($stmt && $stmt->rowCount() === 1);
            $this->store->bumpInternalLinkMonthlySummary($fromSlug, $toSlug, $language, 1, $isNewDay);
        } catch (Exception $e) {
            error_log("Internal link tracking error: " . $e->getMessage());
        }
    }
}
