<?php
// path: ./controllers/SitemapController.php

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/Article.php';

class SitemapController extends Controller {
    private $pageModel;
    private $articleModel;

    public function __construct() {
        parent::__construct();
        $this->pageModel = new Page();
        $this->articleModel = new Article();
    }

    /**
     * Generate sitemap index (links to pages and articles sitemaps)
     */
    public function generateSitemapIndex() {
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        header('Pragma: public');
        
        $baseUrl = $this->getAbsoluteBaseUrl();
        $pageTemplateTs = $this->getPageTemplateTimestamp();
        $articleTemplateTs = $this->getArticleTemplateTimestamp();
        $pages = $this->pageModel->getAll(false); 
        $articles = $this->articleModel->getAll(true);
        $pagesLastmodTs = $this->getMaxEntityLastmod($pages, $pageTemplateTs);
        $articlesLastmodTs = $this->getMaxEntityLastmod($articles, $articleTemplateTs);
        $indexLastmodTs = max($pagesLastmodTs, $articlesLastmodTs, time());
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Pages sitemap
        echo '  <sitemap>' . "\n";
        echo '    <loc>' . $baseUrl . '/sitemap-pages.xml</loc>' . "\n";
        echo '    <lastmod>' . date('Y-m-d', $pagesLastmodTs ?: $indexLastmodTs) . '</lastmod>' . "\n";
        echo '  </sitemap>' . "\n";
        
        // Articles sitemap
        echo '  <sitemap>' . "\n";
        echo '    <loc>' . $baseUrl . '/sitemap-articles.xml</loc>' . "\n";
        echo '    <lastmod>' . date('Y-m-d', $articlesLastmodTs ?: $indexLastmodTs) . '</lastmod>' . "\n";
        echo '  </sitemap>' . "\n";
        
        echo '</sitemapindex>';
        exit;
    }

    /**
     * Generate pages sitemap (existing logic)
     */
    public function generatePagesSitemap() {
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        header('Pragma: public');
        
        $baseUrl = $this->getAbsoluteBaseUrl();
        $pages = $this->pageModel->getAll(false);
        $templateLastmodTs = $this->getPageTemplateTimestamp();
        // Fetch max rotation updated_at per page to reflect rotation edits in lastmod (fixes stale sitemap after rotation-only updates)
        $rotationMax = [];
        try {
            $rows = $this->db->fetchAll("SELECT page_id, MAX(updated_at) as max_rot FROM content_rotations GROUP BY page_id");
            foreach ($rows as $r) $rotationMax[(int)$r['page_id']] = strtotime($r['max_rot']);
        } catch (Throwable $e) {}
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" 
                      xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        // Prevent accidental duplicates (e.g. if both "home" and "main" exist).
        $seen = [];
        
        foreach ($pages as $page) {
            $slug = $page['slug'];
            $updated = $page['updated_at'];
            $rotTs = $rotationMax[(int)($page['id'] ?? 0)] ?? 0;
            $pageTs = $updated ? strtotime($updated) : 0;
            $effectivePageTs = max($pageTs, $rotTs);
            $effectiveUpdated = $effectivePageTs ? date('Y-m-d H:i:s', $effectivePageTs) : $updated;
            $lastmodTs = $this->mergeLastmodTimestamps($effectiveUpdated, $templateLastmodTs);
            
            $isHome = in_array($slug, ['home', 'main'], true);
            $priority = $isHome ? '1.0' : '0.8';
            $changefreq = $page['enable_rotation'] ? 'monthly' : 'yearly';

            // Canonical URL paths: homepage should be "/" (not "/main" or "/home").
            $ruPath = $isHome ? '' : htmlspecialchars($slug);
            $uzPath = $isHome ? 'uz' : (htmlspecialchars($slug) . '/uz');

            $ruLoc = $baseUrl . '/' . $ruPath;
            $uzLoc = $baseUrl . '/' . $uzPath;

            // Normalize double slashes (home path).
            $ruLoc = rtrim($ruLoc, '/');
            $ruLoc = $ruLoc === rtrim($baseUrl, '/') ? ($baseUrl . '/') : ($ruLoc);
            $uzLoc = rtrim($uzLoc, '/');

            if (isset($seen[$ruLoc])) {
                continue;
            }
            $seen[$ruLoc] = true;

            echo '  <url>' . "\n";
            echo '    <loc>' . $ruLoc . '</loc>' . "\n";
            echo '    <lastmod>' . date('Y-m-d', $lastmodTs) . '</lastmod>' . "\n";
            echo '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
            echo '    <priority>' . $priority . '</priority>' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="ru" href="' . $ruLoc . '" />' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="uz" href="' . $uzLoc . '" />' . "\n";
            echo '  </url>' . "\n";
            
            echo '  <url>' . "\n";
            echo '    <loc>' . $uzLoc . '</loc>' . "\n";
            echo '    <lastmod>' . date('Y-m-d', $lastmodTs) . '</lastmod>' . "\n";
            echo '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
            echo '    <priority>' . $priority . '</priority>' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="ru" href="' . $ruLoc . '" />' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="uz" href="' . $uzLoc . '" />' . "\n";
            echo '  </url>' . "\n";
        }
        
        echo '</urlset>';
        exit;
    }

