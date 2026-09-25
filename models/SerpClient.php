<?php
// path: ./models/SerpClient.php
// SerpApi (serpapi.com) Google Search client for AI Studio.
// - Pure curl, zero deps. Key from SERPAPI_API_KEY (.env).
// - File cache (storage/serp_cache, 1h TTL): SerpApi is pay-per-search,
//   so repeat queries within the hour cost nothing.

class SerpClient {

    private const API_URL = 'https://serpapi.com/search.json';
    private const CACHE_TTL = 3600; // seconds
    private const CACHE_MAX_FILES = 200;

    public static function isConfigured(): bool {
        return self::getApiKey() !== '';
    }

    public static function getApiKey(): string {
        return (string)(getenv('SERPAPI_API_KEY') ?: (defined('SERPAPI_API_KEY') ? SERPAPI_API_KEY : ''));
    }

    /**
     * Google search via SerpApi. Returns the decoded JSON payload.
     * @throws InvalidArgumentException on bad params (not retryable)
     * @throws RuntimeException on transport/API errors (retryable where transient)
     */
    public static function search(string $query, string $gl = 'uz', string $hl = 'ru', string $location = 'Tashkent, Uzbekistan', int $num = 10): array {
        $query = trim($query);
        if (mb_strlen($query) < 2 || mb_strlen($query) > 200) {
            throw new InvalidArgumentException('query must be 2-200 chars');
        }
        $num = max(1, min(10, $num));
        $key = self::getApiKey();
        if ($key === '') {
            throw new RuntimeException('SerpApi not configured — set SERPAPI_API_KEY in .env (https://serpapi.com).');
        }

        $params = ['engine' => 'google', 'q' => $query, 'gl' => $gl, 'hl' => $hl, 'location' => $location, 'num' => $num];
        $cacheKey = sha1(json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $cached = self::cacheGet($cacheKey);
        if ($cached !== null) return $cached;

        $url = self::API_URL . '?' . http_build_query($params + ['api_key' => $key]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['User-Agent: CustomSEOFocusedCMS-AIStudio/1.0 (+https://kuplyu-tashkent.uz)'],
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            throw new RuntimeException('SerpApi request timeout/network error: ' . mb_substr($err, 0, 200));
        }
        if ($code === 401 || $code === 403) {
            throw new RuntimeException('SerpApi key invalid or unauthorized (HTTP ' . $code . ') — check SERPAPI_API_KEY in .env.');
        }
        if ($code === 429) {
            throw new RuntimeException('SerpApi rate limit exceeded (HTTP 429) — retry in a moment.');
        }
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('SerpApi error (HTTP ' . $code . '): ' . mb_substr((string)$resp, 0, 300));
        }
        $data = json_decode((string)$resp, true);
        if (!is_array($data)) throw new RuntimeException('SerpApi bad JSON response');
        if (isset($data['error'])) throw new RuntimeException('SerpApi error: ' . mb_substr((string)$data['error'], 0, 300));

        self::cacheSet($cacheKey, $data);
        return $data;
    }

    private static function cacheGet(string $key): ?array {
        $file = BASE_PATH . '/storage/serp_cache/' . $key . '.json';
        if (!is_file($file)) return null;
        $mtime = @filemtime($file);
        if ($mtime === false || (time() - $mtime) > self::CACHE_TTL) return null;
        $fh = @fopen($file, 'r');
        if (!$fh) return null;
        @flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh);
        @flock($fh, LOCK_UN);
        @fclose($fh);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private static function cacheSet(string $key, array $data): void {
        $dir = BASE_PATH . '/storage/serp_cache';
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        $files = glob($dir . '/*.json') ?: [];
        if (count($files) >= self::CACHE_MAX_FILES) {
            usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
            foreach (array_slice($files, 0, count($files) - self::CACHE_MAX_FILES + 1) as $old) {
                if (is_link($old)) continue;
                @unlink($old);
            }
        }
        $file = $dir . '/' . $key . '.json';
        $fh = @fopen($file, 'c');
        if ($fh) {
            @flock($fh, LOCK_EX);
            ftruncate($fh, 0);
            fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            @flock($fh, LOCK_UN);
            @fclose($fh);
        } else {
            @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
    }
}
