<?php
// models/analytics/BotStats.php
// Bot/crawl visibility: crawl frequency, daily bot activity, visit summary, dashboard card.

class BotStats {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
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
        $days = max(1, min(365, (int)$days));
        
        $sql = "SELECT 
                    visit_date,
                    bot_type,
                    SUM(visits) as visits
                FROM analytics_bot_visits
                WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
                GROUP BY visit_date, bot_type
                ORDER BY visit_date ASC";
                
        $data = $this->db->fetchAll($sql);
        
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
     * Get quick crawl insights for dashboard insight card
     */
    public function getCrawlInsights($days = 7) {
        $days = max(1, min(365, (int)$days));
        
        $sql = "SELECT 
                    COUNT(DISTINCT page_slug) as pages_crawled,
                    bot_type as top_bot
                FROM analytics_bot_visits
                WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
                GROUP BY bot_type
                ORDER BY SUM(visits) DESC
                LIMIT 1";
        
        $data = $this->db->fetchOne($sql);
        
        // Get stale pages (not crawled in last $days)
        $staleSql = "SELECT COUNT(DISTINCT page_slug) as stale_count
                     FROM analytics_bot_visits abv1
                     WHERE NOT EXISTS (
                         SELECT 1 FROM analytics_bot_visits abv2
                         WHERE abv2.page_slug = abv1.page_slug
                         AND abv2.visit_date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
                     )";
        
        $stale = $this->db->fetchOne($staleSql);
        
        return [
            'pages_crawled' => $data['pages_crawled'] ?? 0,
            'top_bot' => ucfirst($data['top_bot'] ?? 'N/A'),
            'stale_pages' => $stale['stale_count'] ?? 0
        ];
    }
}
