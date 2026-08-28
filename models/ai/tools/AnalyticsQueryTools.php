<?php
// path: ./models/ai/tools/AnalyticsQueryTools.php
// Read-only ad-hoc analytics SQL for the AI Studio agent. The agent gets a
// real SELECT sandbox over the analytics tables (plus `pages` for slug joins)
// so it can answer bespoke questions the fixed analytics tools can't.
// Safety: single SELECT only, keyword denylist, table allowlist, LIMIT caps,
// cell truncation. Never executes outside the allowlist, never mutates.

require_once BASE_PATH . '/core/Database.php';

class AnalyticsQueryTools {

    /** Tables the agent may query. `pages` is read-only and joinable by slug. */
    private const ALLOWED_TABLES = [
        'analytics', 'analytics_hourly', 'analytics_monthly',
        'analytics_bot_visits', 'analytics_internal_links',
        'analytics_internal_links_monthly', 'analytics_link_clicks',
        'analytics_rotations',
        'analytics_dedup_visits', 'analytics_dedup_clicks',
        'analytics_dedup_phone_calls', 'analytics_dedup_site_visits',
        'analytics_dedup_internal_links', 'analytics_throttle',
        'gsc_data',
        'pages',
    ];

    /** Anything that could read, mutate, or blow up beyond a plain SELECT. */
    private const DENIED_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE',
        'REPLACE', 'RENAME', 'GRANT', 'REVOKE', 'LOCK', 'UNLOCK', 'USE', 'SET',
        'OUTFILE', 'LOAD_FILE', 'SLEEP', 'BENCHMARK', 'INTO', 'CALL', 'DO',
        'INFORMATION_SCHEMA', 'PERFORMANCE_SCHEMA', 'MYSQL', 'UNION',
    ];

    private const MAX_ROWS = 50;
    private const MAX_CELL = 200;

    public static function definitions(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'run_analytics_query',
                    'description' => 'Run a read-only SELECT over the analytics tables to answer questions the fixed analytics tools cannot. Single statement, no semicolons. A LIMIT is added automatically if missing. Allowed tables: '
                        . implode(', ', self::ALLOWED_TABLES)
                        . ". Key columns — analytics_monthly: page_slug, language, year, month, total_visits, total_clicks, total_phone_calls, utm_source, avg_time_seconds, unique_days, unique_visitors. analytics_hourly: page_slug, language, date, hour (0-23), visits, clicks, phone_calls, utm_source. analytics: page_slug, language, date, visits, clicks, phone_calls, utm_source, unique_visitors, bounce_rate, avg_time_seconds. analytics_bot_visits: page_slug, bot_type, visit_date, visits. analytics_internal_links: from_slug, to_slug, language, clicks, date. analytics_internal_links_monthly: from_slug, to_slug, year, month, total_clicks, unique_days. analytics_link_clicks: from_slug, to_slug, link_text, clicks, language, date. analytics_rotations: page_slug, year, rotation_month, language, times_shown, unique_days. pages: id, slug, title_ru, title_uz, is_published (join pages.slug = analytics_*.page_slug).",
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'A single read-only SELECT statement. Example: "SELECT page_slug, SUM(total_visits) AS v FROM analytics_monthly WHERE year = 2026 AND month BETWEEN 1 AND 6 GROUP BY page_slug ORDER BY v DESC".'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'query_builder',
                    'description' => 'Sugar over analytics tables — build a grouped metric query without writing SQL. Maps to allowlisted SELECTs over analytics_monthly, analytics_hourly, analytics, gsc_data. Prefer this for simple aggregates; use run_analytics_query for custom joins.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'metric' => ['type' => 'string', 'enum' => ['visits','clicks','phone_calls','ctr'], 'description' => 'Metric to aggregate (default visits).'],
                            'group_by' => ['type' => 'string', 'enum' => ['page','date','language','utm_source'], 'description' => 'Group dimension (default page).'],
                            'period' => ['type' => 'string', 'enum' => ['last_3_months','last_30_days','today','custom'], 'description' => 'Period preset (default last_3_months).'],
                            'start_date' => ['type' => 'string', 'description' => 'Start date Y-m-d for custom period.'],
                            'end_date' => ['type' => 'string', 'description' => 'End date Y-m-d for custom period.'],
                            'filters' => ['type' => 'object', 'description' => 'Optional filters: slug, utm_source, language', 'properties' => [
                                'slug' => ['type'=>'string'], 'utm_source'=>['type'=>'string'], 'language'=>['type'=>'string','enum'=>['ru','uz']],
                            ]],
                            'limit' => ['type' => 'integer', 'description' => 'Max rows 1-50 (default 15).'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        if ($name === 'run_analytics_query') {
            return self::runQuery($args);
        }
        if ($name === 'query_builder') {
            return self::queryBuilder($args);
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    private static function runQuery(array $args): array {
        $sql = trim((string)($args['query'] ?? ''));
        if ($sql === '') {
            throw new InvalidArgumentException('query is required — example: "SELECT page_slug, SUM(total_visits) AS v FROM analytics_monthly WHERE year = 2026 GROUP BY page_slug ORDER BY v DESC LIMIT 10".');
        }

        self::assertSafe($sql);

        if (preg_match('/\bLIMIT\s+(\d+)/i', $sql, $m)) {
            if ((int)$m[1] > self::MAX_ROWS) {
                $sql = preg_replace('/\bLIMIT\s+\d+/i', 'LIMIT ' . self::MAX_ROWS, $sql, 1);
            }
        } else {
            $sql .= ' LIMIT ' . self::MAX_ROWS;
        }
        // Cap OFFSET to prevent OFFSET 1000000 DoS (H8).
        if (preg_match('/\bOFFSET\s+(\d+)/i', $sql, $om)) {
            if ((int)$om[1] > 10000) {
                $sql = preg_replace('/\bOFFSET\s+\d+/i', 'OFFSET 10000', $sql, 1);
            }
        }
        // Also cap LIMIT ... OFFSET combined via comma syntax LIMIT offset, count
        if (preg_match('/\bLIMIT\s+(\d+)\s*,\s*(\d+)/i', $sql, $cm)) {
            if ((int)$cm[1] > 10000) {
                $sql = preg_replace('/\bLIMIT\s+\d+\s*,\s*\d+/i', 'LIMIT 10000, ' . min((int)$cm[2], self::MAX_ROWS), $sql, 1);
            }
        }

        $rows = Database::getInstance()->query($sql)->fetchAll();

        $out = [];
        $truncated = false;
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $clean = [];
            foreach ($row as $k => $v) {
                if (is_array($v) || is_object($v)) {
                    $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $truncated = true;
                }
                if (is_string($v) && mb_strlen($v) > self::MAX_CELL) {
                    $v = mb_substr($v, 0, self::MAX_CELL) . '…';
                    $truncated = true;
                }
                $clean[$k] = $v;
            }
            $out[] = $clean;
        }

        return [
            'ok' => true,
            'sql' => $sql,
            'rows' => $out,
            'count' => count($out),
            'truncated' => $truncated,
            'note' => 'Read-only analytics query over the allowlisted tables.',
        ];
    }

    private static function queryBuilder(array $args): array {
        $metric = $args['metric'] ?? 'visits';
        if (!in_array($metric, ['visits','clicks','phone_calls','ctr'], true)) $metric = 'visits';
        $groupBy = $args['group_by'] ?? 'page';
        if (!in_array($groupBy, ['page','date','language','utm_source'], true)) $groupBy = 'page';
        $period = $args['period'] ?? 'last_3_months';
        $limit = isset($args['limit']) ? max(1, min(50, (int)$args['limit'])) : 15;
        $filters = is_array($args['filters'] ?? null) ? $args['filters'] : [];
        $slug = trim((string)($filters['slug'] ?? ''));
        $utm = trim((string)($filters['utm_source'] ?? ''));
        $language = ($filters['language'] ?? '') === 'uz' ? 'uz' : (($filters['language'] ?? '') === 'ru' ? 'ru' : '');

        // Build WHERE for monthly table
        $where = [];
        $params = [];
        // Period
        if ($period === 'last_3_months') {
            $where[] = "DATE(CONCAT(year,'-',month,'-01')) >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        } elseif ($period === 'last_30_days') {
            // For monthly fallback, use last 30 days via hourly/daily not available, approximate via year/month same
            $where[] = "DATE(CONCAT(year,'-',month,'-01')) >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        } elseif ($period === 'today') {
            $where[] = "year = YEAR(CURDATE()) AND month = MONTH(CURDATE())";
        } elseif ($period === 'custom') {
            $sd = trim((string)($args['start_date'] ?? ''));
            $ed = trim((string)($args['end_date'] ?? ''));
            if ($sd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sd)) {
                $sy = (int)substr($sd,0,4); $sm = (int)substr($sd,5,2);
                $where[] = "DATE(CONCAT(year,'-',month,'-01')) >= DATE('{$sy}-{$sm}-01')";
            }
            if ($ed !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ed)) {
                $ey = (int)substr($ed,0,4); $em = (int)substr($ed,5,2);
                $where[] = "DATE(CONCAT(year,'-',month,'-01')) <= DATE('{$ey}-{$em}-01')";
            }
        }
        if ($slug !== '') { $where[] = "page_slug = ?"; $params[] = $slug; }
        if ($utm !== '') { $where[] = "utm_source = ?"; $params[] = $utm; }
        if ($language !== '') { $where[] = "language = ?"; $params[] = $language; }

        $whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        // Select / group mapping
        $groupCol = match($groupBy) { 'page'=>'page_slug', 'date'=>"CONCAT(year,'-',LPAD(month,2,'0'))", 'language'=>'language', 'utm_source'=>'utm_source', default=>'page_slug' };
        $metricSql = match($metric) {
            'visits' => 'SUM(total_visits) AS visits',
            'clicks' => 'SUM(total_clicks) AS clicks',
            'phone_calls' => 'SUM(total_phone_calls) AS phone_calls',
            'ctr' => 'ROUND(CASE WHEN SUM(total_visits)=0 THEN 0 ELSE 100.0*SUM(total_phone_calls)/SUM(total_visits) END,2) AS ctr_percent',
            default => 'SUM(total_visits) AS visits',
        };
        $orderBy = match($metric) { 'visits'=>'visits', 'clicks'=>'clicks', 'phone_calls'=>'phone_calls', 'ctr'=>'ctr_percent', default=>'visits' };

        $sql = "SELECT {$groupCol} AS grp, {$metricSql} FROM analytics_monthly {$whereSql} GROUP BY grp ORDER BY {$orderBy} DESC LIMIT {$limit}";
        self::assertSafe($sql);
        $rows = Database::getInstance()->fetchAll($sql, $params);
        $out = [];
        foreach ($rows as $r) {
            $clean = [];
            foreach ($r as $k=>$v) {
                if (is_string($v) && mb_strlen($v) > self::MAX_CELL) $v = mb_substr($v,0,self::MAX_CELL).'…';
                $clean[$k] = $v;
            }
            $out[] = $clean;
        }
        return ['ok'=>true,'sql'=>$sql,'params'=>$params,'metric'=>$metric,'group_by'=>$groupBy,'period'=>$period,'rows'=>$out,'count'=>count($out)];
    }

    private static function assertSafe(string $sql): void {
        $clean = self::stripComments($sql);
        $bare = self::stripStrings($clean);

        if (strpos($clean, ';') !== false) {
            throw new InvalidArgumentException('Only a single statement is allowed (no semicolons).');
        }
        if (!preg_match('/^\s*SELECT\b/i', $bare)) {
            throw new InvalidArgumentException('Only SELECT queries are allowed.');
        }
        foreach (self::DENIED_KEYWORDS as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $bare)) {
                throw new InvalidArgumentException("Query rejected: forbidden keyword {$kw}.");
            }
        }

        if (preg_match_all('/`([^`]+)`/', $bare, $m)) {
            foreach ($m[1] as $t) {
                if (!in_array(strtolower($t), self::ALLOWED_TABLES, true)) {
                    throw new InvalidArgumentException("Query rejected: table `{$t}` is not allowlisted.");
                }
            }
        }

        foreach (self::fromClauseTables($bare) as $t) {
            if (!in_array($t, self::ALLOWED_TABLES, true)) {
                throw new InvalidArgumentException("Query rejected: table {$t} is not allowlisted.");
            }
        }
    }

    /** Extract table names referenced after FROM / JOIN (incl. comma lists). */
    private static function fromClauseTables(string $sql): array {
        $tables = [];
        $break = '/\b(?:WHERE|GROUP\s+BY|HAVING|ORDER\s+BY|LIMIT|UNION|ON|USING|LEFT|RIGHT|INNER|CROSS|FULL|OUTER|STRAIGHT_JOIN|NATURAL|JOIN)\b/i';
        if (preg_match_all('/\b(?:FROM|JOIN)\s+/i', $sql, $posM, PREG_OFFSET_CAPTURE)) {
            foreach ($posM[0] as $match) {
                $rest = substr($sql, $match[1] + strlen($match[0]));
                if (preg_match($break, $rest, $bm, PREG_OFFSET_CAPTURE)) {
                    $rest = substr($rest, 0, $bm[0][1]);
                }
                foreach (explode(',', $rest) as $item) {
                    $item = trim($item);
                    if ($item === '') continue;
                    if ($item[0] === '(') {
                        $tables[] = '(subquery)'; // subqueries rejected by allowlist
                        continue;
                    }
                    if (preg_match('/`?([a-zA-Z_][a-zA-Z0-9_]*)`?/', $item, $tm)) {
                        $tables[] = strtolower($tm[1]);
                    }
                }
            }
        }
        return array_unique($tables);
    }

    private static function stripComments(string $sql): string {
        $sql = (string)preg_replace('/\/\*.*?\*\//s', ' ', $sql);
        $sql = (string)preg_replace('/--[^\n]*/i', ' ', $sql);
        $sql = (string)preg_replace('/#[^\n]*/', ' ', $sql);
        return $sql;
    }

    private static function stripStrings(string $sql): string {
        return (string)preg_replace("/(?:'(?:\\\\.|[^'\\\\])*'|\"(?:\\\\.|[^\"\\\\])*\")/", ' ', $sql);
    }
}