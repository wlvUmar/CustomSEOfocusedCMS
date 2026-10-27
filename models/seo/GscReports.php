<?php
// path: ./models/seo/GscReports.php
// Read-only Search Console reports for the SEO API: generic query, period compare, opportunities,
// URL inspection, sitemaps. No HTTP/auth here — SeoApiController owns that and maps the exceptions:
//   InvalidArgumentException(<param name>)  -> 400 invalid_param
//   GscAuthRequired                         -> 503 gsc_auth_required
//   RuntimeException (code = HTTP status)   -> 429 / 403 / 502 depending on Google's answer
// Numbers come straight from the Search Console API (cached 1h by GscCache). Nothing is estimated except the
// clearly labelled, site-relative heuristics in opportunities().

require_once BASE_PATH . '/models/GscClient.php';

class GscAuthRequired extends RuntimeException {}

class GscReports {
    public const LAG_DAYS = 3;            // GSC data is final ~3 days late
    public const MAX_RANGE_DAYS = 480;    // ~16 months, Search Console retention
    public const DIMENSIONS = ['query', 'page', 'country', 'device', 'date', 'searchAppearance'];
    public const FILTER_DIMENSIONS = ['query', 'page', 'country', 'device', 'searchAppearance'];
    public const OPERATORS = ['equals', 'contains', 'notEquals', 'notContains', 'includingRegex', 'excludingRegex'];
    public const SORTS = ['clicks', 'impressions', 'ctr', 'position'];
    public const SEARCH_TYPES = ['web', 'image', 'video', 'news', 'discover', 'googleNews'];
    private const OWN_HOST = 'kuplyu-tashkent.uz';

    // ── parameter parsing ────────────────────────────────────

    public static function intParam(array $q, string $name, int $default, int $min, int $max): int {
        if (!isset($q[$name]) || $q[$name] === '') return $default;
        if (!preg_match('/^-?\d+$/', (string)$q[$name])) throw new InvalidArgumentException($name);
        return max($min, min($max, (int)$q[$name]));
    }

