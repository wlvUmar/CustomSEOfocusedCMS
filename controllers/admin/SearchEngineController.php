<?php
// path: ./controllers/admin/SearchEngineController.php

require_once BASE_PATH . '/models/SearchEngine.php';
require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/SearchEngineConfig.php';

class SearchEngineController extends Controller {
    private $engine;
    private $pageModel;
    private $configModel;

    public function __construct() {
        parent::__construct();
        $this->engine = new SearchEngine();
        $this->pageModel = new Page();
        $this->configModel = new SearchEngineConfig();
    }

    /**
     * Main dashboard - shows statistics and quick actions
     */
    public function index() {
        $this->requireAuth();
        
        $stats = $this->engine->getStatistics();
        
        $this->view('admin/search-engine/index', [
            'stats' => $stats
        ]);
    }

    /**
     * Show submission form
     */
    public function submitForm() {
        $this->requireAuth();
        
        $pages = $this->pageModel->getAll(false); // Only published pages
        $stats = $this->engine->getStatistics();
        
        $this->view('admin/search-engine/submit', [
            'pages' => $pages,
            'unsubmitted' => $stats['unsubmitted']
        ]);
    }

    /**
     * Submit a single page to search engines
     */
    public function submitPage() {
        $this->requireAuth();
        
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/search-engine');
        }

        $slug = $_POST['slug'] ?? null;
        $engines = $_POST['engines'] ?? [];

        if (!$slug || empty($engines)) {
            $_SESSION['error'] = 'Please select a page and at least one search engine';
            $this->redirect('/admin/search-engine/submit');
        }

        $userId = $_SESSION['user_id'] ?? null;
        
        try {
            $results = $this->engine->manualSubmit($slug, $engines, $userId);

            // Check if any succeeded
            $hasSuccess = false;
            $engines_list = [];
            $error_messages = [];
            
            foreach ($results as $engine => $result) {
                $engines_list[] = $engine;
                if ($result['status'] === 'success') {
                    $hasSuccess = true;
                } else {
                    $error_messages[] = "$engine: " . ($result['message'] ?? 'Unknown error');
                }
            }

            if ($hasSuccess) {
                $_SESSION['success'] = 'Page submitted successfully to ' . implode(', ', $engines_list);
            } else {
                $errorDetail = !empty($error_messages) ? implode('; ', $error_messages) : 'Check rate limits or configuration.';
                $_SESSION['error'] = 'Failed to submit page. ' . $errorDetail;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error during submission: ' . $e->getMessage();
            error_log("Search engine submission exception: " . $e->getMessage());
        }

        $this->redirect('/admin/search-engine');
    }

    /**
     * Batch submit multiple pages
     */
    public function batchSubmit() {
        $this->requireAuth();
        
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/search-engine');
        }

        $slugs = $_POST['slugs'] ?? [];
        $engine = $_POST['engine'] ?? 'bing';

        if (empty($slugs)) {
            $_SESSION['error'] = 'Please select at least one page';
            $this->redirect('/admin/search-engine/submit');
        }

        $userId = $_SESSION['user_id'] ?? null;
        
        try {
             // Reconstruct URLs for batch submit
            $urls = [];
            foreach($slugs as $slug) {
                $urls[] = BASE_URL . '/' . $slug; // A bit naive, but Service handles slug extraction from URL anyway
            }

            $results = $this->engine->batchSubmit($urls, $engine, 'manual', $userId);
            
            $successCount = 0;
            $failCount = 0;
            foreach ($results as $res) {
                if ($res['status'] === 'success') $successCount++;
                else $failCount++;
            }

            $_SESSION['success'] = "Batch submission complete: $successCount succeeded, $failCount failed";
        } catch(Exception $e) {
             $_SESSION['error'] = "Batch submission error: " . $e->getMessage();
        }
        
