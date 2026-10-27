<?php
// path: ./controllers/Api/SeoApiController.php
// Read-only Bearer API for the internal SEO operator (Hermes). Spec: docs/seo-operator-setup.md §1.
// Auth: "Authorization: Bearer <token>", HTTPS only, per-token scope, 120 req/min per token.
// Routes: config/routes/api.php (/api/seo/v1/*). The legacy HMAC bot API is untouched.

require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/models/SeoApiTokenStore.php';
require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/Analytics.php';
require_once BASE_PATH . '/models/GscClient.php';
require_once BASE_PATH . '/models/seo/GscReports.php';
require_once BASE_PATH . '/models/seo/PageAudit.php';

class SeoApiController extends Controller {
    private const RATE_LIMIT_PER_MIN = 120;
    private const GSC_LAG_DAYS = 3; // GSC data is finalized ~3 days late

    private ?array $token = null;
    private float $startedAt;

    public function __construct() {
        parent::__construct();
        $this->startedAt = microtime(true);
    }

    // GET /api/seo/v1/pages?limit=100
    public function pages(): void {
        $this->authenticate('pages');
        $limit = $this->intParam('limit', 100, 1, 500);
        $rows = (new Page())->getAll(false, $limit);
        $data = array_map([$this, 'pageSummary'], $rows);
        $this->send(['data' => $data, 'count' => count($data), 'generated_at' => gmdate('c')]);
    }

    // GET /api/seo/v1/page/{slug}
    public function page(string $slug): void {
        $this->authenticate('pages');
        $slug = rawurldecode($slug);
        if ($slug === '' || strlen($slug) > 255 || preg_match('/[\/\s]/u', $slug)) {
            $this->send(['error' => 'invalid_slug'], 400);
        }
        $row = (new Page())->getBySlug($slug, false);
        if (!$row) {
            $this->send(['error' => 'not_found'], 404);
        }
        $this->send(['data' => $row, 'generated_at' => gmdate('c')]);
    }

    // GET /api/seo/v1/stats/top-pages?days=28&limit=50
    public function topPages(): void {
        $this->authenticate('stats');
        $days = $this->intParam('days', 28, 1, 365);
        $limit = $this->intParam('limit', 50, 1, 200);
        $end = date('Y-m-d');
        $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $rows = array_slice((new Analytics())->getRangeTopPerformers($start, $end), 0, $limit);
        $data = array_map(static fn($r) => [
            'page_slug' => $r['page_slug'],
            'visits' => (int)$r['visits'],
            'clicks' => (int)$r['clicks'],
            'phone_calls' => (int)$r['phone_calls'],
            'ctr' => $r['ctr'] !== null ? (float)$r['ctr'] : null,
        ], $rows);
        $this->send([
            'period' => ['start' => $start, 'end' => $end, 'days' => $days],
            'source' => 'analytics_hourly',
            'data' => $data,
            'generated_at' => gmdate('c'),
        ]);
    }

