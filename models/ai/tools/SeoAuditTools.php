<?php
// path: ./models/ai/tools/SeoAuditTools.php
// READ-ONLY SEO findings for AI Studio: the on-page audit (models/seo/PageAudit.php) and the Search Console
// opportunity lists (models/seo/GscReports.php) — the same data as /admin/search-console and the SEO API.
// These tools only READ. Fixes are made with the normal edit tools (str_replace_field / patch_section / set_field),
// so approvals, page_revisions snapshots and RU↔UZ parity all apply as usual.

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/LinkWidget.php';
require_once BASE_PATH . '/models/GscClient.php';
require_once BASE_PATH . '/models/seo/PageAudit.php';
require_once BASE_PATH . '/models/seo/GscReports.php';

class SeoAuditTools {

    public static function definitions(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_seo_audit',
                    'description' => 'On-page SEO audit of published pages (reads the pages table, no Google call): RU/UZ parity gaps, meta title/description length and duplicates, missing meta, inline style="", missing alt, off-domain canonical, pages with no inbound links. '
                        . 'Returns findings per page WITH the current text of the meta fields involved (both languages), so you can fix without calling get_page first. Call once per request and reuse. '
                        . 'Default severity=warn (warnings + errors). Thin content is info-only on this site (short model pages are normal) — do not "fix" it by padding text. '
                        . 'Fix meta issues with str_replace_field/patch_section, changing RU and UZ together; keep the main keyword and meaning; do not invent claims.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'severity' => ['type' => 'string', 'enum' => ['error', 'warn', 'info'], 'description' => 'Minimum severity (default warn).'],
                            'slug' => ['type' => 'string', 'description' => 'Audit one page only.'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz', 'both'], 'description' => 'Language to audit (default both).'],
                            'limit' => ['type' => 'integer', 'description' => 'Max pages returned, worst first (default 25, max 50).'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_seo_opportunities',
                    'description' => 'Search Console growth lists (live GSC, ~3 day lag, cached 1h — call once per request and reuse): queries at position 5-20 (striking distance), queries with CTR far below this site\'s own CTR for their position, and queries with impressions but zero clicks — each with its page slug. '
                        . 'Use these to decide WHICH pages are worth a title/description/content refresh. Queries are intent signals: use naturally once, no stuffing. Never state rankings or traffic without citing this output and its period.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'days' => ['type' => 'integer', 'description' => 'Lookback window in days (default 28, max 90).'],
                            'min_impressions' => ['type' => 'integer', 'description' => 'Ignore rows below this many impressions (default 50).'],
                            'limit' => ['type' => 'integer', 'description' => 'Rows per list (default 15, max 50).'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        switch ($name) {
            case 'get_seo_audit': return self::audit($args);
            case 'get_seo_opportunities': return self::opportunities($args);
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    private static function audit(array $args): array {
        $severity = in_array($args['severity'] ?? 'warn', ['error', 'warn', 'info'], true) ? ($args['severity'] ?? 'warn') : 'warn';
        $lang = in_array($args['lang'] ?? 'both', ['ru', 'uz', 'both'], true) ? ($args['lang'] ?? 'both') : 'both';
        $limit = max(1, min(50, (int)($args['limit'] ?? 25)));
        $slug = trim((string)($args['slug'] ?? ''));
        $host = strtolower((string)parse_url((string)BASE_URL, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?: 'kuplyu-tashkent.uz';

        $pages = (new Page())->getAll(false);
        $result = PageAudit::run($pages, $host, [
            'lang' => $lang,
            'min_severity' => $severity,
            'only_issues' => true,
            'slug' => $slug,
            'widget_inbound' => (new LinkWidget())->getInboundCounts(),
        ]);

        $bySlug = [];
        foreach ($pages as $p) $bySlug[$p['slug']] = $p;

        $out = [];
        foreach (array_slice($result['pages'], 0, $limit) as $row) {
            $page = $bySlug[$row['slug']] ?? [];
            // Current text of every meta field a finding points at, both languages (parity: change them together).
            $bases = [];
            foreach ($row['issues'] as $i) {
                if (str_contains($i['code'], 'meta_title')) $bases['meta_title'] = true;
                if (str_contains($i['code'], 'meta_description')) $bases['meta_description'] = true;
            }
            $fields = [];
            foreach (array_keys($bases) as $base) {
                foreach (['ru', 'uz'] as $l) {
                    $v = (string)($page["{$base}_$l"] ?? '');
                    $fields["{$base}_$l"] = ['value' => $v, 'length' => mb_strlen($v)];
                }
            }
            $out[] = [
                'slug' => $row['slug'],
                'page_id' => $row['id'],
                'issues' => array_map(static fn($i) => $i['code'] . ($i['lang'] ? " ({$i['lang']})" : '') . ' [' . $i['severity'] . ']: ' . $i['detail'], $row['issues']),
                'current_fields' => $fields,
            ];
        }

        return [
            'pages_checked' => $result['pages_checked'],
            'pages_with_issues' => $result['pages_with_issues'],
            'issues_by_severity' => $result['issues_by_severity'],
            'issues_by_code' => $result['issues_by_code'],
            'returned' => count($out),
            'pages' => $out,
            'guidance' => 'Meta title <= ~60 chars, description <= ~160 chars are heuristics, not Google rules (Google may rewrite them). Change RU and UZ together. Keep the main keyword and meaning. Do not invent prices, offers or claims. Fix a few pages, then re-run get_seo_audit with slug to verify.',
        ];
    }

    private static function opportunities(array $args): array {
        if (!GscClient::isConfigured() || !GscClient::isConnected()) {
            return ['available' => false, 'note' => 'Search Console is not connected — connect it in AI Studio (Connect GSC).'];
        }
        try {
            return GscReports::opportunities([
                'days' => max(1, min(90, (int)($args['days'] ?? 28))),
                'min_impressions' => max(1, (int)($args['min_impressions'] ?? 50)),
                'limit' => max(1, min(50, (int)($args['limit'] ?? 15))),
            ]);
        } catch (GscAuthRequired $e) {
            return ['available' => false, 'note' => 'Search Console authorisation expired — reconnect it in AI Studio.'];
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            error_log('[SeoAuditTools] opportunities: ' . $e->getMessage());
            return ['available' => false, 'note' => 'Search Console request failed — try again later.'];
        }
    }
}
