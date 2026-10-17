<?php
// models/analytics/RotationStats.php
// Content-rotation metrics: effectiveness, daily/weekly/monthly series, comparison, dashboard cards.

class RotationStats {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get rotation effectiveness metrics
     * Shows which content variations are actually being shown
     */
    public function getRotationEffectiveness($months = 6) {
        $months = (int)$months;

        $sql = "SELECT 
                    ar.page_slug,
                    ar.rotation_month,
                    ar.year,
                    ar.times_shown,
                    ar.unique_days,
                    COALESCE(am.total_visits, 0) as total_visits,
                    COALESCE(am.total_clicks, 0) as total_clicks,
                    COALESCE(am.total_phone_calls, 0) as total_phone_calls,
                    p.title_ru,
                    p.id as page_id
                FROM (
                    SELECT 
                        page_slug,
                        rotation_month,
                        year,
                        SUM(times_shown) as times_shown,
                        MAX(unique_days) as unique_days
                    FROM analytics_rotations
                    WHERE (year*12 + rotation_month) >= (YEAR(DATE_SUB(CURDATE(), INTERVAL $months MONTH))*12 + MONTH(DATE_SUB(CURDATE(), INTERVAL $months MONTH)))
                    GROUP BY page_slug, year, rotation_month
                ) ar
                LEFT JOIN (
                    SELECT 
                        page_slug,
                        year,
                        month,
                        SUM(total_visits) as total_visits,
                        SUM(total_clicks) as total_clicks,
                        SUM(total_phone_calls) as total_phone_calls
                    FROM analytics_monthly
                    GROUP BY page_slug, year, month
                ) am ON 
                    ar.page_slug = am.page_slug AND 
                    ar.year = am.year AND 
                    ar.rotation_month = am.month
                LEFT JOIN pages p ON ar.page_slug = p.slug
                ORDER BY ar.year DESC, ar.rotation_month DESC, ar.times_shown DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Get daily rotation data for charts (within a specific month)
     */
    public function getRotationDailyData($pageSlug, $year, $month) {
        $year = (int)$year;
        $month = (int)$month;
        $pageSlug = (string)$pageSlug;

        $sql = "SELECT 
                    a.date,
                    SUM(a.visits) as visits,
                    SUM(a.clicks) as clicks,
                    SUM(a.phone_calls) as phone_calls
                FROM analytics a
                WHERE a.page_slug = ?
                    AND YEAR(a.date) = ?
                    AND MONTH(a.date) = ?
                GROUP BY a.date
                ORDER BY a.date ASC";

        return $this->db->fetchAll($sql, [$pageSlug, $year, $month]);
    }

    /**
     * Get daily rotation data for charts (last N days)
     */
    public function getRotationDailyDataLast($pageSlug, $days = 30) {
        $days = (int)$days;
        $pageSlug = (string)$pageSlug;

        $sql = "SELECT 
                    a.date,
                    SUM(a.visits) as visits,
                    SUM(a.clicks) as clicks,
                    SUM(a.phone_calls) as phone_calls
                FROM analytics a
                WHERE a.page_slug = ?
                    AND a.date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                GROUP BY a.date
                ORDER BY a.date ASC";

        return $this->db->fetchAll($sql, [$pageSlug]);
    }

    /**
     * Get weekly rotation data for charts (last N months)
     */
    public function getRotationWeeklyDataLast($pageSlug, $months = 3) {
        $months = (int)$months;
        $pageSlug = (string)$pageSlug;

        $sql = "SELECT 
                    DATE(DATE_SUB(a.date, INTERVAL WEEKDAY(a.date) DAY)) as week_start,
                    SUM(a.visits) as visits,
                    SUM(a.clicks) as clicks,
                    SUM(a.phone_calls) as phone_calls
                FROM analytics a
                WHERE a.page_slug = ?
                    AND a.date >= DATE_SUB(CURDATE(), INTERVAL $months MONTH)
                GROUP BY YEARWEEK(a.date, 1)
                ORDER BY week_start ASC";

        return $this->db->fetchAll($sql, [$pageSlug]);
    }

    /**
     * Get monthly rotation data for charts (last N months)
     */
    public function getRotationMonthlyDataLast($pageSlug, $months = 12) {
        $months = (int)$months;
        $pageSlug = (string)$pageSlug;

        $sql = "SELECT 
                    DATE_FORMAT(a.date, '%Y-%m-01') as month_start,
                    YEAR(a.date) as year,
                    MONTH(a.date) as month,
                    SUM(a.visits) as visits,
                    SUM(a.clicks) as clicks,
                    SUM(a.phone_calls) as phone_calls
                FROM analytics a
                WHERE a.page_slug = ?
                    AND a.date >= DATE_SUB(CURDATE(), INTERVAL $months MONTH)
                GROUP BY YEAR(a.date), MONTH(a.date)
                ORDER BY a.date ASC";

        return $this->db->fetchAll($sql, [$pageSlug]);
    }

    /**
     * Track which rotation was shown
     */
    public function trackRotationShown($slug, $rotationMonth, $language) {
        try {
            $year = date('Y');
            $date = date('Y-m-d');
            
            $sql = "INSERT INTO analytics_rotations 
                    (page_slug, year, rotation_month, language, times_shown, unique_days, last_shown) 
                    VALUES (?, ?, ?, ?, 1, 1, ?)
                    ON DUPLICATE KEY UPDATE 
                    times_shown = times_shown + 1,
                    unique_days = IF(DATE(last_shown) != DATE(?), unique_days + 1, unique_days),
                    last_shown = ?";
                            
            $this->db->query($sql, [$slug, $year, $rotationMonth, $language, $date, $date, $date]);
        } catch (Exception $e) {
            error_log("Rotation tracking error: " . $e->getMessage());
        }
    }

    /**
     * Get comparison between rotation and base content performance
     */
    public function getRotationComparison($pageSlug, $months = 3) {
        $months = (int)$months;

        $sql = "SELECT 
                    am.month,
                    am.year,
                    am.total_visits,
                    am.total_clicks,
                    COALESCE(ar.times_shown, 0) as times_shown
                FROM (
                    SELECT 
                        year,
                        month,
                        SUM(total_visits) as total_visits,
                        SUM(total_clicks) as total_clicks
                    FROM analytics_monthly
                    WHERE page_slug = ?
                      AND (year*12 + month) >= (YEAR(DATE_SUB(CURDATE(), INTERVAL $months MONTH))*12 + MONTH(DATE_SUB(CURDATE(), INTERVAL $months MONTH)))
                    GROUP BY year, month
                ) am
                LEFT JOIN (
                    SELECT 
                        year,
                        rotation_month,
                        SUM(times_shown) as times_shown
                    FROM analytics_rotations
                    WHERE page_slug = ?
                    GROUP BY year, rotation_month
                ) ar ON 
                    am.year = ar.year AND 
                    am.month = ar.rotation_month
                ORDER BY am.year DESC, am.month DESC";

        return $this->db->fetchAll($sql, [$pageSlug, $pageSlug]);
    }

    /**
     * Get rotation impact metrics for dashboard insight card
     * Shows the actual impact of content rotation vs static pages
     */
     public function getRotationImpact($months = 3) {
        $months = AnalyticsQuery::clampMonths($months);
        
        // Get phone call CTR for pages WITH rotation enabled
        $rotationCtrSql = "SELECT 
                AVG(CASE WHEN am.total_visits > 0 
                    THEN (am.total_phone_calls / am.total_visits) * 100 
                    ELSE 0 END) as avg_ctr,
                COUNT(DISTINCT am.page_slug) as pages_count
            FROM analytics_monthly am
            JOIN pages p ON am.page_slug = p.slug
            WHERE p.enable_rotation = 1
            AND (am.year*12 + am.month) >= (YEAR(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH))*12 + MONTH(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)))";
        
        $rotationData = $this->db->fetchOne($rotationCtrSql);
        
        $rotationCtr = $rotationData['avg_ctr'] ?? 0;

        return [
            'pages_with_rotation' => $rotationData['pages_count'] ?? 0,
            'avg_engagement' => round($rotationCtr, 1)
        ];
    }

    /**
     * Get quick rotation stats for dashboard insight card
     */
    public function getRotationStats($months = 3) {
        $months = AnalyticsQuery::clampMonths($months);
        
        $sql = "SELECT 
                    COUNT(DISTINCT page_slug) as active_rotations,
                    SUM(times_shown) as total_shows,
                    (SELECT page_slug FROM analytics_rotations 
                     WHERE (year*12 + rotation_month) >= (YEAR(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH))*12 + MONTH(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)))
                     GROUP BY page_slug ORDER BY SUM(times_shown) DESC LIMIT 1) as top_page
                FROM analytics_rotations
                WHERE (year*12 + rotation_month) >= (YEAR(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH))*12 + MONTH(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)))";
        
        return $this->db->fetchOne($sql) ?: [
            'active_rotations' => 0,
            'total_shows' => 0,
            'top_page' => 'N/A'
        ];
    }
}
