<?php
// path: ./models/IndexNow.php

class IndexNow {
    // Generated 32-character hex key
    private const API_KEY = '7f5e8d9c2a3b4c5d6e7f8a9b0c1d2e3f';
    private const KEY_FILE_NAME = '7f5e8d9c2a3b4c5d6e7f8a9b0c1d2e3f.txt';
    private const API_ENDPOINT = 'https://api.indexnow.org/indexnow';

    /**
     * Submit a URL or list of URLs to IndexNow
     * @param string|array $urls Single URL or array of URLs
     * @return bool|string True on success, error message on failure
     */
    public static function submit($urls) {
        if (!is_array($urls)) {
            $urls = [$urls];
        }

        // Ensure key file exists
        self::ensureKeyFile();

        $data = [
            'host' => $_SERVER['HTTP_HOST'],
            'key' => self::API_KEY,
            'keyLocation' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/" . self::KEY_FILE_NAME,
            'urlList' => $urls
        ];

        return self::sendRequest($data);
    }

    /**
     * Send HTTP POST request to IndexNow
     */
    private static function sendRequest($data) {
        $ch = curl_init(self::API_ENDPOINT);
        $payload = json_encode($data);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen($payload)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout after 5 seconds

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            logDebug("[IndexNow] CURL Error: $error");
            return "CURL Error: $error";
        }

        if ($httpCode === 200 || $httpCode === 202) {
            logInfo("[IndexNow] Successfully submitted " . count($data['urlList']) . " URLs.");
            return true;
        }

        logDebug("[IndexNow] Submission failed. HTTP Code: $httpCode. Response: $response");
        return "HTTP Error: $httpCode";
    }

    /**
     * Ensure the API key file exists in the public directory
     */
    public static function ensureKeyFile() {
        $filePath = PUBLIC_PATH . '/' . self::KEY_FILE_NAME;
        
        if (!file_exists($filePath)) {
            if (file_put_contents($filePath, self::API_KEY) !== false) {
                logInfo("[IndexNow] Created key file at $filePath");
            } else {
                logDebug("[IndexNow] Failed to create key file at $filePath");
            }
        }
    }
}
