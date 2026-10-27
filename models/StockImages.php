<?php
// path: ./models/StockImages.php
// Pexels stock-photo search + import for the admin media picker (key: PEXELS_API_KEY in .env, free at pexels.com/api).
// Pexels licence: free to use, attribution not required; API terms: show a link to Pexels next to results (the picker does).
// Safety: the client never supplies a download URL — import() asks the Pexels API for the photo and only downloads
// from the Pexels CDN host over https, with a size cap. Search results are file-cached for 30 min to save quota
// (200 requests/hour, 20,000/month by default).
// Errors: RuntimeException, code = HTTP status (401 bad key, 404 no such photo, 429 rate limit, 502 other).

class StockImages {
    private const API = 'https://api.pexels.com/v1';
    private const CDN_HOST = 'images.pexels.com';
    private const CACHE_TTL = 1800;

    /** [regex over slug + Russian title, English search query]. First match wins; Pexels works best in English. */
    private const QUERY_MAP = [
        ['/холодил|морозил|xolodil|morozil|fridge|refriger/iu', 'refrigerator kitchen'],
        ['/стирал|stiral|washing|washer/iu', 'washing machine laundry'],
        ['/телевиз|televiz|\btv\b/iu', 'television living room'],
        ['/кондиц|konditsion|air-?cond|split/iu', 'air conditioner'],
        ['/газов|плит|plita|stove|cooker/iu', 'gas stove kitchen'],
        ['/ковер|ковр|kovr|kovri|carpet|rug/iu', 'carpet rug living room'],
        ['/диван|divan|sofa/iu', 'sofa living room'],
        ['/кроват|матрас|kravat|bed\b/iu', 'bed bedroom'],
        ['/шкаф|shkaf|wardrobe/iu', 'wardrobe bedroom'],
        ['/кухн|kuxn|kitchen/iu', 'kitchen furniture'],
        ['/стол|стул|stol|stul/iu', 'dining table chairs'],
        ['/мебел|mebel|furniture/iu', 'furniture home interior'],
    ];

    public static function apiKey(): string {
        return trim((string)(getenv('PEXELS_API_KEY') ?: ''), " \t\n\r\0\x0B\"'");
    }

    public static function isConfigured(): bool {
        return self::apiKey() !== '';
    }

    /** English query from the page's slug/title; falls back to its parent page (model pages sit under a category). */
    public static function suggestQuery(array $page, ?array $parent = null): string {
        foreach ([$page, $parent ?? []] as $p) {
            $hay = mb_strtolower(($p['slug'] ?? '') . ' ' . ($p['title_ru'] ?? ''));
            if (trim($hay) === '') continue;
            foreach (self::QUERY_MAP as [$re, $query]) {
                if (preg_match($re, $hay)) return $query;
            }
        }
        return 'home appliances';
    }

    /**
     * @return array{results:array<int,array>,page:int,has_more:bool,total:int}
     * @throws RuntimeException
     */
    public static function search(string $query, int $page = 1, int $perPage = 24): array {
        $query = mb_substr(trim($query), 0, 100);
        $page = max(1, min(50, $page));
        $perPage = max(1, min(40, $perPage));
        $url = self::API . '/search?' . http_build_query(['query' => $query, 'page' => $page, 'per_page' => $perPage]);

        $data = self::cached($url);
        if ($data === null) {
            [$code, $body] = self::get($url);
            if ($code < 200 || $code >= 300) self::fail($code);
            $data = json_decode($body, true);
            if (!is_array($data)) throw new RuntimeException('Bad response from Pexels', 502);
            self::cacheSet($url, $data);
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
        [$code, $body] = self::get(self::API . '/photos/' . $photoId);
        if ($code < 200 || $code >= 300) self::fail($code);
        $meta = json_decode($body, true);
        if (!is_array($meta) || empty($meta['src'])) throw new RuntimeException('Bad response from Pexels', 502);

        foreach (['large2x', 'large'] as $size) {
            $src = (string)($meta['src'][$size] ?? '');
            $parts = parse_url($src);
            if (!$parts || ($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== self::CDN_HOST) continue;

            $tmp = tempnam(sys_get_temp_dir(), 'stk');
            $fh = fopen($tmp, 'wb');
            $written = 0;
            $ch = curl_init($src);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 40,
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
                return [
                    'path' => $tmp,
                    'photographer' => (string)($meta['photographer'] ?? ''),
                    'photographer_url' => (string)($meta['photographer_url'] ?? ''),
                    'url' => (string)($meta['url'] ?? ''),
                ];
            }
            @unlink($tmp);
        }
        throw new RuntimeException('Не удалось скачать фото (или оно больше лимита загрузки)', 502);
    }

    // ── internals ────────────────────────────────────────────

    /** @return array{0:int,1:string} [http code, body] */
    private static function get(string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . self::apiKey()],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) throw new RuntimeException('Нет ответа от Pexels: ' . $err, 502);
        return [$code, (string)$body];
    }

    private static function fail(int $code): void {
        if ($code === 401 || $code === 403) throw new RuntimeException('Неверный PEXELS_API_KEY', 401);
        if ($code === 404) throw new RuntimeException('Фото не найдено', 404);
        if ($code === 429) throw new RuntimeException('Лимит запросов Pexels (200 в час) — подождите', 429);
        throw new RuntimeException('Ошибка Pexels (HTTP ' . $code . ')', 502);
    }

    private static function cacheFile(string $url): string {
        return BASE_PATH . '/storage/stock_cache/' . sha1($url) . '.json';
    }

    private static function cached(string $url): ?array {
        $f = self::cacheFile($url);
        if (!is_file($f) || (time() - (int)@filemtime($f)) > self::CACHE_TTL) return null;
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
