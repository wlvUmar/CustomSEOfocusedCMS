<?php
// path: ./models/jsonld/ArticleBuilder.php
// Single owner of BlogPosting + article WebPage nodes.

require_once BASE_PATH . '/models/JsonLdBase.php';

class ArticleBuilder {

    public static function wordCountFromHtml($html) {
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

    public static function buildArticle($article, $lang, $articleUrl, $baseUrl, $seo, $datePublished, $dateModified, $author = null) {
        $wordCount = null;
        if (!empty($article["content_$lang"])) {
            $wordCount = self::wordCountFromHtml($article["content_$lang"]);
        }

        $authorData = [
            '@id' => $baseUrl . '#organization'
        ];

        if (is_array($author) && !empty($author['name'])) {
            $authorData = [
                '@type' => 'Person',
                'name' => $author['name']
            ];
            if (!empty($author['url'])) {
                $authorData['url'] = $author['url'];
            }
            if (!empty($author['sameAs']) && is_array($author['sameAs'])) {
                $authorData['sameAs'] = array_values(array_filter($author['sameAs']));
            }
            $authorData['worksFor'] = [
                '@id' => $baseUrl . '#organization'
            ];
        }

        $schema = [
            '@type' => 'BlogPosting',
            '@id' => $articleUrl . '#article',
            'headline' => $article["title_$lang"] ?? '',
            'description' => $article["meta_description_$lang"] ?? $article["excerpt_$lang"] ?? '',
            'url' => $articleUrl,
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
            'inLanguage' => JsonLdBase::inLanguage($lang),
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

    public static function buildWebPage($article, $lang, $articleUrl, $baseUrl, $datePublished, $dateModified) {
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
            'inLanguage' => JsonLdBase::inLanguage($lang),
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
}
