<?php
// path: ./controllers/admin/AnalyticsController.php

require_once BASE_PATH . '/models/Analytics.php';

class AnalyticsController extends Controller {
    private $analyticsModel;

    public function __construct() {
        parent::__construct();
        $this->analyticsModel = new Analytics();
    }

    public function index() {
        $this->requireAuth();
        
        $months = $_GET['months'] ?? 6;
        
        $stats = [
            'total' => $this->analyticsModel->getTotalStats(),
            'current_month' => $this->analyticsModel->getCurrentMonthStats(),
            'page_stats' => $this->analyticsModel->getPageStats($months),
            'visits_chart' => $this->analyticsModel->getChartData('visits', $months),
            'clicks_chart' => $this->analyticsModel->getChartData('clicks', $months),
            'months' => $months
        ];
        
        $this->view('admin/analytics/index', ['stats' => $stats]);
    }

    public function getData() {
        $this->requireAuth();
        
        $type = $_GET['type'] ?? 'visits';
        $months = $_GET['months'] ?? 6;
        
        $data = $this->analyticsModel->getChartData($type, $months);
        $this->json($data);
    }
}