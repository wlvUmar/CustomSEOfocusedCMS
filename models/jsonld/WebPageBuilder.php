<?php
// path: ./models/jsonld/WebPageBuilder.php
// Single owner of WebPage nodes built from a page row.

require_once BASE_PATH . '/models/JsonLdBase.php';

class WebPageBuilder {

    public static function buildFromPage($page, $lang, $baseUrl, $primaryImageId = null, $faqId = null) {
        $slug = $page['slug'] ?? '';
        $canonicalUrl = canonicalUrlForPage($slug, $lang);

        $schema = [
            '@type' => 'WebPage',
            '@id' => $canonicalUrl . '#webpage',
            'url' => $canonicalUrl,
            'name' => $page["title_$lang"] ?? '',
            'description' => $page["meta_description_$lang"] ?? '',
            'isPartOf' => [
                '@id' => $baseUrl . '#website'
            ],
            'inLanguage' => JsonLdBase::inLanguage($lang)
        ];

        // Real freshness signals, sourced directly from pages.created_at / pages.updated_at.
        // Never fabricate these; only emit when the DB actually has them.
        if (!empty($page['created_at'])) {
            $datePublished = JsonLdBase::toIso8601($page['created_at']);
            if ($datePublished) {
                $schema['datePublished'] = $datePublished;
            }
        }
        if (!empty($page['updated_at'])) {
            $dateModified = JsonLdBase::toIso8601($page['updated_at']);
            if ($dateModified) {
                $schema['dateModified'] = $dateModified;
            }
        }

        // BreadcrumbList is optional; controllers can add it when present.

        // Link Primary Image
        if ($primaryImageId) {
            $schema['primaryImageOfPage'] = [
                '@id' => $primaryImageId
            ];
            $schema['image'] = [
                '@id' => $primaryImageId
            ];
        }

        // Link FAQPage (hasPart)
        if ($faqId) {
            $schema['hasPart'][] = [
                '@id' => $faqId
            ];
        }

        return $schema;
    }
}
