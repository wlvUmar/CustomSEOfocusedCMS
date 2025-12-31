<?php
// path: ./controllers/admin/AnalyticsSectionController.php

require_once BASE_PATH . '/models/Analytics.php';
require_once BASE_PATH . '/models/Page.php';

class AnalyticsSectionController extends Controller {
    private $analyticsModel;
    private $pageModel;

    public function __construct() {
        parent::__construct();
        $this->analyticsModel = new Analytics();
        $this->pageModel = new Page();
    }

    public function index() {
        $this->requireAuth();
        
        // Load the main section page with tabs
        $this->view('admin/sections/analytics_section', [
            'activeTab' => 'overview'
        ]);
    }

    public function overviewContent() {
        $this->requireAuth();
        
        $months = $_GET['months'] ?? 6;
        
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
            'view' => 'overview'
        ];
        
        // Return only the content part
        $this->view('admin/sections/analytics_overview_content', ['stats' => $stats]);
    }

    public function rotationContent() {
        $this->requireAuth();
        
        $months = $_GET['months'] ?? 3;
        
        $data = [
            'effectiveness' => $this->analyticsModel->getRotationEffectiveness($months),
            'months' => $months
        ];
        
        $this->view('admin/sections/analytics_rotation_content', $data);
    }

    public function navigationContent() {
        $this->requireAuth();
        
        $months = $_GET['months'] ?? 3;
        
        $data = [
            'navigation_flow' => $this->analyticsModel->getNavigationFlow(30),
            'popular_paths' => $this->analyticsModel->getPopularPaths($months, 20),
            'link_effectiveness' => $this->analyticsModel->getLinkEffectiveness($months),
            'navigation_funnels' => $this->analyticsModel->getNavigationFunnels($months),
            'months' => $months
        ];
        
        $this->view('admin/sections/analytics_navigation_content', $data);
    }

    public function crawlContent() {
        $this->requireAuth();
        
        $days = $_GET['days'] ?? 30;
        
        $data = [
            'crawl_frequency' => $this->analyticsModel->getCrawlFrequency($days),
            'days' => $days
        ];
        
        $this->view('admin/sections/analytics_crawl_content', $data);
    }
}