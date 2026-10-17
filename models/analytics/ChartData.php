<?php
// models/analytics/ChartData.php
// Chart-ready series: monthly/daily/weekly/hourly/range aggregations + heat-map activity.

class ChartData {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getChartData($type = 'visits', $months = 6, $slug = '', $utmSource = '') {
        $data = (new PageStats())->getMonthlyData($months, $slug, $utmSource);
        
        $result = [];
        
        foreach (array_reverse($data) as $row) {
            $monthName = date('M Y', strtotime("{$row['year']}-{$row['month']}-01"));
            $result[$monthName] = (int)($row[$type] ?? 0);
        }
        
        return $result;
    }

    public function getSitewideChartData($months = 6, $pageSlug = AnalyticsQuery::SITE_SLUG, $slug = '', $utmSource = '') {
        $months = AnalyticsQuery::clampMonths($months);
        $pageSlug = trim((string)$pageSlug) ?: AnalyticsQuery::SITE_SLUG;
        $sql = "SELECT
                    YEAR(date) as year,
                    MONTH(date) as month,
                    SUM(visits) as visits
                FROM analytics_hourly
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)";
        $params = [];
        if ($slug !== '') {
            $sql .= " AND page_slug = ?";
            $params[] = $slug;
        } elseif ($pageSlug !== AnalyticsQuery::SITE_SLUG) {
            $sql .= " AND page_slug = ?";
            $params[] = $pageSlug;
        } else {
            $sql .= " AND page_slug != '" . AnalyticsQuery::SITE_SLUG . "'";
        }
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY year, month
                ORDER BY year ASC, month ASC";

        $data = $this->db->fetchAll($sql, $params);

        $result = [];
        foreach ($data as $row) {
            $monthName = date('M Y', strtotime("{$row['year']}-{$row['month']}-01"));
            $result[$monthName] = (int)($row['visits'] ?? 0);
        }