    // GET /api/seo/v1/gsc/summary?days=28 — proxies GscClient (gsc_tokens); no token is exposed.
    public function gscSummary(): void {
        $this->authenticate('gsc');
        $days = $this->intParam('days', 28, 1, 90);
        try {
            $connected = GscClient::isConnected();
        } catch (Throwable $e) {
            // gsc_tokens missing or unreadable: report as unavailable, never leak an HTML 500.
            error_log('[SeoApi] GSC token store unavailable: ' . $e->getMessage());
            $this->send(['error' => 'gsc_unavailable'], 503);
        }
        if (!$connected) {
            $this->send(['error' => 'gsc_not_connected'], 503);
        }
        $end = date('Y-m-d', strtotime('-' . self::GSC_LAG_DAYS . ' days'));
        $start = date('Y-m-d', strtotime($end . ' -' . ($days - 1) . ' days'));
        try {
            $totals = GscClient::searchAnalytics($start, $end, [], [], 1);
            if ($totals === null) {
                $this->send(['error' => 'gsc_auth_required'], 503);
            }
            $pages = GscClient::searchAnalytics($start, $end, ['page'], [], 25);
        } catch (Throwable $e) {
            error_log('[SeoApi] GSC error: ' . $e->getMessage());
            $this->send(['error' => 'gsc_api_error'], 502);
        }
        $t = $totals[0] ?? [];
        $topPages = array_map(static fn($r) => [
            'page' => $r['keys'][0] ?? null,
            'clicks' => (int)($r['clicks'] ?? 0),
            'impressions' => (int)($r['impressions'] ?? 0),
            'ctr' => round((float)($r['ctr'] ?? 0), 4),
            'position' => round((float)($r['position'] ?? 0), 2),
        ], $pages ?? []);
        usort($topPages, static fn($a, $b) => $b['clicks'] <=> $a['clicks'] ?: $b['impressions'] <=> $a['impressions']);
        $this->send([
            'site' => GscClient::getSiteUrl(),
            'period' => ['start' => $start, 'end' => $end, 'days' => $days],
            'totals' => [
                'clicks' => (int)($t['clicks'] ?? 0),
                'impressions' => (int)($t['impressions'] ?? 0),
                'ctr' => round((float)($t['ctr'] ?? 0), 4),
                'position' => round((float)($t['position'] ?? 0), 2),
            ],
            'top_pages' => array_slice($topPages, 0, 20),
            'generated_at' => gmdate('c'),
        ]);
    }

    // GET /api/seo/v1/gsc/queries?days=28&limit=50[&page=https://host/slug][&with_pages=1]
    // Query-level Search Console rows (intent signals). Read-only; same gsc scope + token store as gsc/summary.
    public function gscQueries(): void {
        $this->authenticate('gsc');
        $days = $this->intParam('days', 28, 1, 90);
        $limit = $this->intParam('limit', 50, 1, 250);
        $pageUrl = trim((string)($_GET['page'] ?? ''));
        $withPages = ($_GET['with_pages'] ?? '') === '1';
        if ($pageUrl !== '' && !preg_match('#^https://kuplyu-tashkent\.uz(/[^\s]*)?$#i', $pageUrl)) {
            $this->send(['error' => 'invalid_param', 'param' => 'page'], 400);
        }
        try {
            $connected = GscClient::isConnected();
        } catch (Throwable $e) {
            error_log('[SeoApi] GSC token store unavailable: ' . $e->getMessage());
            $this->send(['error' => 'gsc_unavailable'], 503);
        }
        if (!$connected) {
            $this->send(['error' => 'gsc_not_connected'], 503);
        }
        $end = date('Y-m-d', strtotime('-' . self::GSC_LAG_DAYS . ' days'));
        $start = date('Y-m-d', strtotime($end . ' -' . ($days - 1) . ' days'));
        $filters = $pageUrl === '' ? [] : [[
            'filters' => [['dimension' => 'page', 'operator' => 'equals', 'expression' => $pageUrl]],
        ]];
        try {
            $rows = GscClient::searchAnalytics($start, $end, $withPages ? ['query', 'page'] : ['query'], $filters, $limit);
            if ($rows === null) {
                $this->send(['error' => 'gsc_auth_required'], 503);
            }
        } catch (Throwable $e) {
            error_log('[SeoApi] GSC error: ' . $e->getMessage());
            $this->send(['error' => 'gsc_api_error'], 502);
        }
        $data = array_map(static function ($r) use ($withPages) {
            $row = ['query' => $r['keys'][0] ?? null];
            if ($withPages) $row['page'] = $r['keys'][1] ?? null;
            return $row + [
                'clicks' => (int)($r['clicks'] ?? 0),
                'impressions' => (int)($r['impressions'] ?? 0),
                'ctr' => round((float)($r['ctr'] ?? 0), 4),
                'position' => round((float)($r['position'] ?? 0), 2),
            ];
        }, $rows);
        usort($data, static fn($a, $b) => $b['impressions'] <=> $a['impressions']);
        $this->send([
            'site' => GscClient::getSiteUrl(),
            'period' => ['start' => $start, 'end' => $end, 'days' => $days],
            'page_filter' => $pageUrl ?: null,
            'count' => count($data),
            'data' => $data,
            'generated_at' => gmdate('c'),
        ]);
    }

