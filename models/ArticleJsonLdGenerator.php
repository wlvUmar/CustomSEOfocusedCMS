<?php
// path: ./models/ArticleJsonLdGenerator.php
// ARTICLE-SPECIFIC JSON-LD GENERATOR - COMPLETELY SEPARATE FROM PAGE LOGIC

class ArticleJsonLdGenerator {

    private static function computeWordCountFromHtml($html) {
        $html = (string)($html ?? '');
        if (trim($html) === '') return null;

        // Remove scripts/styles that can inflate or distort text extraction.
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html);

        // Ensure block-level boundaries don't smash words together.
        $html = preg_replace('/<(br|hr)\b[^>]*>/i', ' ', $html);
        $html = preg_replace('/<\/(p|div|li|h[1-6]|tr|td|th|blockquote|section|article)\s*>/i', ' ', $html);

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Normalize NBSP and whitespace.
        $text = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $text);
        $text = preg_replace('/\s{2,}/u', ' ', trim($text));
        if ($text === '') return null;

        if (!preg_match_all('/[\\p{L}\\p{N}]+(?:-[\\p{L}\\p{N}]+)*/u', $text, $m)) {
            return null;
        }

        $count = count($m[0]);
        return $count > 0 ? $count : null;
    }
    
    /**
     * Generate complete @graph structure for an article
     * Includes: Article, WebPage, BreadcrumbList, ImageObject, Organization reference
     */
    public static function generateArticleGraph($article, $lang, $seo, $faqs = [], $datePublished = null, $dateModified = null) {
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
        $articleSchema = self::generateArticleSchema($article, $lang, $articleUrl, $baseUrl, $seo, $datePublished, $dateModified);
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
        
        $result = [
            '@context' => 'https://schema.org',
            '@graph' => $graph
        ];
        
        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    
    /**
     * Generate Article/BlogPosting schema
     */
    public static function generateArticleSchema($article, $lang, $articleUrl, $baseUrl, $seo, $datePublished, $dateModified) {
        $wordCount = null;
        if (!empty($article["content_$lang"])) {
            $wordCount = self::computeWordCountFromHtml($article["content_$lang"]);
        }

        // Brand-authored articles: keep meta author and JSON-LD author aligned to Organization.
        $authorData = [
            '@id' => $baseUrl . '#organization'
        ];

        $schema = [
            '@type' => 'Article',
            '@id' => $articleUrl . '#article',
            'headline' => $article["title_$lang"] ?? '',
            'description' => $article["meta_description_$lang"] ?? $article["excerpt_$lang"] ?? '',
            'url' => $articleUrl,
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
            'inLanguage' => $lang === 'ru' ? 'ru-RU' : 'uz-UZ',
            'isAccessibleForFree' => true,
            'author' => $authorData,
            'publisher' => [
                '@id' => $baseUrl . '#organization'
            ],
            'mainEntityOfPage' => [
                '@id' => $articleUrl . '#webpage'
            ]
        ];

        if ($wordCount !== null) {
            $schema['wordCount'] = $wordCount;
        }
        
        // Primary image wiring (featured image preferred, fallback to logo) via ImageObject node.
        $schema['image'] = [
            '@id' => $articleUrl . '#primaryimage'
        ];
        
        // Add article section (category)
        if (!empty($article["category_$lang"])) {
            $schema['articleSection'] = $article["category_$lang"];
        }
        
        // Add keywords if available
        if (!empty($article["meta_keywords_$lang"])) {
            $keywords = explode(',', $article["meta_keywords_$lang"]);
            $schema['keywords'] = array_map('trim', $keywords);
        }
        
        return $schema;
    }
    
    /**
     * Generate WebPage schema for article
     */
    public static function generateWebPageSchema($article, $lang, $articleUrl, $baseUrl, $datePublished, $dateModified) {
        $schema = [
            '@type' => 'WebPage',
            '@id' => $articleUrl . '#webpage',
            'url' => $articleUrl,
            'name' => $article["title_$lang"] ?? '',
            'description' => $article["meta_description_$lang"] ?? $article["excerpt_$lang"] ?? '',
            'isPartOf' => [
                '@id' => $baseUrl . '#website'
            ],
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
            'breadcrumb' => [
                '@id' => $articleUrl . '#breadcrumb'
            ],
            'inLanguage' => $lang === 'ru' ? 'ru-RU' : 'uz-UZ',
            'potentialAction' => [
                '@type' => 'ReadAction',
                'target' => [$articleUrl]
            ]
        ];
        
        // Primary image wiring always present (featured image preferred, fallback to logo).
        $schema['primaryImageOfPage'] = [
            '@id' => $articleUrl . '#primaryimage'
        ];
        $schema['image'] = [
            '@id' => $articleUrl . '#primaryimage'
        ];
        
        return $schema;
    }
    
    /**
     * Generate BreadcrumbList schema for article
     */
    public static function generateBreadcrumbSchema($article, $lang, $baseUrl, $articleUrl) {
        $schema = [
            '@type' => 'BreadcrumbList',
            '@id' => $articleUrl . '#breadcrumb',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => $lang === 'ru' ? 'Главная' : 'Bosh sahifa',
                    'item' => $baseUrl . '/'
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $lang === 'ru' ? 'Статьи' : 'Maqolalar',
                    'item' => $baseUrl . '/articles'
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $article["title_$lang"] ?? '',
                    'item' => $articleUrl
                ]
            ]
        ];
        
        return $schema;
    }
    
    /**
     * Generate ImageObject schema for featured image
     */
    public static function generateImageSchema($article, $lang, $articleUrl, $baseUrl, $seo) {
        if (!empty($article['image'])) {
            $imageUrl = absoluteUrl('/uploads/' . $article['image'], $baseUrl);
        } else {
            $imageUrl = absoluteUrl($seo['org_logo'] ?? '/css/logo.png', $baseUrl);
        }
        
        $schema = [
            '@type' => 'ImageObject',
            '@id' => $articleUrl . '#primaryimage',
            'url' => $imageUrl,
            'contentUrl' => $imageUrl,
            'caption' => $article["title_$lang"] ?? '',
            'description' => $article["meta_description_$lang"] ?? $article["excerpt_$lang"] ?? ''
        ];
        
        return $schema;
    }
    
    /**
     * Generate FAQPage schema (only if article contains FAQs)
     */
    public static function generateFAQSchema($faqs, $lang, $articleUrl) {
        $faqItems = [];
        
        foreach ($faqs as $faq) {
            $faqItems[] = [
                '@type' => 'Question',
                'name' => $faq["question_$lang"] ?? '',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq["answer_$lang"] ?? ''
                ]
            ];
        }
        
        $schema = [
            '@type' => 'FAQPage',
            '@id' => $articleUrl . '#faq',
            'mainEntity' => $faqItems
        ];
        
        return $schema;
    }
    

    
    /**
     * Get absolute base URL
     */
    private static function getBaseUrl() {
        return siteBaseUrl();
    }
}
