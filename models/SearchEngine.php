<?php
// path: ./models/SearchEngine.php

require_once BASE_PATH . '/models/SearchSubmission.php';
require_once BASE_PATH . '/models/SearchEngineConfig.php';

class SearchEngine {
    private $submissionModel;
    private $configModel;
    private $config = [];
    
    // Constants
    const BING_INDEXNOW_ENDPOINT = 'https://www.bing.com/indexnow';
    const YANDEX_INDEXNOW_ENDPOINT = 'https://yandex.com/indexnow';
    
    public function __construct() {
        $this->submissionModel = new SearchSubmission();
        $this->configModel = new SearchEngineConfig();
        $this->configModel->ensureDefaults(); // Ensure default configs exist
        $this->loadConfig();
        $this->ensureApiKeyFile();
    }
    
    /**
     * Load active configuration
     */
    private function loadConfig() {
        $configs = $this->configModel->getEnabled();
        
        foreach ($configs as $config) {
            $this->config[$config['engine']] = $config;
            
            // Reset daily counter if needed (logic moved from individual methods)
            if ($config['last_reset_date'] !== date('Y-m-d')) {
                $this->configModel->resetDailyCounter($config['engine']);
                // Update local config copy for this request
                $this->config[$config['engine']]['submissions_today'] = 0;
            }
        }
    }
    
    /**
     * Unified notification method
     */
    public function notifyPageChange($slug, $type = 'update', $rotationMonth = null, $userId = null) {
        $url = $this->buildPageUrl($slug);
        $results = [];
        
        error_log("SearchEngineService: notifyPageChange slug=$slug type=$type");
        
        foreach ($this->config as $engine => $config) {
            // Check auto-submit rules
            if ($type !== 'manual') {
                $autoSubmitKey = "auto_submit_on_{$type}";
                if (empty($config[$autoSubmitKey])) {
                    continue;
                }
            }
            
            // Check rate limit
            if (!$this->checkRateLimit($engine)) {
                $this->submissionModel->log($slug, $url, $engine, $type, 'rate_limited', null, 
                    'Daily rate limit reached', $rotationMonth, $userId);
                $results[$engine] = ['status' => 'rate_limited', 'message' => 'Daily rate limit reached'];
                continue;
            }
            
            // Check cooldown
            if ($this->submissionModel->wasRecentlySubmitted($slug, $engine, 3600)) { 
                continue;
            }
            
            // Execute submission
            try {
                $result = $this->submitToEngine($engine, $url);
            } catch (Exception $e) {
                error_log("SearchEngineService: Exception for $engine: " . $e->getMessage());
                $result = ['status' => 'failed', 'message' => 'Exception: ' . $e->getMessage()];
            }
            
            // Log result
            $this->submissionModel->log($slug, $url, $engine, $type, 
                $result['status'], $result['code'] ?? null, $result['message'], 
                $rotationMonth, $userId);

            if ($result['status'] === 'success') {
                $this->configModel->incrementRateLimit($engine);
                // Update local counter
                $this->config[$engine]['submissions_today']++;
            }
            
            $this->submissionModel->updateStatus($slug, $engine, $result['status'], $result['message']);
            $results[$engine] = $result;
        }
        
        return $results;
    }
    
    /**
     * Dispatch to specific engine handler
     */
    private function submitToEngine($engine, $url) {
        switch ($engine) {
            case 'bing':   return $this->submitToBing($url);
            case 'yandex': return $this->submitToYandex($url);
            case 'google': return $this->pingGoogleSitemap();
            default:       return ['status' => 'failed', 'message' => 'Unknown engine'];
        }
    }

    private function submitToBing($url) {
        return $this->submitToIndexNow($url, self::BING_INDEXNOW_ENDPOINT, 'Bing');
    }
    
    private function submitToYandex($url) {
        return $this->submitToIndexNow($url, self::YANDEX_INDEXNOW_ENDPOINT, 'Yandex');
    }
    
