<?php
// path: ./models/gsc/GscSearchApi.php
// GSC Search Analytics + site list + page→slug helper.
// Loaded via models/GscClient.php (thin facade keeps caller API stable).

require_once BASE_PATH . '/models/gsc/GscCache.php';

class GscSearchApi {

    private const API_BASE = 'https://www.googleapis.com/webmasters/v3';

    /**
     * Single curl helper for GSC JSON API calls (timeout always set).
     * @return array{code:int,err:string,resp:string}
     */
    private static function request(string $method, string $url, string $token, ?string $body, int $timeout): array {
        $ch = curl_init($url);
        $headers = ['Authorization: Bearer ' . $token];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $body !== null ? array_merge($headers, ['Content-Type: application/json']) : $headers,
            CURLOPT_TIMEOUT => $timeout,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = (string)$body;
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['code' => $code, 'err' => $err, 'resp' => (string)$resp];
    }

    /**
     * Query Search Analytics.
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param string[] $dimensions e.g. ['query'], ['page'], ['query','page']
     * @param array $dimensionFilterGroups GSC filter groups
     * @param int $rowLimit max 25000 (we default 1000-5000)
     * @return array|null rows or null on auth failure
     */
    public static function searchAnalytics(string $startDate, string $endDate, array $dimensions = [], array $dimensionFilterGroups = [], int $rowLimit = 1000, string $aggregationType = 'auto', ?string $siteUrlOverride = null): ?array {
        $siteUrl = GscTokenStore::getSiteUrlFor($siteUrlOverride);
        $payload = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => $dimensions,
            'dimensionFilterGroups' => $dimensionFilterGroups,
            'aggregationType' => $aggregationType,
            'rowLimit' => min(25000, max(1, $rowLimit)),
        ];
        // Remove empty filters for cleaner cache key.
        if (empty($dimensionFilterGroups)) unset($payload['dimensionFilterGroups']);

        $token = GscTokenStore::getAccessToken();
        if ($token === null) return null; // auth_required

        $cacheKey = GscCache::key($siteUrl, $payload);
        $cached = GscCache::get($cacheKey);
        if ($cached !== null) return $cached;

        $url = self::API_BASE . '/sites/' . rawurlencode($siteUrl) . '/searchAnalytics/query';
        $r = self::request('POST', $url, $token, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 30);

        if ($r['code'] === 401 || $r['code'] === 403) {
            error_log("GscClient searchAnalytics auth error HTTP {$r['code']}: " . mb_substr($r['resp'], 0, 800));
            // Invalidate cache on auth failure to avoid serving stale data (H10).
            GscCache::clearCache();
            // Also clear stored access_token so next call triggers refresh.
            try { Database::getInstance()->query("UPDATE gsc_tokens SET access_token = NULL, expires_at = NULL WHERE id = 1"); } catch (Throwable $e) {}
            return null; // treat as auth_required; token may be revoked
        }
        if ($r['err'] !== '' || $r['code'] < 200 || $r['code'] >= 300) {
            $msg = $r['err'] !== '' ? $r['err'] : mb_substr($r['resp'], 0, 800);
            throw new RuntimeException("GSC API error HTTP {$r['code']}: $msg");
        }
        $data = json_decode($r['resp'], true);
        if (!is_array($data)) throw new RuntimeException("GSC API bad JSON");
        $rows = $data['rows'] ?? [];
        if (!is_array($rows)) $rows = [];

        GscCache::set($cacheKey, $rows);
        return $rows;
    }

    public static function listSites(): ?array {
        $token = GscTokenStore::getAccessToken();
        if ($token === null) return null;
        $r = self::request('GET', self::API_BASE . '/sites', $token, null, 15);
        if ($r['code'] < 200 || $r['code'] >= 300) return null;
        $data = json_decode($r['resp'], true);
        return $data['siteEntry'] ?? [];
    }

    public static function slugFromPage(string $pageUrl): string {
        $page = trim($pageUrl);
        if (preg_match('#https?://[^/]+/(.*)#i', $page, $m)) $page = '/' . $m[1];
        $page = trim($page, "/ \t");
        $page = preg_replace('#^(ru|uz)/#i', '', $page);
        if ($page === '' || $page === '/') return 'main';
        if (str_contains($page, '/')) {
            $parts = explode('/', $page);
            $page = end($parts);
        }
        $slug = mb_strtolower($page, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9_\-]/', '-', $slug);
        $slug = trim((string)$slug, '-');
        return $slug === '' ? 'main' : $slug;
    }
}
