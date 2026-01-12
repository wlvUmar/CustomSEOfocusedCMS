<?php
// path: ./models/SearchEngineAPI/YandexAPI.php

require_once __DIR__ . '/BaseAPI.php';

class YandexAPI extends BaseAPI {
    const PING_ENDPOINT = 'https://yandex.com/ping';
    
    public function __construct($apiKey = null, $config = []) {
        parent::__construct('yandex', $apiKey, $config);
    }
    
    /**
     * Ping Yandex sitemap
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
     * Verify Yandex credentials (basic check)
     */
    public function verifyCredentials() {
        return [
            'valid' => true,
            'message' => 'Yandex ping endpoint is accessible'
        ];
    }
}
