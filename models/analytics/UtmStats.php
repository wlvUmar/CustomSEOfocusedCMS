<?php
// models/analytics/UtmStats.php
// UTM attribution plus slug/utm-filtered period-over-period performance comparisons.

class UtmStats {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get clicks and phone calls by utm_source for a date range
     */
    public function getUtmSourceStats($startDate, $endDate, $slug = '', $utmSource = '') {
        $sql = "SELECT 
                    IFNULL(NULLIF(utm_source, ''), 'direct') as utm_source,
                    SUM(visits) as visits,
                    SUM(clicks) as clicks,
                    SUM(phone_calls) as phone_calls,
                    COUNT(DISTINCT page_slug) as pages_affected
                FROM analytics_hourly
                WHERE date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY utm_source
                ORDER BY SUM(visits) DESC";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get utm_source stats by page
     */
    public function getUtmSourceByPage($startDate, $endDate, $pageSlug = null) {
        $params = [$startDate, $endDate];
        $whereClause = "WHERE date BETWEEN ? AND ?";
        
        if ($pageSlug) {
            $whereClause .= " AND page_slug = ?";
            $params[] = $pageSlug;
        }
        
        $sql = "SELECT 
                    page_slug,
                    IFNULL(NULLIF(utm_source, ''), 'direct') as utm_source,
                    language,
                    SUM(clicks) as clicks,
                    SUM(phone_calls) as phone_calls
                FROM analytics
                $whereClause
                GROUP BY page_slug, utm_source, language
                ORDER BY (clicks + phone_calls) DESC";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get monthly utm_source trends
     */
    public function getUtmSourceMonthlyTrends($months = 6) {
        $months = AnalyticsQuery::clampMonths($months);
        
        $sql = "SELECT 
                    year,
                    month,
                    IFNULL(NULLIF(utm_source, ''), 'direct') as utm_source,
                    SUM(total_clicks) as clicks,
                    SUM(total_phone_calls) as phone_calls
                FROM analytics_monthly
                WHERE (year*12 + month) >= (YEAR(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH))*12 + MONTH(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)))
                GROUP BY year, month, utm_source
                ORDER BY year DESC, month DESC, clicks DESC";
        
        return $this->db->fetchAll($sql);
    }

    /**
     * Get top utm sources for dashboard
     */
    public function getTopUtmSources($limit = 5, $days = 30, $slug = '', $utmSource = '') {
        $days = max(1, min(365, (int)$days));
        
        $sql = "SELECT 
                    IFNULL(NULLIF(utm_source, ''), 'direct') as utm_source,
                    SUM(visits) as visits,
                    SUM(clicks) as clicks,
                    SUM(phone_calls) as phone_calls,
                    COUNT(DISTINCT page_slug) as pages_affected
                FROM analytics_hourly
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)";
        $params = [];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY utm_source
                ORDER BY SUM(visits) DESC";
        
