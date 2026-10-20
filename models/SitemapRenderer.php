<?php
// path: ./models/SitemapRenderer.php
// XML/text body builder for sitemap index, pages/articles sitemaps, robots.txt, ads.txt.
// Extracted from controllers/SitemapController.php per docs/hygiene-audit-report.md 1.3.
// Pure string building only: no DB, no headers, no exit. Controller owns HTTP + fetch.
//
// lastmod is derived ONLY from content timestamps (page/article updated_at, rotation updated_at).
// Template/file mtimes are deliberately NOT used: they change on every deploy and churn lastmod
// across all URLs. An entry with no known timestamp omits <lastmod> rather than emitting time().
// hreflang x-default points at the RU URL, matching views/templates/header.php and article.php.

class SitemapRenderer {

    public function renderSitemapIndex(string $baseUrl, int $pagesLastmodTs, int $articlesLastmodTs): string {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $out .= '  <sitemap>' . "\n";
        $out .= '    <loc>' . $baseUrl . '/sitemap-pages.xml</loc>' . "\n";
        $out .= $this->lastmodLine($pagesLastmodTs, '    ');
        $out .= '  </sitemap>' . "\n";
        $out .= '  <sitemap>' . "\n";
        $out .= '    <loc>' . $baseUrl . '/sitemap-articles.xml</loc>' . "\n";
        $out .= $this->lastmodLine($articlesLastmodTs, '    ');
        $out .= '  </sitemap>' . "\n";
        $out .= '</sitemapindex>';
        return $out;
    }

    public function renderPagesSitemap(string $baseUrl, array $pages, array $rotationMax): string {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ' . "\n";
        $out .= '                  xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        // Prevent accidental duplicates (e.g. if both "home" and "main" exist).
        $seen = [];

        foreach ($pages as $page) {
            $slug = $page['slug'];
            $rotTs = $rotationMax[(int)($page['id'] ?? 0)] ?? 0;
            $lastmodTs = $this->contentLastmodTs($page['updated_at'] ?? null, $rotTs);

            $isHome = in_array($slug, ['home', 'main'], true);
            $priority = $isHome ? '1.0' : '0.8';
            $changefreq = $page['enable_rotation'] ? 'monthly' : 'yearly';

            // Canonical URL paths (must match Url::canonicalUrlForPage): homepage is "/" and "/uz".
            $ruPath = $isHome ? '' : htmlspecialchars($slug);
            $uzPath = $isHome ? 'uz' : (htmlspecialchars($slug) . '/uz');

            $ruLoc = rtrim($baseUrl . '/' . $ruPath, '/');
            $ruLoc = $ruLoc === rtrim($baseUrl, '/') ? ($baseUrl . '/') : $ruLoc;
            $uzLoc = rtrim($baseUrl . '/' . $uzPath, '/');

            if (isset($seen[$ruLoc])) {
                continue;
            }
            $seen[$ruLoc] = true;

            // Same rule as views/templates/header.php: only languages with published content.
            $langs = $this->languagesWithContent($page);
            if (!$langs) {
                continue;
            }
            $locByLang = ['ru' => $ruLoc, 'uz' => $uzLoc];
            $alternates = [];
            foreach ($langs as $l) {
                $alternates[$l] = $locByLang[$l];
            }
            $xDefault = in_array('ru', $langs, true) ? $alternates['ru'] : $alternates[$langs[0]];
            foreach ($langs as $l) {
                $out .= $this->urlBlock($alternates[$l], $lastmodTs, $changefreq, $priority, $alternates, $xDefault);
            }
        }

        $out .= '</urlset>';
        return $out;
    }

    public function renderArticlesSitemap(string $baseUrl, array $articles): string {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ' . "\n";
        $out .= '                  xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($articles as $article) {
            $id = $article['id'];
            $lastmodTs = $this->contentLastmodTs($article['updated_at'] ?? null, 0);

            // Articles have medium priority and monthly changefreq
            $priority = '0.7';
            $changefreq = 'monthly';

            $ruLoc = $baseUrl . '/articles/' . $id;
            $uzLoc = $baseUrl . '/articles/' . $id . '/uz';
            $alternates = ['ru' => $ruLoc, 'uz' => $uzLoc];

            // Article templates emit ru+uz unconditionally, x-default = ru (see views/templates/article.php).
            $out .= $this->urlBlock($ruLoc, $lastmodTs, $changefreq, $priority, $alternates, $ruLoc);
            $out .= $this->urlBlock($uzLoc, $lastmodTs, $changefreq, $priority, $alternates, $ruLoc);
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

    /**
     * One <url> block. Both RU and UZ entries carry the same full alternate set
     * including x-default (= RU), mirroring the HTML <link> tags in the page template.
     */
    private function urlBlock(string $loc, int $lastmodTs, string $changefreq, string $priority, array $alternates, string $xDefault): string {
        $out = '  <url>' . "\n";
        $out .= '    <loc>' . $loc . '</loc>' . "\n";
        $out .= $this->lastmodLine($lastmodTs, '    ');
        $out .= '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
        $out .= '    <priority>' . $priority . '</priority>' . "\n";
        foreach ($alternates as $hl => $href) {
            $out .= '    <xhtml:link rel="alternate" hreflang="' . $hl . '" href="' . $href . '" />' . "\n";
        }
        $out .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $xDefault . '" />' . "\n";
        $out .= '  </url>' . "\n";
        return $out;
    }

    /** Languages with non-empty title or content, in SUPPORTED_LANGUAGES order (matches header.php). */
    private function languagesWithContent(array $page): array {
        $supported = defined('SUPPORTED_LANGUAGES') ? SUPPORTED_LANGUAGES : ['ru', 'uz'];
        $out = [];
        foreach ($supported as $l) {
            if (!in_array($l, ['ru', 'uz'], true)) continue;
            if (trim((string)($page["title_$l"] ?? '')) !== '' || trim((string)($page["content_$l"] ?? '')) !== '') {
                $out[] = $l;
            }
        }
        return $out;
    }

    private function lastmodLine(int $ts, string $indent): string {
        if ($ts <= 0) {
            return '';
        }
        return $indent . '<lastmod>' . date('Y-m-d', $ts) . '</lastmod>' . "\n";
    }

    /**
     * Content-only lastmod: max(updated_at, rotation max). 0 when unknown.
     */
    private function contentLastmodTs($updatedAt, $rotationTs): int {
        $updatedTs = $updatedAt ? (int)strtotime((string)$updatedAt) : 0;
        $rotationTs = (int)$rotationTs;
        return max($updatedTs > 0 ? $updatedTs : 0, $rotationTs > 0 ? $rotationTs : 0);
    }
}
