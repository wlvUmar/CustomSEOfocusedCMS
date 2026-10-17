<?php
// models/analytics/PageStats.php
// Page-level aggregates: monthly rollups, per-page/per-language totals, top performers, growth.

class PageStats {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getMonthlyData($months = 6, $slug = '', $utmSource = '') {
        $months = AnalyticsQuery::clampMonths($months);

        $sql = "SELECT YEAR(date) as year, MONTH(date) as month, SUM(visits) as visits, SUM(clicks) as clicks, SUM(phone_calls) as phone_calls
                FROM analytics_hourly
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)";
        $params = [];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY YEAR(date), MONTH(date) ORDER BY YEAR(date) DESC, MONTH(date) DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function getPageStats($months = 6, $slug = '', $utmSource = '') {
        $months = AnalyticsQuery::clampMonths($months);

        $sql = "SELECT page_slug, language, SUM(visits) as visits, SUM(clicks) as clicks, SUM(phone_calls) as phone_calls
                FROM analytics_hourly
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)";
        $params = [];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY page_slug, language ORDER BY visits DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function getRangePageStats($startDate, $endDate, $slug = '', $utmSource = '') {
        $sql = "SELECT 
                    page_slug,
                    language,
                    SUM(visits) as visits,
                    SUM(clicks) as clicks,
                    SUM(phone_calls) as phone_calls
                FROM analytics_hourly
                WHERE date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY page_slug, language ORDER BY visits DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function getTotalStats($months = null, $slug = '', $utmSource = '') {
        $sql = "SELECT 
                    SUM(visits) as total_visits,
                    SUM(clicks) as total_clicks,
                    SUM(phone_calls) as total_phone_calls,
                    COUNT(DISTINCT page_slug) as unique_pages
                FROM analytics_hourly";
        $params = [];
        $needsPrefix = true;
        if ($months !== null) {
            $months = AnalyticsQuery::clampMonths($months);
            $sql .= " WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)";
            $needsPrefix = false;
        }
        $tmp = [];
        $sql .= ($needsPrefix ? ' WHERE ' : ' AND ') . AnalyticsQuery::slugExpr($slug, $tmp);
        $params = array_merge($params, $tmp);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        
        return $this->db->fetchOne($sql, $params);
    }

    public function getRangeTotalStats($startDate, $endDate, $slug = '', $utmSource = '') {
        $sql = "SELECT 
                    SUM(visits) as total_visits,
                    SUM(clicks) as total_clicks,
                    SUM(phone_calls) as total_phone_calls,
                    COUNT(DISTINCT page_slug) as unique_pages
                FROM analytics_hourly
                WHERE date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);

        return $this->db->fetchOne($sql, $params);
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

    /**
     * Get top performing pages by conversion rate
     */
     public function getTopPerformers($months = 3, $limit = 500, $slug = '', $utmSource = '') {
        $months = AnalyticsQuery::clampMonths($months);
        $limit = max(1, min(500, (int)$limit));
        $utmFiltered = $utmSource !== '';
        $sql = "SELECT 
                    page_slug,
                    " . ($utmFiltered ? "IFNULL(NULLIF(utm_source, ''), 'direct') as utm_source," : "") . "
                    SUM(visits) as visits,
                    SUM(clicks) as clicks,
                    SUM(phone_calls) as phone_calls,
                    ROUND((SUM(phone_calls) / NULLIF(SUM(visits), 0)) * 100, 2) as ctr,
                    COUNT(DISTINCT DATE_FORMAT(date, '%Y-%m')) as active_months
                FROM analytics_hourly
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)";
        $params = [];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY " . ($utmFiltered ? "page_slug, utm_source" : "page_slug") . "
                HAVING visits > 0 OR phone_calls > 0
                ORDER BY ctr DESC, visits DESC
                LIMIT {$limit}";
        
        return $this->db->fetchAll($sql, $params);
    }

    public function getRangeTopPerformers($startDate, $endDate, $slug = '', $utmSource = '') {
        $utmFiltered = $utmSource !== '';
        $sql = "SELECT 
                    page_slug,
                    " . ($utmFiltered ? "IFNULL(NULLIF(utm_source, ''), 'direct') as utm_source," : "") . "
                    SUM(visits) as visits,
                    SUM(clicks) as clicks,
                    SUM(phone_calls) as phone_calls,
                    ROUND((SUM(phone_calls) / NULLIF(SUM(visits), 0)) * 100, 2) as ctr
                FROM analytics_hourly
                WHERE date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY " . ($utmFiltered ? "page_slug, utm_source" : "page_slug") . "
                HAVING visits > 0 OR phone_calls > 0
                ORDER BY ctr DESC, visits DESC";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get language preference statistics
     */
    public function getLanguageStats($months = 3, $slug = '', $utmSource = '') {
        $months = AnalyticsQuery::clampMonths($months);
        $sql = "SELECT 
                    language,
                    SUM(visits) as visits,
                    SUM(clicks) as clicks,
                    COUNT(DISTINCT page_slug) as unique_pages
                FROM analytics_hourly
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)";
        $params = [];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY language";
        
        return $this->db->fetchAll($sql, $params);
    }

    public function getRangeLanguageStats($startDate, $endDate, $slug = '', $utmSource = '') {
        $sql = "SELECT 
                    language,
                    SUM(visits) as visits,
                    SUM(clicks) as clicks,
                    COUNT(DISTINCT page_slug) as unique_pages
                FROM analytics_hourly
                WHERE date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY language";

        return $this->db->fetchAll($sql, $params);
    }

    public function getGrowthTrends($months = 6) {
        $months = AnalyticsQuery::clampMonths($months);
        // Fallback for MySQL <8: compute lag in PHP if window function unavailable
        try {
            $sql = "SELECT 
                        year,
                        month,
                        SUM(total_visits) as visits,
                        SUM(total_clicks) as clicks,
                        LAG(SUM(total_visits)) OVER (ORDER BY year, month) as prev_visits,
                        LAG(SUM(total_clicks)) OVER (ORDER BY year, month) as prev_clicks
                    FROM analytics_monthly
                    WHERE (year*12 + month) >= (YEAR(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH))*12 + MONTH(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)))
                    GROUP BY year, month
                    ORDER BY year, month";
            
            $results = $this->db->fetchAll($sql);
        } catch (Throwable $e) {
            // MySQL 5.7: manual lag
            $sql = "SELECT year, month, SUM(total_visits) as visits, SUM(total_clicks) as clicks
                    FROM analytics_monthly
                    WHERE (year*12 + month) >= (YEAR(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH))*12 + MONTH(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)))
                    GROUP BY year, month ORDER BY year, month";
            $raw = $this->db->fetchAll($sql);
            $results = [];
            $prevV = null; $prevC = null;
            foreach ($raw as $r) {
                $r['prev_visits'] = $prevV; $r['prev_clicks'] = $prevC;
                $results[] = $r;
                $prevV = $r['visits']; $prevC = $r['clicks'];
            }
        }
        
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
