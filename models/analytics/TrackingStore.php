<?php
// models/analytics/TrackingStore.php
// Persistence-level tracking primitives: rate limit, click cooldown, rollup bumps, dedup GC.

class TrackingStore {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function trackingRateLimit(string $action, int $maxAttempts, int $windowSeconds): bool
    {
        $maxAttempts = max(1, (int)$maxAttempts);
        $windowSeconds = max(1, (int)$windowSeconds);
        $now = time();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $key = 'trk_' . preg_replace('/[^a-z0-9_\-]/i', '_', $action);
            $state = $_SESSION[$key] ?? ['count' => 0, 'ts' => $now];
            if (!is_array($state) || !isset($state['count'], $state['ts'])) {
                $state = ['count' => 0, 'ts' => $now];
            }
            if (($now - (int)$state['ts']) > $windowSeconds) {
                $state = ['count' => 0, 'ts' => $now];
            }
            $state['count'] = (int)$state['count'] + 1;
            $_SESSION[$key] = $state;
            return $state['count'] <= $maxAttempts;
        }

        $ip = getClientIp();
        if (!$ip) return true;
        $throttleKey = 'trl_' . md5($action . '_' . $ip);
        $expiresAt = date('Y-m-d H:i:s', $now + $windowSeconds);

        try {
            $result = $this->db->query(
                "INSERT INTO analytics_throttle (throttle_key, hits, expires_at)
                 VALUES (?, 1, ?)
                 ON DUPLICATE KEY UPDATE hits = hits + 1, expires_at = IF(? > expires_at, ?, expires_at)",
                [$throttleKey, $expiresAt, $expiresAt, $expiresAt]
            );
            $row = $this->db->fetchOne("SELECT hits, expires_at FROM analytics_throttle WHERE throttle_key = ?", [$throttleKey]);
            if ($row) {
                $hits = (int)$row['hits'];
                $expires = strtotime($row['expires_at']);
                if ($now > $expires) {
                    $this->db->query("DELETE FROM analytics_throttle WHERE throttle_key = ?", [$throttleKey]);
                    return true;
                }
                return $hits <= $maxAttempts;
            }
        } catch (Exception $e) {
            error_log("Rate limit error: " . $e->getMessage());
        }

