<?php
// path: ./controllers/admin/SearchEngineController.php

require_once BASE_PATH . '/models/SearchEngineManager.php';
require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/SearchEngineConfig.php';

class SearchEngineController extends Controller {
    private $manager;
    private $pageModel;
    private $configModel;

    public function __construct() {
        parent::__construct();
        $this->manager = new SearchEngineManager();
        $this->pageModel = new Page();
        $this->configModel = new SearchEngineConfig();
    }

    /**
     * Main dashboard - shows configuration and manual ping options
     */
    public function index() {
        $this->requireAuth();
        
        $enabledEngines = $this->manager->getEnabledEngines();
        
        $this->view('admin/search-engine/index', [
            'enabledEngines' => $enabledEngines,
            'sitemapUrl' => rtrim(BASE_URL, '/') . '/sitemap.xml',
            'pageName' => 'features/search-engine'
        ]);
    }

    /**
     * Show configuration page
     */
    public function config() {
        $this->requireAuth();
        
        // Ensure all engines exist in config (auto-insert if missing)
        $this->configModel->ensureDefaults();
        
        $configs = $this->configModel->getAll();
        
        $this->view('admin/search-engine/config', [
            'configs' => $configs,
            'pageName' => 'features/search-engine'
        ]);
    }

    /**
     * Save configuration
     */
    public function saveConfig() {
        $this->requireAuth();
        
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/search-engine/config');
        }

        $engines = ['bing', 'yandex', 'google'];

        foreach ($engines as $engine) {
            $data = [
                'enabled' => isset($_POST[$engine . '_enabled']) ? 1 : 0,
                'api_key' => !empty($_POST[$engine . '_api_key'] ?? '') ? trim($_POST[$engine . '_api_key']) : null,
                'api_endpoint' => !empty($_POST[$engine . '_api_endpoint'] ?? '') ? trim($_POST[$engine . '_api_endpoint']) : null,
                'rate_limit_per_day' => intval($_POST[$engine . '_rate_limit'] ?? 10000),
            ];
            
            $this->configModel->update($engine, $data);
        }

        $_SESSION['success'] = 'Configuration saved successfully';
        $this->redirect('/admin/search-engine/config');
    }

    /**
     * Manually ping sitemap to all configured search engines
     */
    public function pingNow() {
        $this->requireAuth();
        
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/search-engine');
        }

        try {
            $sitemapUrl = rtrim(BASE_URL, '/') . '/sitemap.xml';
            $results = $this->manager->autoPingSitemap($sitemapUrl);
            
            $successCount = 0;
            $failCount = 0;
            
            foreach ($results as $engine => $result) {
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
            
            if ($successCount > 0) {
                $_SESSION['success'] = "Sitemap pinged successfully to $successCount search engine(s)";
            }
            if ($failCount > 0) {
                $_SESSION['warning'] = "Failed to ping $failCount search engine(s). Check logs for details.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error pinging sitemap: ' . $e->getMessage();
            error_log("Sitemap ping error: " . $e->getMessage());
        }
        
        $this->redirect('/admin/search-engine');
    }

    /**
     * Get API instance for external use (analytics, etc)
     */
    public function getAPI($engine) {
        return $this->manager->getAPI($engine);
    }
}

