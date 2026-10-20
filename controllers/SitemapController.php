<?php
// path: ./controllers/SitemapController.php

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/Article.php';
require_once BASE_PATH . '/models/SitemapRenderer.php';

class SitemapController extends Controller {
    private $pageModel;
    private $articleModel;
    private $renderer;

    public function __construct() {
        parent::__construct();
        $this->pageModel = new Page();
        $this->articleModel = new Article();
        $this->renderer = new SitemapRenderer();
    }

    /**
     * Generate sitemap index (links to pages and articles sitemaps)
     */
    public function generateSitemapIndex() {
        $baseUrl = $this->getAbsoluteBaseUrl();
        $pages = $this->pageModel->getAll(false);
        $articles = $this->articleModel->getAll(true);
        // Content-only lastmod (no file mtimes, no time()) so index lastmod is stable across deploys.
        $pagesLastmodTs = $this->getMaxEntityLastmod($pages, true);
        $articlesLastmodTs = $this->getMaxEntityLastmod($articles, false);

        $this->sendXmlResponse(
            $this->renderer->renderSitemapIndex($baseUrl, $pagesLastmodTs, $articlesLastmodTs)
        );
    }

    /**
     * Generate pages sitemap (existing logic)
     */
    public function generatePagesSitemap() {
        $baseUrl = $this->getAbsoluteBaseUrl();
        $pages = $this->pageModel->getAll(false);
        // Rotation max per page (enable_rotation=1 only) feeds content lastmod — cached per-request.
        $rotationMax = $this->getRotationMaxMap();

        $this->sendXmlResponse(
            $this->renderer->renderPagesSitemap($baseUrl, $pages, $rotationMax)
        );
    }

    /**
     * Generate articles sitemap
     */
    public function generateArticlesSitemap() {
        $baseUrl = $this->getAbsoluteBaseUrl();
        $articles = $this->articleModel->getAll(true); // Published only

        $this->sendXmlResponse(
            $this->renderer->renderArticlesSitemap($baseUrl, $articles)
        );
    }

    /**
     * Get absolute base URL for sitemap - fallback to server values if BASE_URL is relative
     */
    private function getAbsoluteBaseUrl() {
        return siteBaseUrl();
    }

    private function getRotationMaxMap(): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = [];
        try {
            // Only rotations for pages where enable_rotation=1 count toward lastmod (remaining-bugs #3)
            $rows = $this->db->fetchAll("SELECT cr.page_id, MAX(cr.updated_at) as max_rot FROM content_rotations cr JOIN pages p ON p.id = cr.page_id WHERE p.enable_rotation = 1 GROUP BY cr.page_id");
            foreach ($rows as $r) $cache[(int)$r['page_id']] = strtotime($r['max_rot']);
        } catch (Throwable $e) {}
        return $cache;
    }

    /**
     * Max content timestamp across items (updated_at, plus rotation max for pages).
     * Returns 0 when no timestamp is known; the renderer then omits <lastmod>.
     * Never uses file mtimes or time(): those churn on every deploy.
     */
    private function getMaxEntityLastmod(array $items, bool $applyRotation): int {
        $max = 0;
        $rotMaxByPage = $applyRotation ? $this->getRotationMaxMap() : [];
        foreach ($items as $item) {
            $updated = $item['updated_at'] ?? null;
            $ts = $updated ? (int)strtotime((string)$updated) : 0;
            if ($applyRotation && isset($item['id']) && isset($rotMaxByPage[(int)$item['id']])) {
                $ts = max($ts, (int)$rotMaxByPage[(int)$item['id']]);
            }
            if ($ts > $max) {
                $max = $ts;
            }
        }
        return $max;
    }

    private function sendXmlResponse(string $body): void {
        while (ob_get_level()) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header_remove('Cache-Control');
            header_remove('Pragma');
            header('Content-Type: application/xml; charset=utf-8', true);
            header('Cache-Control: public, max-age=3600', true);
        }
        echo $body;
        exit;
    }

    private function sendTextResponse(string $body): void {
        while (ob_get_level()) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header_remove('Cache-Control');
            header_remove('Pragma');
            header('Content-Type: text/plain; charset=utf-8', true);
            header('Cache-Control: public, max-age=3600', true);
        }
        echo $body;
        exit;
    }


    public function generateRobotsTxt() {
        $this->sendTextResponse(
            $this->renderer->renderRobotsTxt(IS_PRODUCTION, siteUrl('sitemap.xml'))
        );
    }
    public function generateAdsTxt() {
        $this->sendTextResponse($this->renderer->renderAdsTxt());
    }

    public function adminPanel() {
        $this->requireAuth();
        
        $pages = $this->pageModel->getAll(false);
        $articles = $this->articleModel->getAll(false);
        $totalPageUrls = count($pages) * 2;
        $totalArticleUrls = count($articles) * 2;
        
        $data = [
            'totalPages' => count($pages),
            'totalArticles' => count($articles),
            'totalPageUrls' => $totalPageUrls,
            'totalArticleUrls' => $totalArticleUrls,
            'totalUrls' => $totalPageUrls + $totalArticleUrls,
            'sitemapIndexUrl' => BASE_URL . '/sitemap.xml',
            'sitemapPagesUrl' => BASE_URL . '/sitemap-pages.xml',
            'sitemapArticlesUrl' => BASE_URL . '/sitemap-articles.xml',
            'robotsUrl' => BASE_URL . '/robots.txt',
            'pages' => $pages,
            'articles' => $articles,
            'isProduction' => IS_PRODUCTION,
            'pageName' => 'seo/sitemap'
        ];
        
        $this->view('admin/seo/sitemap', $data);
    }

    /**
     * Ping search engines about sitemap update
     */

}