        return true;
    }

    public function shouldCountClickThisSession(string $slug, string $language, int $cooldownSeconds = 5): bool
    {
        $slug = (string)$slug;
        $language = normalizeTrackingLanguage($language);
        $now = time();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $key = 'trk_clicks';
            $state = $_SESSION[$key] ?? [];
            if (!is_array($state)) {
                $state = [];
            }

            $clickKey = $slug . '|' . $language;

            if (isset($state[$clickKey])) {
                $lastClickTime = $state[$clickKey];
                if (($now - $lastClickTime) < $cooldownSeconds) {
                    return false;
                }
            }

            $state[$clickKey] = $now;
            $_SESSION[$key] = $state;

            return true;
        }

        $ip = getClientIp();
        if (!$ip) return true;
        $throttleKey = 'clk_' . md5($ip . $slug . $language);
        $expiresAt = date('Y-m-d H:i:s', $now + $cooldownSeconds);

        try {
            $row = $this->db->fetchOne("SELECT hits, expires_at FROM analytics_throttle WHERE throttle_key = ?", [$throttleKey]);
            if ($row) {
                $hits = (int)$row['hits'];
                $expires = strtotime($row['expires_at']);
                if ($now > $expires) {
                    $this->db->query(
                        "UPDATE analytics_throttle SET hits = 1, expires_at = ? WHERE throttle_key = ?",
                        [$expiresAt, $throttleKey]
                    );
                    return true;
                }
                return $hits <= 1;
            }

            $this->db->query(
                "INSERT INTO analytics_throttle (throttle_key, hits, expires_at) VALUES (?, 1, ?)",
                [$throttleKey, $expiresAt]
            );
            return true;
        } catch (Exception $e) {
            return true;
        }
    }

    public function bumpMonthlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $isNewDay, $utmSource = ''): void
    {
        try {
            $year = (int)date('Y');
            $month = (int)date('n');

            $deltaVisits = (int)$deltaVisits;
            $deltaClicks = (int)$deltaClicks;
            $deltaPhoneCalls = (int)$deltaPhoneCalls;
            $deltaDays = $isNewDay ? 1 : 0;
            $utmSource = trim((string)$utmSource) ?: '';

            $sql = "INSERT INTO analytics_monthly
                        (page_slug, language, year, month, total_visits, total_clicks, total_phone_calls, utm_source, unique_days)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        total_visits = total_visits + VALUES(total_visits),
                        total_clicks = total_clicks + VALUES(total_clicks),
                        total_phone_calls = total_phone_calls + VALUES(total_phone_calls),
                        unique_days = unique_days + VALUES(unique_days)";

            $this->db->query($sql, [$slug, $language, $year, $month, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $utmSource, $deltaDays]);
        } catch (Exception $e) {
            error_log("Monthly summary error: " . $e->getMessage());
        }
    }

    public function bumpHourlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $utmSource = ''): void
    {
        try {
            $date = date('Y-m-d');
            $hour = (int)date('G');

            $deltaVisits = (int)$deltaVisits;
            $deltaClicks = (int)$deltaClicks;
            $deltaPhoneCalls = (int)$deltaPhoneCalls;
            $utmSource = trim((string)$utmSource) ?: '';

            $sql = "INSERT INTO analytics_hourly
                        (page_slug, language, date, hour, visits, clicks, phone_calls, utm_source)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        visits = visits + VALUES(visits),
                        clicks = clicks + VALUES(clicks),
                        phone_calls = phone_calls + VALUES(phone_calls)";

            $this->db->query($sql, [$slug, $language, $date, $hour, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $utmSource]);
        } catch (Exception $e) {
            error_log("Hourly summary error: " . $e->getMessage());
        }
    }

    public function bumpInternalLinkMonthlySummary($fromSlug, $toSlug, $language, $deltaClicks, $isNewDay): void
    {
        try {
            $year = (int)date('Y');
            $month = (int)date('n');
            $deltaClicks = (int)$deltaClicks;
            $deltaDays = $isNewDay ? 1 : 0;

            $sql = "INSERT INTO analytics_internal_links_monthly
                        (from_slug, to_slug, language, year, month, total_clicks, unique_days)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        total_clicks = total_clicks + VALUES(total_clicks),
                        unique_days = unique_days + VALUES(unique_days)";

            $this->db->query($sql, [$fromSlug, $toSlug, $language, $year, $month, $deltaClicks, $deltaDays]);
        } catch (Exception $e) {
            error_log("Internal link monthly summary error: " . $e->getMessage());
        }
    }

    /**
     * @deprecated Legacy recompute retained for admin repair scripts; prefer bumpInternalLinkMonthlySummary.
     */
    public function updateInternalLinkMonthlySummary($fromSlug, $toSlug, $language) {
        try {
            $year = date('Y');
            $month = date('n');

            $sql = "INSERT INTO analytics_internal_links_monthly
                    (from_slug, to_slug, language, year, month, total_clicks, unique_days)
                    SELECT from_slug, to_slug, language, YEAR(date), MONTH(date),
                           SUM(clicks), COUNT(DISTINCT date)
                    FROM analytics_internal_links
                    WHERE from_slug = ? AND to_slug = ? AND language = ?
                      AND YEAR(date) = ? AND MONTH(date) = ?
                    GROUP BY from_slug, to_slug, language, YEAR(date), MONTH(date)
                    ON DUPLICATE KEY UPDATE
                        total_clicks = VALUES(total_clicks),
                        unique_days = VALUES(unique_days)";

            $this->db->query($sql, [$fromSlug, $toSlug, $language, $year, $month]);
        } catch (Exception $e) {
            error_log("Internal link monthly summary error: " . $e->getMessage());
        }
    }

    public function cleanupDedupTables(): void
    {
        static $lastCleanup = 0;
        $now = time();
        if ($now - $lastCleanup < 3600) return;
        if (random_int(1, 100) !== 1) return;
        $lastCleanup = $now;

        try {
            $this->db->query("DELETE FROM analytics_dedup_visits WHERE view_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
            $this->db->query("DELETE FROM analytics_dedup_clicks WHERE view_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
            $this->db->query("DELETE FROM analytics_dedup_internal_links WHERE view_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
            $this->db->query("DELETE FROM analytics_dedup_phone_calls WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $this->db->query("DELETE FROM analytics_dedup_site_visits WHERE view_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
            $this->db->query("DELETE FROM analytics_throttle WHERE expires_at < NOW()");
        } catch (Exception $e) {
            error_log("Dedup cleanup error: " . $e->getMessage());
        }
    }
}
