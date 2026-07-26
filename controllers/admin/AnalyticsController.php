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
        $slug = trim((string)($_GET['slug'] ?? ''));
        $utmSource = trim((string)($_GET['utm_source'] ?? ''));
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
            $utmStats = $this->analyticsModel->getUtmSourceStats($start, $end, $slug, $utmSource);
            $utmSources = array_column($utmStats, 'utm_source');

            $allPageStats = $this->analyticsModel->getRangePageStats($start, $end, '', '');
            $allPageSlugs = array_values(array_unique(array_filter(array_column($allPageStats, 'page_slug'))));
            $allUtmStats = $this->analyticsModel->getUtmSourceStats($start, $end, '', '');
            $allUtmSources = array_values(array_unique(array_filter(array_column($allUtmStats, 'utm_source'))));
            
            $stats = [
                'total' => $this->analyticsModel->getRangeTotalStats($start, $end, $slug, $utmSource),
                'current_month' => $this->analyticsModel->getCurrentMonthStats(),
                'page_stats' => $this->analyticsModel->getRangePageStats($start, $end, $slug, $utmSource),
                'visits_chart' => $isSingleDay
                    ? $this->analyticsModel->getSitewideHourlyChartDataForDate($start, $slug, $utmSource)
                    : $this->analyticsModel->getSitewideRangeChartData($start, $end, $isWeeklyRange ? 'weekday' : 'date', $slug, $utmSource),
                'clicks_chart' => $isSingleDay
                    ? $this->analyticsModel->getHourlyChartDataForDate('clicks', $start, $slug, $utmSource)
                    : $this->analyticsModel->getRangeChartData('clicks', $start, $end, $isWeeklyRange ? 'weekday' : 'date', $slug, $utmSource),
                'phone_calls_chart' => $isSingleDay
                    ? $this->analyticsModel->getHourlyChartDataForDate('phone_calls', $start, $slug, $utmSource)
                    : $this->analyticsModel->getRangeChartData('phone_calls', $start, $end, $isWeeklyRange ? 'weekday' : 'date', $slug, $utmSource),
                'trends' => $this->analyticsModel->getPerformanceTrendsByDateRange($start, $end, null, $slug, $utmSource),
                'top_performers' => $this->analyticsModel->getRangeTopPerformers($start, $end, $slug, $utmSource),
                'language_stats' => $this->analyticsModel->getRangeLanguageStats($start, $end, $slug, $utmSource),
                'utm_stats' => $utmStats,
                'utm_sources' => $utmSources,
                'all_page_slugs' => $allPageSlugs,
                'all_utm_sources' => $allUtmSources,
                'months' => $months,
                'slug' => $slug,
                'utm_source' => $utmSource,
                'view' => $view,
                'range' => $range,
                'range_label' => $rangeInfo['label'],
                'range_granularity' => $isSingleDay ? 'hourly' : 'daily',
                'pageName' => 'analytics/index'
            ];
        } else {
            $chartAggregation = $months <= 1 ? 'daily' : ($months <= 3 ? 'weekly' : 'monthly');
            $utmStats = $this->analyticsModel->getTopUtmSources(null, $months * 30, $slug, $utmSource);
            $utmSources = array_column($utmStats, 'utm_source');

            $allPageStats = $this->analyticsModel->getPageStats($months, '', '');
            $allPageSlugs = array_values(array_unique(array_filter(array_column($allPageStats, 'page_slug'))));
            $allUtmStats = $this->analyticsModel->getTopUtmSources(null, $months * 30, '', '');
            $allUtmSources = array_values(array_unique(array_filter(array_column($allUtmStats, 'utm_source'))));
            
            $stats = [
                'total' => $this->analyticsModel->getTotalStats($months, $slug, $utmSource),
                'current_month' => $this->analyticsModel->getCurrentMonthStats(),
                'page_stats' => $this->analyticsModel->getPageStats($months, $slug, $utmSource),
                'visits_chart' => $chartAggregation === 'daily'
                    ? $this->analyticsModel->getSitewideDailyChartData('visits', $months, $slug, '', $utmSource)
                    : ($chartAggregation === 'weekly'
                        ? $this->analyticsModel->getSitewideWeeklyChartData($months, $slug, '', $utmSource)
                        : $this->analyticsModel->getSitewideChartData($months, $slug, '', $utmSource)),
                'clicks_chart' => $chartAggregation === 'daily'
                    ? $this->analyticsModel->getSitewideDailyChartData('clicks', $months, $slug, '', $utmSource)
                    : ($chartAggregation === 'weekly'
                        ? $this->analyticsModel->getWeeklyChartData('clicks', $months, $slug, $utmSource)
                        : $this->analyticsModel->getChartData('clicks', $months, $slug, $utmSource)),
                'phone_calls_chart' => $chartAggregation === 'daily'
                    ? $this->analyticsModel->getSitewideDailyChartData('phone_calls', $months, $slug, '', $utmSource)
                    : ($chartAggregation === 'weekly'
                        ? $this->analyticsModel->getWeeklyChartData('phone_calls', $months, $slug, $utmSource)
                        : $this->analyticsModel->getChartData('phone_calls', $months, $slug, $utmSource)),
                'trends' => $this->analyticsModel->getPerformanceTrends(null, $slug, $utmSource),
                'top_performers' => $this->analyticsModel->getTopPerformers($months, 500, $slug, $utmSource),
                'language_stats' => $this->analyticsModel->getLanguageStats($months, $slug, $utmSource),
                'utm_stats' => $utmStats,
                'utm_sources' => $utmSources,
                'all_page_slugs' => $allPageSlugs,
                'all_utm_sources' => $allUtmSources,
                'months' => $months,
                'aggregation' => $chartAggregation,
                'view' => $view,
                'range' => '',
                'range_label' => '',
                'slug' => $slug,
                'utm_source' => $utmSource,
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
        
        $months = (int)($_GET['months'] ?? 6);
        $aggregation = $_GET['aggregation'] ?? 'monthly';
        $slug = trim((string)($_GET['slug'] ?? ''));
        $utmSource = trim((string)($_GET['utm_source'] ?? ''));
        $range = $_GET['range'] ?? '';
        $rangeInfo = $this->resolveDateRange($range);
        
        $visits = null;
        $clicks = null;
        $phone_calls = null;

        if ($rangeInfo) {
            $start = $rangeInfo['start'];
            $end = $rangeInfo['end'];
            if ($start === $end) {
                $visits = $this->analyticsModel->getHourlyChartDataForDate('visits', $start, $slug, $utmSource);
                $clicks = $this->analyticsModel->getHourlyChartDataForDate('clicks', $start, $slug, $utmSource);
                $phone_calls = $this->analyticsModel->getHourlyChartDataForDate('phone_calls', $start, $slug, $utmSource);
            } else {
                $labelMode = $range === 'last_week' ? 'weekday' : 'date';
                $visits = $this->analyticsModel->getRangeChartData('visits', $start, $end, $labelMode, $slug, $utmSource);
                $clicks = $this->analyticsModel->getRangeChartData('clicks', $start, $end, $labelMode, $slug, $utmSource);
                $phone_calls = $this->analyticsModel->getRangeChartData('phone_calls', $start, $end, $labelMode, $slug, $utmSource);
            }
            $total = $this->analyticsModel->getRangeTotalStats($start, $end, $slug, $utmSource);
            $page_stats = $this->analyticsModel->getRangePageStats($start, $end, $slug, $utmSource);
            $trends = $this->analyticsModel->getPerformanceTrendsByDateRange($start, $end, null, $slug, $utmSource);
            $top_performers = $this->analyticsModel->getRangeTopPerformers($start, $end, $slug, $utmSource);
            $language_stats = $this->analyticsModel->getRangeLanguageStats($start, $end, $slug, $utmSource);
            $utm_stats = $this->analyticsModel->getUtmSourceStats($start, $end, $slug, $utmSource);
        } else {
            switch ($aggregation) {
                case 'daily':
                    $visits = $this->analyticsModel->getSitewideDailyChartData('visits', $months, $slug, '', $utmSource);
                    $clicks = $this->analyticsModel->getSitewideDailyChartData('clicks', $months, $slug, '', $utmSource);
                    $phone_calls = $this->analyticsModel->getSitewideDailyChartData('phone_calls', $months, $slug, '', $utmSource);
                    break;
                case 'weekly':
                    $visits = $this->analyticsModel->getSitewideWeeklyChartData($months, $slug, '', $utmSource);
                    $clicks = $this->analyticsModel->getWeeklyChartData('clicks', $months, $slug, $utmSource);
                    $phone_calls = $this->analyticsModel->getWeeklyChartData('phone_calls', $months, $slug, $utmSource);
                    break;
                case 'monthly':
                default:
                    $visits = $this->analyticsModel->getSitewideChartData($months, $slug, '', $utmSource);
                    $clicks = $this->analyticsModel->getChartData('clicks', $months, $slug, $utmSource);
                    $phone_calls = $this->analyticsModel->getChartData('phone_calls', $months, $slug, $utmSource);
                    break;
            }
            $total = $this->analyticsModel->getTotalStats($months, $slug, $utmSource);
            $page_stats = $this->analyticsModel->getPageStats($months, $slug, $utmSource);
            $trends = $this->analyticsModel->getPerformanceTrends(null, $slug, $utmSource);
            $top_performers = $this->analyticsModel->getTopPerformers($months, 500, $slug, $utmSource);
            $language_stats = $this->analyticsModel->getLanguageStats($months, $slug, $utmSource);
            $utm_stats = $this->analyticsModel->getTopUtmSources(null, $months * 30, $slug, $utmSource);
        }
        
        $allPageSlugsRaw = $rangeInfo
            ? $this->analyticsModel->getRangePageStats($start, $end, '', '')
            : $this->analyticsModel->getPageStats($months, '', '');
        $allPageSlugs = array_values(array_unique(array_filter(array_column($allPageSlugsRaw, 'page_slug'))));

        $allUtmRaw = $rangeInfo
            ? $this->analyticsModel->getUtmSourceStats($start, $end, '', '')
            : $this->analyticsModel->getTopUtmSources(null, $months * 30, '', '');
        $allUtmSources = array_values(array_unique(array_filter(array_column($allUtmRaw, 'utm_source'))));

        $this->json([
            'visits' => $visits,
            'clicks' => $clicks,
            'phone_calls' => $phone_calls,
            'total' => $total,
            'page_stats' => $page_stats,
            'trends' => $trends,
            'top_performers' => $top_performers,
            'language_stats' => $language_stats,
            'utm_stats' => $utm_stats,
            'all_page_slugs' => $allPageSlugs,
            'all_utm_sources' => $allUtmSources
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
        fputcsv($output, ['Page', 'Language', 'Visits', 'Clicks', 'Phone Calls', 'Phone Call CTR %', 'Period']);
        
        // Data
        foreach ($stats as $row) {
            $ctr = $row['visits'] > 0 ? round(($row['phone_calls'] / $row['visits']) * 100, 2) : 0;
            fputcsv($output, [
                $row['page_slug'],
                strtoupper($row['language']),
                $row['visits'],
                $row['clicks'],
                $row['phone_calls'],
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
