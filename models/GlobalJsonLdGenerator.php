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
                // Clean description
                if (!empty($orgSchema['description'])) {
                    $orgSchema['description'] = self::cleanText($orgSchema['description']);
                }
                // STRICT ID ENFORCEMENT: Override any ID from DB
                $orgSchema['@id'] = $baseUrl . '#organization';
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
                'url' => $seoSettings['org_logo'] ?? ($baseUrl . '/css/logo.png')
            ]
        ];

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
                 // STRICT ID ENFORCEMENT
                 $siteSchema['@id'] = $baseUrl . '#website';
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
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        
        if (strpos($baseUrl, '://') !== false) {
            return rtrim($baseUrl, '/');
        }
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // If BASE_URL is just a path (e.g., /myapp), append it
        if ($baseUrl && strpos($baseUrl, '/') === 0) {
            return $protocol . '://' . rtrim($host, '/') . rtrim($baseUrl, '/');
        }
        
        return $protocol . '://' . rtrim($host, '/');
    }

    private static function cleanText($text) {
        return preg_replace('/\s{2,}/', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $text));
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
        $canonicalUrl = $page['canonical_url'] ?? $baseUrl . '/' . $page['slug'];
        if ($lang !== DEFAULT_LANGUAGE) {
             $canonicalUrl .= "/$lang";
        }

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
        
        // Link Breadcrumb
        $schema['breadcrumb'] = [
             '@id' => $canonicalUrl . '#breadcrumb'
        ];
        
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