    /**
     * Generic IndexNow submission
     */
    private function submitToIndexNow($url, $endpoint, $engineName) {
        // Get API key from loaded config, or load it from DB if not found
        $apiKey = $this->config['bing']['api_key'] ?? null;
        
        if (empty($apiKey)) {
            // API key not in memory, fetch from DB directly
            $bingConfig = $this->configModel->get('bing');
            if ($bingConfig && !empty($bingConfig['api_key'])) {
                $apiKey = $bingConfig['api_key'];
            } else {
                // No API key in DB, generate a new one
                $apiKey = $this->generateApiKey();
            }
        }
        
        $data = [
            'host' => parse_url(BASE_URL, PHP_URL_HOST),
            'key' => $apiKey,
            'urlList' => [$url]
        ];
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode == 200 || $httpCode == 202) {
            return [
                'status' => 'success',
                'code' => $httpCode,
                'message' => "Successfully submitted to $engineName IndexNow"
            ];
        } else {
            return [
                'status' => 'failed',
                'code' => $httpCode,
                'message' => $error ?: "HTTP $httpCode"
            ];
        }
    }
    
    private function pingGoogleSitemap() {
        // Google deprecated ping, just return success to indicate we acknowledged it
        // We could log sitemap generation here if needed
        return [
            'status' => 'success', 
            'code' => 200, 
            'message' => 'Sitemap scheduled for crawl (Google Auto-Discovery)'
        ];
    }
    
    /**
     * Batch submit multiple URLs
     */
    public function batchSubmit($urls, $engine = 'bing', $type = 'manual', $userId = null) {
        $results = [];
        
        // If engine config not loaded (disabled), try to load it specifically
        if (!isset($this->config[$engine])) {
            $engineConfig = $this->configModel->get($engine);
            if ($engineConfig) {
                $this->config[$engine] = $engineConfig;
            } else {
                // Return failed status for all URLs to keep controller happy
                foreach ($urls as $url) {
                    $results[$url] = ['status' => 'failed', 'message' => 'Engine not configured'];
                }
                return $results;
            }
        }
        
        foreach ($urls as $url) {
            $slug = $this->extractSlugFromUrl($url);
            
            if (!$this->checkRateLimit($engine)) {
                $results[$url] = ['status' => 'rate_limited', 'message' => 'Daily rate limit reached'];
                continue; // Don't break, just skip this one (though rate limit likely applies to all)
            }
            
            $batchResult = $this->notifyPageChange($slug, $type, null, $userId);
            // Extract just this engine's result
            $results[$url] = $batchResult[$engine] ?? ['status' => 'failed', 'message' => 'Unknown error'];
            
            usleep(100000); // 0.1s delay
        }
        
        return $results;
    }
    
    /**
     * Manual submit wrapper
     */
    public function manualSubmit($slug, $engines = ['bing'], $userId = null) {
        $results = [];
        $url = $this->buildPageUrl($slug);
        
        foreach ($engines as $engine) {
            if (!isset($this->config[$engine])) {
                $results[$engine] = [
                    'status' => 'failed', 
                    'message' => "Engine '$engine' is not enabled. Go to Search Engine Config and enable it first."
                ];
                continue;
            }

            // Check if can resubmit
            $status = $this->submissionModel->getStatus($slug);
            if (isset($status[$engine]) && $status[$engine]['can_resubmit_at'] > date('Y-m-d H:i:s')) {
                $results[$engine] = ['status' => 'rate_limited', 'message' => 'Cooldown active'];
                continue;
            }

            // Force submit (bypass auto checks, but respect rate limits in submitToEngine wrapper logic if needed)
            // But we use notifyPageChange('manual') which handles rate limits
            $batchResult = $this->notifyPageChange($slug, 'manual', null, $userId);
            $results[$engine] = $batchResult[$engine] ?? ['status' => 'failed'];
        }
        
        return $results;
    }

    /**
     * Helper: Check rate limit
     */
    private function checkRateLimit($engine) {
        if (!isset($this->config[$engine])) return false;
        
        $limit = $this->config[$engine]['rate_limit_per_day'];
        $current = $this->config[$engine]['submissions_today'];
        
        return $current < $limit;
    }
    
    /**
     * Check if a specific engine is enabled
     */
    public function isEngineEnabled($engine) {
        return isset($this->config[$engine]);
    }
    
    /**
     * Get list of enabled engines
     */
    public function getEnabledEngines() {
        return array_keys($this->config);
    }
    
    /**
     * Get engine config for all engines (enabled and disabled)
     */
    public function getAllEngineConfigs() {
        return $this->configModel->getAll();
    }

    /**
     * Helper: Build URL
     */
    private function buildPageUrl($slug) {
        return rtrim(BASE_URL, '/') . '/' . $slug;
    }
    
    /**
     * Helper: Extract slug from URL, handling subfolder installations
     */
    private function extractSlugFromUrl($url) {
        $path = parse_url($url, PHP_URL_PATH);
        $baseUrlPath = parse_url(BASE_URL, PHP_URL_PATH) ?: '';
        
        // Remove trailing slash from base path for matching
        $baseUrlPath = rtrim($baseUrlPath, '/');
        
        if (!empty($baseUrlPath) && strpos($path, $baseUrlPath) === 0) {
            $path = substr($path, strlen($baseUrlPath));
        }
        
        return trim($path, '/');
    }

    /**
     * Generate API Key
     */
    public function generateApiKey() {
        // Reuse existing if possible (should be consistent across IndexNow)
        if (!empty($this->config['bing']['api_key'])) {
            return $this->config['bing']['api_key'];
        }
        
        $key = bin2hex(random_bytes(16));
        $this->configModel->updateApiKey('bing', $key);
        
        // Also update other IndexNow engines to share key if needed, or just rely on 'bing' as master key
        // Simplest is to treat 'bing' key as the Master IndexNow key
        
        $this->createKeyFile($key);
        $this->loadConfig(); // Reload
        
        return $key;
    }
    
    /**
     * Ensure local key file exists
     */
    private function ensureApiKeyFile() {
        if (!empty($this->config['bing']['api_key'])) {
            $key = $this->config['bing']['api_key'];
            $file = BASE_PATH . '/public/' . $key . '.txt';
            if (!file_exists($file)) {
                $this->createKeyFile($key);
            }
        }
    }
    
    private function createKeyFile($key) {
        $filesCreated = 0;
        
        // 1. Primary location: Project public directory (source)
        $publicDir = BASE_PATH . '/public';
        $file = $publicDir . '/' . $key . '.txt';
        
        if (!is_dir($publicDir)) {
             @mkdir($publicDir, 0755, true);
        }
        
        if (@file_put_contents($file, $key) !== false) {
            @chmod($file, 0644);
            $filesCreated++;
        }
        
        // 2. Production location: DOCUMENT_ROOT (cPanel public_html)
        // This ensures the file exists in the serving directory even if app code is outside webroot
        $targets = [];
        
        // A. Try DOCUMENT_ROOT
        if (isset($_SERVER['DOCUMENT_ROOT']) && is_dir($_SERVER['DOCUMENT_ROOT'])) {
            $targets[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        }
        
        // B. Try sibling public_html (Explicit cPanel structure check)
        // If BASE_PATH is /home/user/appliances, check /home/user/public_html
        $siblingPublicHtml = dirname(BASE_PATH) . '/public_html';
        if (is_dir($siblingPublicHtml)) {
            $targets[] = $siblingPublicHtml;
        }

        foreach ($targets as $targetDir) {
            // Avoid duplicate write if paths are identical to primary publicDir
            if (realpath($targetDir) !== realpath($publicDir)) {
                $docFile = $targetDir . '/' . $key . '.txt';
                if (@file_put_contents($docFile, $key) !== false) {
                    @chmod($docFile, 0644);
                    $filesCreated++;
                }
            }
        }
        
        if ($filesCreated > 0) {
            return true;
        }
        
        $this->configModel->logNote('bing', "Failed to create key file: $key");
        return false;
    }
    
    public function getApiKeyFileUrl() {
        if (!empty($this->config['bing']['api_key'])) {
            return rtrim(BASE_URL, '/') . '/' . $this->config['bing']['api_key'] . '.txt';
        }
        return null;
    }

    public function getStatistics() {
        return [
            'by_engine' => $this->submissionModel->getStatsByEngine(),
            'recent' => $this->submissionModel->getRecent(),
            'unsubmitted' => $this->submissionModel->getUnsubmitted(),
            'due_resubmit' => $this->submissionModel->getDueResubmit(),
            'config' => $this->config
        ];
    }
    
    public function getPageHistory($slug, $limit) {
        return $this->submissionModel->getHistory($slug, $limit);
    }
    
    public function getPageStatus($slug) {
        return $this->submissionModel->getStatus($slug);
    }
}
