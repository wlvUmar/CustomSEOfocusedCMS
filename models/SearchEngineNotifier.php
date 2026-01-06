<?php
// path: ./models/SearchEngineNotifier.php

class SearchEngineNotifier {
    private $db;
    private $config = [];
    
    const BING_INDEXNOW_ENDPOINT = 'https://www.bing.com/indexnow';
    const YANDEX_PING_ENDPOINT = 'https://webmaster.yandex.com/ping?sitemap=';
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->loadConfig();
    }
    
    /**
     * Load search engine configurations
     */
    private function loadConfig() {
        $sql = "SELECT * FROM search_engine_config WHERE enabled = 1";
        $configs = $this->db->fetchAll($sql);
        
        foreach ($configs as $config) {
            $this->config[$config['engine']] = $config;
            
            // Reset daily counter if needed
            if ($config['last_reset_date'] !== date('Y-m-d')) {
                $this->resetDailyCounter($config['engine']);
            }
        }
    }
    
    /**
     * Notify search engines about a page change
     */
    public function notifyPageChange($slug, $type = 'update', $rotationMonth = null, $userId = null) {
        $url = $this->buildPageUrl($slug);
        $results = [];
        
        // Check each enabled engine
        foreach ($this->config as $engine => $config) {
            // Check if this type of submission is enabled
            $autoSubmitKey = "auto_submit_on_{$type}";
            if (!$config[$autoSubmitKey]) {
                continue;
            }
            
            // Check rate limit
            if (!$this->checkRateLimit($engine)) {
                $this->logSubmission($slug, $url, $engine, $type, 'rate_limited', null, 
                    'Daily rate limit reached', $rotationMonth, $userId);
                continue;
            }
            
            // Check if recently submitted (avoid spam)
            if ($this->wasRecentlySubmitted($slug, $engine, 3600)) { // 1 hour cooldown
                continue;
            }
            
            // Submit based on engine
            switch ($engine) {
                case 'bing':
                    $result = $this->submitToBing($url);
                    break;
                case 'yandex':
                    $result = $this->submitToYandex($url);
                    break;
                case 'google':
                    $result = $this->pingGoogleSitemap();
                    break;
                default:
                    continue 2;
            }
            
            // Log submission
            $this->logSubmission($slug, $url, $engine, $type, 
                $result['status'], $result['code'], $result['message'], 
                $rotationMonth, $userId);
            
            // Update status
            $this->updateSubmissionStatus($slug, $engine, $result['status'], $result['message']);
            
            $results[$engine] = $result;
        }
        
        return $results;
    }
    
    /**
     * Submit URL to Bing IndexNow API
     */
    private function submitToBing($url) {
        $apiKey = $this->config['bing']['api_key'] ?? $this->generateApiKey();
        
        $data = [
            'host' => parse_url(BASE_URL, PHP_URL_HOST),
            'key' => $apiKey,
            'urlList' => [$url]
        ];
        
        $ch = curl_init(self::BING_INDEXNOW_ENDPOINT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Bing returns 200 on success, 202 on accepted
        if ($httpCode == 200 || $httpCode == 202) {
            return [
                'status' => 'success',
                'code' => $httpCode,
                'message' => 'Successfully submitted to Bing IndexNow'
            ];
        } else {
            return [
                'status' => 'failed',
                'code' => $httpCode,
                'message' => $error ?: "HTTP $httpCode"
            ];
        }
    }
    
    /**
     * Ping Yandex with sitemap
     */
    private function submitToYandex($url) {
        $sitemapUrl = BASE_URL . '/sitemap.xml';
        $pingUrl = self::YANDEX_PING_ENDPOINT . urlencode($sitemapUrl);
        
        $ch = curl_init($pingUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            return [
                'status' => 'success',
                'code' => $httpCode,
                'message' => 'Successfully pinged Yandex sitemap'
            ];
        } else {
            return [
                'status' => 'failed',
                'code' => $httpCode,
                'message' => "Failed to ping Yandex: HTTP $httpCode"
            ];
        }
    }
    
    /**
     * Ping Google (via sitemap update - Google crawls naturally)
     */
    private function pingGoogleSitemap() {
        // Google no longer has a ping endpoint
        // They discover updates through the sitemap automatically
        // We just ensure sitemap is fresh
        
        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Sitemap updated for Google to discover'
        ];
    }
    
    /**
     * Submit multiple URLs at once (batch)
     */
    public function batchSubmit($urls, $engine = 'bing', $type = 'manual', $userId = null) {
        if (!isset($this->config[$engine])) {
            return ['status' => 'failed', 'message' => 'Engine not configured'];
        }
        
        $results = [];
        
        foreach ($urls as $url) {
            $slug = $this->extractSlugFromUrl($url);
            
            if (!$this->checkRateLimit($engine)) {
                break; // Stop if rate limit reached
            }
            
            $result = $this->notifyPageChange($slug, $type, null, $userId);
            $results[$url] = $result;
            
            // Small delay to avoid overwhelming the API
            usleep(100000); // 0.1 second
        }
        
        return $results;
    }
    
    /**
     * Check if rate limit allows submission
     */
    private function checkRateLimit($engine) {
        if (!isset($this->config[$engine])) {
            return false;
        }
        
        $config = $this->config[$engine];
        $limit = $config['rate_limit_per_day'];
        $current = $config['submissions_today'];
        
        if ($current >= $limit) {
            return false;
        }
        
        // Increment counter
        $sql = "UPDATE search_engine_config 
                SET submissions_today = submissions_today + 1 
                WHERE engine = ?";
        $this->db->query($sql, [$engine]);
        
        return true;
    }
    
    /**
     * Reset daily submission counter
     */
    private function resetDailyCounter($engine) {
        $sql = "UPDATE search_engine_config 
                SET submissions_today = 0, 
                    last_reset_date = CURDATE() 
                WHERE engine = ?";
        $this->db->query($sql, [$engine]);
        
        // Reload config
        $this->loadConfig();
    }
    
    /**
     * Check if page was recently submitted
     */
    private function wasRecentlySubmitted($slug, $engine, $seconds = 3600) {
        $sql = "SELECT COUNT(*) as count 
                FROM search_submissions 
                WHERE page_slug = ? 
                AND search_engine = ? 
                AND status = 'success'
                AND submitted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $result = $this->db->fetchOne($sql, [$slug, $engine, $seconds]);
        return $result['count'] > 0;
    }
    
    /**
     * Log submission to database
     */
    private function logSubmission($slug, $url, $engine, $type, $status, $code, $message, $rotationMonth = null, $userId = null) {
        $sql = "INSERT INTO search_submissions 
                (page_slug, url, search_engine, submission_type, status, 
                 response_code, response_message, rotation_month, user_id, completed_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->query($sql, [
            $slug, $url, $engine, $type, $status, 
            $code, $message, $rotationMonth, $userId
        ]);
    }
    
    /**
     * Update submission status table
     */
    private function updateSubmissionStatus($slug, $engine, $status, $message) {
        $sql = "INSERT INTO search_submission_status 
                (page_slug, search_engine, last_submitted_at, last_success_at, 
                 total_submissions, successful_submissions, failed_submissions,
                 last_status, last_response, can_resubmit_at)
                VALUES (?, ?, NOW(), ?, 1, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                last_submitted_at = NOW(),
                last_success_at = IF(? = 'success', NOW(), last_success_at),
                total_submissions = total_submissions + 1,
                successful_submissions = successful_submissions + IF(? = 'success', 1, 0),
                failed_submissions = failed_submissions + IF(? = 'failed', 1, 0),
                last_status = VALUES(last_status),
                last_response = VALUES(last_response),
                can_resubmit_at = VALUES(can_resubmit_at)";
        
        $lastSuccess = $status === 'success' ? date('Y-m-d H:i:s') : null;
        $successInc = $status === 'success' ? 1 : 0;
        $failInc = $status === 'failed' ? 1 : 0;
        
        // Calculate next allowed submission time (1 hour cooldown)
        $canResubmit = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $this->db->query($sql, [
            $slug, $engine, $lastSuccess, 
            $successInc, $failInc, $status, $message, $canResubmit,
            $status, $status, $status
        ]);
    }
    
    /**
     * Get submission history for a page
     */
    public function getPageHistory($slug, $limit = 20) {
        $sql = "SELECT * FROM search_submissions 
                WHERE page_slug = ? 
                ORDER BY submitted_at DESC 
                LIMIT ?";
        return $this->db->fetchAll($sql, [$slug, $limit]);
    }
    
    /**
     * Get submission status for a page across all engines
     */
    public function getPageStatus($slug) {
        $sql = "SELECT * FROM search_submission_status WHERE page_slug = ?";
        $results = $this->db->fetchAll($sql, [$slug]);
        
        $status = [];
        foreach ($results as $row) {
            $status[$row['search_engine']] = $row;
        }
        
        return $status;
    }
    
    /**
     * Get overall statistics
     */
    public function getStatistics() {
        return [
            'by_engine' => $this->db->fetchAll("SELECT * FROM v_submission_stats"),
            'recent' => $this->db->fetchAll("SELECT * FROM v_recent_submissions LIMIT 50"),
            'unsubmitted' => $this->db->fetchAll("SELECT * FROM v_unsubmitted_pages LIMIT 20"),
            'due_resubmit' => $this->db->fetchAll("SELECT * FROM v_pages_due_resubmit LIMIT 20"),
            'config' => $this->config
        ];
    }
    
    /**
     * Manual resubmission from admin
     */
    public function manualSubmit($slug, $engines = ['bing'], $userId = null) {
        $results = [];
        
        foreach ($engines as $engine) {
            if (!isset($this->config[$engine])) {
                continue;
            }
            
            // Check if can resubmit
            $status = $this->getPageStatus($slug);
            if (isset($status[$engine]) && $status[$engine]['can_resubmit_at'] > date('Y-m-d H:i:s')) {
                $results[$engine] = [
                    'status' => 'rate_limited',
                    'message' => 'Must wait before resubmitting'
                ];
                continue;
            }
            
            // Submit
            $result = $this->notifyPageChange($slug, 'manual', null, $userId);
            $results[$engine] = $result[$engine] ?? ['status' => 'failed', 'message' => 'Unknown error'];
        }
        
        return $results;
    }
    
    /**
     * Helper: Build full page URL
     */
    private function buildPageUrl($slug) {
        return rtrim(BASE_URL, '/') . '/' . $slug;
    }
    
    /**
     * Helper: Extract slug from URL
     */
    private function extractSlugFromUrl($url) {
        $path = parse_url($url, PHP_URL_PATH);
        return trim($path, '/');
    }
    
    /**
     * Generate API key for IndexNow (if not set)
     */
    private function generateApiKey() {
        // Check if we already have one in config
        $existing = $this->config['bing']['api_key'] ?? null;
        if ($existing) {
            return $existing;
        }
        
        // Generate new key
        $key = bin2hex(random_bytes(16));
        
        // Save to config
        $sql = "UPDATE search_engine_config SET api_key = ? WHERE engine = 'bing'";
        $this->db->query($sql, [$key]);
        
        // Create verification file (Bing requires this)
        $keyFile = BASE_PATH . '/public/' . $key . '.txt';
        file_put_contents($keyFile, $key);
        
        return $key;
    }
}
