<?php
// path: ./controllers/admin/AnalyticsController.php

require_once BASE_PATH . '/models/Analytics.php';
require_once BASE_PATH . '/models/Page.php';

class AnalyticsController extends Controller {
    private $analyticsModel;
    private $pageModel;

    public function __construct() {
        parent::__construct();
        $this->analyticsModel = new Analytics();
        $this->pageModel = new Page();
    }

    public function index() {
        $this->requireAuth();

        $months = isset($_GET['months']) ? intval($_GET['months']) : 6;
        $months = max(1, min(24, $months));
        $range = $_GET['range'] ?? '';
        $rangeInfo = $this->resolveDateRange($range);
        $isWeeklyRange = ($range === 'last_week');
        
        $view = $_GET['view'] ?? 'overview';
        if (!in_array($view, ['overview', 'rotation', 'navigation', 'crawl'])) {
            $view = 'overview';
        }

        if ($rangeInfo) {
            $start = $rangeInfo['start'];
            $end = $rangeInfo['end'];

            $isSingleDay = ($start === $end);
            $stats = [
                'total' => $this->analyticsModel->getRangeTotalStats($start, $end),
                'current_month' => $this->analyticsModel->getCurrentMonthStats(),
                'page_stats' => $this->analyticsModel->getRangePageStats($start, $end),
                'visits_chart' => $isSingleDay
                    ? $this->analyticsModel->getHourlyChartDataForDate('visits', $start)
                    : $this->analyticsModel->getRangeChartData('visits', $start, $end, $isWeeklyRange ? 'weekday' : 'date'),
                'clicks_chart' => $isSingleDay
                    ? $this->analyticsModel->getHourlyChartDataForDate('clicks', $start)
                    : $this->analyticsModel->getRangeChartData('clicks', $start, $end, $isWeeklyRange ? 'weekday' : 'date'),
                'phone_calls_chart' => $isSingleDay
                    ? $this->analyticsModel->getHourlyChartDataForDate('phone_calls', $start)
                    : $this->analyticsModel->getRangeChartData('phone_calls', $start, $end, $isWeeklyRange ? 'weekday' : 'date'),
                'trends' => $this->analyticsModel->getPerformanceTrendsByDateRange($start, $end),
                'top_performers' => $this->analyticsModel->getRangeTopPerformers($start, $end),
                'language_stats' => $this->analyticsModel->getRangeLanguageStats($start, $end),
                'months' => $months,
                'view' => $view,
                'range' => $range,
                'range_label' => $rangeInfo['label'],
                'range_granularity' => $isSingleDay ? 'hourly' : 'daily',
                'pageName' => 'analytics/index'
            ];
        } else {
            $chartAggregation = ($months <= 1) ? 'weekly' : 'monthly';
            $stats = [
                'total' => $this->analyticsModel->getTotalStats(),
                'current_month' => $this->analyticsModel->getCurrentMonthStats(),
                'page_stats' => $this->analyticsModel->getPageStats($months),
                'visits_chart' => $chartAggregation === 'weekly'
                    ? $this->analyticsModel->getWeeklyChartData('visits', $months)
                    : $this->analyticsModel->getChartData('visits', $months),
                'clicks_chart' => $chartAggregation === 'weekly'
                    ? $this->analyticsModel->getWeeklyChartData('clicks', $months)
                    : $this->analyticsModel->getChartData('clicks', $months),
                'phone_calls_chart' => $chartAggregation === 'weekly'
                    ? $this->analyticsModel->getWeeklyChartData('phone_calls', $months)
                    : $this->analyticsModel->getChartData('phone_calls', $months),
                'trends' => $this->analyticsModel->getPerformanceTrends(),
                'top_performers' => $this->analyticsModel->getTopPerformers($months),
                'language_stats' => $this->analyticsModel->getLanguageStats($months),
                'months' => $months,
                'aggregation' => $chartAggregation,
                'view' => $view,
                'range' => '',
                'range_label' => '',
                'pageName' => 'analytics/index'
            ];
        }
        
        $this->view('admin/analytics/index', [
            'stats' => $stats,
            'pageName' => $stats['pageName']
        ]);
    }

    /**
     * Rotation-specific analytics
     */
    public function rotationAnalytics() {
        $this->requireAuth();
        
        $months = isset($_GET['months']) ? intval($_GET['months']) : 3;
        $months = max(1, min(24, $months));
        
        $data = [
            'effectiveness' => $this->analyticsModel->getRotationEffectiveness($months),
            'months' => $months,
            'pageName' => 'analytics/rotation'
        ];
        
        $this->view('admin/analytics/rotation', $data);
    }

    /**
     * Crawl frequency analysis
     */
    public function crawlAnalysis() {
        $this->requireAuth();
        
        $days = $_GET['days'] ?? 30;
        
        $data = [
            'crawl_frequency' => $this->analyticsModel->getCrawlFrequency($days),
            'bot_summary' => $this->analyticsModel->getBotVisitSummary($days),
            'daily_stats' => $this->analyticsModel->getDailyBotActivity($days),
            'days' => $days,
            'pageName' => 'analytics/crawl'
        ];
        
        $this->view('admin/analytics/crawl', $data);
    }

