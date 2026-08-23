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
        'INFORMATION_SCHEMA', 'PERFORMANCE_SCHEMA', 'MYSQL',
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
        ];
    }

    public static function handle(string $name, array $args): array {
        if ($name === 'run_analytics_query') {
            return self::runQuery($args);
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    private static function runQuery(array $args): array {
        $sql = trim((string)($args['query'] ?? ''));
        if ($sql === '') {
            throw new InvalidArgumentException('query is required');
        }

        self::assertSafe($sql);

        if (preg_match('/\bLIMIT\s+(\d+)/i', $sql, $m)) {
            if ((int)$m[1] > self::MAX_ROWS) {
                $sql = preg_replace('/\bLIMIT\s+\d+/i', 'LIMIT ' . self::MAX_ROWS, $sql);
            }
        } else {
            $sql .= ' LIMIT ' . self::MAX_ROWS;
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