    /**
     * Generate articles sitemap
     */
    public function generateArticlesSitemap() {
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        header('Pragma: public');
        
        $baseUrl = $this->getAbsoluteBaseUrl();
        $articles = $this->articleModel->getAll(true); // Published only
        $templateLastmodTs = $this->getArticleTemplateTimestamp();
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" 
                      xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        
        foreach ($articles as $article) {
            $id = $article['id'];
            $updated = $article['updated_at'];
            $lastmodTs = $this->mergeLastmodTimestamps($updated, $templateLastmodTs);
            
            // Articles have medium priority and monthly changefreq
            $priority = '0.7';
            $changefreq = 'monthly';
            
            // Russian version
            echo '  <url>' . "\n";
            echo '    <loc>' . $baseUrl . '/articles/' . $id . '</loc>' . "\n";
            echo '    <lastmod>' . date('Y-m-d', $lastmodTs) . '</lastmod>' . "\n";
            echo '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
            echo '    <priority>' . $priority . '</priority>' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="ru" href="' . $baseUrl . '/articles/' . $id . '" />' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="uz" href="' . $baseUrl . '/articles/' . $id . '/uz" />' . "\n";
            echo '  </url>' . "\n";
            
            // Uzbek version
            echo '  <url>' . "\n";
            echo '    <loc>' . $baseUrl . '/articles/' . $id . '/uz</loc>' . "\n";
            echo '    <lastmod>' . date('Y-m-d', $lastmodTs) . '</lastmod>' . "\n";
            echo '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
            echo '    <priority>' . $priority . '</priority>' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="ru" href="' . $baseUrl . '/articles/' . $id . '" />' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="uz" href="' . $baseUrl . '/articles/' . $id . '/uz" />' . "\n";
            echo '  </url>' . "\n";
        }
        
        echo '</urlset>';
        exit;
    }

    /**
     * Get absolute base URL for sitemap - fallback to server values if BASE_URL is relative
     */
    private function getAbsoluteBaseUrl() {
        return siteBaseUrl();
    }

    private function mergeLastmodTimestamps($updatedAt, $templateTs) {
        $updatedTs = $updatedAt ? strtotime($updatedAt) : 0;
        $updatedTs = $updatedTs ?: 0;
        $templateTs = $templateTs ?: 0;
        $merged = max($updatedTs, $templateTs);
        return $merged > 0 ? $merged : time();
    }

    private function getMaxEntityLastmod($items, $templateTs) {
        $max = $templateTs ?: 0;
        // Include rotation max for pages that have rotations
        $rotMaxByPage = [];
        try {
            $rows = $this->db->fetchAll("SELECT page_id, MAX(updated_at) as max_rot FROM content_rotations GROUP BY page_id");
            foreach ($rows as $r) $rotMaxByPage[(int)$r['page_id']] = strtotime($r['max_rot']);
        } catch (Throwable $e) {}
        foreach ($items as $item) {
            $updated = $item['updated_at'] ?? null;
            $ts = $updated ? strtotime($updated) : 0;
            if (isset($item['id']) && isset($rotMaxByPage[(int)$item['id']])) {
                $ts = max($ts, $rotMaxByPage[(int)$item['id']]);
            }
            if ($ts && $ts > $max) {
                $max = $ts;
            }
        }
        return $max > 0 ? $max : time();
    }

    private function getPageTemplateTimestamp() {
        return $this->getLastModifiedTimestamp([
            '/views/templates/header.php',
            '/views/templates/footer.php',
            '/views/templates/page.php',
            '/core/helpers.php',
            '/models/GlobalJsonLdGenerator.php',
            '/models/JsonLdGenerator.php',
            '/controllers/PageController.php'
        ]);
    }

    private function getArticleTemplateTimestamp() {
        return $this->getLastModifiedTimestamp([
            '/views/templates/header.php',
            '/views/templates/footer.php',
            '/views/templates/article.php',
            '/core/helpers.php',
            '/models/GlobalJsonLdGenerator.php',
            '/models/ArticleJsonLdGenerator.php',
            '/models/JsonLdGenerator.php',
            '/controllers/ArticleController.php'
        ]);
    }

    private function getLastModifiedTimestamp($paths) {
        $max = 0;
        foreach ($paths as $path) {
            $full = BASE_PATH . '/' . ltrim($path, '/');
            if (file_exists($full)) {
                $mtime = filemtime($full);
                if ($mtime && $mtime > $max) {
                    $max = $mtime;
                }
            }
        }
        return $max > 0 ? $max : time();
    }


    public function generateRobotsTxt() {
        // Clear any output buffering to ensure headers can be sent
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        header('Pragma: public');
        
        $isProduction = IS_PRODUCTION;
        
        if ($isProduction) {
            echo "User-agent: *\n";
            echo "Allow: /\n";
            echo "\n";
            
            echo "Disallow: /admin/\n";
            echo "Disallow: /config/\n";
            echo "Disallow: /logs/\n";
            echo "Disallow: /database/\n";
            echo "\n";
            
            echo "Sitemap: " . siteUrl('sitemap.xml') . "\n";
            
        } else {
            echo "User-agent: *\n";
            echo "Disallow: /\n";
        }
        
        exit;
    }
    public function generateAdsTxt() {
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo "google.com, pub-7628492698305234, DIRECT, f08c47fec0942fa0\n";
        exit;
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
