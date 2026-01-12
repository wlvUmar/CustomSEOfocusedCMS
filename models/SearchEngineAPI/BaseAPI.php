<?php
abstract class BaseAPI {
    protected $engine;
    protected $apiKey;
    protected $config = [];
    
    public function __construct($engine, $apiKey = null, $config = []) {
        $this->engine = $engine;
        $this->apiKey = $apiKey;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }
    

    protected function getDefaultConfig() {
        return [
            'timeout' => 10,
            'verify_ssl' => true
        ];
    }
    

    abstract public function pingSitemap($sitemapUrl);

    public function getStats() {
        return ['engine' => $this->engine, 'status' => 'not_implemented'];
    }
    

    public function verifyCredentials() {
        return ['valid' => false, 'message' => 'Not implemented for this engine'];
    }
    

    protected function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
        $ch = curl_init($url);
        
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl']
        ];
        
        if ($data) {
            if (is_array($data)) {
                $data = json_encode($data);
                $headers[] = 'Content-Type: application/json';
            }
            $options[CURLOPT_POSTFIELDS] = $data;
        }
        
        if (!empty($headers)) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'code' => $httpCode,
            'response' => $response,
            'error' => $error
        ];
    }
}
