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
     * Get crawl frequency per slug from BOT visits only
     */
    public function getCrawlFrequency($days = 30) {
        $days = (int)$days;

        $sql = "SELECT 
                    page_slug,
                    bot_type,
                    COUNT(DISTINCT visit_date) as days_with_visits,
                    SUM(visits) as total_visits,
                    AVG(visits) as avg_visits_per_day,
                    MAX(visit_date) as last_visit,
                    MAX(last_visit) as last_visit_time
                FROM analytics_bot_visits
                WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                GROUP BY page_slug, bot_type
                ORDER BY days_with_visits DESC, total_visits DESC";

        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get bot visit summary
     */
    public function getBotVisitSummary($days = 30) {
        $days = (int)$days;
        
        $sql = "SELECT 
                    bot_type,
                    COUNT(DISTINCT page_slug) as pages_visited,
                    SUM(visits) as total_visits,
                    COUNT(DISTINCT visit_date) as active_days
                FROM analytics_bot_visits
                WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                GROUP BY bot_type
                ORDER BY total_visits DESC";
        
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

        $paramsCurrent = [];
        $paramsPrevious = [];

        $whereCurrent = [];
        $wherePrevious = [];

        if ($pageSlug !== null) {
            $whereCurrent[] = "page_slug = ?";
            $wherePrevious[] = "page_slug = ?";
            $paramsCurrent[] = $pageSlug;
            $paramsPrevious[] = $pageSlug;
        }

        $whereCurrent[] = "year = ?";
        $whereCurrent[] = "month = ?";
        $paramsCurrent[] = $currentYear;
        $paramsCurrent[] = $currentMonth;

        $wherePrevious[] = "year = ?";
        $wherePrevious[] = "month = ?";
        $paramsPrevious[] = $lastMonthYear;
        $paramsPrevious[] = $lastMonth;

        $sql = "
            SELECT 'current' as period, SUM(total_visits) as visits, SUM(total_clicks) as clicks
            FROM analytics_monthly
            WHERE " . implode(' AND ', $whereCurrent) . "
            UNION ALL
            SELECT 'previous' as period, SUM(total_visits) as visits, SUM(total_clicks) as clicks
            FROM analytics_monthly
            WHERE " . implode(' AND ', $wherePrevious);

        $params = array_merge($paramsCurrent, $paramsPrevious);

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
                'visits' => $previous['visits'] > 0
                    ? round((($current['visits'] - $previous['visits']) / $previous['visits']) * 100, 1)
                    : 0,
                'clicks' => $previous['clicks'] > 0
                    ? round((($current['clicks'] - $previous['clicks']) / $previous['clicks']) * 100, 1)
                    : 0
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

    public function getNavigationFlow($days = 30) {
        $sql = "SELECT 
                    from_slug,
                    to_slug,
                    language,
                    SUM(clicks) as total_clicks,
                    COUNT(DISTINCT date) as active_days,
                    MAX(date) as last_click_date
                FROM analytics_internal_links
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY from_slug, to_slug, language
                HAVING total_clicks > 2
                ORDER BY total_clicks DESC
                LIMIT 100";
        
        return $this->db->fetchAll($sql, [$days]);
    }

    /**
     * Get most popular navigation paths
     */
    public function getPopularPaths($months = 3, $limit = 20) {
        $sql = "SELECT 
                    from_slug,
                    to_slug,
                    SUM(total_clicks) as clicks,
                    COUNT(DISTINCT CONCAT(year, '-', month)) as active_months
                FROM analytics_internal_links_monthly
                WHERE DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY from_slug, to_slug
                ORDER BY clicks DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$months, $limit]);
    }

    /**
     * Get outbound links from a specific page
     */
    public function getOutboundLinks($slug, $months = 3) {
        $sql = "SELECT 
                    to_slug,
                    language,
                    SUM(total_clicks) as clicks
                FROM analytics_internal_links_monthly
                WHERE from_slug = ?
                  AND DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY to_slug, language
                ORDER BY clicks DESC";
        
        return $this->db->fetchAll($sql, [$slug, $months]);
    }


    public function getInboundLinks($slug, $months = 3) {
        $sql = "SELECT 
                    from_slug,
                    language,
                    SUM(total_clicks) as clicks
                FROM analytics_internal_links_monthly
                WHERE to_slug = ?
                  AND DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY from_slug, language
                ORDER BY clicks DESC";
        
        return $this->db->fetchAll($sql, [$slug, $months]);
    }


    public function getLinkEffectiveness($months = 3) {
        $sql = "SELECT 
                    il.from_slug,
                    il.to_slug,
                    SUM(il.total_clicks) as link_clicks,
                    COALESCE(SUM(am.total_visits), 0) as from_page_visits,
                    CASE 
                        WHEN SUM(am.total_visits) > 0 
                        THEN ROUND((SUM(il.total_clicks) / SUM(am.total_visits)) * 100, 2)
                        ELSE 0 
                    END as click_through_rate
                FROM analytics_internal_links_monthly il
                LEFT JOIN analytics_monthly am ON 
                    il.from_slug = am.page_slug AND 
                    il.year = am.year AND 
                    il.month = am.month AND
                    il.language = am.language
                WHERE DATE(CONCAT(il.year, '-', il.month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY il.from_slug, il.to_slug
                HAVING link_clicks > 5
                ORDER BY click_through_rate DESC
                LIMIT 50";
        
        return $this->db->fetchAll($sql, [$months]);
    }


    public function getNavigationFunnels($months = 3) {
        // This gets sequential navigation patterns
        $sql = "SELECT 
                    l1.from_slug as step1,
                    l1.to_slug as step2,
                    l2.to_slug as step3,
                    COUNT(*) as occurrences
                FROM analytics_internal_links_monthly l1
                LEFT JOIN analytics_internal_links_monthly l2 ON 
                    l1.to_slug = l2.from_slug AND
                    l1.year = l2.year AND
                    l1.month = l2.month AND
                    l1.language = l2.language
                WHERE DATE(CONCAT(l1.year, '-', l1.month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                  AND l2.to_slug IS NOT NULL
                GROUP BY step1, step2, step3
                HAVING occurrences > 3
                ORDER BY occurrences DESC
                LIMIT 30";
        
        return $this->db->fetchAll($sql, [$months]);
    }
    public function getHourlyActivity($days = 7) {
        $days = (int)$days;
        
        $sql = "SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as activity_count
                FROM (
                    SELECT created_at FROM analytics 
                    WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                    UNION ALL
                    SELECT created_at FROM analytics_internal_links
                    WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ) combined
                GROUP BY hour
                ORDER BY hour";
        
        $results = $this->db->fetchAll($sql, [$days, $days]);
        
        // Fill in missing hours
        $hourlyData = array_fill(0, 24, 0);
        foreach ($results as $row) {
            $hourlyData[(int)$row['hour']] = (int)$row['activity_count'];
        }
        
        return [
            'hours' => array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23)),
            'values' => array_values($hourlyData)
        ];
    }

    /**
     * Get conversion funnel data
     */
    public function getConversionFunnel($months = 3) {
        $months = (int)$months;
        
        // Get users who visited 2+ pages
        $sql = "SELECT COUNT(DISTINCT page_slug) as engaged_count
                FROM analytics
                WHERE DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY CONCAT(page_slug, language)
                HAVING engaged_count >= 2";
        
        $engaged = $this->db->fetchAll($sql, [$months]);
        
        return [
            'engaged' => count($engaged)
        ];
    }

    /**
     * Get page load speed stats (if tracking)
     */
    public function getPageSpeed($months = 1) {
        $months = (int)$months;
        
        $sql = "SELECT 
                    page_slug,
                    AVG(avg_time_seconds) as avg_load_time,
                    COUNT(*) as sample_size
                FROM analytics
                WHERE DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                  AND avg_time_seconds > 0
                GROUP BY page_slug
                ORDER BY avg_load_time DESC
                LIMIT 10";
        
        return $this->db->fetchAll($sql, [$months]);
    }

    /**
     * Get bounce rate by page
     */
    public function getBounceRates($months = 3) {
        $months = (int)$months;
        
        $sql = "SELECT 
                    page_slug,
                    language,
                    AVG(bounce_rate) as avg_bounce,
                    SUM(visits) as total_visits
                FROM analytics
                WHERE DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY page_slug, language
                HAVING total_visits > 10
                ORDER BY avg_bounce DESC
                LIMIT 10";
        
        return $this->db->fetchAll($sql, [$months]);
    }

    /**
     * Get growth trends (month over month)
     */
    public function getGrowthTrends($months = 6) {
        $months = (int)$months;
        
        $sql = "SELECT 
                    year,
                    month,
                    SUM(total_visits) as visits,
                    SUM(total_clicks) as clicks,
                    LAG(SUM(total_visits)) OVER (ORDER BY year, month) as prev_visits,
                    LAG(SUM(total_clicks)) OVER (ORDER BY year, month) as prev_clicks
                FROM analytics_monthly
                WHERE DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY year, month
                ORDER BY year, month";
        
        $results = $this->db->fetchAll($sql, [$months]);
        
        // Calculate growth percentages
        foreach ($results as &$row) {
            if ($row['prev_visits'] > 0) {
                $row['visits_growth'] = round((($row['visits'] - $row['prev_visits']) / $row['prev_visits']) * 100, 1);
            } else {
                $row['visits_growth'] = 0;
            }
            
            if ($row['prev_clicks'] > 0) {
                $row['clicks_growth'] = round((($row['clicks'] - $row['prev_clicks']) / $row['prev_clicks']) * 100, 1);
            } else {
                $row['clicks_growth'] = 0;
            }
        }
        
        return $results;
    }
}
    