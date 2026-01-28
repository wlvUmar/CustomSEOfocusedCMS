<?php
// path: ./models/ArticleJsonLdGenerator.php
// ARTICLE-SPECIFIC JSON-LD GENERATOR - COMPLETELY SEPARATE FROM PAGE LOGIC

class ArticleJsonLdGenerator {
    
    /**
     * Generate complete @graph structure for an article
     * Includes: Article, WebPage, BreadcrumbList, ImageObject, Organization reference
     */
    public static function generateArticleGraph($article, $lang, $seo, $faqs = [], $datePublished = null, $dateModified = null) {
        $baseUrl = self::getBaseUrl();
        $articleUrl = $baseUrl . '/articles/' . $article['id'];
        if ($lang !== DEFAULT_LANGUAGE) {
            $articleUrl .= '/' . $lang;
        }
        
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
        
        // 4. Primary ImageObject schema (if featured image exists)
        if (!empty($article['image'])) {
            $imageSchema = self::generateImageSchema($article, $lang, $articleUrl, $baseUrl);
            $graph[] = $imageSchema;
        }
        
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
        $wordCount = 0;
        if (!empty($article["content_$lang"])) {
             $text = strip_tags($article["content_$lang"]);
             $wordCount = count(preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY));
        }

        // Author logic: Avoid "Admin". Use Organization as fallback identity if author is generic.
        $authorName = $article['author'] ?? 'Editorial Team';
        if (stripos($authorName, 'admin') !== false || empty($authorName)) {
            $authorData = [
                '@id' => $baseUrl . '#organization'
            ];
        } else {
             $authorData = [
                '@type' => 'Person',
                'name' => $authorName
            ];
        }

        $schema = [
            '@type' => 'Article',
            '@id' => $articleUrl . '#article',
            'headline' => $article["title_$lang"] ?? '',
            'description' => $article["meta_description_$lang"] ?? $article["excerpt_$lang"] ?? '',
            'url' => $articleUrl,
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
            'wordCount' => $wordCount,
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
        
        // Add featured image
        if (!empty($article['image'])) {
            $imageUrl = $baseUrl . '/uploads/' . $article['image'];
            $schema['image'] = [
                '@id' => $articleUrl . '#primaryimage'
            ];
        }
        
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
        
        // Only add primaryImageOfPage if image exists
        if (!empty($article['image'])) {
            $schema['primaryImageOfPage'] = [
                '@id' => $articleUrl . '#primaryimage'
            ];
        }
        
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
    public static function generateImageSchema($article, $lang, $articleUrl, $baseUrl) {
        $imageUrl = $baseUrl . '/uploads/' . $article['image'];
        
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
        $baseUrl = BASE_URL;
        
        // Check if BASE_URL is already absolute
        if (strpos($baseUrl, '://') !== false) {
            return rtrim($baseUrl, '/');
        }
        
        // Derive from server
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        return $protocol . '://' . rtrim($host, '/');
    }
}