    // POST /api/seo/v1/page/{slug}/fields   (scope: write)
    // Body: {"fields": {"meta_title_ru": "...", "meta_title_uz": "..."}, "expected_updated_at": "<page.updated_at>", "dry_run": true|false}
    // Only SEO metadata is writable (never content/slug/publish/theme/jsonld — those stay in AI Studio / admin where
    // approvals exist). Every write goes through Page::update(): snapshot to page_revisions + audit_log, labelled seo_api.
    private const WRITABLE_PAIRS = [
        'title' => 90, 'meta_title' => 90, 'meta_description' => 300, 'meta_keywords' => 300,
        'og_title' => 90, 'og_description' => 300,
    ];

    public function pageFields(string $slug): void {
        $this->authenticate('write');
        $slug = rawurldecode($slug);
        if ($slug === '' || strlen($slug) > 255 || preg_match('/[\/\s]/u', $slug)) {
            $this->send(['error' => 'invalid_slug'], 400);
        }
        $raw = (string)file_get_contents('php://input', false, null, 0, 65537);
        if (strlen($raw) > 65536) $this->send(['error' => 'payload_too_large'], 413);
        $body = json_decode($raw, true);
        $fields = is_array($body) ? ($body['fields'] ?? null) : null;
        if (!is_array($fields) || !$fields) {
            $this->send(['error' => 'invalid_body', 'hint' => '{"fields":{...},"expected_updated_at":"...","dry_run":false}'], 400);
        }
        $dryRun = ($body['dry_run'] ?? false) === true;

        $pageModel = new Page();
        $page = $pageModel->getBySlug($slug, false);
        if (!$page) $this->send(['error' => 'not_found'], 404);

        $limits = ['canonical_url' => 500];
        foreach (self::WRITABLE_PAIRS as $base => $max) {
            $limits["{$base}_ru"] = $max;
            $limits["{$base}_uz"] = $max;
        }

        $errors = [];
        $data = [];
        foreach ($fields as $name => $value) {
            if (!isset($limits[$name])) { $errors[$name] = 'field_not_writable'; continue; }
            if (!is_string($value)) { $errors[$name] = 'must_be_string'; continue; }
            $value = trim($value);
            if ($value === '') { $errors[$name] = 'empty_not_allowed'; continue; }
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F<>]/u', $value)) { $errors[$name] = 'control_chars_or_html_not_allowed'; continue; }
            if (mb_strlen($value) > $limits[$name]) { $errors[$name] = 'too_long_max_' . $limits[$name]; continue; }
            if (str_contains($name, 'description') && preg_match('/\s[—–-]\s*\.\s*$/u', $value)) {
                $errors[$name] = 'dangling_dash_at_end'; continue;
            }
            if ($name === 'canonical_url' && !preg_match('#^https://kuplyu-tashkent\.uz(/\S*)?$#i', $value)) {
                $errors[$name] = 'canonical_must_be_on_own_domain'; continue;
            }
            $data[$name] = $value;
        }
        // RU↔UZ parity (AGENTS.md): a language pair is always written together.
        foreach (array_keys(self::WRITABLE_PAIRS) as $base) {
            if (isset($fields["{$base}_ru"]) xor isset($fields["{$base}_uz"])) {
                $errors[$base] = 'parity_send_both_ru_and_uz';
            }
        }
        if ($errors) $this->send(['error' => 'validation_failed', 'details' => $errors], 422);

