<?php
// path: ./models/Analytics.php

class Analytics {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getMonthlyData($months = 6) {
        $months = (int)$months;

        $sql = "SELECT year, month, SUM(total_visits) as visits, SUM(total_clicks) as clicks, SUM(total_phone_calls) as phone_calls
                FROM analytics_monthly
                WHERE DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL $months MONTH)
                GROUP BY year, month
                ORDER BY year DESC, month DESC";

        return $this->db->fetchAll($sql);
    }

    public function getPageStats($months = 6) {
        $months = (int)$months;

        $sql = "SELECT page_slug, language, SUM(total_visits) as visits, SUM(total_clicks) as clicks, SUM(total_phone_calls) as phone_calls
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
                    SUM(total_phone_calls) as total_phone_calls,
                    COUNT(DISTINCT page_slug) as unique_pages
                FROM analytics_monthly";
        
        return $this->db->fetchOne($sql);
    }

    public function getCurrentMonthStats() {
        $year = date('Y');
        $month = date('n');
        
        $sql = "SELECT 
                    SUM(total_visits) as visits,
                    SUM(total_clicks) as clicks,
                    SUM(total_phone_calls) as phone_calls
                FROM analytics_monthly
                WHERE year = ? AND month = ?";
        
        return $this->db->fetchOne($sql, [$year, $month]);
    }

    public function getChartData($type = 'visits', $months = 6) {
        $data = $this->getMonthlyData($months);
        
        $result = [];
        
        foreach (array_reverse($data) as $row) {
            $monthName = date('M Y', strtotime("{$row['year']}-{$row['month']}-01"));
            $result[$monthName] = (int)($row[$type] ?? 0);
        }
        
        return $result;
    }

    /**
     * Get daily aggregated data for charts
     */
    public function getDailyChartData($type = 'visits', $months = 1) {
        $months = (int)$months;
        $field = ($type === 'visits') ? 'visits' : (($type === 'phone_calls') ? 'phone_calls' : 'clicks');
        
        $sql = "SELECT 
                    date,
                    SUM($field) as value
                FROM analytics
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY date
                ORDER BY date ASC";
        
        $data = $this->db->fetchAll($sql, [$months]);
        
        $result = [];
        foreach ($data as $row) {
            $dateLabel = date('M j', strtotime($row['date']));
            $result[$dateLabel] = (int)($row['value'] ?? 0);
        }
        
        return $result;
    }

    /**
     * Get weekly aggregated data for charts
     */
    public function getWeeklyChartData($type = 'visits', $months = 3) {
        $months = (int)$months;
        $field = ($type === 'visits') ? 'visits' : (($type === 'phone_calls') ? 'phone_calls' : 'clicks');
        
        $sql = "SELECT 
                    YEARWEEK(date, 1) as week_year,
                    DATE(DATE_SUB(date, INTERVAL WEEKDAY(date) DAY)) as week_start,
                    SUM($field) as value
                FROM analytics
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY YEARWEEK(date, 1), week_start
                ORDER BY week_start ASC";
        
        $data = $this->db->fetchAll($sql, [$months]);
        
        $result = [];
        foreach ($data as $row) {
            $weekLabel = date('M j', strtotime($row['week_start']));
            $result[$weekLabel] = (int)($row['value'] ?? 0);
        }
        
        return $result;
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
                    WHERE DATE(CONCAT(year, '-', rotation_month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL $months MONTH)
                    GROUP BY page_slug, year, rotation_month
                ) ar
                LEFT JOIN (
                    SELECT 
                        page_slug,
                        year,
                        month,
                        SUM(total_visits) as total_visits,
                        SUM(total_clicks) as total_clicks
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
                    MAX(visit_date) as last_visit
                FROM analytics_bot_visits
                WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                GROUP BY page_slug, bot_type
                ORDER BY days_with_visits DESC, total_visits DESC";

        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get daily bot activity for chart
     */
    public function getDailyBotActivity($days = 30) {
        $days = (int)$days;
        
        $sql = "SELECT 
                    visit_date,
                    bot_type,
                    SUM(visits) as visits
                FROM analytics_bot_visits
                WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY visit_date, bot_type
                ORDER BY visit_date ASC";
                
        $data = $this->db->fetchAll($sql, [$days]);
        
        return $data;
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
                    COALESCE(ar.times_shown, 0) as times_shown
                FROM (
                    SELECT 
                        year,
                        month,
                        SUM(total_visits) as total_visits,
                        SUM(total_clicks) as total_clicks
                    FROM analytics_monthly
                    WHERE page_slug = ?
                      AND DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL $months MONTH)
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
                FROM (
                    SELECT 
                        from_slug,
                        to_slug,
                        language,
                        year,
                        month,
                        SUM(total_clicks) as total_clicks
                    FROM analytics_internal_links_monthly
                    WHERE DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                    GROUP BY from_slug, to_slug, language, year, month
                ) il
                LEFT JOIN (
                    SELECT 
                        page_slug,
                        language,
                        year,
                        month,
                        SUM(total_visits) as total_visits
                    FROM analytics_monthly
                    GROUP BY page_slug, language, year, month
                ) am ON 
                    il.from_slug = am.page_slug AND 
                    il.year = am.year AND 
                    il.month = am.month AND
                    il.language = am.language
                GROUP BY il.from_slug, il.to_slug
                HAVING link_clicks > 5
                ORDER BY click_through_rate DESC
                LIMIT 50";
        
        return $this->db->fetchAll($sql, [$months]);
    }

    /**
     * Get CTR distribution stats for chart
     */
    public function getLinkEffectivenessStats($months = 3) {
        $data = $this->getLinkEffectiveness($months);
        
        $distribution = [
            'poor' => 0,      // < 2%
            'fair' => 0,      // 2-5%
            'good' => 0,      // 5-10%
            'excellent' => 0  // > 10%
        ];
        
        foreach ($data as $row) {
            $ctr = (float)$row['click_through_rate'];
            if ($ctr >= 10) $distribution['excellent']++;
            elseif ($ctr >= 5) $distribution['good']++;
            elseif ($ctr >= 2) $distribution['fair']++;
            else $distribution['poor']++;
        }
        
        return $distribution;
    }

    /**
     * Get top navigation flows for visualization
     */
    public function getNavigationFlow($days = 30) {
        // Get most common source -> dest pairs
        $sql = "SELECT 
                    from_slug, 
                    to_slug, 
                    SUM(clicks) as weight
                FROM analytics_internal_links
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY from_slug, to_slug
                ORDER BY weight DESC
                LIMIT 10";
                
        return $this->db->fetchAll($sql, [$days]);
    }

    /**
     * Get monthly trend of internal link clicks
     */
    public function getDailyNavigationTrends($days = 30) {
        $days = (int)$days;
        
        $sql = "SELECT 
                    date,
                    SUM(clicks) as clicks
                FROM analytics_internal_links
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY date
                ORDER BY date ASC";
                
        $data = $this->db->fetchAll($sql, [$days]);
        
        $labels = [];
        $values = [];
        
        foreach ($data as $row) {
            $labels[] = date('M j', strtotime($row['date']));
            $values[] = (int)$row['clicks'];
        }
        
        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

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

    /**
     * Get rotation impact metrics for dashboard insight card
     * Shows the actual impact of content rotation vs static pages
     */
    public function getRotationImpact($months = 3) {
        $months = (int)$months;
        
        // Get CTR for pages WITH rotation enabled
        $rotationCtrSql = "SELECT 
                AVG(CASE WHEN am.total_visits > 0 
                    THEN (am.total_clicks / am.total_visits) * 100 
                    ELSE 0 END) as avg_ctr,
                COUNT(DISTINCT am.page_slug) as pages_count
            FROM analytics_monthly am
            JOIN pages p ON am.page_slug = p.slug
            WHERE p.enable_rotation = 1
            AND DATE(CONCAT(am.year, '-', am.month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)";
        
        $rotationData = $this->db->fetchOne($rotationCtrSql, [$months]);
        
        // Get CTR for pages WITHOUT rotation (for comparison)
        $noRotationCtrSql = "SELECT 
                AVG(CASE WHEN am.total_visits > 0 
                    THEN (am.total_clicks / am.total_visits) * 100 
                    ELSE 0 END) as avg_ctr
            FROM analytics_monthly am
            JOIN pages p ON am.page_slug = p.slug
            WHERE p.enable_rotation = 0
            AND DATE(CONCAT(am.year, '-', am.month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)";
        
        $noRotationData = $this->db->fetchOne($noRotationCtrSql, [$months]);
        
        $rotationCtr = $rotationData['avg_ctr'] ?? 0;
        $noRotationCtr = $noRotationData['avg_ctr'] ?? 0;
        
        // Calculate improvement
        $improvement = $noRotationCtr > 0 
            ? round((($rotationCtr - $noRotationCtr) / $noRotationCtr) * 100, 1)
            : 0;
        
        return [
            'ctr_improvement' => $improvement,
            'pages_with_rotation' => $rotationData['pages_count'] ?? 0,
            'avg_engagement' => round($rotationCtr, 1)
        ];
    }

    /**
     * Get quick rotation stats for dashboard insight card
     */
    public function getRotationStats($months = 3) {
        $months = (int)$months;
        
        $sql = "SELECT 
                    COUNT(DISTINCT page_slug) as active_rotations,
                    SUM(times_shown) as total_shows,
                    (SELECT page_slug FROM analytics_rotations 
                     WHERE DATE(CONCAT(year, '-', rotation_month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                     GROUP BY page_slug ORDER BY SUM(times_shown) DESC LIMIT 1) as top_page
                FROM analytics_rotations
                WHERE DATE(CONCAT(year, '-', rotation_month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)";
        
        return $this->db->fetchOne($sql, [$months, $months]) ?: [
            'active_rotations' => 0,
            'total_shows' => 0,
            'top_page' => 'N/A'
        ];
    }

    /**
     * Get quick navigation stats for dashboard insight card
     */
    public function getNavigationStats($months = 3) {
        $months = (int)$months;
        
        $sql = "SELECT 
                    SUM(total_clicks) as total_clicks,
                    CONCAT(from_slug, ' → ', to_slug) as top_path
                FROM analytics_internal_links_monthly
                WHERE DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY from_slug, to_slug
                ORDER BY total_clicks DESC
                LIMIT 1";
        
        $topPath = $this->db->fetchOne($sql, [$months]);
        
        // Get total clicks and average CTR
        $statsSql = "SELECT 
                        SUM(ailm.total_clicks) as total_clicks,
                        AVG(
                            CASE WHEN am.total_visits > 0 
                            THEN (ailm.total_clicks / am.total_visits) * 100 
                            ELSE 0 END
                        ) as avg_ctr
                    FROM analytics_internal_links_monthly ailm
                    LEFT JOIN analytics_monthly am ON  
                        ailm.from_slug = am.page_slug AND 
                        ailm.year = am.year AND 
                        ailm.month = am.month
                    WHERE DATE(CONCAT(ailm.year, '-', ailm.month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)";
        
        $stats = $this->db->fetchOne($statsSql, [$months]);
        
        return [
            'total_clicks' => $stats['total_clicks'] ?? 0,
            'avg_ctr' => round($stats['avg_ctr'] ?? 0, 1),
            'top_path' => $topPath['top_path'] ?? 'N/A'
        ];
    }

    /**
     * Get quick crawl insights for dashboard insight card
     */
    public function getCrawlInsights($days = 7) {
        $days = (int)$days;
        
        $sql = "SELECT 
                    COUNT(DISTINCT page_slug) as pages_crawled,
                    bot_type as top_bot
                FROM analytics_bot_visits
                WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY bot_type
                ORDER BY SUM(visits) DESC
                LIMIT 1";
        
        $data = $this->db->fetchOne($sql, [$days]);
        
        // Get stale pages (not crawled in last $days)
        $staleSql = "SELECT COUNT(DISTINCT page_slug) as stale_count
                     FROM analytics_bot_visits abv1
                     WHERE NOT EXISTS (
                         SELECT 1 FROM analytics_bot_visits abv2
                         WHERE abv2.page_slug = abv1.page_slug
                         AND abv2.visit_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                     )";
        
        $stale = $this->db->fetchOne($staleSql, [$days]);
        
        return [
            'pages_crawled' => $data['pages_crawled'] ?? 0,
            'top_bot' => ucfirst($data['top_bot'] ?? 'N/A'),
            'stale_pages' => $stale['stale_count'] ?? 0
        ];
    }
}