        if (!empty($limit)) {
            $limit = max(1, min(100, (int)$limit));
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get utm_source performance comparison
     */
    public function getUtmSourceComparison($startDate, $endDate, $slug = '', $utmSource = '') {
        $sql = "SELECT 
                    IFNULL(NULLIF(utm_source, ''), 'direct') as utm_source,
                    SUM(visits) as visits,
                    SUM(clicks) as clicks,
                    SUM(phone_calls) as phone_calls,
                    CASE 
                        WHEN SUM(visits) > 0 THEN ROUND((SUM(clicks) / SUM(visits)) * 100, 2)
                        ELSE 0 
                    END as click_rate,
                    CASE 
                        WHEN SUM(visits) > 0 THEN ROUND((SUM(phone_calls) / SUM(visits)) * 100, 2)
                        ELSE 0 
                    END as phone_rate
                FROM analytics_hourly
                WHERE date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY utm_source
                ORDER BY phone_calls DESC";
        
        return $this->db->fetchAll($sql, $params);
    }

    public function getPerformanceTrends($pageSlug = null, $slug = '', $utmSource = '', $months = null) {
        if ($months !== null) {
            $months = max(1, (int)$months);
            $today = new DateTime('today');
            $start = (clone $today)->modify('-' . $months . ' months')->format('Y-m-d');
            $end = $today->format('Y-m-d');
            return $this->getPerformanceTrendsByDateRange($start, $end, $pageSlug, $slug, $utmSource);
        }

        $currentMonth = date('n');
        $currentYear = date('Y');
        $lastMonth = date('n', strtotime('-1 month'));
        $lastMonthYear = date('Y', strtotime('-1 month'));

        $paramsCurrent = [];
        $paramsPrevious = [];

        $whereCurrent = [];
        $wherePrevious = [];

        AnalyticsQuery::pageConds($whereCurrent, $paramsCurrent, $slug, $pageSlug);
        AnalyticsQuery::pageConds($wherePrevious, $paramsPrevious, $slug, $pageSlug);
        AnalyticsQuery::utmConds($whereCurrent, $paramsCurrent, $utmSource);
        AnalyticsQuery::utmConds($wherePrevious, $paramsPrevious, $utmSource);

        $whereCurrent[] = "YEAR(date) = ?";
        $whereCurrent[] = "MONTH(date) = ?";
        $paramsCurrent[] = $currentYear;
        $paramsCurrent[] = $currentMonth;

        $wherePrevious[] = "YEAR(date) = ?";
        $wherePrevious[] = "MONTH(date) = ?";
        $paramsPrevious[] = $lastMonthYear;
        $paramsPrevious[] = $lastMonth;

        $sql = "
            SELECT 'current' as period, SUM(visits) as visits, SUM(clicks) as clicks, SUM(phone_calls) as phone_calls
            FROM analytics_hourly
            WHERE " . implode(' AND ', $whereCurrent) . "
            UNION ALL
            SELECT 'previous' as period, SUM(visits) as visits, SUM(clicks) as clicks, SUM(phone_calls) as phone_calls
            FROM analytics_hourly
            WHERE " . implode(' AND ', $wherePrevious);

        $params = array_merge($paramsCurrent, $paramsPrevious);

        $results = $this->db->fetchAll($sql, $params);

        $current = ['visits' => 0, 'clicks' => 0, 'phone_calls' => 0];
        $previous = ['visits' => 0, 'clicks' => 0, 'phone_calls' => 0];

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
                    : 0,
                'phone_calls' => $previous['phone_calls'] > 0
                    ? round((($current['phone_calls'] - $previous['phone_calls']) / $previous['phone_calls']) * 100, 1)
                    : 0,
                'ctr' => $this->ctrChange($current, $previous)
            ]
        ];
    }

    public function getPerformanceTrendsByDateRange($startDate, $endDate, $pageSlug = null, $slug = '', $utmSource = '') {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $days = (int)$start->diff($end)->format('%a') + 1;

        $prevEnd = (clone $start)->modify('-1 day');
        $prevStart = (clone $prevEnd)->modify('-' . ($days - 1) . ' days');

        $paramsCurrent = [$start->format('Y-m-d'), $end->format('Y-m-d')];
        $paramsPrevious = [$prevStart->format('Y-m-d'), $prevEnd->format('Y-m-d')];

        $whereCurrent = ["date BETWEEN ? AND ?"];
        $wherePrevious = ["date BETWEEN ? AND ?"];

        AnalyticsQuery::pageConds($whereCurrent, $paramsCurrent, $slug, $pageSlug);
        AnalyticsQuery::pageConds($wherePrevious, $paramsPrevious, $slug, $pageSlug);
        AnalyticsQuery::utmConds($whereCurrent, $paramsCurrent, $utmSource);
        AnalyticsQuery::utmConds($wherePrevious, $paramsPrevious, $utmSource);

        $sql = "
            SELECT 'current' as period, SUM(visits) as visits, SUM(clicks) as clicks, SUM(phone_calls) as phone_calls
            FROM analytics_hourly
            WHERE " . implode(' AND ', $whereCurrent) . "
            UNION ALL
            SELECT 'previous' as period, SUM(visits) as visits, SUM(clicks) as clicks, SUM(phone_calls) as phone_calls
            FROM analytics_hourly
            WHERE " . implode(' AND ', $wherePrevious);

        $params = array_merge($paramsCurrent, $paramsPrevious);

        $results = $this->db->fetchAll($sql, $params);

        $current = ['visits' => 0, 'clicks' => 0, 'phone_calls' => 0];
        $previous = ['visits' => 0, 'clicks' => 0, 'phone_calls' => 0];

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
                    : 0,
                'phone_calls' => $previous['phone_calls'] > 0
                    ? round((($current['phone_calls'] - $previous['phone_calls']) / $previous['phone_calls']) * 100, 1)
                    : 0,
                'ctr' => $this->ctrChange($current, $previous)
            ]
        ];
    }

    /**
     * Percentage-point difference in call CTR (phone_calls/visits * 100)
     * between the current and previous periods.
     */
    private function ctrChange($current, $previous) {
        $currentCtr = ($current['visits'] ?? 0) > 0
            ? (($current['phone_calls'] ?? 0) / $current['visits']) * 100
            : 0;
        $previousCtr = ($previous['visits'] ?? 0) > 0
            ? (($previous['phone_calls'] ?? 0) / $previous['visits']) * 100
            : 0;
        return round($currentCtr - $previousCtr, 2);
    }
}
