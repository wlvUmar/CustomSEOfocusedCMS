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
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Pages sitemap
        echo '  <sitemap>' . "\n";
        echo '    <loc>' . $baseUrl . '/sitemap-pages.xml</loc>' . "\n";
        echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
        echo '  </sitemap>' . "\n";
        
        // Articles sitemap
        echo '  <sitemap>' . "\n";
        echo '    <loc>' . $baseUrl . '/sitemap-articles.xml</loc>' . "\n";
        echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
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
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" 
                      xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        
        foreach ($pages as $page) {
            $slug = $page['slug'];
            $updated = $page['updated_at'];
            
            $priority = $slug === 'home' ? '1.0' : '0.8';
            $changefreq = $page['enable_rotation'] ? 'monthly' : 'yearly';
            
            echo '  <url>' . "\n";
            echo '    <loc>' . $baseUrl . '/' . htmlspecialchars($slug) . '</loc>' . "\n";
            echo '    <lastmod>' . date('Y-m-d', strtotime($updated)) . '</lastmod>' . "\n";
            echo '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
            echo '    <priority>' . $priority . '</priority>' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="ru" href="' . $baseUrl . '/' . htmlspecialchars($slug) . '" />' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="uz" href="' . $baseUrl . '/' . htmlspecialchars($slug) . '/uz" />' . "\n";
            echo '  </url>' . "\n";
            
            echo '  <url>' . "\n";
            echo '    <loc>' . $baseUrl . '/' . htmlspecialchars($slug) . '/uz</loc>' . "\n";
            echo '    <lastmod>' . date('Y-m-d', strtotime($updated)) . '</lastmod>' . "\n";
            echo '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
            echo '    <priority>' . $priority . '</priority>' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="ru" href="' . $baseUrl . '/' . htmlspecialchars($slug) . '" />' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="uz" href="' . $baseUrl . '/' . htmlspecialchars($slug) . '/uz" />' . "\n";
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
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" 
                      xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        
        foreach ($articles as $article) {
            $id = $article['id'];
            $updated = $article['updated_at'];
            
            // Articles have medium priority and monthly changefreq
            $priority = '0.7';
            $changefreq = 'monthly';
            
            // Russian version
            echo '  <url>' . "\n";
            echo '    <loc>' . $baseUrl . '/articles/' . $id . '</loc>' . "\n";
            echo '    <lastmod>' . date('Y-m-d', strtotime($updated)) . '</lastmod>' . "\n";
            echo '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
            echo '    <priority>' . $priority . '</priority>' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="ru" href="' . $baseUrl . '/articles/' . $id . '" />' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="uz" href="' . $baseUrl . '/articles/' . $id . '/uz" />' . "\n";
            echo '  </url>' . "\n";
            
            // Uzbek version
            echo '  <url>' . "\n";
            echo '    <loc>' . $baseUrl . '/articles/' . $id . '/uz</loc>' . "\n";
            echo '    <lastmod>' . date('Y-m-d', strtotime($updated)) . '</lastmod>' . "\n";
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
