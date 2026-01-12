<?php
// path: ./controllers/SitemapController.php

require_once BASE_PATH . '/models/Page.php';

class SitemapController extends Controller {
    private $pageModel;

    public function __construct() {
        parent::__construct();
        $this->pageModel = new Page();
    }

    public function generateXML() {
        // Clear any output buffering to ensure headers can be sent
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        header('Pragma: public');
        
        // Get absolute base URL - ensure it's a full URL not relative path
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
     * Get absolute base URL for sitemap - fallback to server values if BASE_URL is relative
     */
    private function getAbsoluteBaseUrl() {
        $baseUrl = BASE_URL;
        
        // Check if BASE_URL is already absolute (contains :// for scheme)
        if (strpos($baseUrl, '://') !== false) {
            return rtrim($baseUrl, '/');
        }
        
        // If BASE_URL is relative or empty, derive from server
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        return $protocol . '://' . rtrim($host, '/');
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
            
            echo "Sitemap: " . BASE_URL . "/sitemap.xml\n";
            
        } else {
            echo "User-agent: *\n";
            echo "Disallow: /\n";
        }
        
        exit;
    }

    public function adminPanel() {
        $this->requireAuth();
        
        $pages = $this->pageModel->getAll(false);
        $totalUrls = count($pages) * 2;
        
        $data = [
            'totalPages' => count($pages),
            'totalUrls' => $totalUrls,
            'sitemapUrl' => BASE_URL . '/sitemap.xml',
            'robotsUrl' => BASE_URL . '/robots.txt',
            'pages' => $pages,
            'isProduction' => IS_PRODUCTION,
            'pageName' => 'seo/sitemap'
        ];
        
        $this->view('admin/seo/sitemap', $data);
    }

    /**
     * Ping search engines about sitemap update
     */
    public function pingSearchEngines() {
        $this->requireAuth();
        
        if (!IS_PRODUCTION) {
            $_SESSION['error'] = 'Cannot ping search engines in development mode';
            $this->redirect('/admin/seo/sitemap');
            return;
        }
        
        $sitemapUrl = urlencode(BASE_URL . '/sitemap.xml');
        $results = [];
        
        // Google
        $googleUrl = "https://www.google.com/ping?sitemap=$sitemapUrl";
        $googleResult = @file_get_contents($googleUrl);
        $results['google'] = $googleResult !== false;
        
        // Bing
        $bingUrl = "https://www.bing.com/ping?sitemap=$sitemapUrl";
        $bingResult = @file_get_contents($bingUrl);
        $results['bing'] = $bingResult !== false;
        
        if ($results['google'] || $results['bing']) {
            $_SESSION['success'] = 'Successfully pinged search engines!';
        } else {
            $_SESSION['error'] = 'Failed to ping search engines';
        }
        
        $this->redirect('/admin/seo/sitemap');
    }
}