        return $result;
    }

    public function getSitewideDailyChartData($type = 'visits', $months = 1, $pageSlug = AnalyticsQuery::SITE_SLUG, $slug = '', $utmSource = '') {
        $months = AnalyticsQuery::clampMonths($months);
        $pageSlug = trim((string)$pageSlug) ?: AnalyticsQuery::SITE_SLUG;
        $field = AnalyticsQuery::metricField($type);
        $sql = "SELECT
                    date as visit_date,
                    SUM($field) as value
                FROM analytics_hourly
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)";
        $params = [];
        if ($slug !== '') {
            $sql .= " AND page_slug = ?";
            $params[] = $slug;
        } elseif ($pageSlug !== AnalyticsQuery::SITE_SLUG) {
            $sql .= " AND page_slug = ?";
            $params[] = $pageSlug;
        } else {
            $sql .= " AND page_slug != '" . AnalyticsQuery::SITE_SLUG . "'";
        }
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY visit_date
                ORDER BY visit_date ASC";

        $data = $this->db->fetchAll($sql, $params);

        $result = [];
        foreach ($data as $row) {
            $label = date('M j', strtotime($row['visit_date']));
            $result[$label] = (int)($row['value'] ?? 0);
        }

        return $result;
    }

    public function getSitewideWeeklyChartData($months = 3, $pageSlug = AnalyticsQuery::SITE_SLUG, $slug = '', $utmSource = '') {
        $months = AnalyticsQuery::clampMonths($months);
        $pageSlug = trim((string)$pageSlug) ?: AnalyticsQuery::SITE_SLUG;
        $sql = "SELECT
                    DATE(DATE_SUB(date, INTERVAL WEEKDAY(date) DAY)) as week_start,
                    SUM(visits) as visits
                FROM analytics_hourly
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)";
        $params = [];
        if ($slug !== '') {
            $sql .= " AND page_slug = ?";
            $params[] = $slug;
        } elseif ($pageSlug !== AnalyticsQuery::SITE_SLUG) {
            $sql .= " AND page_slug = ?";
            $params[] = $pageSlug;
        } else {
            $sql .= " AND page_slug != '" . AnalyticsQuery::SITE_SLUG . "'";
        }
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY week_start
                ORDER BY week_start ASC";

        $data = $this->db->fetchAll($sql, $params);

        $result = [];
        foreach ($data as $row) {
            $weekLabel = date('M j', strtotime($row['week_start']));
            $result[$weekLabel] = (int)($row['visits'] ?? 0);
        }

        return $result;
    }

    public function getSitewideRangeChartData($startDate, $endDate, $labelMode = 'date', $slug = '', $utmSource = '') {
        $sql = "SELECT
                    date as visit_date,
                    SUM(visits) as value
                FROM analytics_hourly
                WHERE date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY visit_date
                ORDER BY visit_date ASC";

        $data = $this->db->fetchAll($sql, $params);

        list($result, $spansYear) = AnalyticsQuery::fillDailyRange($startDate, $endDate, $labelMode);

        foreach ($data as $row) {
            $dateObj = new DateTime($row['visit_date']);
            $dateLabel = AnalyticsQuery::rangeLabel($dateObj, $labelMode, $spansYear);
            $result[$dateLabel] = ($result[$dateLabel] ?? 0) + (int)($row['value'] ?? 0);
        }

        return $result;
    }

    public function getSitewideHourlyChartDataForDate($date, $slug = '', $utmSource = '') {
        $sql = "SELECT
                    hour,
                    SUM(visits) as visits
                FROM analytics_hourly
                WHERE date = ?";
        $params = [$date];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY hour
                ORDER BY hour ASC";

        $data = $this->db->fetchAll($sql, $params);

        $result = AnalyticsQuery::hourlySkeleton();

        foreach ($data as $row) {
            $hour = (int)($row['hour'] ?? 0);
            $label = str_pad((string)$hour, 2, '0', STR_PAD_LEFT) . ':00';
            $result[$label] = (int)($row['visits'] ?? 0);
        }

        return $result;
    }

    public function getRangeChartData($type, $startDate, $endDate, $labelMode = 'date', $slug = '', $utmSource = '') {
        $field = AnalyticsQuery::metricField($type);
        $sql = "SELECT 
                    date,
                    SUM($field) as value
                FROM analytics_hourly
                WHERE date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY date
                ORDER BY date ASC";

        $data = $this->db->fetchAll($sql, $params);

        list($result, $spansYear) = AnalyticsQuery::fillDailyRange($startDate, $endDate, $labelMode);

        foreach ($data as $row) {
            $dateObj = new DateTime($row['date']);
            $dateLabel = AnalyticsQuery::rangeLabel($dateObj, $labelMode, $spansYear);
            $result[$dateLabel] = ($result[$dateLabel] ?? 0) + (int)($row['value'] ?? 0);
        }

        return $result;
    }

    public function getHourlyChartDataForDate($type, $date, $slug = '', $utmSource = '') {
        $field = AnalyticsQuery::metricField($type);

        $sql = "SELECT 
                    hour,
                    SUM($field) as value
                FROM analytics_hourly
                WHERE date = ?";
        $params = [$date];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY hour
                ORDER BY hour ASC";

        $data = $this->db->fetchAll($sql, $params);

        $result = AnalyticsQuery::hourlySkeleton();

        foreach ($data as $row) {
            $hour = (int)($row['hour'] ?? 0);
            $label = str_pad((string)$hour, 2, '0', STR_PAD_LEFT) . ':00';
            $result[$label] = (int)($row['value'] ?? 0);
        }

        return $result;
    }

    /**
     * Get daily aggregated data for charts
     */
    public function getDailyChartData($type = 'visits', $months = 1, $slug = '', $utmSource = '') {
        $months = AnalyticsQuery::clampMonths($months);
        $field = AnalyticsQuery::metricField($type);
        
        $sql = "SELECT 
                    date,
                    SUM($field) as value
                FROM analytics_hourly
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)";
        $params = [];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY date
                ORDER BY date ASC";
        
        $data = $this->db->fetchAll($sql, $params);
        
        $result = [];
        foreach ($data as $row) {
            // Include year when data spans Jan to avoid M j collision (project-10 #6)
            $dateLabel = date('M j', strtotime($row['date']));
            if (count($data) > 60 && date('Y', strtotime($row['date'])) !== date('Y')) {
                $dateLabel = date('M j Y', strtotime($row['date']));
            }
            $result[$dateLabel] = ($result[$dateLabel] ?? 0) + (int)($row['value'] ?? 0);
        }
        
        return $result;
    }

    /**
     * Get weekly aggregated data for charts
     */
    public function getWeeklyChartData($type = 'visits', $months = 3, $slug = '', $utmSource = '') {
        $months = AnalyticsQuery::clampMonths($months);
        $field = AnalyticsQuery::metricField($type);
        
        $sql = "SELECT 
                    YEARWEEK(date, 1) as week_year,
                    DATE(DATE_SUB(date, INTERVAL WEEKDAY(date) DAY)) as week_start,
                    SUM($field) as value
                FROM analytics_hourly
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)";
        $params = [];
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY YEARWEEK(date, 1), week_start
                ORDER BY week_start ASC";
        
        $data = $this->db->fetchAll($sql, $params);
        
        $result = [];
        foreach ($data as $row) {
            $weekLabel = date('M j', strtotime($row['week_start']));
            $result[$weekLabel] = (int)($row['value'] ?? 0);
        }
        
        return $result;
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
}
