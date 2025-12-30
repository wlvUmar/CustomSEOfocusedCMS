<?php
// path: ./models/Analytics.php

class Analytics {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getMonthlyData($months = 6) {
        $months = (int)$months;

        $sql = "SELECT year, month, SUM(total_visits) as visits, SUM(total_clicks) as clicks
                FROM analytics_monthly
                WHERE DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL $months MONTH)
                GROUP BY year, month
                ORDER BY year DESC, month DESC";

        return $this->db->fetchAll($sql);
    }

    public function getPageStats($months = 6) {
        $months = (int)$months;

        $sql = "SELECT page_slug, language, SUM(total_visits) as visits, SUM(total_clicks) as clicks
                FROM analytics_monthly
                WHERE DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL $months MONTH)
                GROUP BY page_slug, language
                ORDER BY visits DESC";

        return $this->db->fetchAll($sql);
    }

    public function getTotalStats() {
        $sql = "SELECT 
                    SUM(total_visits) as total_visits,
                    SUM(total_clicks) as total_clicks,
                    COUNT(DISTINCT page_slug) as unique_pages
                FROM analytics_monthly";
        
        return $this->db->fetchOne($sql);
    }

    public function getCurrentMonthStats() {
        $year = date('Y');
        $month = date('n');
        
        $sql = "SELECT 
                    SUM(total_visits) as visits,
                    SUM(total_clicks) as clicks
                FROM analytics_monthly
                WHERE year = ? AND month = ?";
        
        return $this->db->fetchOne($sql, [$year, $month]);
    }

    public function getChartData($type = 'visits', $months = 6) {
        $data = $this->getMonthlyData($months);
        
        $labels = [];
        $values = [];
        
        foreach (array_reverse($data) as $row) {
            $monthName = date('M Y', strtotime("{$row['year']}-{$row['month']}-01"));
            $labels[] = $monthName;
            $values[] = $row[$type] ?? 0;
        }
        
        return [
            'labels' => $labels,
            'values' => $values
        ];
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
                    ar.times_shown,
                    ar.unique_days,
                    am.total_visits,
                    am.total_clicks,
                    p.title_ru
                FROM analytics_rotations ar
                LEFT JOIN analytics_monthly am ON 
                    ar.page_slug = am.page_slug AND 
                    ar.year = am.year AND 
                    ar.rotation_month = am.month
                LEFT JOIN pages p ON ar.page_slug = p.slug
                WHERE DATE(CONCAT(ar.year, '-', ar.rotation_month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL $months MONTH)
                ORDER BY ar.year DESC, ar.rotation_month DESC, ar.times_shown DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Get crawl frequency per slug
     */
    public function getCrawlFrequency($days = 30) {
        $days = (int)$days;

        $sql = "SELECT 
                    page_slug,
                    COUNT(DISTINCT date) as days_with_visits,
                    SUM(visits) as total_visits,
                    AVG(visits) as avg_visits_per_day,
                    MAX(date) as last_visit
                FROM analytics
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                GROUP BY page_slug
                ORDER BY days_with_visits DESC, total_visits DESC";

        return $this->db->fetchAll($sql);
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
                        unique_days = (
                            SELECT COUNT(DISTINCT DATE(last_shown))
                            FROM (SELECT last_shown FROM analytics_rotations WHERE page_slug = ? AND year = ? AND rotation_month = ?) as t
                        ) + 1,
                        last_shown = ?";
            
            $this->db->query($sql, [$slug, $year, $rotationMonth, $language, $date, $slug, $year, $rotationMonth, $date]);
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
                    ar.rotation_month,
                    ar.times_shown
                FROM analytics_monthly am
                LEFT JOIN analytics_rotations ar ON 
                    am.page_slug = ar.page_slug AND 
                    am.year = ar.year AND 
                    am.month = ar.rotation_month
                WHERE am.page_slug = ?
                  AND DATE(CONCAT(am.year, '-', am.month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL $months MONTH)
                ORDER BY am.year DESC, am.month DESC";

        return $this->db->fetchAll($sql, [$pageSlug]);
    }


    /**
     * Get performance trends - compare periods
     */
public function getPerformanceTrends($pageSlug = null) {
    $currentMonth = date('n');
    $currentYear = date('Y');
    $lastMonth = date('n', strtotime('-1 month'));
    $lastMonthYear = date('Y', strtotime('-1 month'));

    $conditions = [];
    $params = [];

    if ($pageSlug !== null) {
        $conditions[] = "page_slug = ?";
        $params[] = $pageSlug;
    }

    $conditions[] = "year = ?";
    $conditions[] = "month = ?";
    $params[] = $currentYear;
    $params[] = $currentMonth;

    $whereCurrent = 'WHERE ' . implode(' AND ', $conditions);

    // duplicate params for UNION
    $params[] = $pageSlug;
    $params[] = $lastMonthYear;
    $params[] = $lastMonth;

    $wherePrevious = $pageSlug !== null
        ? "WHERE page_slug = ? AND year = ? AND month = ?"
        : "WHERE year = ? AND month = ?";

    $sql = "
        SELECT 'current' as period, SUM(total_visits) as visits, SUM(total_clicks) as clicks
        FROM analytics_monthly
        $whereCurrent
        UNION ALL
        SELECT 'previous' as period, SUM(total_visits) as visits, SUM(total_clicks) as clicks
        FROM analytics_monthly
        $wherePrevious
    ";

    $results = $this->db->fetchAll($sql, $params);

    $current = ['visits' => 0, 'clicks' => 0];
    $previous = ['visits' => 0, 'clicks' => 0];

    foreach ($results as $row) {
        if ($row['period'] === 'current') {
            $current = $row;
        } else {
            $previous = $row;
        }
    }

    return [
        'current' => $current,
        'previous' => $previous,
        'changes' => [
            'visits' => $previous['visits'] > 0 ? round((($current['visits'] - $previous['visits']) / $previous['visits']) * 100, 1) : 0,
            'clicks' => $previous['clicks'] > 0 ? round((($current['clicks'] - $previous['clicks']) / $previous['clicks']) * 100, 1) : 0
        ]
    ];
}

    /**
     * Get daily activity for heat map visualization
     */
    public function getDailyActivity($pageSlug = null, $days = 30) {
        $days = (int)$days;

        $conditions = ["date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)"];
        $params = [];

        if ($pageSlug !== null) {
            $conditions[] = "page_slug = ?";
            $params[] = $pageSlug;
        }

        $sql = "SELECT 
                    date,
                    DAYOFWEEK(date) as day_of_week,
                    SUM(visits) as visits,
                    SUM(clicks) as clicks
                FROM analytics
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY date, day_of_week
                ORDER BY date DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get top performing pages by conversion rate
     */
    public function getTopPerformers($months = 3, $limit = 10) {
        $sql = "SELECT 
                    page_slug,
                    SUM(total_visits) as visits,
                    SUM(total_clicks) as clicks,
                    ROUND((SUM(total_clicks) / NULLIF(SUM(total_visits), 0)) * 100, 2) as ctr,
                    COUNT(DISTINCT CONCAT(year, '-', month)) as active_months
                FROM analytics_monthly
                WHERE DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY page_slug
                HAVING visits > 0
                ORDER BY ctr DESC, visits DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$months, $limit]);
    }

    /**
     * Get language preference statistics
     */
    public function getLanguageStats($months = 3) {
        $sql = "SELECT 
                    language,
                    SUM(total_visits) as visits,
                    SUM(total_clicks) as clicks,
                    COUNT(DISTINCT page_slug) as unique_pages
                FROM analytics_monthly
                WHERE DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY language";
        
        return $this->db->fetchAll($sql, [$months]);
    }
}