    /**
     * Page-specific detailed analytics
     */
    public function pageDetail($slug) {
        $this->requireAuth();
        
        $page = $this->pageModel->getBySlug($slug);
        if (!$page) {
            $_SESSION['error'] = 'Page not found';
            $this->redirect('/admin/analytics');
            return;
        }
        
        $months = $_GET['months'] ?? 3;
        
        $data = [
            'page' => $page,
            'trends' => $this->analyticsModel->getPerformanceTrends($slug),
            'rotation_comparison' => $this->analyticsModel->getRotationComparison($slug, $months),
            'daily_activity' => $this->analyticsModel->getDailyActivity($slug, 30),
            'months' => $months,
            'pageName' => 'analytics/page_detail'
        ];
        
        $this->view('admin/analytics/page_detail', $data);
    }

    public function getData() {
        $this->requireAuth();
        
        $months = $_GET['months'] ?? 6;
        $aggregation = $_GET['aggregation'] ?? 'monthly';
        $range = $_GET['range'] ?? '';
        $rangeInfo = $this->resolveDateRange($range);
        
        $visits = null;
        $clicks = null;
        $phone_calls = null;

        if ($rangeInfo) {
            $start = $rangeInfo['start'];
            $end = $rangeInfo['end'];
            if ($start === $end) {
                $visits = $this->analyticsModel->getHourlyChartDataForDate('visits', $start);
                $clicks = $this->analyticsModel->getHourlyChartDataForDate('clicks', $start);
                $phone_calls = $this->analyticsModel->getHourlyChartDataForDate('phone_calls', $start);
            } else {
                $labelMode = $range === 'last_week' ? 'weekday' : 'date';
                $visits = $this->analyticsModel->getRangeChartData('visits', $start, $end, $labelMode);
                $clicks = $this->analyticsModel->getRangeChartData('clicks', $start, $end, $labelMode);
                $phone_calls = $this->analyticsModel->getRangeChartData('phone_calls', $start, $end, $labelMode);
            }
        } else {
            switch ($aggregation) {
                case 'daily':
                    $visits = $this->analyticsModel->getDailyChartData('visits', $months);
                    $clicks = $this->analyticsModel->getDailyChartData('clicks', $months);
                    $phone_calls = $this->analyticsModel->getDailyChartData('phone_calls', $months);
                    break;
                case 'weekly':
                    $visits = $this->analyticsModel->getWeeklyChartData('visits', $months);
                    $clicks = $this->analyticsModel->getWeeklyChartData('clicks', $months);
                    $phone_calls = $this->analyticsModel->getWeeklyChartData('phone_calls', $months);
                    break;
                case 'monthly':
                default:
                    $visits = $this->analyticsModel->getChartData('visits', $months);
                    $clicks = $this->analyticsModel->getChartData('clicks', $months);
                    $phone_calls = $this->analyticsModel->getChartData('phone_calls', $months);
                    break;
            }
        }
        
        $this->json([
            'visits' => $visits,
            'clicks' => $clicks,
            'phone_calls' => $phone_calls
        ]);
    }

    /**
     * Export analytics data as CSV
     */
    public function export() {
        $this->requireAuth();
        
        $months = $_GET['months'] ?? 6;
        $range = $_GET['range'] ?? '';
        $rangeInfo = $this->resolveDateRange($range);

        if ($rangeInfo) {
            $stats = $this->analyticsModel->getRangePageStats($rangeInfo['start'], $rangeInfo['end']);
        } else {
            $stats = $this->analyticsModel->getPageStats($months);
        }
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="analytics_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, ['Page', 'Language', 'Visits', 'Clicks', 'CTR %', 'Period']);
        
        // Data
        foreach ($stats as $row) {
            $ctr = $row['visits'] > 0 ? round(($row['clicks'] / $row['visits']) * 100, 2) : 0;
            fputcsv($output, [
                $row['page_slug'],
                strtoupper($row['language']),
                $row['visits'],
                $row['clicks'],
                $ctr,
                $rangeInfo ? $rangeInfo['label'] : ($months . ' months')
            ]);
        }
        
        fclose($output);
        exit;
    }
    public function navigationAnalytics() {
        $this->requireAuth();
        
        $months = $_GET['months'] ?? 3;
        
        $data = [
            'navigation_flow' => $this->analyticsModel->getNavigationFlow(30),
            'link_effectiveness' => $this->analyticsModel->getLinkEffectiveness($months),
            'link_stats' => $this->analyticsModel->getLinkEffectivenessStats($months),
            'navigation_trends' => $this->analyticsModel->getDailyNavigationTrends($months * 30),
            'months' => $months,
            'pageName' => 'analytics/navigation'
        ];
        
        $this->view('admin/analytics/navigation', $data);
    }
    public function getAnalyticsModel() {
        return $this->analyticsModel;
    }

    private function resolveDateRange($range) {
        if (empty($range)) {
            return null;
        }

        $today = new DateTime('today');
        $start = null;
        $end = null;
        $label = '';

        switch ($range) {
            case 'today':
                $start = clone $today;
                $end = clone $today;
                $label = 'Today (' . $today->format('M j, Y') . ')';
                break;
            case 'yesterday':
                $start = (clone $today)->modify('-1 day');
                $end = clone $start;
                $label = 'Yesterday (' . $start->format('M j, Y') . ')';
                break;
            case 'day_before':
                $start = (clone $today)->modify('-2 days');
                $end = clone $start;
                $label = 'Day before yesterday (' . $start->format('M j, Y') . ')';
                break;
            case 'last_week':
                $currentWeekStart = (clone $today)->modify('monday this week');
                $start = (clone $currentWeekStart)->modify('-7 days');
                $end = (clone $start)->modify('+6 days');
                $label = 'Last week (' . $start->format('M j, Y') . ' - ' . $end->format('M j, Y') . ')';
                break;
            default:
                return null;
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'label' => $label
        ];
    }
}
