<?php
// path: ./models/jsonld/ServiceBuilder.php
// Single owner of Service nodes built from a page row.

require_once BASE_PATH . '/models/JsonLdBase.php';

class ServiceBuilder {

    public static function buildFromPage($page, $lang, $baseUrl, $seo, $pageUrl, $parentService = null) {
        $title = $page["title_$lang"] ?? '';
        $description = $page["meta_description_$lang"] ?? '';
        // resolve {{phone}} etc. like WebPage does — otherwise the Service description ends with "— ."
        if (function_exists('replacePlaceholders')) {
            $title = replacePlaceholders($title, $page, $seo);
            $description = replacePlaceholders($description, $page, $seo);
        }

        // Smarter serviceType logic
        $serviceType = $seo['service_type'] ?? 'Service';
        $applianceName = '';
        if (preg_match('/(?:продать|скупка|выкуп)\s+([а-яёa-z\s]+?)(?:\s+быстро|$)/ui', $title, $matches)) {
            $applianceName = trim($matches[1]);
        }

        if (!empty($applianceName)) {
            $serviceType = $lang === 'ru' ? "Скупка: $applianceName" : "Xarid qilish: $applianceName";
        } elseif ($serviceType === 'Service') {
             $serviceType = $title;
        }

        $schema = [
            '@type' => 'Service',
            '@id' => $pageUrl . '#service', // Unique per page
            'serviceType' => $serviceType,
            'name' => $title,
            'description' => JsonLdBase::cleanDescription($description),
            'provider' => [
                '@id' => $baseUrl . '#organization'
            ],
            // Tie the Service entity to the WebPage entity (minor enrichment).
            'mainEntityOfPage' => [
                '@id' => $pageUrl . '#webpage'
            ],
            'areaServed' => [
                '@type' => 'City',
                'name' => $seo['city'] ?? 'Tashkent'
            ]
        ];
        if ($parentService) {
            $schema['isPartOf'] = $parentService;
        }

        // Optional buyback price range (what we PAY the customer, not an Offer/price
        // we charge — schema.org has no clean "we purchase for X-Y" property, so
        // priceRange as a short string (e.g. "500,000 - 2,000,000 UZS") is the
        // closest accurate fit without misusing Offer, which implies a sale TO the customer.
        // Inert until real per-page min/max price columns exist on `pages` — never fabricated.
        // Wire $page['price_range_min'] / $page['price_range_max'] (or similar) once those
        // columns are added; until then this silently does nothing.
        if (!empty($page['price_range_text'])) {
            $schema['priceRange'] = trim((string)$page['price_range_text']);
        } elseif (!empty($page['price_range_min']) && !empty($page['price_range_max'])) {
            $currency = $page['price_range_currency'] ?? 'UZS';
            $schema['priceRange'] = number_format((float)$page['price_range_min'], 0, '.', ',')
                . ' - ' . number_format((float)$page['price_range_max'], 0, '.', ',')
                . ' ' . $currency;
        }
        return $schema;
    }
}
