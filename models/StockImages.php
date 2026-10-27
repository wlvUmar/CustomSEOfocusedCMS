<?php
// path: ./models/StockImages.php
// Stock-photo search + import for the admin media picker / Quick add. Two interchangeable providers, both free:
//   Pexels   — PEXELS_API_KEY   (pexels.com/api; licence: free, no attribution; 200 req/h)
//   Pixabay  — PIXABAY_API_KEY  (pixabay.com/api/docs; Content License: free, no attribution; 100 req/min;
//              API rules: cache results 24 h, never hotlink — images are downloaded to our server on import;
//              largeImageURL is max 1280 px without special approval)
// Provider: STOCK_PROVIDER=pexels|pixabay in .env, else whichever key is present (Pexels first).
// Safety: the client never supplies a download URL — import() asks the provider API for the photo and only
// downloads from that provider's CDN host over https, with a size cap.
// Errors: RuntimeException, code = HTTP status (401 bad key, 404 no such photo, 429 rate limit, 502 other).

class StockImages {
    private const PEXELS_API = 'https://api.pexels.com/v1';
    private const PIXABAY_API = 'https://pixabay.com/api/';
    private const CACHE_TTL = ['pexels' => 1800, 'pixabay' => 86400]; // Pixabay's API terms require 24 h caching

    /** 'pexels' | 'pixabay' — STOCK_PROVIDER if set, else the first provider that has a key. */
    public static function provider(): string {
        $forced = strtolower(trim((string)(getenv('STOCK_PROVIDER') ?: '')));
        if (in_array($forced, ['pexels', 'pixabay'], true)) return $forced;
        if (self::keyFor('pexels') !== '') return 'pexels';
        if (self::keyFor('pixabay') !== '') return 'pixabay';
        return 'pexels';
    }

    private static function keyFor(string $provider): string {
        $name = $provider === 'pixabay' ? 'PIXABAY_API_KEY' : 'PEXELS_API_KEY';
        return trim((string)(getenv($name) ?: ''), " \t\n\r\0\x0B\"'");
    }

    public static function apiKey(): string {
        return self::keyFor(self::provider());
    }

    public static function isConfigured(): bool {
        return self::apiKey() !== '';
    }

    /** @return array{name:string,url:string} where to credit/link the results ("show users where images are from"). */
    public static function attribution(): array {
        return self::provider() === 'pixabay'
            ? ['name' => 'Pixabay', 'url' => 'https://pixabay.com']
            : ['name' => 'Pexels', 'url' => 'https://www.pexels.com'];
    }

    /** config/stock_queries.php (editable slug → search-words map); empty array if the file is missing. */
    private static function config(): array {
        static $cfg = null;
        if ($cfg === null) {
            $file = BASE_PATH . '/config/stock_queries.php';
            $cfg = is_file($file) ? (array)(include $file) : [];
        }
        return $cfg;
    }

    /** @return string[] one or more queries from a config value (string | list) */
    private static function asList($v): array {
        return array_values(array_filter(array_map('strval', (array)$v), static fn($s) => trim($s) !== ''));
    }

    /**
     * Search queries for a page, best first: exact slug → slug patterns → slug+title patterns, then the same for the
     * parent page (model pages sit under a category), then the default. First entry is the default search.
     * @return string[]
     */
    public static function suggestQueries(array $page, ?array $parent = null): array {
        $cfg = self::config();
        foreach ([$page, $parent ?? []] as $p) {
            $slug = (string)($p['slug'] ?? '');
            if ($slug === '') continue;
            if (isset($cfg['exact'][$slug])) {
                $list = self::asList($cfg['exact'][$slug]);
                if ($list) return $list;
            }
            foreach (($cfg['slug_patterns'] ?? []) as [$re, $v]) {
                if (@preg_match($re, $slug) === 1) return self::asList($v);
            }
            $hay = mb_strtolower($slug . ' ' . ($p['title_ru'] ?? ''));
            foreach (($cfg['text_patterns'] ?? []) as [$re, $v]) {
                if (@preg_match($re, $hay) === 1) return self::asList($v);
            }
        }
        return self::asList($cfg['default'] ?? ['home appliances']) ?: ['home appliances'];
    }

    public static function suggestQuery(array $page, ?array $parent = null): string {
        return self::suggestQueries($page, $parent)[0];
    }

