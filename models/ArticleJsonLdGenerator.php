<?php
// path: ./models/ArticleJsonLdGenerator.php
// ARTICLE-SPECIFIC JSON-LD GENERATOR - COMPLETELY SEPARATE FROM PAGE LOGIC
// Thin facade: breadcrumb / image / FAQ / base URL delegate to models/jsonld/* builders.

require_once BASE_PATH . '/models/JsonLdBase.php';
require_once BASE_PATH . '/models/jsonld/BreadcrumbBuilder.php';
require_once BASE_PATH . '/models/jsonld/ImageBuilder.php';
require_once BASE_PATH . '/models/jsonld/FaqBuilder.php';
require_once BASE_PATH . '/models/jsonld/ArticleBuilder.php';

class ArticleJsonLdGenerator {

    /**
     * Generate complete @graph structure for an article
     * Includes: Article, WebPage, BreadcrumbList, ImageObject, Organization reference, Media ImageObjects
     */
    public static function generateArticleGraph($article, $lang, $seo, $faqs = [], $datePublished = null, $dateModified = null, $author = null) {
        $baseUrl = self::getBaseUrl();
        $articleUrl = canonicalUrlForArticle($article['id'], $lang);

        // Use passed dates or fallback (though Controller should now always pass them)
        $datePublished = $datePublished ?? date('c', strtotime($article['created_at']));
        $dateModified = $dateModified ?? date('c', strtotime($article['updated_at']));

        $graph = [];

        // Note: Organization and WebSite schemas should be included sitewide in header
        // They are NOT generated here to avoid duplication

        // 1. WebPage schema
        $webPageSchema = self::generateWebPageSchema($article, $lang, $articleUrl, $baseUrl, $datePublished, $dateModified);
        $graph[] = $webPageSchema;

        // 2. Article/BlogPosting schema
        $articleSchema = self::generateArticleSchema($article, $lang, $articleUrl, $baseUrl, $seo, $datePublished, $dateModified, $author);
        $graph[] = $articleSchema;

        // 3. BreadcrumbList schema
        $breadcrumbSchema = self::generateBreadcrumbSchema($article, $lang, $baseUrl, $articleUrl);
        $graph[] = $breadcrumbSchema;

        // 4. Primary ImageObject schema (featured image preferred, fallback to logo)
        $imageSchema = self::generateImageSchema($article, $lang, $articleUrl, $baseUrl, $seo);
        $graph[] = $imageSchema;

        // 5. FAQPage schema (only if article contains FAQs)
        if (!empty($faqs)) {
            $faqSchema = self::generateFAQSchema($faqs, $lang, $articleUrl);
            $graph[] = $faqSchema;
        }

        // 6. Media ImageObject schemas (additional images attached to article)
        $mediaImageSchemas = self::generateMediaImageSchemas($article['id'] ?? null, $lang, $baseUrl);
        foreach ($mediaImageSchemas as $imageSchema) {
            $graph[] = $imageSchema;
        }

        $result = [
            '@context' => 'https://schema.org',
            '@graph' => $graph
        ];

        return JsonLdBase::encode($result);
    }

    /**
     * Generate Article/BlogPosting schema
     */
    public static function generateArticleSchema($article, $lang, $articleUrl, $baseUrl, $seo, $datePublished, $dateModified, $author = null) {
        return ArticleBuilder::buildArticle($article, $lang, $articleUrl, $baseUrl, $seo, $datePublished, $dateModified, $author);
    }

    /**
     * Generate WebPage schema for article
     */
    public static function generateWebPageSchema($article, $lang, $articleUrl, $baseUrl, $datePublished, $dateModified) {
        return ArticleBuilder::buildWebPage($article, $lang, $articleUrl, $baseUrl, $datePublished, $dateModified);
    }

    /**
     * Generate BreadcrumbList schema for article
     */
    public static function generateBreadcrumbSchema($article, $lang, $baseUrl, $articleUrl) {
        return BreadcrumbBuilder::buildArticleTrail($article["title_$lang"] ?? '', $lang, $baseUrl, $articleUrl);
    }

    /**
     * Generate ImageObject schema for featured image
     */
    public static function generateImageSchema($article, $lang, $articleUrl, $baseUrl, $seo) {
        return ImageBuilder::buildArticlePrimary($article, $lang, $articleUrl, $baseUrl, $seo);
    }

    /**
     * Generate FAQPage schema (only if article contains FAQs)
     */
    public static function generateFAQSchema($faqs, $lang, $articleUrl) {
        return FaqBuilder::build($faqs, $lang, $articleUrl);
    }

    /**
     * Generate ImageObject schemas for article media
     * Note: Articles use featured image only. For page-style media attachments, extend ArticleMedia model.
     */
    public static function generateMediaImageSchemas($articleId, $lang, $baseUrl) {
        // Articles currently don't have media attachments like pages
        // They use a single featured image (stored in 'image' column)
        // To extend: create ArticleMedia table and model following PageMedia pattern
        return [];
    }


    /**
     * Get absolute base URL
     */
    private static function getBaseUrl() {
        return JsonLdBase::getBaseUrl();
    }
}
