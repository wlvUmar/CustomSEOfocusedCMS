<?php
// path: ./models/SitemapRenderer.php
// XML/text body builder for sitemap index, pages/articles sitemaps, robots.txt, ads.txt.
// Extracted from controllers/SitemapController.php per docs/hygiene-audit-report.md 1.3.
// Pure string building only: no DB, no headers, no exit. Controller owns HTTP + fetch.

class SitemapRenderer {

    public function renderSitemapIndex(string $baseUrl, int $pagesLastmodTs, int $articlesLastmodTs, int $indexLastmodTs): string {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $out .= '  <sitemap>' . "\n";
        $out .= '    <loc>' . $baseUrl . '/sitemap-pages.xml</loc>' . "\n";
        $out .= '    <lastmod>' . date('Y-m-d', $pagesLastmodTs ?: $indexLastmodTs) . '</lastmod>' . "\n";
        $out .= '  </sitemap>' . "\n";
        $out .= '  <sitemap>' . "\n";
        $out .= '    <loc>' . $baseUrl . '/sitemap-articles.xml</loc>' . "\n";
        $out .= '    <lastmod>' . date('Y-m-d', $articlesLastmodTs ?: $indexLastmodTs) . '</lastmod>' . "\n";
        $out .= '  </sitemap>' . "\n";
        $out .= '</sitemapindex>';
        return $out;
    }

    public function renderPagesSitemap(string $baseUrl, array $pages, int $templateTs, array $rotationMax): string {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ' . "\n";
        $out .= '                  xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        // Prevent accidental duplicates (e.g. if both "home" and "main" exist).
        $seen = [];

        foreach ($pages as $page) {
            $slug = $page['slug'];
            $updated = $page['updated_at'];
            $rotTs = $rotationMax[(int)($page['id'] ?? 0)] ?? 0;
            $pageTs = $updated ? strtotime($updated) : 0;
            $effectivePageTs = max($pageTs, $rotTs);
            $effectiveUpdated = $effectivePageTs ? date('Y-m-d H:i:s', $effectivePageTs) : $updated;
            $lastmodTs = $this->mergeLastmodTimestamps($effectiveUpdated, $templateTs);

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

            $out .= '  <url>' . "\n";
            $out .= '    <loc>' . $ruLoc . '</loc>' . "\n";
            $out .= '    <lastmod>' . date('Y-m-d', $lastmodTs) . '</lastmod>' . "\n";
            $out .= '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
            $out .= '    <priority>' . $priority . '</priority>' . "\n";
            $out .= '    <xhtml:link rel="alternate" hreflang="ru" href="' . $ruLoc . '" />' . "\n";
            $out .= '    <xhtml:link rel="alternate" hreflang="uz" href="' . $uzLoc . '" />' . "\n";
            $out .= '  </url>' . "\n";

            $out .= '  <url>' . "\n";
            $out .= '    <loc>' . $uzLoc . '</loc>' . "\n";
            $out .= '    <lastmod>' . date('Y-m-d', $lastmodTs) . '</lastmod>' . "\n";
            $out .= '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
            $out .= '    <priority>' . $priority . '</priority>' . "\n";
            $out .= '    <xhtml:link rel="alternate" hreflang="ru" href="' . $ruLoc . '" />' . "\n";
            $out .= '    <xhtml:link rel="alternate" hreflang="uz" href="' . $uzLoc . '" />' . "\n";
            $out .= '  </url>' . "\n";
        }

        $out .= '</urlset>';
        return $out;
    }

    public function renderArticlesSitemap(string $baseUrl, array $articles, int $templateTs): string {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ' . "\n";
        $out .= '                  xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($articles as $article) {
            $id = $article['id'];
            $updated = $article['updated_at'];
            $lastmodTs = $this->mergeLastmodTimestamps($updated, $templateTs);

            // Articles have medium priority and monthly changefreq
            $priority = '0.7';
            $changefreq = 'monthly';

            // Russian version
            $out .= '  <url>' . "\n";
            $out .= '    <loc>' . $baseUrl . '/articles/' . $id . '</loc>' . "\n";
            $out .= '    <lastmod>' . date('Y-m-d', $lastmodTs) . '</lastmod>' . "\n";
            $out .= '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
            $out .= '    <priority>' . $priority . '</priority>' . "\n";
            $out .= '    <xhtml:link rel="alternate" hreflang="ru" href="' . $baseUrl . '/articles/' . $id . '" />' . "\n";
            $out .= '    <xhtml:link rel="alternate" hreflang="uz" href="' . $baseUrl . '/articles/' . $id . '/uz" />' . "\n";
            $out .= '  </url>' . "\n";

            // Uzbek version
            $out .= '  <url>' . "\n";
            $out .= '    <loc>' . $baseUrl . '/articles/' . $id . '/uz</loc>' . "\n";
            $out .= '    <lastmod>' . date('Y-m-d', $lastmodTs) . '</lastmod>' . "\n";
            $out .= '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
            $out .= '    <priority>' . $priority . '</priority>' . "\n";
            $out .= '    <xhtml:link rel="alternate" hreflang="ru" href="' . $baseUrl . '/articles/' . $id . '" />' . "\n";
            $out .= '    <xhtml:link rel="alternate" hreflang="uz" href="' . $baseUrl . '/articles/' . $id . '/uz" />' . "\n";
            $out .= '  </url>' . "\n";
        }

        $out .= '</urlset>';
        return $out;
    }

    public function renderRobotsTxt(bool $isProduction, string $sitemapUrl): string {
        if ($isProduction) {
            $out = "User-agent: *\n";
            $out .= "Allow: /\n";
            $out .= "\n";
            $out .= "Disallow: /admin/\n";
            $out .= "Disallow: /config/\n";
            $out .= "Disallow: /logs/\n";
            $out .= "Disallow: /database/\n";
            $out .= "\n";
            $out .= "Sitemap: " . $sitemapUrl . "\n";
            return $out;
        }
        return "User-agent: *\nDisallow: /\n";
    }

    public function renderAdsTxt(): string {
        return "google.com, pub-7628492698305234, DIRECT, f08c47fec0942fa0\n";
    }

    private function mergeLastmodTimestamps($updatedAt, $templateTs) {
        $updatedTs = $updatedAt ? strtotime($updatedAt) : 0;
        $updatedTs = $updatedTs ?: 0;
        $templateTs = $templateTs ?: 0;
        $merged = max($updatedTs, $templateTs);
        return $merged > 0 ? $merged : time();
    }
}