    private static function dateParam(string $value, string $param): string {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            throw new InvalidArgumentException($param);
        }
        return $value;
    }

    /** @return array{0:string,1:string} [start, end] — explicit start/end, or `days` ending LAG_DAYS ago. */
    public static function period(array $q, int $defaultDays = 28, int $maxDays = self::MAX_RANGE_DAYS): array {
        $defaultEnd = date('Y-m-d', strtotime('-' . self::LAG_DAYS . ' days'));
        if (!empty($q['start']) || !empty($q['end'])) {
            $start = self::dateParam((string)($q['start'] ?? ''), 'start');
            $end = self::dateParam((string)($q['end'] ?? $defaultEnd), 'end');
        } else {
            $days = self::intParam($q, 'days', $defaultDays, 1, $maxDays);
            $end = $defaultEnd;
            $start = date('Y-m-d', strtotime($end . ' -' . ($days - 1) . ' days'));
        }
        if ($start > $end) throw new InvalidArgumentException('start');
        if ($end > date('Y-m-d')) throw new InvalidArgumentException('end');
        if ((strtotime($end) - strtotime($start)) / 86400 + 1 > $maxDays) throw new InvalidArgumentException('start');
        return [$start, $end];
    }

    public static function dimensions(array $q, array $default = ['query']): array {
        if (!isset($q['dimensions']) || $q['dimensions'] === '') return $default;
        $dims = array_values(array_unique(array_map('trim', explode(',', (string)$q['dimensions']))));
        if (count($dims) > 4) throw new InvalidArgumentException('dimensions');
        foreach ($dims as $d) {
            if (!in_array($d, self::DIMENSIONS, true)) throw new InvalidArgumentException('dimensions');
        }
        return $dims;
    }

    private static function filter($dimension, $operator, $expression, string $param): array {
        if (!in_array($dimension, self::FILTER_DIMENSIONS, true)) throw new InvalidArgumentException($param);
        if (!in_array($operator, self::OPERATORS, true)) throw new InvalidArgumentException($param);
        $expression = (string)$expression;
        if ($expression === '' || mb_strlen($expression) > 300) throw new InvalidArgumentException($param);
        return ['dimension' => $dimension, 'operator' => $operator, 'expression' => $expression];
    }

    /**
     * Filters from shortcut params (query_contains, page_equals, country, device …) and/or a JSON `filters`
     * array [{"dimension","operator","expression"}]. All are AND-ed.
     */
    public static function filters(array $q): array {
        $sugar = [
            'query_contains' => ['query', 'contains'], 'query_equals' => ['query', 'equals'],
            'query_not_contains' => ['query', 'notContains'], 'query_regex' => ['query', 'includingRegex'],
            'page_contains' => ['page', 'contains'], 'page_equals' => ['page', 'equals'],
            'page_not_contains' => ['page', 'notContains'],
            'country' => ['country', 'equals'], 'device' => ['device', 'equals'],
        ];
        $filters = [];
        foreach ($sugar as $param => [$dim, $op]) {
            if (!isset($q[$param]) || $q[$param] === '') continue;
            $val = (string)$q[$param];
            if ($param === 'country') $val = strtoupper($val);
            if ($param === 'device') {
                $val = strtoupper($val);
                if (!in_array($val, ['DESKTOP', 'MOBILE', 'TABLET'], true)) throw new InvalidArgumentException('device');
            }
            $filters[] = self::filter($dim, $op, $val, $param);
        }
        if (isset($q['filters']) && $q['filters'] !== '') {
            $arr = json_decode((string)$q['filters'], true);
            if (!is_array($arr) || count($arr) > 10) throw new InvalidArgumentException('filters');
            foreach ($arr as $f) {
                if (!is_array($f)) throw new InvalidArgumentException('filters');
                $filters[] = self::filter($f['dimension'] ?? '', $f['operator'] ?? 'equals', $f['expression'] ?? '', 'filters');
            }
        }
        return $filters ? [['groupType' => 'and', 'filters' => $filters]] : [];
    }

    private static function metrics(array $r): array {
        return [
            'clicks' => (int)($r['clicks'] ?? 0),
            'impressions' => (int)($r['impressions'] ?? 0),
            'ctr' => round((float)($r['ctr'] ?? 0), 4),
            'position' => round((float)($r['position'] ?? 0), 2),
        ];
    }

    private static function fetch(string $start, string $end, array $dims, array $filters, int $limit, array $options = []): array {
        $rows = GscClient::searchAnalytics($start, $end, $dims, $filters, $limit, 'auto', null, $options);
        if ($rows === null) throw new GscAuthRequired('gsc_auth_required');
        return $rows;
    }

    // ── 1. generic query ─────────────────────────────────────

    public static function query(array $q): array {
        [$start, $end] = self::period($q);
        $dims = self::dimensions($q);
        $limit = self::intParam($q, 'limit', 100, 1, 5000);
        $startRow = self::intParam($q, 'start_row', 0, 0, 50000);
        $type = (string)($q['search_type'] ?? 'web');
        if (!in_array($type, self::SEARCH_TYPES, true)) throw new InvalidArgumentException('search_type');
        $sort = (string)($q['sort'] ?? 'clicks');
        if (!in_array($sort, self::SORTS, true)) throw new InvalidArgumentException('sort');
        $order = strtolower((string)($q['order'] ?? ($sort === 'position' ? 'asc' : 'desc')));
        if (!in_array($order, ['asc', 'desc'], true)) throw new InvalidArgumentException('order');
        $minImpr = self::intParam($q, 'min_impressions', 0, 0, 10000000);
        $minClicks = self::intParam($q, 'min_clicks', 0, 0, 10000000);
        $filters = self::filters($q);

        $raw = self::fetch($start, $end, $dims, $filters, $limit, ['startRow' => $startRow, 'type' => $type]);

        $rows = [];
        foreach ($raw as $r) {
            $m = self::metrics($r);
            if ($m['impressions'] < $minImpr || $m['clicks'] < $minClicks) continue;
            $row = [];
            foreach ($dims as $i => $d) {
                $row[$d] = $r['keys'][$i] ?? null;
                if ($d === 'page' && is_string($row[$d])) $row['slug'] = GscClient::slugFromPage($row[$d]);
            }
            $rows[] = $row + $m;
        }
        usort($rows, static function ($a, $b) use ($sort, $order) {
            return $order === 'asc' ? $a[$sort] <=> $b[$sort] : $b[$sort] <=> $a[$sort];
        });

        return [
            'site' => GscClient::getSiteUrl(),
            'period' => ['start' => $start, 'end' => $end],
            'dimensions' => $dims,
            'search_type' => $type,
            'filters' => $filters ? $filters[0]['filters'] : [],
            'start_row' => $startRow,
            'count' => count($rows),
            'has_more' => count($raw) >= $limit,
            'rows' => $rows,
            'note' => 'Google returns rows ordered by clicks within the requested limit; sort/min_* only reorder or trim that page. '
                . 'Page with start_row for more. Rare queries are anonymised by Google, so row sums are lower than site totals.',
        ];
    }

    // ── 2. period over period ────────────────────────────────

    public static function compare(array $q): array {
        $dim = (string)($q['dimension'] ?? 'page');
        if (!in_array($dim, ['page', 'query'], true)) throw new InvalidArgumentException('dimension');
        $days = self::intParam($q, 'days', 28, 1, 240);
        $limit = self::intParam($q, 'limit', 50, 1, 500);
        $minImpr = self::intParam($q, 'min_impressions', 20, 0, 10000000);
        $direction = (string)($q['direction'] ?? 'any');
        if (!in_array($direction, ['any', 'up', 'down'], true)) throw new InvalidArgumentException('direction');

        $curEnd = date('Y-m-d', strtotime('-' . self::LAG_DAYS . ' days'));
        $curStart = date('Y-m-d', strtotime($curEnd . ' -' . ($days - 1) . ' days'));
        $prevEnd = date('Y-m-d', strtotime($curStart . ' -1 day'));
        $prevStart = date('Y-m-d', strtotime($prevEnd . ' -' . ($days - 1) . ' days'));

        $filters = self::filters($q);
        $cur = self::keyed(self::fetch($curStart, $curEnd, [$dim], $filters, 5000));
        $prev = self::keyed(self::fetch($prevStart, $prevEnd, [$dim], $filters, 5000));

        $zero = ['clicks' => 0, 'impressions' => 0, 'ctr' => 0.0, 'position' => 0.0];
        $out = [];
        foreach (array_unique(array_merge(array_keys($cur), array_keys($prev))) as $key) {
            $c = $cur[$key] ?? $zero;
            $p = $prev[$key] ?? $zero;
            if (max($c['impressions'], $p['impressions']) < $minImpr) continue;
            $clicksDelta = $c['clicks'] - $p['clicks'];
            if (($direction === 'up' && $clicksDelta <= 0) || ($direction === 'down' && $clicksDelta >= 0)) continue;
            $status = $p['impressions'] === 0 ? 'new' : ($c['impressions'] === 0 ? 'lost' : 'changed');
            $row = [$dim => $key];
            if ($dim === 'page') $row['slug'] = GscClient::slugFromPage($key);
            $out[] = $row + [
                'status' => $status,
                'current' => $c,
                'previous' => $p,
                'clicks_delta' => $clicksDelta,
                'impressions_delta' => $c['impressions'] - $p['impressions'],
                // position: a NEGATIVE delta means the average position improved (moved up)
                'position_delta' => ($c['impressions'] > 0 && $p['impressions'] > 0) ? round($c['position'] - $p['position'], 2) : null,
                'ctr_delta' => round($c['ctr'] - $p['ctr'], 4),
            ];
        }
        usort($out, static fn($a, $b) => abs($b['clicks_delta']) <=> abs($a['clicks_delta']) ?: abs($b['impressions_delta']) <=> abs($a['impressions_delta']));

        return [
            'site' => GscClient::getSiteUrl(),
            'dimension' => $dim,
            'current_period' => ['start' => $curStart, 'end' => $curEnd],
            'previous_period' => ['start' => $prevStart, 'end' => $prevEnd],
            'totals' => [
                'current' => self::totals($curStart, $curEnd, $filters),
                'previous' => self::totals($prevStart, $prevEnd, $filters),
            ],
            'count' => min($limit, count($out)),
            'rows' => array_slice($out, 0, $limit),
            'note' => 'Compares two equal-length consecutive periods. position_delta < 0 = improved. Rows are limited to the top 5000 per period by clicks.',
        ];
    }

    private static function keyed(array $rows): array {
        $map = [];
        foreach ($rows as $r) {
            $key = $r['keys'][0] ?? null;
            if ($key !== null) $map[$key] = self::metrics($r);
        }
        return $map;
    }

    private static function totals(string $start, string $end, array $filters): ?array {
        try {
            $rows = self::fetch($start, $end, [], $filters, 1);
        } catch (GscAuthRequired $e) {
            throw $e;
        } catch (Throwable $e) {
            return null;
        }
        return isset($rows[0]) ? self::metrics($rows[0]) : null;
    }

    // ── 3. opportunities ─────────────────────────────────────

    public static function opportunities(array $q): array {
        [$start, $end] = self::period($q, 28, 120);
        $minImpr = self::intParam($q, 'min_impressions', 50, 1, 10000000);
        $limit = self::intParam($q, 'limit', 25, 1, 100);
        $filters = self::filters($q);

        $items = [];
        foreach (self::fetch($start, $end, ['query', 'page'], $filters, 5000) as $r) {
            $page = (string)($r['keys'][1] ?? '');
            $items[] = ['query' => $r['keys'][0] ?? null, 'page' => $page, 'slug' => GscClient::slugFromPage($page)] + self::metrics($r);
        }

        // Site-relative CTR by position bucket (impression-weighted) — no external benchmark is assumed.
        $bucketOf = static fn(float $pos) => $pos <= 3 ? '1-3' : ($pos <= 6 ? '4-6' : ($pos <= 10 ? '7-10' : '11-20'));
        $sums = [];
        foreach ($items as $it) {
            if ($it['position'] > 20 || $it['impressions'] <= 0) continue;
            $b = $bucketOf($it['position']);
            $sums[$b]['clicks'] = ($sums[$b]['clicks'] ?? 0) + $it['clicks'];
            $sums[$b]['impressions'] = ($sums[$b]['impressions'] ?? 0) + $it['impressions'];
        }
        $bucketCtr = [];
        foreach ($sums as $b => $s) $bucketCtr[$b] = $s['impressions'] > 0 ? $s['clicks'] / $s['impressions'] : 0;

        $striking = $lowCtr = $zero = [];
        foreach ($items as $it) {
            if ($it['impressions'] < $minImpr || $it['position'] > 20) continue;
            if ($it['position'] > 5) {
                $striking[] = $it;
            }
            $b = $bucketOf($it['position']);
            $expected = $bucketCtr[$b] ?? 0;
            if ($it['position'] <= 10 && $expected > 0 && $it['ctr'] < 0.5 * $expected) {
                $lowCtr[] = $it + ['bucket' => $b, 'bucket_ctr' => round($expected, 4), 'clicks_gap' => (int)round($expected * $it['impressions'] - $it['clicks'])];
            }
            if ($it['clicks'] === 0 && $it['impressions'] >= $minImpr * 2) {
                $zero[] = $it;
            }
        }
        usort($striking, static fn($a, $b) => $b['impressions'] <=> $a['impressions']);
        usort($lowCtr, static fn($a, $b) => $b['clicks_gap'] <=> $a['clicks_gap']);
        usort($zero, static fn($a, $b) => $b['impressions'] <=> $a['impressions']);

        return [
            'site' => GscClient::getSiteUrl(),
            'period' => ['start' => $start, 'end' => $end],
            'params' => ['min_impressions' => $minImpr, 'limit' => $limit],
            'rows_analysed' => count($items),
            'site_ctr_by_position' => array_map(static fn($v) => round($v, 4), $bucketCtr),
            'striking_distance' => ['definition' => 'average position 5-20 with enough impressions; most upside from a content/title refresh', 'rows' => array_slice($striking, 0, $limit)],
            'low_ctr' => ['definition' => 'position <= 10 and CTR below half of this site\'s own CTR for that position bucket; clicks_gap = expected - actual clicks', 'rows' => array_slice($lowCtr, 0, $limit)],
            'zero_clicks' => ['definition' => 'position <= 20, impressions >= 2x min_impressions, no clicks', 'rows' => array_slice($zero, 0, $limit)],
            'note' => 'Heuristics on query+page rows (Google anonymises rare queries, so totals are higher). Thresholds are defaults — tune min_impressions.',
        ];
    }

    // ── 4. URL inspection ────────────────────────────────────

    public static function inspect(string $url): array {
        $url = trim($url);
        if ($url === '' || strlen($url) > 500 || !preg_match('#^https://' . preg_quote(self::OWN_HOST, '#') . '(/[^\s]*)?$#i', $url)) {
            throw new InvalidArgumentException('url');
        }
        $res = GscClient::inspectUrl($url);
        if ($res === null) throw new GscAuthRequired('gsc_auth_required');

        $idx = $res['indexStatusResult'] ?? [];
        $mobile = $res['mobileUsabilityResult'] ?? [];
        $rich = $res['richResultsResult'] ?? [];

        $richItems = [];
        foreach (($rich['detectedItems'] ?? []) as $d) {
            $issues = [];
            foreach (($d['items'] ?? []) as $item) {
                foreach (($item['issues'] ?? []) as $iss) {
                    $issues[] = ($iss['severity'] ?? '') . ': ' . ($iss['issueMessage'] ?? '');
                }
            }
            $richItems[] = ['type' => $d['richResultType'] ?? null, 'items' => count($d['items'] ?? []), 'issues' => array_slice(array_values(array_unique($issues)), 0, 10)];
        }

        return [
            'url' => $url,
            'index' => [
                'verdict' => $idx['verdict'] ?? null,                 // PASS = indexed
                'coverage_state' => $idx['coverageState'] ?? null,
                'indexing_state' => $idx['indexingState'] ?? null,
                'robots_txt_state' => $idx['robotsTxtState'] ?? null,
                'page_fetch_state' => $idx['pageFetchState'] ?? null,
                'last_crawl_time' => $idx['lastCrawlTime'] ?? null,
                'crawled_as' => $idx['crawledAs'] ?? null,
                'google_canonical' => $idx['googleCanonical'] ?? null,
                'user_canonical' => $idx['userCanonical'] ?? null,
                'canonical_matches' => isset($idx['googleCanonical'], $idx['userCanonical']) ? ($idx['googleCanonical'] === $idx['userCanonical']) : null,
                'sitemaps' => $idx['sitemap'] ?? [],
                'referring_urls' => array_slice($idx['referringUrls'] ?? [], 0, 5),
            ],
            'mobile_usability' => [
                'verdict' => $mobile['verdict'] ?? null,
                'issues' => array_map(static fn($i) => $i['issueType'] ?? null, $mobile['issues'] ?? []),
            ],
            'rich_results' => ['verdict' => $rich['verdict'] ?? null, 'detected' => $richItems],
            'inspection_link' => $res['inspectionResultLink'] ?? null,
            'note' => 'Live Search Console URL Inspection (quota ~2000/day, 600/min per property). Shows the indexed version, not a live test.',
        ];
    }

    // ── 5. sitemaps ──────────────────────────────────────────

    public static function sitemaps(): array {
        $list = GscClient::sitemaps();
        if ($list === null) throw new GscAuthRequired('gsc_auth_required');
        $rows = array_map(static function ($s) {
            return [
                'path' => $s['path'] ?? null,
                'type' => $s['type'] ?? null,
                'is_index' => $s['isSitemapsIndex'] ?? null,
                'is_pending' => $s['isPending'] ?? null,
                'last_submitted' => $s['lastSubmitted'] ?? null,
                'last_downloaded' => $s['lastDownloaded'] ?? null,
                'errors' => isset($s['errors']) ? (int)$s['errors'] : 0,
                'warnings' => isset($s['warnings']) ? (int)$s['warnings'] : 0,
                'contents' => array_map(static fn($c) => [
                    'type' => $c['type'] ?? null,
                    'submitted' => isset($c['submitted']) ? (int)$c['submitted'] : null,
                    'indexed' => isset($c['indexed']) ? (int)$c['indexed'] : null,
                ], $s['contents'] ?? []),
            ];
        }, $list);
        return ['site' => GscClient::getSiteUrl(), 'count' => count($rows), 'sitemaps' => $rows];
    }
}
