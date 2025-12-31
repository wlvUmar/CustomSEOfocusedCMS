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
        
        $months = $_GET['months'] ?? 6;
        $view = $_GET['view'] ?? 'overview';
        
        $stats = [
            'total' => $this->analyticsModel->getTotalStats(),
            'current_month' => $this->analyticsModel->getCurrentMonthStats(),
            'page_stats' => $this->analyticsModel->getPageStats($months),
            'visits_chart' => $this->analyticsModel->getChartData('visits', $months),
            'clicks_chart' => $this->analyticsModel->getChartData('clicks', $months),
            'trends' => $this->analyticsModel->getPerformanceTrends(),
            'top_performers' => $this->analyticsModel->getTopPerformers($months),
            'language_stats' => $this->analyticsModel->getLanguageStats($months),
            'months' => $months,
            'view' => $view
        ];
        
        $this->view('admin/analytics/index', ['stats' => $stats]);
    }

    /**
     * Rotation-specific analytics
     */
    public function rotationAnalytics() {
        $this->requireAuth();
        
        $months = $_GET['months'] ?? 3;
        
        $data = [
            'effectiveness' => $this->analyticsModel->getRotationEffectiveness($months),
            'months' => $months
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
            'days' => $days
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
            'months' => $months
        ];
        
        $this->view('admin/analytics/page_detail', $data);
    }

    public function getData() {
        $this->requireAuth();
        
        $type = $_GET['type'] ?? 'visits';
        $months = $_GET['months'] ?? 6;
        
        $data = $this->analyticsModel->getChartData($type, $months);
        $this->json($data);
    }

    /**
     * Export analytics data as CSV
     */
    public function export() {
        $this->requireAuth();
        
        $months = $_GET['months'] ?? 6;
        $stats = $this->analyticsModel->getPageStats($months);
        
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
                $months . ' months'
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
            'popular_paths' => $this->analyticsModel->getPopularPaths($months, 20),
            'link_effectiveness' => $this->analyticsModel->getLinkEffectiveness($months),
            'navigation_funnels' => $this->analyticsModel->getNavigationFunnels($months),
            'months' => $months
        ];
        
        $this->view('admin/analytics/navigation', $data);
    }
}