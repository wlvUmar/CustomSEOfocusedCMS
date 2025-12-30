<?php
// path: ./models/Analytics.php

class Analytics {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getMonthlyData($months = 6) {
        $sql = "SELECT year, month, SUM(total_visits) as visits, SUM(total_clicks) as clicks
                FROM analytics_monthly
                WHERE DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY year, month
                ORDER BY year DESC, month DESC";
        
        return $this->db->fetchAll($sql, [$months]);
    }

    public function getPageStats($months = 6) {
        $sql = "SELECT page_slug, language, SUM(total_visits) as visits, SUM(total_clicks) as clicks
                FROM analytics_monthly
                WHERE DATE(CONCAT(year, '-', month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY page_slug, language
                ORDER BY visits DESC";
        
        return $this->db->fetchAll($sql, [$months]);
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
}