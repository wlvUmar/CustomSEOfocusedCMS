<?php
// path: ./models/SearchEngineManager.php
/**
 * Manages search engine integrations
 * Handles auto-pinging of sitemaps across all configured search engines
 * Designed to be scalable for future analytics and advanced features
 */

require_once BASE_PATH . '/models/SearchEngineAPI/BingAPI.php';
require_once BASE_PATH . '/models/SearchEngineAPI/YandexAPI.php';
require_once BASE_PATH . '/models/SearchEngineAPI/GoogleAPI.php';
require_once BASE_PATH . '/models/SearchEngineConfig.php';

class SearchEngineManager {
    private $configModel;
    private $apis = [];
    
    public function __construct() {
        $this->configModel = new SearchEngineConfig();
        // Ensure all engines exist in config (auto-insert if missing)
        $this->configModel->ensureDefaults();
        $this->initializeAPIs();
    }
    
    /**
     * Initialize API instances for all configured engines
     */
    private function initializeAPIs() {
        $configs = $this->configModel->getAll();
        
        foreach ($configs as $config) {
            if (!$config['enabled']) {
                continue;
            }
            
            switch ($config['engine']) {
                case 'bing':
                    $this->apis['bing'] = new BingAPI($config['api_key']);
                    break;
                case 'yandex':
                    $this->apis['yandex'] = new YandexAPI($config['api_key']);
                    break;
                case 'google':
                    $this->apis['google'] = new GoogleAPI($config['api_key']);
                    break;
            }
        }
    }
    
    /**
     * Auto-ping sitemap to all enabled search engines
     * Called automatically when pages are created, updated, or rotated
     */
    public function autoPingSitemap($sitemapUrl = null) {
        if (!$sitemapUrl) {
            $sitemapUrl = rtrim(BASE_URL, '/') . '/sitemap.xml';
        }
        
        $results = [];
        
        foreach ($this->apis as $engine => $api) {
            try {
                $result = $api->pingSitemap($sitemapUrl);
                $results[$engine] = $result;
                
                if ($result['success']) {
                    error_log("✓ Auto-ping SUCCESS: {$engine} sitemap");
                } else {
                    error_log("✗ Auto-ping FAILED: {$engine} sitemap - " . ($result['error'] ?? 'unknown error'));
                }
            } catch (Exception $e) {
                $results[$engine] = [
                    'success' => false,
                    'code' => 0,
                    'message' => 'Exception occurred',
                    'error' => $e->getMessage()
                ];
                error_log("✗ Auto-ping EXCEPTION: {$engine} - " . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Get API instance for a specific engine
     * Useful for analytics and advanced features
     */
    public function getAPI($engine) {
        return $this->apis[$engine] ?? null;
    }
    
    /**
     * Get all available API instances
     */
    public function getAllAPIs() {
        return $this->apis;
    }
    
    /**
     * Check if engine is enabled and available
     */
    public function isEngineEnabled($engine) {
        return isset($this->apis[$engine]);
    }
    
    /**
     * Get list of enabled engines
     */
    public function getEnabledEngines() {
        return array_keys($this->apis);
    }
    
    /**
     * Get engine configuration
     */
    public function getEngineConfig($engine) {
        return $this->configModel->get($engine);
    }
    
    /**
     * Get all engine configurations
     */
    public function getAllEngineConfigs() {
        return $this->configModel->getAll();
    }
}
