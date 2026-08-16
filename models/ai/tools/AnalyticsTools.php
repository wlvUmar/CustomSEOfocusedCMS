<?php
// path: ./models/ai/tools/AnalyticsTools.php
// READ-ONLY analytics tools. Backed entirely by real tables
// (analytics_hourly / analytics_monthly / analytics_bot_visits /
// analytics_internal_links_monthly / analytics_rotations).
// There is no keyword/SERP-position data in this CMS — the agent reasons
// over visits, clicks, phone calls, CTR, crawl frequency and internal links.

require_once BASE_PATH . '/models/Analytics.php';

class AnalyticsTools {

    public static function definitions(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_top_pages',
                    'description' => 'Top pages ordered by conversion rate (phone calls per visit), with visits, clicks, calls and CTR. The closest thing this CMS has to a ranking signal.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'months' => ['type' => 'integer', 'description' => 'Lookback window in months (default 3).'],
                            'limit' => ['type' => 'integer', 'description' => 'Max rows (default 10).'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_page_stats',
                    'description' => 'Traffic stats for one page: visits, clicks, phone calls per language, plus totals.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'slug' => ['type' => 'string', 'description' => 'Page slug (e.g. "services").'],
                            'months' => ['type' => 'integer', 'description' => 'Lookback window in months (default 3).'],
                        ],
                        'required' => ['slug'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_underperforming_pages',
                    'description' => 'Published pages with the fewest visits (and calls) in the lookback window — the pages that most likely need content/SEO work. Use this to decide what to fix.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'months' => ['type' => 'integer', 'description' => 'Lookback window in months (default 3).'],
                            'max_visits' => ['type' => 'integer', 'description' => 'Return pages with at most this many visits (default 1 = near-zero traffic).'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_crawl_frequency',
                    'description' => 'How often search-engine bots crawl each page (days with visits, total visits, last crawl date). Zero-crawl pages may be orphaned or excluded.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'days' => ['type' => 'integer', 'description' => 'Lookback window in days (default 30).'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_internal_links',
                    'description' => 'Internal link performance: with a slug, returns inbound and outbound links with click counts; without one, returns the most effective page-to-page links by click-through rate.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'slug' => ['type' => 'string', 'description' => 'Optional page slug.'],
                            'months' => ['type' => 'integer', 'description' => 'Lookback window in months (default 3).'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_rotation_effectiveness',
                    'description' => 'How often content-rotation variants were actually shown per page, joined with page traffic. Use to judge whether rotation is working on a page.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'months' => ['type' => 'integer', 'description' => 'Lookback window in months (default 6).'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        switch ($name) {
            case 'get_top_pages':
                return self::topPages($args);
            case 'get_page_stats':
                return self::pageStats($args);
            case 'get_underperforming_pages':
                return self::underperforming($args);
            case 'get_crawl_frequency':
                return self::crawlFrequency($args);
            case 'get_internal_links':
                return self::internalLinks($args);
            case 'get_rotation_effectiveness':
                return self::rotationEffectiveness($args);
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    private static function topPages(array $args): array {
        $months = isset($args['months']) ? max(1, min(24, (int)$args['months'])) : 3;
        $limit = isset($args['limit']) ? max(1, min(20, (int)$args['limit'])) : 10;
        $model = new Analytics();
        $rows = $model->getTopPerformers($months, $limit);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'slug' => $r['page_slug'],
                'visits' => (int)$r['visits'],
                'clicks' => (int)$r['clicks'],
                'phone_calls' => (int)$r['phone_calls'],
                'ctr_percent' => (float)$r['ctr'],
            ];
        }
        return ['months' => $months, 'pages' => $out, 'count' => count($out)];
    }

    private static function pageStats(array $args): array {
        $slug = trim((string)($args['slug'] ?? ''));
        $months = isset($args['months']) ? max(1, min(24, (int)$args['months'])) : 3;
        if ($slug === '') throw new InvalidArgumentException('slug is required');
        $model = new Analytics();
        $rows = $model->getPageStats($months, $slug);
        $perLang = [];
        foreach ($rows as $r) {
            $perLang[] = [
                'language' => $r['language'],
                'visits' => (int)$r['visits'],
                'clicks' => (int)$r['clicks'],
                'phone_calls' => (int)$r['phone_calls'],
            ];
        }
        $total = $model->getTotalStats($months, $slug);
        return [
            'slug' => $slug,
            'months' => $months,
            'per_language' => $perLang,
            'totals' => [
                'visits' => (int)($total['total_visits'] ?? 0),
                'clicks' => (int)($total['total_clicks'] ?? 0),
                'phone_calls' => (int)($total['total_phone_calls'] ?? 0),
            ],
        ];
    }

    private static function underperforming(array $args): array {
        $months = isset($args['months']) ? max(1, min(24, (int)$args['months'])) : 3;
        $maxVisits = isset($args['max_visits']) ? max(0, (int)$args['max_visits']) : 1;
        $db = Database::getInstance();
        $sql = "SELECT p.id, p.slug, p.title_ru, p.title_uz, p.is_published,
                       COALESCE(SUM(am.total_visits), 0) AS visits,
                       COALESCE(SUM(am.total_clicks), 0) AS clicks,
                       COALESCE(SUM(am.total_phone_calls), 0) AS phone_calls
                FROM pages p
                LEFT JOIN analytics_monthly am
                  ON am.page_slug = p.slug
                 AND DATE(CONCAT(am.year, '-', am.month, '-01')) >= DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)
                WHERE p.is_published = 1
                GROUP BY p.id, p.slug, p.title_ru, p.title_uz, p.is_published
                HAVING visits <= {$maxVisits}
                ORDER BY visits ASC, phone_calls ASC
                LIMIT 20";
        $rows = $db->fetchAll($sql);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'page_id' => (int)$r['id'],
                'slug' => $r['slug'],
                'title_ru' => $r['title_ru'] ?? '',
                'visits' => (int)$r['visits'],
                'clicks' => (int)$r['clicks'],
                'phone_calls' => (int)$r['phone_calls'],
            ];
        }
        return ['months' => $months, 'threshold_visits' => $maxVisits, 'pages' => $out, 'count' => count($out)];
    }

    private static function crawlFrequency(array $args): array {
        $days = isset($args['days']) ? max(1, min(365, (int)$args['days'])) : 30;
        $model = new Analytics();
        $rows = $model->getCrawlFrequency($days);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'slug' => $r['page_slug'],
                'bot_type' => $r['bot_type'],
                'days_with_visits' => (int)$r['days_with_visits'],
                'total_visits' => (int)$r['total_visits'],
                'last_visit' => $r['last_visit'] ?? '',
            ];
        }
        return ['days' => $days, 'pages' => $out, 'count' => count($out)];
    }

    private static function internalLinks(array $args): array {
        $slug = trim((string)($args['slug'] ?? ''));
        $months = isset($args['months']) ? max(1, min(24, (int)$args['months'])) : 3;
        $model = new Analytics();
        if ($slug !== '') {
            $inbound = $model->getInboundLinks($slug, $months);
            $outbound = $model->getOutboundLinks($slug, $months);
            $map = fn($rows) => array_map(fn($r) => [
                'slug' => $r['from_slug'] ?? $r['to_slug'],
                'language' => $r['language'] ?? 'ru',
                'clicks' => (int)$r['clicks'],
            ], $rows);
            return [
                'slug' => $slug,
                'months' => $months,
                'inbound' => $map($inbound),
                'outbound' => $map($outbound),
            ];
        }
        $rows = $model->getLinkEffectiveness($months);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'from_slug' => $r['from_slug'],
                'to_slug' => $r['to_slug'],
                'link_clicks' => (int)$r['link_clicks'],
                'from_page_visits' => (int)$r['from_page_visits'],
                'ctr_percent' => (float)$r['click_through_rate'],
            ];
        }
        return ['months' => $months, 'links' => $out, 'count' => count($out)];
    }

    private static function rotationEffectiveness(array $args): array {
        $months = isset($args['months']) ? max(1, min(24, (int)$args['months'])) : 6;
        $model = new Analytics();
        $rows = $model->getRotationEffectiveness($months);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'slug' => $r['page_slug'],
                'year' => (int)$r['year'],
                'rotation_month' => (int)$r['rotation_month'],
                'times_shown' => (int)$r['times_shown'],
                'unique_days' => (int)$r['unique_days'],
                'visits' => (int)$r['total_visits'],
                'clicks' => (int)$r['total_clicks'],
                'phone_calls' => (int)$r['total_phone_calls'],
            ];
        }
        return ['months' => $months, 'pages' => $out, 'count' => count($out)];
    }
}