        $changes = [];
        foreach ($data as $name => $new) {
            $old = (string)($page[$name] ?? '');
            if ($old !== $new) $changes[] = ['field' => $name, 'before' => $old, 'after' => $new];
        }
        if (!$changes) {
            $this->send(['ok' => true, 'changed' => [], 'note' => 'no_change', 'updated_at' => $page['updated_at']]);
        }
        if ($dryRun) {
            $this->send(['ok' => true, 'dry_run' => true, 'would_change' => $changes, 'updated_at' => $page['updated_at']]);
        }
        // optimistic concurrency: agent must have read the page and not lost a race with an admin edit
        $expected = (string)($body['expected_updated_at'] ?? '');
        if ($expected === '' || $expected !== (string)$page['updated_at']) {
            $this->send(['error' => 'stale_or_missing_expected_updated_at', 'current_updated_at' => $page['updated_at']], 409);
        }

        require_once BASE_PATH . '/models/PageRevision.php';
        $GLOBALS['WRITE_ACTOR'] = ['source' => 'seo_api', 'name' => 'seo-api#' . ($this->token['id'] ?? '?') . ' ' . ($this->token['label'] ?? '')];
        try {
            $pageModel->update((int)$page['id'], array_column($changes, 'after', 'field'));
        } catch (Throwable $e) {
            error_log('[SeoApi] write failed: ' . $e->getMessage());
            $this->send(['error' => 'write_failed'], 500);
        }
        $fresh = $pageModel->getById((int)$page['id']);
        $rev = (new PageRevision())->getByPageId((int)$page['id'], 1);
        $this->send([
            'ok' => true,
            'slug' => $slug,
            'changed' => $changes,
            'revision_id' => $rev[0]['id'] ?? null, // snapshot of the state BEFORE this write; restorable in admin
            'updated_at' => $fresh['updated_at'] ?? null,
            'generated_at' => gmdate('c'),
        ]);
    }

    // ── extended read-only SEO endpoints ─────────────────────
    // Logic lives in models/seo/GscReports.php and PageAudit.php; this class only authenticates, validates
    // request shape and maps errors. All of them are GET and read-only.

    // GET /api/seo/v1/help — endpoint catalogue for agents (any valid token, no specific scope)
    public function help(): void {
        $this->authenticate('');
        $period = 'start=YYYY-MM-DD&end=YYYY-MM-DD, or days=N (default 28, ends 3 days ago; max 480)';
        $this->send([
            'version' => 'v1',
            'auth' => 'Authorization: Bearer <token>; HTTPS only; 120 req/min per token. Scope per endpoint in brackets.',
            'conventions' => [
                'errors' => '{"error": "<code>", ...} with 400 invalid_param(param) | 401 | 403 insufficient_scope | 429 | 502/503 gsc_*',
                'gsc_data' => 'Search Console data lags ~3 days and is cached for 1h. Numbers are Google\'s, never estimated.',
                'position' => 'average position — lower is better; deltas < 0 mean improved',
            ],
            'endpoints' => [
                ['endpoint' => 'GET /api/seo/v1/gsc/query', 'scope' => 'gsc', 'desc' => 'Generic Search Analytics query (any dimension mix, filters, paging, sort)',
                    'params' => ['dimensions' => 'comma list of query,page,country,device,date,searchAppearance (max 4; default query)', 'period' => $period,
                        'filters' => 'query_contains|query_equals|query_not_contains|query_regex|page_contains|page_equals|page_not_contains|country (ISO alpha-3, e.g. uzb)|device (DESKTOP|MOBILE|TABLET), or filters=[{"dimension","operator","expression"}]',
                        'paging/sort' => 'limit<=5000, start_row, sort=clicks|impressions|ctr|position, order, min_impressions, min_clicks, search_type=web|image|video|news|discover|googleNews']],
                ['endpoint' => 'GET /api/seo/v1/gsc/compare', 'scope' => 'gsc', 'desc' => 'This period vs the previous equal period per page or query (traffic decay / gains)',
                    'params' => ['dimension' => 'page|query (default page)', 'days' => '1-240 (default 28)', 'direction' => 'any|up|down', 'min_impressions' => 'default 20', 'limit' => '<=500', 'filters' => 'same shortcuts as gsc/query']],
                ['endpoint' => 'GET /api/seo/v1/gsc/opportunities', 'scope' => 'gsc', 'desc' => 'Striking-distance (pos 5-20), low-CTR (vs the site\'s own CTR per position) and zero-click queries with their pages',
                    'params' => ['period' => $period . ' (max 120 days)', 'min_impressions' => 'default 50', 'limit' => '<=100', 'filters' => 'same shortcuts']],
                ['endpoint' => 'GET /api/seo/v1/gsc/overview', 'scope' => 'gsc', 'desc' => 'Daily clicks/impressions series + totals vs the previous equal period', 'params' => ['days' => '1-240 (default 28)']],
                ['endpoint' => 'GET /api/seo/v1/gsc/brand', 'scope' => 'gsc', 'desc' => 'Brand-query report: every query containing the term, impression-weighted position, landing pages, homepage share', 'params' => ['q' => 'brand term (required)', 'days' => '1-240 (default 90)']],
                ['endpoint' => 'GET /api/seo/v1/gsc/inspect', 'scope' => 'gsc', 'desc' => 'URL Inspection: indexed?, Google canonical vs declared, last crawl, mobile usability, rich results', 'params' => ['url' => 'https://kuplyu-tashkent.uz/... (own domain only)']],
                ['endpoint' => 'GET /api/seo/v1/gsc/sitemaps', 'scope' => 'gsc', 'desc' => 'Sitemaps Search Console knows: submitted/indexed counts, errors, warnings, last download', 'params' => []],
                ['endpoint' => 'GET /api/seo/v1/gsc/summary', 'scope' => 'gsc', 'desc' => 'Site totals + top 20 pages', 'params' => ['days' => '1-90']],
                ['endpoint' => 'GET /api/seo/v1/gsc/queries', 'scope' => 'gsc', 'desc' => 'Top queries (older, simpler form of gsc/query)', 'params' => ['days', 'limit<=250', 'page', 'with_pages=1']],
                ['endpoint' => 'GET /api/seo/v1/audit/pages', 'scope' => 'pages', 'desc' => 'On-page audit of published pages: RU/UZ parity, meta lengths, duplicates, thin content, H1/alt/inline-style, off-domain canonical, orphan-ish pages',
                    'params' => ['lang' => 'ru|uz|both', 'severity' => 'info|warn|error (minimum)', 'only_issues' => '1|0 (default 1)', 'slug' => 'one page', 'limit' => '<=500', 'offset' => '']],
                ['endpoint' => 'GET /api/seo/v1/stats/cta', 'scope' => 'stats', 'desc' => 'Per-button views/clicks/CTR (first-party analytics, unique visitors per day)', 'params' => ['days' => '1-365', 'slug' => 'optional page']],
                ['endpoint' => 'GET /api/seo/v1/stats/top-pages', 'scope' => 'stats', 'desc' => 'First-party visits/clicks/calls per page', 'params' => ['days', 'limit']],
                ['endpoint' => 'GET /api/seo/v1/pages', 'scope' => 'pages', 'desc' => 'Published pages with SEO fields', 'params' => ['limit<=500']],
                ['endpoint' => 'GET /api/seo/v1/page/{slug}', 'scope' => 'pages', 'desc' => 'One page, all fields', 'params' => []],
                ['endpoint' => 'POST /api/seo/v1/page/{slug}/fields', 'scope' => 'write', 'desc' => 'Write SEO metadata (RU+UZ together, dry_run, optimistic lock) — see docs/seo-operator-setup.md', 'params' => []],
            ],
            'generated_at' => gmdate('c'),
        ]);
    }

    // GET /api/seo/v1/gsc/query
    public function gscQuery(): void {
        $this->gscRun(static fn() => GscReports::query($_GET));
    }

    // GET /api/seo/v1/gsc/compare
    public function gscCompare(): void {
        $this->gscRun(static fn() => GscReports::compare($_GET));
    }

    // GET /api/seo/v1/gsc/opportunities
    public function gscOpportunities(): void {
        $this->gscRun(static fn() => GscReports::opportunities($_GET));
    }

    // GET /api/seo/v1/gsc/overview?days=28
    public function gscOverview(): void {
        $this->gscRun(static fn() => GscReports::overview($_GET));
    }

    // GET /api/seo/v1/gsc/brand?q=<brand term>&days=90
    public function gscBrand(): void {
        $this->gscRun(static fn() => GscReports::brand($_GET));
    }

    // GET /api/seo/v1/gsc/inspect?url=https://kuplyu-tashkent.uz/...
    public function gscInspect(): void {
        $this->gscRun(static fn() => GscReports::inspect((string)($_GET['url'] ?? '')));
    }

    // GET /api/seo/v1/gsc/sitemaps
    public function gscSitemaps(): void {
        $this->gscRun(static fn() => GscReports::sitemaps());
    }

    // GET /api/seo/v1/audit/pages
    public function auditPages(): void {
        $this->authenticate('pages');
        $lang = (string)($_GET['lang'] ?? 'both');
        if (!in_array($lang, ['ru', 'uz', 'both'], true)) $this->send(['error' => 'invalid_param', 'param' => 'lang'], 400);
        $severity = (string)($_GET['severity'] ?? 'info');
        if (!in_array($severity, ['info', 'warn', 'error'], true)) $this->send(['error' => 'invalid_param', 'param' => 'severity'], 400);
        $slug = trim((string)($_GET['slug'] ?? ''));
        if ($slug !== '' && (strlen($slug) > 255 || preg_match('/[\/\s]/u', $slug))) $this->send(['error' => 'invalid_param', 'param' => 'slug'], 400);
        $limit = $this->intParam('limit', 200, 1, 500);
        $offset = $this->intParam('offset', 0, 0, 100000);
        $host = strtolower((string)parse_url((string)BASE_URL, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?: 'kuplyu-tashkent.uz';

        $result = PageAudit::run((new Page())->getAll(false), $host, [
            'lang' => $lang,
            'min_severity' => $severity,
            'only_issues' => ($_GET['only_issues'] ?? '1') !== '0',
            'slug' => $slug,
        ]);
        $total = count($result['pages']);
        $result['pages'] = array_slice($result['pages'], $offset, $limit);
        $result['paging'] = ['total_pages_returned' => $total, 'offset' => $offset, 'limit' => $limit, 'has_more' => $offset + $limit < $total];
        $result['source'] = 'pages table (published)';
        $result['generated_at'] = gmdate('c');
        $this->send($result);
    }

    // GET /api/seo/v1/stats/cta?days=28&slug=
    public function ctaStats(): void {
        $this->authenticate('stats');
        $days = $this->intParam('days', 28, 1, 365);
        $slug = trim((string)($_GET['slug'] ?? ''));
        if ($slug !== '' && !preg_match('/^[a-z0-9_\-]{1,100}$/i', $slug)) $this->send(['error' => 'invalid_param', 'param' => 'slug'], 400);
        $end = date('Y-m-d');
        $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $stats = (new Analytics())->getCtaStats($start, $end, $slug, '');
        $this->send([
            'period' => ['start' => $start, 'end' => $end, 'days' => $days],
            'slug' => $slug ?: null,
            'source' => 'analytics_cta (unique visitors per page/day; view = half of the button visible)',
            'collected_since' => $stats['since'],
            'data' => $stats['rows'],
            'generated_at' => gmdate('c'),
        ]);
    }

    /** Auth + "GSC connected" preflight, run the report, map its exceptions to HTTP errors. Exits via send(). */
    private function gscRun(callable $report): void {
        $this->authenticate('gsc');
        try {
            $connected = GscClient::isConnected();
        } catch (Throwable $e) {
            error_log('[SeoApi] GSC token store unavailable: ' . $e->getMessage());
            $this->send(['error' => 'gsc_unavailable'], 503);
        }
        if (!$connected) {
            $this->send(['error' => 'gsc_not_connected'], 503);
        }
        try {
            $result = $report();
        } catch (InvalidArgumentException $e) {
            $this->send(['error' => 'invalid_param', 'param' => $e->getMessage()], 400);
        } catch (GscAuthRequired $e) {
            $this->send(['error' => 'gsc_auth_required'], 503);
        } catch (Throwable $e) {
            error_log('[SeoApi] GSC error: ' . $e->getMessage());
            $code = (int)$e->getCode();
            if ($code === 429) {
                header('Retry-After: 60');
                $this->send(['error' => 'gsc_quota_exceeded'], 429);
            }
            if ($code === 403) {
                $this->send(['error' => 'gsc_forbidden', 'hint' => 'the connected Google account lacks access or scope for this call'], 403);
            }
            $this->send(['error' => 'gsc_api_error'], 502);
        }
        $result['generated_at'] = gmdate('c');
        $this->send($result);
    }

    // ── internals ────────────────────────────────────────────

    private function authenticate(string $scope): void {
        header('Cache-Control: no-store');
        if (!$this->isHttps()) {
            $this->send(['error' => 'https_required'], 403);
        }
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(\S+)$/', trim($auth), $m)) {
            header('WWW-Authenticate: Bearer realm="seo-api"');
            $this->send(['error' => 'unauthorized'], 401);
        }
        $row = SeoApiTokenStore::findActiveByToken($m[1]);
        if (!$row) {
            header('WWW-Authenticate: Bearer realm="seo-api", error="invalid_token"');
            $this->send(['error' => 'unauthorized'], 401);
        }
        $this->token = $row;
        if ($scope !== '' && !in_array($scope, explode(',', $row['scopes']), true)) { // '' = any valid token (help)
            $this->send(['error' => 'insufficient_scope', 'required' => $scope], 403);
        }
        if (!SeoApiTokenStore::rateLimitOk($row['id'], self::RATE_LIMIT_PER_MIN)) {
            header('Retry-After: 60');
            $this->send(['error' => 'rate_limited'], 429);
        }
        SeoApiTokenStore::touch($row['id']);
    }

    private function isHttps(): bool {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (str_contains($_SERVER['HTTP_CF_VISITOR'] ?? '', '"scheme":"https"'))
            || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on');
    }

    private function intParam(string $name, int $default, int $min, int $max): int {
        if (!isset($_GET[$name]) || $_GET[$name] === '') return $default;
        if (!preg_match('/^-?\d+$/', (string)$_GET[$name])) {
            $this->send(['error' => 'invalid_param', 'param' => $name], 400);
        }
        return max($min, min($max, (int)$_GET[$name]));
    }

    private function pageSummary(array $r): array {
        return [
            'id' => (int)$r['id'],
            'slug' => $r['slug'],
            'parent_id' => $r['parent_id'] !== null ? (int)$r['parent_id'] : null,
            'depth' => (int)($r['depth'] ?? 0),
            'title_ru' => $r['title_ru'],
            'title_uz' => $r['title_uz'],
            'meta_title_ru' => $r['meta_title_ru'],
            'meta_title_uz' => $r['meta_title_uz'],
            'meta_description_ru' => $r['meta_description_ru'],
            'meta_description_uz' => $r['meta_description_uz'],
            'canonical_url' => $r['canonical_url'],
            'is_published' => (bool)$r['is_published'],
            'sort_order' => (int)$r['sort_order'],
            'updated_at' => $r['updated_at'],
        ];
    }

    private function send($data, int $code = 200): void {
        $route = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '-';
        error_log(sprintf('[SeoApi] token=%s %s %s %d %dms',
            $this->token['id'] ?? '-', $_SERVER['REQUEST_METHOD'] ?? '-', $route, $code,
            (int)round((microtime(true) - $this->startedAt) * 1000)));
        $this->json($data, $code); // exits
    }
}
