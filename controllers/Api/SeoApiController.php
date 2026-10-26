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
        if (!in_array($scope, explode(',', $row['scopes']), true)) {
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
