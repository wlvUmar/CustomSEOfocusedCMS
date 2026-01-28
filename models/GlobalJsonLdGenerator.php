<?php
// path: ./models/GlobalJsonLdGenerator.php

class GlobalJsonLdGenerator {

    /**
     * Generate Organization schema
     */
    public static function generateOrganizationSchema($seoSettings, $lang, $baseUrl) {
        // If hardcoded schema exists in settings, prioritize it but ensure ID is correct
        if (!empty($seoSettings['organization_schema'])) {
            $orgSchema = json_decode($seoSettings['organization_schema'], true);
            if (is_array($orgSchema)) {
                $orgSchema = self::sanitizeUrlValues($orgSchema, $baseUrl);
                // Clean description
                if (!empty($orgSchema['description'])) {
                    $orgSchema['description'] = self::cleanText($orgSchema['description']);
                }

                // STRICT ENFORCEMENT: Override any host/ids from DB to avoid localhost leakage.
                $orgSchema['@id'] = $baseUrl . '#organization';
                $orgSchema['url'] = $baseUrl;

                if (!empty($orgSchema['logo'])) {
                    if (is_string($orgSchema['logo'])) {
                        $orgSchema['logo'] = [
                            '@type' => 'ImageObject',
                            'url' => absoluteUrl($orgSchema['logo'], $baseUrl),
                        ];
                    } elseif (is_array($orgSchema['logo']) && !empty($orgSchema['logo']['url'])) {
                        $orgSchema['logo']['url'] = absoluteUrl($orgSchema['logo']['url'], $baseUrl);
                    }
                }

                // If DB schema has a bad/empty description, prefer admin description fields.
                $fallbackDesc = $seoSettings["org_description_$lang"] ?? '';
                if (empty($orgSchema['description']) && !empty($fallbackDesc)) {
                    $orgSchema['description'] = self::cleanText($fallbackDesc);
                }

                return $orgSchema;
            }
        }

        // Fallback to generating from fields
        $schema = [
            '@type' => $seoSettings['org_type'] ?? 'Organization',
            '@id' => $baseUrl . '#organization',
            'name' => $seoSettings["org_name_$lang"] ?? $seoSettings["site_name_$lang"] ?? 'Site Name',
            'url' => $baseUrl,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => absoluteUrl($seoSettings['org_logo'] ?? ($baseUrl . '/css/logo.png'), $baseUrl)
            ]
        ];

        if (!empty($seoSettings["org_description_$lang"])) {
            $schema['description'] = self::cleanText($seoSettings["org_description_$lang"]);
        }

        if (!empty($seoSettings['phone'])) {
            $schema['telephone'] = $seoSettings['phone'];
        }
        
        // Add address if available
        if (!empty($seoSettings["address_$lang"])) {
           $schema['address'] = [
               '@type' => 'PostalAddress',
               'streetAddress' => $seoSettings["address_$lang"],
               'addressCountry' => $seoSettings['country'] ?? 'UZ'
           ];
           if (!empty($seoSettings['city'])) {
               $schema['address']['addressLocality'] = $seoSettings['city'];
           }
        }
        
        // Add sameAs links
        $sameAs = [];
        $socials = ['social_facebook', 'social_instagram', 'social_twitter', 'social_youtube'];
        foreach ($socials as $social) {
            if (!empty($seoSettings[$social])) {
                $sameAs[] = $seoSettings[$social];
            }
        }
        
        if (!empty($seoSettings['org_sameas_extra'])) {
             $extras = array_map('trim', explode(',', $seoSettings['org_sameas_extra']));
             $sameAs = array_merge($sameAs, $extras);
        }
        
        if (!empty($sameAs)) {
            $schema['sameAs'] = $sameAs;
        }

        return $schema;
    }

    /**
     * Generate WebSite schema
     */
    public static function generateWebSiteSchema($seoSettings, $lang, $baseUrl) {
        // If hardcoded schema exists
        if (!empty($seoSettings['website_schema'])) {
             $siteSchema = json_decode($seoSettings['website_schema'], true);
             if (is_array($siteSchema)) {
                 $siteSchema = self::sanitizeUrlValues($siteSchema, $baseUrl);
                 // STRICT ID ENFORCEMENT
                 $siteSchema['@id'] = $baseUrl . '#website';
                 $siteSchema['url'] = $baseUrl;
                 $siteSchema['publisher'] = ['@id' => $baseUrl . '#organization'];
                 return $siteSchema;
             }
        }

        return [
            '@type' => 'WebSite',
            '@id' => $baseUrl . '#website',
            'url' => $baseUrl,
            'name' => $seoSettings["site_name_$lang"] ?? '',
            'publisher' => [
                '@id' => $baseUrl . '#organization'
            ]
        ];
    }
    
    /**
     * Get validated Base URL
     */
    public static function getBaseUrl() {
        // Delegate to the shared resolver so templates, sitemap, and JSON-LD agree.
        return siteBaseUrl();
    }

    private static function cleanText($text) {
        $text = (string)($text ?? '');
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        $text = preg_replace('/\s{2,}/', ' ', trim($text));

        // Remove duplicated sentences (common copy/paste issue in descriptions).
        $parts = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || count($parts) < 2) return $text;

        $out = [];
        $seen = [];
        foreach ($parts as $p) {
            $key = mb_strtolower(trim($p));
            if ($key === '') continue;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = trim($p);
        }

        return trim(implode(' ', $out));
    }

    /**
     * Recursively sanitize URL-like string values inside a schema array:
     * - Rewrite localhost/127.0.0.1 to the canonical host
     * - Convert root-relative paths to absolute URLs
     */
    private static function sanitizeUrlValues($value, $baseUrl) {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::sanitizeUrlValues($v, $baseUrl);
            }
            return $value;
        }

        if (!is_string($value)) return $value;

        // Convert root-relative to absolute.
        if (strpos($value, '/') === 0) {
            return absoluteUrl($value, $baseUrl);
        }

        $parsed = @parse_url($value);
        if (!is_array($parsed) || empty($parsed['scheme'])) return $value;

        $host = strtolower((string)($parsed['host'] ?? ''));
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) return $value;

        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? ('?' . $parsed['query']) : '';
        $fragment = isset($parsed['fragment']) ? ('#' . $parsed['fragment']) : '';
        return rtrim($baseUrl, '/') . ($path ?: '') . $query . $fragment;
    }

    /**
     * Generate Service schema with strict provider reference and page-unique ID
     */
    public static function generateServiceSchema($page, $lang, $baseUrl, $seo, $pageUrl) {
        $title = $page["title_$lang"] ?? '';
        $description = $page["meta_description_$lang"] ?? '';
        
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
            'description' => self::cleanText($description),
            'inLanguage' => $lang === 'ru' ? 'ru-RU' : 'uz-UZ',
            'provider' => [
                '@id' => $baseUrl . '#organization'
            ],
            'areaServed' => [
                '@type' => 'City',
                'name' => $seo['city'] ?? 'Tashkent'
            ]
        ];

        // Validating price - remove if '$$' or invalid
        if (!empty($seo['price_range']) && $seo['price_range'] !== '$$' && strlen($seo['price_range']) > 1) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'priceCurrency' => 'UZS',
                'price' => $seo['price_range']
            ];
        }

        return $schema;
    }

    /**
     * Generate WebPage schema for standard pages
     */
    public static function generateWebPageSchema($page, $lang, $baseUrl, $primaryImageId = null, $faqId = null) {
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
            'inLanguage' => $lang === 'ru' ? 'ru-RU' : 'uz-UZ'
        ];

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