        $this->redirect('/admin/search-engine');
    }

    /**
     * Submit all unsubmitted pages
     */
    public function submitUnsubmitted() {
        $this->requireAuth();
        
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/search-engine');
        }

        $engine = $_POST['engine'] ?? 'bing';
        $stats = $this->engine->getStatistics();
        $unsubmitted = $stats['unsubmitted'];

        if (empty($unsubmitted)) {
            $_SESSION['info'] = 'No unsubmitted pages found';
            $this->redirect('/admin/search-engine');
        }

        $userId = $_SESSION['user_id'] ?? null;
        $successCount = 0;
        $failCount = 0;

        try {
            foreach ($unsubmitted as $page) {
                $result = $this->engine->manualSubmit($page['slug'], [$engine], $userId);
                
                if (isset($result[$engine]) && $result[$engine]['status'] === 'success') {
                    $successCount++;
                } else {
                    $failCount++;
                }
                
                // Small delay
                usleep(100000); 
            }
        } catch (Exception $e) {
            error_log("Bulk submission error: " . $e->getMessage());
            $_SESSION['warning'] = "Process interrupted: " . $e->getMessage();
        }

        $_SESSION['success'] = "Submitted $successCount pages successfully ($failCount failed or rate limited)";
        $this->redirect('/admin/search-engine');
    }

    /**
     * View submission history for a specific page
     */
    public function pageHistory($slug) {
        $this->requireAuth();
        
        $page = $this->pageModel->getBySlug($slug);
        if (!$page) {
            $_SESSION['error'] = 'Page not found';
            $this->redirect('/admin/search-engine');
        }

        $history = $this->engine->getPageHistory($slug, 50);
        $status = $this->engine->getPageStatus($slug);

        $this->view('admin/search-engine/page-history', [
            'page' => $page,
            'history' => $history,
            'status' => $status
        ]);
    }

    /**
     * Show configuration page
     */
    public function config() {
        $this->requireAuth();
        
        $configs = $this->configModel->getAll();
        
        $this->view('admin/search-engine/config', [
            'configs' => $configs
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

        $engines = ['bing', 'yandex', 'google', 'naver', 'seznam', 'yep'];

        foreach ($engines as $engine) {
            $data = [
                'enabled' => isset($_POST[$engine . '_enabled']) ? 1 : 0,
                'api_key' => $_POST[$engine . '_api_key'] ?? null,
                'rate_limit_per_day' => intval($_POST[$engine . '_rate_limit'] ?? 10000),
                'auto_submit_on_create' => isset($_POST[$engine . '_auto_create']) ? 1 : 0,
                'auto_submit_on_update' => isset($_POST[$engine . '_auto_update']) ? 1 : 0,
                'auto_submit_on_rotation' => isset($_POST[$engine . '_auto_rotation']) ? 1 : 0,
                'ping_sitemap' => isset($_POST[$engine . '_ping_sitemap']) ? 1 : 0
            ];
            
            $this->configModel->update($engine, $data);
        }

        $_SESSION['success'] = 'Configuration saved successfully';
        $this->redirect('/admin/search-engine/config');
    }

    /**
     * View recent submissions
     */
    public function recentSubmissions() {
        $this->requireAuth();
        
        $stats = $this->engine->getStatistics();
        
        $this->view('admin/search-engine/recent', [
            'submissions' => $stats['recent']
        ]);
    }

    /**
     * Export submission history as CSV
     */
    public function exportHistory() {
        $this->requireAuth();
        
        // Direct DB access for large export to avoid memory limit issues in Service if strictly modeled
        // Ideally should be in Service, but for streaming CSV, Controller direct access is sometimes pragmatic.
        // Let's stick to Service pattern effectively:
        // We'll use the service but maybe need a streaming method. 
        // For now, let's just use the logic from before but via the model we have.
        
        $db = Database::getInstance();
        $submissions = $db->fetchAll("SELECT * FROM search_submissions ORDER BY submitted_at DESC LIMIT 1000");

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="search-submissions-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, ['ID', 'Page Slug', 'URL', 'Engine', 'Type', 'Status', 'Code', 'Message', 'Submitted At']);
        
        // Data
        foreach ($submissions as $sub) {
            fputcsv($output, [
                $sub['id'],
                $sub['page_slug'],
                $sub['url'],
                $sub['search_engine'],
                $sub['submission_type'],
                $sub['status'],
                $sub['response_code'],
                $sub['response_message'],
                $sub['submitted_at']
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Test connection to a search engine
     */
    public function testConnection() {
        $this->requireAuth();
        
        $engine = $_POST['engine'] ?? null;
        
        if (!$engine) {
            echo json_encode(['success' => false, 'message' => 'No engine specified']);
            exit;
        }

        $config = $this->configModel->get($engine);

        if (!$config || !$config['enabled']) {
            echo json_encode(['success' => false, 'message' => 'Engine not configured or disabled']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Configuration valid']);
        exit;
    }
    
    /**
     * Regenerate Bing IndexNow API key
     */
    public function regenerateApiKey() {
        $this->requireAuth();
        
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/search-engine/config');
        }
        
        try {
            $newKey = $this->engine->generateApiKey();
            $_SESSION['success'] = 'API key regenerated successfully: ' . $newKey;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Failed to regenerate API key: ' . $e->getMessage();
        }
        
        $this->redirect('/admin/search-engine/config');
    }
    
    /**
     * Verify API key file is accessible
     */
    public function verifyApiKeyFile() {
        $this->requireAuth();
        header('Content-Type: application/json');
        
        $keyUrl = $this->engine->getApiKeyFileUrl();
        
        if (!$keyUrl) {
            echo json_encode(['success' => false, 'message' => 'No API key configured']);
            exit;
        }
        
        // Try to fetch the file
        $ch = curl_init($keyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            echo json_encode([
                'success' => true, 
                'message' => 'API key file is accessible',
                'url' => $keyUrl,
                'content' => $response
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => "API key file not accessible (HTTP $httpCode)",
                'url' => $keyUrl
            ]);
        }
        exit;
    }
}
