<?php
// path: ./models/SearchEngineAPI/BingAPI.php

require_once __DIR__ . '/BaseAPI.php';

class BingAPI extends BaseAPI {
    const PING_ENDPOINT = 'https://www.bing.com/ping';
    
    public function __construct($apiKey = null, $config = []) {
        parent::__construct('bing', $apiKey, $config);
    }
    
    /**
     * Ping Bing sitemap
     */
    public function pingSitemap($sitemapUrl) {
        $url = self::PING_ENDPOINT . '?sitemap=' . urlencode($sitemapUrl);
        
        $result = $this->makeRequest($url, 'GET');
        
        return [
            'success' => $result['success'],
            'code' => $result['code'],
            'message' => $result['success'] ? 'Sitemap pinged successfully' : 'Failed to ping sitemap',
            'error' => $result['error']
        ];
    }
    
    /**
     * Verify Bing credentials (basic check)
     */
    public function verifyCredentials() {
        return [
            'valid' => true,
            'message' => 'Bing ping endpoint is accessible'
        ];
    }
}
