<?php
// path: ./models/ai/tools/GscTools.php
// READ-ONLY GSC tools — live Google Search Console Search Analytics API via GscClient.
// When GSC is not connected the tools return empty with a connect hint (no CSV fallback UI).

require_once BASE_PATH . '/core/Database.php';
require_once BASE_PATH . '/models/GscClient.php';

class GscTools {

    public static function definitions(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_gsc_overview',
                    'description' => 'Site-wide GSC summary: total impressions, clicks, average CTR and position plus number of distinct queries/pages with data. Use to check if GSC data is present and how the site performs in Search. Live via Search Console API (requires GSC connection in AI Studio).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'days' => ['type' => 'integer', 'description' => 'Lookback window in days (default 28, max 90).'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_page_gsc',
                    'description' => 'GSC data for one page: totals (impressions, clicks, CTR, avg position) plus top queries sorted by impressions or clicks. Call this before auditing a page — it tells you which keywords the page actually ranks for. Live API when connected.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'slug' => ['type' => 'string', 'description' => 'Page slug (e.g. "services" or "noutbuki").'],
                            'days' => ['type' => 'integer', 'description' => 'Lookback window in days (default 28).'],
                            'limit' => ['type' => 'integer', 'description' => 'Max queries to return (default 20).'],
                            'order_by' => ['type' => 'string', 'enum' => ['impressions', 'clicks', 'position'], 'description' => 'Sort field (default impressions).'],
                        ],
                        'required' => ['slug'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_gsc_queries',
                    'description' => 'Top search queries across the whole site. Find which keywords drive the most impressions/clicks, or which have high impressions but low CTR (opportunity).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'days' => ['type' => 'integer', 'description' => 'Lookback window in days (default 28).'],
                            'limit' => ['type' => 'integer', 'description' => 'Max rows (default 20).'],
                            'order_by' => ['type' => 'string', 'enum' => ['impressions', 'clicks', 'ctr', 'position'], 'description' => 'Sort field (default impressions).'],
                            'min_impressions' => ['type' => 'integer', 'description' => 'Only queries with at least this many impressions (default 0).'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_gsc_pages',
                    'description' => 'Pages ranked by GSC totals — which pages get the most impressions/clicks/CTR in Search. Use to find weakest pages by search visibility (complement to get_underperforming_pages which uses internal analytics).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'days' => ['type' => 'integer', 'description' => 'Lookback window in days (default 28).'],
                            'limit' => ['type' => 'integer', 'description' => 'Max rows (default 15).'],
                            'order_by' => ['type' => 'string', 'enum' => ['impressions', 'clicks', 'ctr'], 'description' => 'Sort field (default impressions).'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_gsc_queries',
                    'description' => 'Search GSC queries by keyword substring. Find all queries containing a term (e.g. "noutbuk", "tashkent") and see which pages they drive traffic to.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'term' => ['type' => 'string', 'description' => 'Keyword substring to search for.'],
                            'days' => ['type' => 'integer', 'description' => 'Lookback window in days (default 28).'],
                            'limit' => ['type' => 'integer', 'description' => 'Max rows (default 20).'],
                        ],
                        'required' => ['term'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'query_gsc',
                    'description' => 'Flexible, MCP-like GSC Search Analytics query. Use when sugar tools are too limited. Build any combination of dimensions (query, page, country, device, searchAppearance, date), filters, and ordering. This is the freedom tool for custom breakdowns (e.g. device/country splits, searchAppearance, date trends, regex page filters, period comparison via explicit startDate/endDate). Returns raw API rows mapped to {keys, impressions, clicks, ctr_percent, position}.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'days' => ['type' => 'integer', 'description' => 'Lookback window in days (default 28). Ignored if startDate/endDate provided.'],
                            'startDate' => ['type' => 'string', 'description' => 'Explicit start date Y-m-d (overrides days).'],
                            'endDate' => ['type' => 'string', 'description' => 'Explicit end date Y-m-d (overrides days).'],
                            'dimensions' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['query', 'page', 'country', 'device', 'searchAppearance', 'date']], 'description' => 'Dimensions to group by. Empty = site totals. Example ["query","device"] for per-query device split.'],
                            'filters' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['dimension' => ['type' => 'string', 'enum' => ['query', 'page', 'country', 'device', 'searchAppearance']], 'operator' => ['type' => 'string', 'enum' => ['equals', 'contains', 'notContains', 'includingRegex', 'excludingRegex']], 'expression' => ['type' => 'string', 'description' => 'Value or regex. For page, full URL or slug fragment; for regex use RE2 syntax.']], 'required' => ['dimension', 'operator', 'expression']], 'description' => 'AND-combined filters. Example [{"dimension":"device","operator":"equals","expression":"MOBILE"}].'],
                            'orderBy' => ['type' => 'string', 'enum' => ['impressions', 'clicks', 'ctr', 'position'], 'description' => 'Client-side sort (default impressions).'],
                            'limit' => ['type' => 'integer', 'description' => 'Max rows 1-100 (default 25, max 100).'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        switch ($name) {
            case 'get_gsc_overview': return self::overview($args);
            case 'get_page_gsc': return self::pageGsc($args);
            case 'get_gsc_queries': return self::gscQueries($args);
            case 'get_gsc_pages': return self::gscPages($args);
            case 'search_gsc_queries': return self::searchQueries($args);
            case 'query_gsc': return self::queryGsc($args);
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    // ---- helpers -------------------------------------------------------

    private static function dateRange(int $days): array {
        // GSC data lags 2-3 days; endDate = yesterday, startDate = days ago.
        $end = date('Y-m-d', strtotime('-2 days'));
        $start = date('Y-m-d', strtotime("-{$days} days"));
        // Clamp start not after end.
        if (strtotime($start) > strtotime($end)) $start = $end;
        return [$start, $end];
    }

    private static function shouldUseApi(): bool {
        return GscClient::isConfigured() && GscClient::isConnected();
    }

    private static function ensureTable(): void {
        $db = Database::getInstance();
        $exists = $db->fetchOne("SHOW TABLES LIKE 'gsc_data'");
        if (!$exists) {
            $db->query("CREATE TABLE IF NOT EXISTS `gsc_data` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `page_slug` varchar(255) NOT NULL,
              `query` varchar(500) NOT NULL,
              `clicks` int(11) UNSIGNED NOT NULL DEFAULT 0,
              `impressions` int(11) UNSIGNED NOT NULL DEFAULT 0,
              `ctr` decimal(5,2) NOT NULL DEFAULT 0.00,
              `position` decimal(10,2) NOT NULL DEFAULT 0.00,
              `date` date DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_page_query_date` (`page_slug`,`query`(191),`date`),
              KEY `idx_page_slug` (`page_slug`),
              KEY `idx_query` (`query`(191)),
              KEY `idx_date` (`date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }

    // ---- overview ------------------------------------------------------

    private static function overview(array $args): array {
        $days = isset($args['days']) ? max(1, min(365, (int)$args['days'])) : 28;
        // Try live API first.
        if (self::shouldUseApi()) {
            try {
                [$start, $end] = self::dateRange(min($days, 90));
                $rows = GscClient::searchAnalytics($start, $end, [], [], 1);
                if ($rows === null) {
                    // auth_required — fall through to DB
                } elseif (empty($rows)) {
                    return ['days' => $days, 'source' => 'api', 'impressions' => 0, 'clicks' => 0, 'ctr_percent' => 0, 'avg_position' => 0, 'distinct_queries' => 0, 'distinct_pages' => 0, 'rows' => 0, 'start_date' => $start, 'end_date' => $end, 'note' => 'No GSC rows for this date range (site may be unverified or date range too recent).'];
                } else {
                    // Aggregated call with no dimensions returns single row with totals? Actually if dimensions empty, API returns one row with no keys and totals.
                    // Some accounts return empty when no dimensions; fallback to querying with dimensions and summing.
                    // If we got a row with keys empty, treat as totals.
                    $r = $rows[0] ?? null;
                    if ($r && empty($r['keys'])) {
                        return [
                            'days' => $days, 'source' => 'api', 'start_date' => $start, 'end_date' => $end,
                            'impressions' => (int)($r['impressions'] ?? 0),
                            'clicks' => (int)($r['clicks'] ?? 0),
                            'ctr_percent' => round((float)($r['ctr'] ?? 0) * 100, 2),
                            'avg_position' => round((float)($r['position'] ?? 0), 2),
                            'distinct_queries' => null, 'distinct_pages' => null, 'rows' => 1,
                        ];
                    }
                    // If API returned no aggregated row, sum via a query grouped by page (weighted avg position by impressions).
                    $sumRows = GscClient::searchAnalytics($start, $end, ['page'], [], 5000);
                    if ($sumRows !== null) {
                        $imp = 0; $clk = 0; $posWeighted = 0; $cnt = 0;
                        foreach ($sumRows as $rr) { $imp += (int)($rr['impressions'] ?? 0); $clk += (int)($rr['clicks'] ?? 0); $posWeighted += (float)($rr['position'] ?? 0) * (int)($rr['impressions'] ?? 0); $cnt++; }
                        return [
                            'days' => $days, 'source' => 'api', 'start_date' => $start, 'end_date' => $end,
                            'impressions' => $imp, 'clicks' => $clk,
                            'ctr_percent' => $imp > 0 ? round(100.0 * $clk / $imp, 2) : 0,
                            'avg_position' => $imp > 0 ? round($posWeighted / $imp, 2) : 0,
                            'distinct_queries' => null, 'distinct_pages' => $cnt, 'rows' => $cnt,
                        ];
                    }
                }
            } catch (Throwable $e) {
                error_log("GscTools overview API fallback: " . $e->getMessage());
            }
        }
        // Fallback to local DB.
        self::ensureTable();
        $db = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT COALESCE(SUM(impressions),0) AS impressions,
                    COALESCE(SUM(clicks),0) AS clicks,
                    ROUND(CASE WHEN SUM(impressions)=0 THEN 0 ELSE 100.0*SUM(clicks)/SUM(impressions) END,2) AS ctr,
                    ROUND(SUM(position*impressions)/NULLIF(SUM(impressions),0),2) AS avg_position,
                    COUNT(DISTINCT query) AS distinct_queries,
                    COUNT(DISTINCT page_slug) AS distinct_pages,
                    COUNT(*) AS row_count,
                    MIN(date) AS earliest,
                    MAX(date) AS latest
             FROM gsc_data WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY) OR date IS NULL"
        );
        if (!$row) {
            return ['days' => $days, 'source' => 'db', 'impressions' => 0, 'clicks' => 0, 'ctr_percent' => 0, 'avg_position' => 0, 'distinct_queries' => 0, 'distinct_pages' => 0, 'rows' => 0, 'note' => 'No GSC data — connect Search Console in AI Studio.'];
        }
        return [
            'days' => $days, 'source' => self::shouldUseApi() ? 'db-fallback' : 'db',
            'impressions' => (int)($row['impressions'] ?? 0),
            'clicks' => (int)($row['clicks'] ?? 0),
            'ctr_percent' => (float)($row['ctr'] ?? 0),
            'avg_position' => (float)($row['avg_position'] ?? 0),
            'distinct_queries' => (int)($row['distinct_queries'] ?? 0),
            'distinct_pages' => (int)($row['distinct_pages'] ?? 0),
            'rows' => (int)($row['row_count'] ?? 0),
            'earliest' => $row['earliest'] ?? null,
            'latest' => $row['latest'] ?? null,
            'note' => ((int)($row['row_count'] ?? 0) === 0) ? 'No GSC rows — connect Search Console in AI Studio.' : (self::shouldUseApi() ? null : 'Live GSC not connected — showing cached data. Connect via AI Studio → Connect GSC for live data.'),
        ];
    }

    // ---- pageGsc -------------------------------------------------------

    private static function pageGsc(array $args): array {
        $slug = trim((string)($args['slug'] ?? ''));
        if ($slug === '') throw new InvalidArgumentException('slug is required');
        $days = isset($args['days']) ? max(1, min(90, (int)$args['days'])) : 28;
        $limit = isset($args['limit']) ? max(1, min(50, (int)$args['limit'])) : 20;
        $orderBy = in_array($args['order_by'] ?? '', ['impressions', 'clicks', 'position'], true) ? $args['order_by'] : 'impressions';

        if (self::shouldUseApi()) {
            try {
                [$start, $end] = self::dateRange($days);
                $regex = '.*\/' . preg_quote($slug, '/') . '(?:\/|$|\?|#).*';
                $filter = [['dimension' => 'page', 'operator' => 'includingRegex', 'expression' => $regex]];
                $rows = GscClient::searchAnalytics($start, $end, ['query'], [['filters' => $filter]], 1000);
                if ($rows !== null) {
                    // rows are per-query for pages containing slug. Aggregate per query.
                    $map = [];
                    $totImp = 0; $totClk = 0; $posWeighted = 0;
                    foreach ($rows as $r) {
                        $q = (string)($r['keys'][0] ?? '');
                        if ($q === '') continue;
                        $imp = (int)($r['impressions'] ?? 0);
                        $clk = (int)($r['clicks'] ?? 0);
                        $ctr = round((float)($r['ctr'] ?? 0) * 100, 2);
                        $pos = (float)($r['position'] ?? 0);
                        $map[$q] = ['query' => $q, 'impressions' => $imp, 'clicks' => $clk, 'ctr_percent' => $ctr, 'position' => round($pos, 2)];
                        $totImp += $imp; $totClk += $clk; $posWeighted += $pos * $imp;
                    }
                    $queries = array_values($map);
                    // Client-side sort.
                    usort($queries, function($a, $b) use ($orderBy) {
                        if ($orderBy === 'position') return $a['position'] <=> $b['position'];
                        if ($orderBy === 'clicks') return $b['clicks'] <=> $a['clicks'];
                        return $b['impressions'] <=> $a['impressions'];
                    });
                    $queries = array_slice($queries, 0, $limit);
                    $avgPos = $totImp > 0 ? round($posWeighted / $totImp, 2) : 0;
                    $ctrTot = $totImp > 0 ? round(100.0 * $totClk / $totImp, 2) : 0;
                    if (!empty($queries) || $totImp > 0) {
                        return [
                            'slug' => $slug, 'days' => $days, 'source' => 'api', 'start_date' => $start, 'end_date' => $end,
                            'totals' => ['impressions' => $totImp, 'clicks' => $totClk, 'ctr_percent' => $ctrTot, 'avg_position' => $avgPos, 'rows' => count($rows)],
                            'queries' => $queries, 'count' => count($queries),
                        ];
                    }
                }
            } catch (Throwable $e) {
                error_log("GscTools pageGsc API fallback: " . $e->getMessage());
            }
        }

        // Fallback DB.
        self::ensureTable();
        $db = Database::getInstance();
        $orderSql = $orderBy === 'position' ? 'position ASC' : $orderBy . ' DESC';
        $totals = $db->fetchOne(
            "SELECT COALESCE(SUM(impressions),0) AS impressions,
                    COALESCE(SUM(clicks),0) AS clicks,
                    ROUND(CASE WHEN SUM(impressions)=0 THEN 0 ELSE 100.0*SUM(clicks)/SUM(impressions) END,2) AS ctr,
                    ROUND(SUM(position*impressions)/NULLIF(SUM(impressions),0),2) AS avg_position,
                    COUNT(*) AS row_count
             FROM gsc_data WHERE page_slug = ? AND (date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY) OR date IS NULL)",
            [$slug]
        );
        $rows = $db->fetchAll(
            "SELECT query, SUM(impressions) AS impressions, SUM(clicks) AS clicks,
                    ROUND(CASE WHEN SUM(impressions)=0 THEN 0 ELSE 100.0*SUM(clicks)/SUM(impressions) END,2) AS ctr,
                    ROUND(SUM(position*impressions)/NULLIF(SUM(impressions),0),2) AS position
             FROM gsc_data WHERE page_slug = ? AND (date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY) OR date IS NULL)
             GROUP BY query ORDER BY {$orderSql} LIMIT {$limit}",
            [$slug]
        );
        $queries = [];
        foreach ($rows as $r) {
            $queries[] = ['query' => $r['query'], 'impressions' => (int)$r['impressions'], 'clicks' => (int)$r['clicks'], 'ctr_percent' => (float)$r['ctr'], 'position' => (float)$r['position']];
        }
        return [
            'slug' => $slug, 'days' => $days, 'source' => 'db',
            'totals' => ['impressions' => (int)($totals['impressions'] ?? 0), 'clicks' => (int)($totals['clicks'] ?? 0), 'ctr_percent' => (float)($totals['ctr'] ?? 0), 'avg_position' => (float)($totals['avg_position'] ?? 0), 'rows' => (int)($totals['row_count'] ?? 0)],
            'queries' => $queries, 'count' => count($queries),
            'note' => empty($queries) ? 'No GSC data for this slug — try a different slug or connect Search Console.' : (self::shouldUseApi() ? 'API returned no rows for this slug — falling back to local data.' : null),
        ];
    }

    private static function gscQueries(array $args): array {
        $days = isset($args['days']) ? max(1, min(90, (int)$args['days'])) : 28;
        $limit = isset($args['limit']) ? max(1, min(50, (int)$args['limit'])) : 20;
        $minImp = isset($args['min_impressions']) ? max(0, (int)$args['min_impressions']) : 0;
        $orderBy = in_array($args['order_by'] ?? '', ['impressions', 'clicks', 'ctr', 'position'], true) ? $args['order_by'] : 'impressions';

        if (self::shouldUseApi()) {
            try {
                [$start, $end] = self::dateRange($days);
                $rows = GscClient::searchAnalytics($start, $end, ['query'], [], 5000);
                if ($rows !== null) {
                    $out = [];
                    foreach ($rows as $r) {
                        $q = (string)($r['keys'][0] ?? '');
                        $imp = (int)($r['impressions'] ?? 0);
                        if ($imp < $minImp) continue;
                        $out[] = [
                            'query' => $q,
                            'page_slug' => '', // aggregated across pages when only query dimension
                            'impressions' => $imp,
                            'clicks' => (int)($r['clicks'] ?? 0),
                            'ctr_percent' => round((float)($r['ctr'] ?? 0) * 100, 2),
                            'position' => round((float)($r['position'] ?? 0), 2),
                        ];
                    }
                    usort($out, function($a,$b) use ($orderBy) {
                        if ($orderBy === 'clicks') return $b['clicks'] <=> $a['clicks'];
                        if ($orderBy === 'ctr') return $b['ctr_percent'] <=> $a['ctr_percent'];
                        if ($orderBy === 'position') return $a['position'] <=> $b['position'];
                        return $b['impressions'] <=> $a['impressions'];
                    });
                    $out = array_slice($out, 0, $limit);
                    // If we need page_slug per query, do a second call with both dimensions and map?
                    // For now return query-only.
                    return ['days' => $days, 'source' => 'api', 'start_date' => $start, 'end_date' => $end, 'order_by' => $orderBy, 'min_impressions' => $minImp, 'queries' => $out, 'count' => count($out)];
                }
            } catch (Throwable $e) { error_log("GscTools gscQueries API fallback: " . $e->getMessage()); }
        }

        self::ensureTable();
        $db = Database::getInstance();
        $orderSql = match ($orderBy) { 'clicks' => 'clicks DESC', 'ctr' => 'ctr DESC', 'position' => 'position ASC', default => 'impressions DESC' };
        $having = $minImp > 0 ? "HAVING impressions >= {$minImp}" : '';
        $rows = $db->fetchAll(
            "SELECT query, page_slug, SUM(impressions) AS impressions, SUM(clicks) AS clicks,
                    ROUND(CASE WHEN SUM(impressions)=0 THEN 0 ELSE 100.0*SUM(clicks)/SUM(impressions) END,2) AS ctr,
                    ROUND(SUM(position*impressions)/NULLIF(SUM(impressions),0),2) AS position
             FROM gsc_data WHERE (date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY) OR date IS NULL)
             GROUP BY query, page_slug {$having} ORDER BY {$orderSql} LIMIT {$limit}"
        );
        $out = [];
        foreach ($rows as $r) $out[] = ['query' => $r['query'], 'page_slug' => $r['page_slug'], 'impressions' => (int)$r['impressions'], 'clicks' => (int)$r['clicks'], 'ctr_percent' => (float)$r['ctr'], 'position' => (float)$r['position']];
        return ['days' => $days, 'source' => 'db', 'order_by' => $orderBy, 'min_impressions' => $minImp, 'queries' => $out, 'count' => count($out)];
    }

    private static function gscPages(array $args): array {
        $days = isset($args['days']) ? max(1, min(90, (int)$args['days'])) : 28;
        $limit = isset($args['limit']) ? max(1, min(50, (int)$args['limit'])) : 15;
        $orderBy = in_array($args['order_by'] ?? '', ['impressions', 'clicks', 'ctr'], true) ? $args['order_by'] : 'impressions';

        if (self::shouldUseApi()) {
            try {
                [$start, $end] = self::dateRange($days);
                $rows = GscClient::searchAnalytics($start, $end, ['page'], [], 5000);
                if ($rows !== null) {
                    $out = [];
                    foreach ($rows as $r) {
                        $pageUrl = (string)($r['keys'][0] ?? '');
                        $slug = GscClient::slugFromPage($pageUrl);
                        $imp = (int)($r['impressions'] ?? 0);
                        $out[] = [
                            'slug' => $slug, 'page' => $pageUrl,
                            'impressions' => $imp,
                            'clicks' => (int)($r['clicks'] ?? 0),
                            'ctr_percent' => round((float)($r['ctr'] ?? 0) * 100, 2),
                            'avg_position' => round((float)($r['position'] ?? 0), 2),
                            'distinct_queries' => null,
                        ];
                    }
                    usort($out, function($a,$b) use ($orderBy) {
                        if ($orderBy === 'clicks') return $b['clicks'] <=> $a['clicks'];
                        if ($orderBy === 'ctr') return $b['ctr_percent'] <=> $a['ctr_percent'];
                        return $b['impressions'] <=> $a['impressions'];
                    });
                    $out = array_slice($out, 0, $limit);
                    return ['days' => $days, 'source' => 'api', 'start_date' => $start, 'end_date' => $end, 'order_by' => $orderBy, 'pages' => $out, 'count' => count($out)];
                }
            } catch (Throwable $e) { error_log("GscTools gscPages API fallback: " . $e->getMessage()); }
        }

        self::ensureTable();
        $db = Database::getInstance();
        $orderSql = $orderBy === 'ctr' ? 'ctr DESC' : $orderBy . ' DESC';
        $rows = $db->fetchAll(
            "SELECT page_slug, SUM(impressions) AS impressions, SUM(clicks) AS clicks,
                    ROUND(CASE WHEN SUM(impressions)=0 THEN 0 ELSE 100.0*SUM(clicks)/SUM(impressions) END,2) AS ctr,
                    ROUND(SUM(position*impressions)/NULLIF(SUM(impressions),0),2) AS avg_position, COUNT(DISTINCT query) AS distinct_queries
             FROM gsc_data WHERE (date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY) OR date IS NULL)
             GROUP BY page_slug ORDER BY {$orderSql} LIMIT {$limit}"
        );
        $out = [];
        foreach ($rows as $r) $out[] = ['slug' => $r['page_slug'], 'impressions' => (int)$r['impressions'], 'clicks' => (int)$r['clicks'], 'ctr_percent' => (float)$r['ctr'], 'avg_position' => (float)$r['avg_position'], 'distinct_queries' => (int)$r['distinct_queries']];
        return ['days' => $days, 'source' => 'db', 'order_by' => $orderBy, 'pages' => $out, 'count' => count($out)];
    }

    private static function searchQueries(array $args): array {
        $term = trim((string)($args['term'] ?? ''));
        if ($term === '') throw new InvalidArgumentException('term is required');
        $days = isset($args['days']) ? max(1, min(90, (int)$args['days'])) : 28;
        $limit = isset($args['limit']) ? max(1, min(50, (int)$args['limit'])) : 20;

        if (self::shouldUseApi()) {
            try {
                [$start, $end] = self::dateRange($days);
                // API has no direct query-contains filter for searchAnalytics — we fetch top queries and filter client-side.
                $rows = GscClient::searchAnalytics($start, $end, ['query','page'], [], 5000);
                if ($rows !== null) {
                    $needle = mb_strtolower($term, 'UTF-8');
                    $out = [];
                    foreach ($rows as $r) {
                        $q = (string)($r['keys'][0] ?? '');
                        $pageUrl = (string)($r['keys'][1] ?? '');
                        if (mb_stripos($q, $needle, 0, 'UTF-8') === false) continue;
                        $out[] = [
                            'query' => $q, 'page_slug' => GscClient::slugFromPage($pageUrl), 'page' => $pageUrl,
                            'impressions' => (int)($r['impressions'] ?? 0),
                            'clicks' => (int)($r['clicks'] ?? 0),
                            'ctr_percent' => round((float)($r['ctr'] ?? 0) * 100, 2),
                            'position' => round((float)($r['position'] ?? 0), 2),
                        ];
                    }
                    usort($out, fn($a,$b) => $b['impressions'] <=> $a['impressions']);
                    $out = array_slice($out, 0, $limit);
                    return ['term' => $term, 'days' => $days, 'source' => 'api', 'start_date' => $start, 'end_date' => $end, 'queries' => $out, 'count' => count($out)];
                }
            } catch (Throwable $e) { error_log("GscTools searchQueries API fallback: " . $e->getMessage()); }
        }

        self::ensureTable();
        $db = Database::getInstance();
        $like = '%' . $term . '%';
        $rows = $db->fetchAll(
            "SELECT query, page_slug, SUM(impressions) AS impressions, SUM(clicks) AS clicks,
                    ROUND(CASE WHEN SUM(impressions)=0 THEN 0 ELSE 100.0*SUM(clicks)/SUM(impressions) END,2) AS ctr,
                    ROUND(SUM(position*impressions)/NULLIF(SUM(impressions),0),2) AS position
             FROM gsc_data WHERE query LIKE ? AND (date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY) OR date IS NULL)
             GROUP BY query, page_slug ORDER BY impressions DESC LIMIT {$limit}",
            [$like]
        );
        $out = [];
        foreach ($rows as $r) $out[] = ['query' => $r['query'], 'page_slug' => $r['page_slug'], 'impressions' => (int)$r['impressions'], 'clicks' => (int)$r['clicks'], 'ctr_percent' => (float)$r['ctr'], 'position' => (float)$r['position']];
        return ['term' => $term, 'days' => $days, 'source' => 'db', 'queries' => $out, 'count' => count($out)];
    }

    private static function queryGsc(array $args): array {
        if (!self::shouldUseApi()) {
            return ['error' => 'GSC not connected', 'note' => 'Connect Search Console in AI Studio to use query_gsc. Sugar tools fall back to cached gsc_data, but query_gsc is live-API only.'];
        }
        $days = isset($args['days']) ? max(1, min(90, (int)$args['days'])) : 28;
        $startDate = isset($args['startDate']) ? trim((string)$args['startDate']) : '';
        $endDate = isset($args['endDate']) ? trim((string)$args['endDate']) : '';
        if ($startDate !== '' || $endDate !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                throw new InvalidArgumentException('startDate/endDate must be Y-m-d');
            }
            if (strtotime($startDate) > strtotime($endDate)) throw new InvalidArgumentException('startDate must be <= endDate');
            $start = $startDate; $end = $endDate;
        } else {
            [$start, $end] = self::dateRange($days);
        }
        $allowedDims = ['query','page','country','device','searchAppearance','date'];
        $dims = $args['dimensions'] ?? [];
        if (!is_array($dims)) $dims = [];
        $dims = array_values(array_intersect(array_map('strval', $dims), $allowedDims));
        // GSC API max 5 dimensions? Keep up to 3 for sanity.
        if (count($dims) > 3) $dims = array_slice($dims, 0, 3);

        $allowedOps = ['equals','contains','notContains','includingRegex','excludingRegex'];
        $filters = [];
        if (isset($args['filters']) && is_array($args['filters'])) {
            foreach ($args['filters'] as $f) {
                if (!is_array($f)) continue;
                $dim = (string)($f['dimension'] ?? '');
                $op = (string)($f['operator'] ?? '');
                $exp = trim((string)($f['expression'] ?? ''));
                if (!in_array($dim, $allowedDims, true)) continue;
                if (!in_array($op, $allowedOps, true)) continue;
                if ($exp === '' || mb_strlen($exp) > 500) continue;
                $filters[] = ['dimension' => $dim, 'operator' => $op, 'expression' => $exp];
                if (count($filters) >= 5) break;
            }
        }
        $filterGroups = [];
        if (!empty($filters)) $filterGroups = [['filters' => $filters]];

        $limit = isset($args['limit']) ? max(1, min(100, (int)$args['limit'])) : 25;
        $orderBy = in_array($args['orderBy'] ?? '', ['impressions','clicks','ctr','position'], true) ? $args['orderBy'] : 'impressions';

        $rows = GscClient::searchAnalytics($start, $end, $dims, $filterGroups, $limit * 2);
        if ($rows === null) {
            return ['error' => 'GSC API auth failed or site unverified', 'note' => 'Check GSC connection and site_url (sc-domain:... vs https://...). Try get_gsc_overview first.', 'start_date' => $start, 'end_date' => $end];
        }
        $out = [];
        foreach ($rows as $r) {
            $keys = $r['keys'] ?? [];
            if (!is_array($keys)) $keys = [];
            $entry = [
                'keys' => $keys,
                'impressions' => (int)($r['impressions'] ?? 0),
                'clicks' => (int)($r['clicks'] ?? 0),
                'ctr_percent' => round((float)($r['ctr'] ?? 0) * 100, 2),
                'position' => round((float)($r['position'] ?? 0), 2),
            ];
            // Map keys to dimension names for ergonomics
            foreach ($dims as $i => $d) {
                $entry[$d] = $keys[$i] ?? null;
                if ($d === 'page' && isset($entry[$d])) {
                    $entry['page_slug'] = GscClient::slugFromPage((string)$entry[$d]);
                }
            }
            $out[] = $entry;
        }
        usort($out, function($a,$b) use ($orderBy) {
            if ($orderBy === 'clicks') return $b['clicks'] <=> $a['clicks'];
            if ($orderBy === 'ctr') return $b['ctr_percent'] <=> $a['ctr_percent'];
            if ($orderBy === 'position') return $a['position'] <=> $b['position'];
            return $b['impressions'] <=> $a['impressions'];
        });
        $out = array_slice($out, 0, $limit);
        return [
            'source' => 'api',
            'start_date' => $start,
            'end_date' => $end,
            'dimensions' => $dims,
            'filters' => $filters,
            'orderBy' => $orderBy,
            'limit' => $limit,
            'rows' => $out,
            'count' => count($out),
            'note' => empty($out) ? 'No rows for this query — try broader dimensions/filters or larger days.' : null,
        ];
    }
}
