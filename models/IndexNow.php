<?php
// path: ./models/IndexNow.php

class IndexNow {
    // Legacy key, leaked in git history. Used ONLY as a backward-compat fallback
    // when INDEXNOW_API_KEY is not configured. Rotate by setting INDEXNOW_API_KEY.
    private const LEGACY_API_KEY = '7f5e8d9c2a3b4c5d6e7f8a9b0c1d2e3f';
    private const API_ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** @var bool Per-request guard so the key file is checked at most once. */
    private static $keyFileChecked = false;

    /**
     * Submit a URL or list of URLs to IndexNow
     * @param string|array $urls Single URL or array of URLs
     * @return bool|string True on success, error message on failure
     */
    public static function submit($urls) {
        if (!is_array($urls)) {
            $urls = [$urls];
        }

        $apiKey = self::resolveApiKey();

        // Host and keyLocation come from the canonical BASE_URL, never from the request Host header.
        $base = Url::siteBaseUrl();
        $host = parse_url($base, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return 'IndexNow: invalid site base URL';
        }

        self::ensureKeyFile($apiKey);

        $data = [
            'host' => $host,
            'key' => $apiKey,
            'keyLocation' => $base . '/' . self::keyFileName($apiKey),
            'urlList' => $urls
        ];

        return self::sendRequest($data);
    }

    /**
     * Resolve the API key: INDEXNOW_API_KEY (preferred), else legacy fallback with a deprecation log.
     */
    private static function resolveApiKey() {
        $key = defined('INDEXNOW_API_KEY') ? trim((string)INDEXNOW_API_KEY) : '';
        if ($key !== '') {
            return $key;
        }

        error_log('[IndexNow] DEPRECATED: INDEXNOW_API_KEY is not set; falling back to the hardcoded legacy key. Set INDEXNOW_API_KEY in .env and rotate the key.');
        return self::LEGACY_API_KEY;
    }

    private static function keyFileName($apiKey) {
        return $apiKey . '.txt';
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
     * Ensure the key file exists in PUBLIC_PATH with the key as its content.
     * Idempotent: checks the filesystem at most once per request, and only writes
     * when the file is missing or its content does not match the key.
     */
    public static function ensureKeyFile($apiKey = null) {
        if (self::$keyFileChecked) {
            return true;
        }
        if ($apiKey === null) {
            $apiKey = self::resolveApiKey();
        }

        $filePath = rtrim(PUBLIC_PATH, '/\\') . '/' . self::keyFileName($apiKey);

        if (is_file($filePath) && trim((string)@file_get_contents($filePath)) === $apiKey) {
            self::$keyFileChecked = true;
            return true;
        }

        if (file_put_contents($filePath, $apiKey) !== false) {
            logInfo("[IndexNow] Created key file at $filePath");
            self::$keyFileChecked = true;
            return true;
        }

        logDebug("[IndexNow] Failed to create key file at $filePath");
        return false;
    }
}