    /**
     * @return array{results:array<int,array>,page:int,has_more:bool,total:int}
     * @throws RuntimeException
     */
    public static function search(string $query, int $page = 1, int $perPage = 24): array {
        $query = mb_substr(trim($query), 0, 100);
        $page = max(1, min(50, $page));
        $perPage = max(3, min(40, $perPage));
        $provider = self::provider();
        $url = $provider === 'pixabay'
            ? self::PIXABAY_API . '?' . http_build_query(['key' => self::apiKey(), 'q' => $query, 'image_type' => 'photo', 'safesearch' => 'true', 'page' => $page, 'per_page' => $perPage])
            : self::PEXELS_API . '/search?' . http_build_query(['query' => $query, 'page' => $page, 'per_page' => $perPage]);

        $data = self::cached($url, $provider);
        if ($data === null) {
            [$code, $body] = self::get($url, $provider);
            if ($code < 200 || $code >= 300) self::fail($code, $provider);
            $data = json_decode($body, true);
            if (!is_array($data)) throw new RuntimeException('Bad response from ' . self::attribution()['name'], 502);
            self::cacheSet($url, $data);
        }

        if ($provider === 'pixabay') {
            $results = [];
            foreach (($data['hits'] ?? []) as $p) {
                $results[] = [
                    'id' => (int)$p['id'],
                    'thumb' => (string)($p['webformatURL'] ?? $p['previewURL'] ?? ''),   // temporary display of results is allowed
                    'width' => (int)($p['webformatWidth'] ?? 0),
                    'height' => (int)($p['webformatHeight'] ?? 0),
                    'alt' => (string)($p['tags'] ?? ''),
                    'photographer' => (string)($p['user'] ?? ''),
                    'photographer_url' => !empty($p['user']) ? 'https://pixabay.com/users/' . rawurlencode((string)$p['user']) . '-' . (int)($p['user_id'] ?? 0) . '/' : '',
                    'url' => (string)($p['pageURL'] ?? ''),
                ];
            }
            return [
                'results' => $results,
                'page' => $page,
                'has_more' => $page * $perPage < (int)($data['totalHits'] ?? 0),
                'total' => (int)($data['totalHits'] ?? count($results)),
            ];
        }

        $results = [];
        foreach (($data['photos'] ?? []) as $p) {
            $results[] = [
                'id' => (int)$p['id'],
                'thumb' => (string)($p['src']['medium'] ?? $p['src']['small'] ?? ''),
                'width' => (int)($p['width'] ?? 0),
                'height' => (int)($p['height'] ?? 0),
                'alt' => (string)($p['alt'] ?? ''),
                'photographer' => (string)($p['photographer'] ?? ''),
                'photographer_url' => (string)($p['photographer_url'] ?? ''),
                'url' => (string)($p['url'] ?? ''),
            ];
        }
        return [
            'results' => $results,
            'page' => (int)($data['page'] ?? $page),
            'has_more' => !empty($data['next_page']),
            'total' => (int)($data['total_results'] ?? count($results)),
        ];
    }

