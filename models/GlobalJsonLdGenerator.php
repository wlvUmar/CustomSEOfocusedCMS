<?php
// path: ./models/GlobalJsonLdGenerator.php
// Thin facade over models/jsonld/* builders. All public signatures unchanged.

require_once BASE_PATH . '/models/JsonLdBase.php';
require_once BASE_PATH . '/models/jsonld/OrganizationBuilder.php';
require_once BASE_PATH . '/models/jsonld/WebsiteBuilder.php';
require_once BASE_PATH . '/models/jsonld/ImageBuilder.php';
require_once BASE_PATH . '/models/jsonld/ServiceBuilder.php';
require_once BASE_PATH . '/models/jsonld/WebPageBuilder.php';

class GlobalJsonLdGenerator {

    /**
     * Generate Organization schema
     * @param $seoSettings - SEO settings from database
     * @param $lang - Language code
     * @param $baseUrl - Base URL of the site
     * @param $pageId - Optional page ID to fetch associated images
     */
    public static function generateOrganizationSchema($seoSettings, $lang, $baseUrl, $pageId = null) {
        $pageImage = null;
        if (!empty($pageId)) {
            $pageImage = ImageBuilder::buildFirstPageImage($pageId, $baseUrl);
        }
        return OrganizationBuilder::build($seoSettings, $lang, $baseUrl, $pageImage);
    }

    /**
     * Generate WebSite schema
     */
    public static function generateWebSiteSchema($seoSettings, $lang, $baseUrl) {
        return WebsiteBuilder::build($seoSettings, $lang, $baseUrl);
    }

    /**
     * Get validated Base URL
     */
    public static function getBaseUrl() {
        // Delegate to the shared resolver so templates, sitemap, and JSON-LD agree.
        return JsonLdBase::getBaseUrl();
    }

    /**
     * Generate Service schema with strict provider reference and page-unique ID
     */
    public static function generateServiceSchema($page, $lang, $baseUrl, $seo, $pageUrl, $parentService = null) {
        return ServiceBuilder::buildFromPage($page, $lang, $baseUrl, $seo, $pageUrl, $parentService);
    }

    /**
     * Generate WebPage schema for standard pages
     */
    public static function generateWebPageSchema($page, $lang, $baseUrl, $primaryImageId = null, $faqId = null) {
        return WebPageBuilder::buildFromPage($page, $lang, $baseUrl, $primaryImageId, $faqId);
    }

    /**
     * Generate ImageObject schemas for page media
     */
    public static function generateMediaImageSchemas($pageId, $lang, $baseUrl) {
        return ImageBuilder::buildMediaList($pageId, $lang, $baseUrl);
    }

    /**
     * Generate SiteNavigationElement schemas for sitelinks (limited to major sections only)
     */
    public static function generateSitelinksSchema($pageId, $lang, $baseUrl, $pageUrl) {
        require_once BASE_PATH . '/models/LinkWidget.php';
        $linkWidgetModel = new LinkWidget();
        $links = $linkWidgetModel->getLinksForPage($pageId);

        if (empty($links)) {
            return null;
        }

        // Keep only top 7 links to avoid navigation spam
        // Filter out very deeply nested pages (depth > 2)
        $filteredLinks = [];
        foreach ($links as $link) {
            if (count($filteredLinks) >= 7) {
                break;
            }
            // Skip pages with very deep nesting (minor subsections)
            $depth = substr_count($link['slug'] ?? '', '/');
            if ($depth <= 2) {
                $filteredLinks[] = $link;
            }
        }

        if (empty($filteredLinks)) {
            return null;
        }

        $navigationElements = [];
        foreach ($filteredLinks as $index => $link) {
            $linkUrl = canonicalUrlForPage($link['slug'] ?? '', $lang);
            $navigationElements[] = [
                '@type' => 'SiteNavigationElement',
                '@id' => $linkUrl . '#sitenavigation',
                'name' => $link["title_$lang"] ?? '',
                'url' => $linkUrl,
                'position' => $index + 1
            ];
        }

        // Return array of SiteNavigationElement schemas to be added individually to graph
        return $navigationElements;
    }
}
