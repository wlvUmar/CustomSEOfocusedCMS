<?php
// models/analytics/NavigationStats.php
// Internal-link navigation: popular paths, inbound/outbound, CTR effectiveness, dashboard card.

class NavigationStats {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get most popular navigation paths
     */
    public function getPopularPaths($months = 3, $limit = 20) {
        $months = AnalyticsQuery::clampMonths($months);
        $limit = max(1, min(100, (int)$limit));
        $sql = "SELECT 
                    from_slug,
                    to_slug,
                    SUM(total_clicks) as clicks,
                    COUNT(DISTINCT CONCAT(year, '-', month)) as active_months
                FROM analytics_internal_links_monthly
                WHERE (year*12 + month) >= (YEAR(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH))*12 + MONTH(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)))
                GROUP BY from_slug, to_slug
                ORDER BY clicks DESC
                LIMIT {$limit}";
        
        return $this->db->fetchAll($sql);
    }

    /**
     * Get outbound links from a specific page
     */
    public function getOutboundLinks($slug, $months = 3) {
        $months = AnalyticsQuery::clampMonths($months);
        $sql = "SELECT 
                    to_slug,
                    language,
                    SUM(total_clicks) as clicks
                FROM analytics_internal_links_monthly
                WHERE from_slug = ?
                  AND (year*12 + month) >= (YEAR(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH))*12 + MONTH(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)))
                GROUP BY to_slug, language
                ORDER BY clicks DESC";
        
        return $this->db->fetchAll($sql, [$slug]);
    }

    public function getInboundLinks($slug, $months = 3) {
        $months = AnalyticsQuery::clampMonths($months);
        $sql = "SELECT 
                    from_slug,
                    language,
                    SUM(total_clicks) as clicks
                FROM analytics_internal_links_monthly
                WHERE to_slug = ?
                  AND (year*12 + month) >= (YEAR(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH))*12 + MONTH(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)))
                GROUP BY from_slug, language
                ORDER BY clicks DESC";
        
        return $this->db->fetchAll($sql, [$slug]);
    }

    public function getLinkEffectiveness($months = 3) {
        $months = AnalyticsQuery::clampMonths($months);
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
                    WHERE (year*12 + month) >= (YEAR(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH))*12 + MONTH(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)))
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
        
        return $this->db->fetchAll($sql);
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
        $days = max(1, min(365, (int)$days));
        $sql = "SELECT 
                    from_slug, 
                    to_slug, 
                    SUM(clicks) as weight
                FROM analytics_internal_links
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
                GROUP BY from_slug, to_slug
                ORDER BY weight DESC
                LIMIT 10";
                
        return $this->db->fetchAll($sql);
    }

    /**
     * Get monthly trend of internal link clicks
     */
    public function getDailyNavigationTrends($days = 30) {
        $days = max(1, min(365, (int)$days));
        
        $sql = "SELECT 
                    date,
                    SUM(clicks) as clicks
                FROM analytics_internal_links
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
                GROUP BY date
                ORDER BY date ASC";
                
        $data = $this->db->fetchAll($sql);
        
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

    /**
     * Get quick navigation stats for dashboard insight card
     */
    public function getNavigationStats($months = 3) {
        $months = AnalyticsQuery::clampMonths($months);
        
        $sql = "SELECT 
                    SUM(total_clicks) as total_clicks,
                    CONCAT(from_slug, ' → ', to_slug) as top_path
                FROM analytics_internal_links_monthly
                WHERE (year*12 + month) >= (YEAR(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH))*12 + MONTH(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)))
                GROUP BY from_slug, to_slug
                ORDER BY total_clicks DESC
                LIMIT 1";
        
        $topPath = $this->db->fetchOne($sql);
        
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
                    WHERE (ailm.year*12 + ailm.month) >= (YEAR(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH))*12 + MONTH(DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)))";
        
        $stats = $this->db->fetchOne($statsSql);
        
        return [
            'total_clicks' => $stats['total_clicks'] ?? 0,
            'avg_ctr' => round($stats['avg_ctr'] ?? 0, 1),
            'top_path' => $topPath['top_path'] ?? 'N/A'
        ];
    }

    public function getPageSpeed($months = 1) {
        return [];
    }

    public function getBounceRates($months = 3) {
        return [];
    }
}