    /**
     * Download one photo to a temp file (caller deletes it). Tries large2x then large to stay under $maxBytes.
     * @return array{path:string,photographer:string,photographer_url:string,url:string}
     * @throws RuntimeException
     */
    public static function download(int $photoId, int $maxBytes): array {
        if ($photoId <= 0) throw new RuntimeException('Invalid photo id', 400);
        $provider = self::provider();

        if ($provider === 'pixabay') {
            [$code, $body] = self::get(self::PIXABAY_API . '?' . http_build_query(['key' => self::apiKey(), 'id' => $photoId]), $provider);
            if ($code < 200 || $code >= 300) self::fail($code, $provider);
            $meta = json_decode($body, true);
            $hit = is_array($meta) ? ($meta['hits'][0] ?? null) : null;
            if (!is_array($hit)) throw new RuntimeException('Фото не найдено', 404);
            // largeImageURL (<= 1280 px) is the biggest size without special API approval; webformatURL (640 px) as a fallback
            $sources = [(string)($hit['largeImageURL'] ?? ''), (string)($hit['webformatURL'] ?? '')];
            $hostRe = '/^(cdn\.)?pixabay\.com$/';
            $info = [
                'photographer' => (string)($hit['user'] ?? ''),
                'photographer_url' => !empty($hit['user']) ? 'https://pixabay.com/users/' . rawurlencode((string)$hit['user']) . '-' . (int)($hit['user_id'] ?? 0) . '/' : '',
                'url' => (string)($hit['pageURL'] ?? ''),
            ];
        } else {
            [$code, $body] = self::get(self::PEXELS_API . '/photos/' . $photoId, $provider);
            if ($code < 200 || $code >= 300) self::fail($code, $provider);
            $meta = json_decode($body, true);
            if (!is_array($meta) || empty($meta['src'])) throw new RuntimeException('Bad response from Pexels', 502);
            $sources = [(string)($meta['src']['large2x'] ?? ''), (string)($meta['src']['large'] ?? '')];
            $hostRe = '/^images\.pexels\.com$/';
            $info = [
                'photographer' => (string)($meta['photographer'] ?? ''),
                'photographer_url' => (string)($meta['photographer_url'] ?? ''),
                'url' => (string)($meta['url'] ?? ''),
            ];
        }

        foreach ($sources as $src) {
            $parts = parse_url($src);
            if (!$parts || ($parts['scheme'] ?? '') !== 'https' || !preg_match($hostRe, strtolower($parts['host'] ?? ''))) continue;

            $tmp = tempnam(sys_get_temp_dir(), 'stk');
            $fh = fopen($tmp, 'wb');
            $written = 0;
            $ch = curl_init($src);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 40,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SiteAdminImageImport/1.0)', // some CDNs refuse requests without a UA
                CURLOPT_WRITEFUNCTION => static function ($c, $chunk) use ($fh, &$written, $maxBytes) {
                    $written += strlen($chunk);
                    if ($written > $maxBytes) return 0; // abort: too large
                    return fwrite($fh, $chunk);
                },
            ]);
            $ok = curl_exec($ch);
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fh);

            if ($ok && $http === 200 && $written > 0) {
                return ['path' => $tmp] + $info;
            }
            @unlink($tmp);
        }
        throw new RuntimeException('Не удалось скачать фото (или оно больше лимита загрузки)', 502);
    }

    // ── internals ────────────────────────────────────────────

    /** @return array{0:int,1:string} [http code, body] */
    private static function get(string $url, string $provider): array {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
        ];
        // Pexels takes the key in a header; Pixabay takes it as the "key" query parameter (already in $url)
        if ($provider === 'pexels') $opts[CURLOPT_HTTPHEADER] = ['Authorization: ' . self::apiKey()];
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) throw new RuntimeException('Нет ответа от ' . self::attribution()['name'] . ': ' . $err, 502);
        return [$code, (string)$body];
    }

    private static function fail(int $code, string $provider): void {
        $name = $provider === 'pixabay' ? 'Pixabay' : 'Pexels';
        $env = $provider === 'pixabay' ? 'PIXABAY_API_KEY' : 'PEXELS_API_KEY';
        if ($code === 400 && $provider === 'pixabay') throw new RuntimeException('Pixabay отклонил запрос (проверьте ' . $env . ')', 400);
        if ($code === 401 || $code === 403) throw new RuntimeException('Неверный ' . $env, 401);
        if ($code === 404) throw new RuntimeException('Фото не найдено', 404);
        if ($code === 429) throw new RuntimeException('Лимит запросов ' . $name . ' — подождите минуту', 429);
        throw new RuntimeException('Ошибка ' . $name . ' (HTTP ' . $code . ')', 502);
    }

    private static function cacheFile(string $url): string {
        return BASE_PATH . '/storage/stock_cache/' . sha1($url) . '.json';
    }

    private static function cached(string $url, string $provider): ?array {
        $f = self::cacheFile($url);
        if (!is_file($f) || (time() - (int)@filemtime($f)) > (self::CACHE_TTL[$provider] ?? 1800)) return null;
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? $d : null;
    }

    private static function cacheSet(string $url, array $data): void {
        $dir = dirname(self::cacheFile($url));
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        // bounded: drop the oldest files past 300
        $files = glob($dir . '/*.json') ?: [];
        if (count($files) > 300) {
            usort($files, static fn($a, $b) => filemtime($a) <=> filemtime($b));
            foreach (array_slice($files, 0, count($files) - 250) as $old) @unlink($old);
        }
        @file_put_contents(self::cacheFile($url), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}
