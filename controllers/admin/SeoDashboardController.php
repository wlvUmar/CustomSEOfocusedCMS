<?php
// path: ./controllers/admin/SeoDashboardController.php
// Admin "SEO Dashboard" (/admin/search-console): the same read-only reports the SEO API serves
// (models/seo/GscReports.php, PageAudit.php), behind the admin session instead of a Bearer token.
// The page shell renders instantly; every section is loaded by JS from data() so one slow/failed
// Google call never blocks the rest. GET only, read-only.

require_once BASE_PATH . '/models/GscClient.php';
require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/seo/GscReports.php';
require_once BASE_PATH . '/models/seo/PageAudit.php';

class SeoDashboardController extends Controller {
    private const GSC_SECTIONS = ['overview', 'brand', 'opportunities', 'movers', 'sitemaps', 'inspect'];

    public function index() {
        $this->requireAuth();
        try {
            $gsc = GscClient::getStatus();
        } catch (Throwable $e) {
            error_log('[SeoDashboard] GSC status unavailable: ' . $e->getMessage());
            $gsc = ['configured' => false, 'connected' => false];
        }
        $this->view('admin/seo-dashboard/index', [
            'pageName' => 'seo-dashboard',
            'gsc' => $gsc,
        ]);
    }

    // GET /admin/search-console/data?section=overview|brand|opportunities|movers|sitemaps|inspect|audit&days=28&...
    public function data() {
        $this->requireAuth();
        // Read-only endpoint: release the session lock so the page's parallel section requests don't queue behind each other.
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        header('Cache-Control: no-store');
        $section = (string)($_GET['section'] ?? '');
        $days = (int)($_GET['days'] ?? 28);
        if (!in_array($days, [7, 28, 90], true)) $days = 28;

        if ($section === 'audit') {
            $this->json(['success' => true, 'data' => $this->audit()]);
        }
        if (!in_array($section, self::GSC_SECTIONS, true)) {
            $this->json(['success' => false, 'error' => 'unknown_section'], 400);
        }

        try {
            if (!GscClient::isConnected()) {
                $this->json(['success' => false, 'error' => 'gsc_not_connected'], 503);
            }
        } catch (Throwable $e) {
            error_log('[SeoDashboard] GSC token store unavailable: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'gsc_unavailable'], 503);
        }

        try {
            switch ($section) {
                case 'overview':
                    $data = GscReports::overview(['days' => $days]);
                    break;
                case 'brand':
                    $data = GscReports::brand(['q' => (string)($_GET['q'] ?? ''), 'days' => max($days, 28)]);
                    break;
                case 'opportunities':
                    $data = GscReports::opportunities(['days' => $days, 'limit' => 15, 'min_impressions' => max(10, (int)($_GET['min_impressions'] ?? 50))]);
                    break;
                case 'movers':
                    $data = GscReports::compare(['dimension' => 'page', 'days' => $days, 'limit' => 40, 'min_impressions' => 20]);
                    break;
                case 'sitemaps':
                    $data = GscReports::sitemaps();
                    break;
                case 'inspect':
                    $data = GscReports::inspect((string)($_GET['url'] ?? ''));
                    break;
            }
        } catch (InvalidArgumentException $e) {
            $this->json(['success' => false, 'error' => 'invalid_param', 'param' => $e->getMessage()], 400);
        } catch (GscAuthRequired $e) {
            $this->json(['success' => false, 'error' => 'gsc_auth_required'], 503);
        } catch (Throwable $e) {
            error_log('[SeoDashboard] ' . $section . ': ' . $e->getMessage());
            $code = (int)$e->getCode();
            if ($code === 429) $this->json(['success' => false, 'error' => 'gsc_quota_exceeded'], 429);
            if ($code === 403) $this->json(['success' => false, 'error' => 'gsc_forbidden'], 403);
            $this->json(['success' => false, 'error' => 'gsc_api_error'], 502);
        }

        $this->json(['success' => true, 'data' => $data, 'generated_at' => gmdate('c')]);
    }

    private function audit(): array {
        $severity = (string)($_GET['severity'] ?? 'warn');
        if (!in_array($severity, ['info', 'warn', 'error'], true)) $severity = 'warn';
        $host = strtolower((string)parse_url((string)BASE_URL, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?: 'kuplyu-tashkent.uz';

        $result = PageAudit::run((new Page())->getAll(false), $host, [
            'lang' => 'both',
            'min_severity' => $severity,
            'only_issues' => true,
        ]);
        $result['pages'] = array_slice($result['pages'], 0, 100);
        $result['generated_at'] = gmdate('c');
        return $result;
    }
}
