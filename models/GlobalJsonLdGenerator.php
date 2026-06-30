<?php 
// path: ./models/GlobalJsonLdGenerator.php

class GlobalJsonLdGenerator {

    /**
     * Build a ContactPoint enriched with areaServed and hoursAvailable,
     * sourced only from columns that already exist in seo_settings.
     */
    private static function buildContactPoint($seoSettings) {
        $contactPoint = [
            '@type' => 'ContactPoint',
            'telephone' => $seoSettings['phone'],
            'contactType' => 'customer service',
            'availableLanguage' => ['ru', 'uz']
        ];

        $areaServed = $seoSettings['area_served'] ?: ($seoSettings['city'] ?? null);
        if (!empty($areaServed)) {
            $contactPoint['areaServed'] = [
                '@type' => 'City',
                'name' => $areaServed
            ];
        }

        if (!empty($seoSettings['opening_hours'])) {
            $hoursLines = array_values(array_filter(array_map('trim', explode("\n", $seoSettings['opening_hours']))));
            if (!empty($hoursLines)) {
                $contactPoint['hoursAvailable'] = $hoursLines;
            }
        }

        return $contactPoint;
    }

    private static function unifiedOrganizationDescriptionRu() {
        // Single source of truth for brand description across all pages (RU).
        // Keep it clean, factual, and reusable for homepage/service pages/articles.
        return 'Сервис скупки бытовой техники в Ташкенте: быстро оцениваем, честно предлагаем цену и выкупаем технику в удобное время. Принимаем разные категории техники, работаем аккуратно и прозрачно, отвечаем на вопросы и помогаем с вывозом при необходимости.';
    }

    /**
     * Generate Organization schema
     * @param $seoSettings - SEO settings from database
     * @param $lang - Language code
     * @param $baseUrl - Base URL of the site
     * @param $pageId - Optional page ID to fetch associated images
     */
    public static function generateOrganizationSchema($seoSettings, $lang, $baseUrl, $pageId = null) {
        // If hardcoded schema exists in settings, prioritize it but ensure ID is correct
        if (!empty($seoSettings['organization_schema'])) {
           $orgSchema = json_decode($seoSettings['organization_schema'], true);
           if (is_array($orgSchema)) {
               $orgSchema = self::sanitizeUrlValues($orgSchema, $baseUrl);
               // Clean description
               // Enforce a single clean RU description everywhere (avoid typos/duplication from DB).
               $orgSchema['description'] = self::cleanText(self::unifiedOrganizationDescriptionRu());
                
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
                   if (is_array($orgSchema['logo'])) {
                       $logoDims = getPublicImageDimensions($orgSchema['logo']['url'] ?? '');
                       if ($logoDims) {
                           $orgSchema['logo']['width'] = $logoDims['width'];
                           $orgSchema['logo']['height'] = $logoDims['height'];
                       }
                   }
               }
               // Opening hours
                if (!empty($seoSettings['opening_hours'])) {
                    $hoursLines = array_values(array_filter(array_map('trim', explode("\n", $seoSettings['opening_hours']))));
                    if (!empty($hoursLines)) {
                        $orgSchema['openingHours'] = $hoursLines;
                    }
                }

                // Area served
                $orgSchema['areaServed'] = [
                    '@type' => 'City',
                    'name' => $seoSettings['area_served'] ?: ($seoSettings['city'] ?? 'Tashkent')
                ];

                // Contact point
                if (!empty($seoSettings['phone'])) {
                    $orgSchema['contactPoint'] = self::buildContactPoint($seoSettings);
                }

                // Brand
                $orgSchema['brand'] = [
                    '@type' => 'Brand',
                    'name' => $orgSchema['name'] ?? ($seoSettings["org_name_$lang"] ?? $seoSettings["site_name_$lang"] ?? '')
                ];
               // Add page image if available
               if (!empty($pageId)) {
                   $pageImage = self::getPageImageForSchema($pageId, $baseUrl);
                   if ($pageImage) {
                       $orgSchema['image'] = $pageImage;
                   }
               }

               unset($orgSchema['@context']);
               return $orgSchema;
           }
        }

        // Fallback to generating from fields
        $schema = [
           '@type' => 'LocalBusiness',
           '@id' => $baseUrl . '#organization',
           'name' => $seoSettings["org_name_$lang"] ?? $seoSettings["site_name_$lang"] ?? 'Site Name',
           'url' => $baseUrl,
           'logo' => [
               '@type' => 'ImageObject',
               'url' => absoluteUrl($seoSettings['org_logo'] ?? ($baseUrl . '/css/logo.png'), $baseUrl)
           ]
        ];

        $schema['description'] = self::cleanText(self::unifiedOrganizationDescriptionRu());

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
        // Opening hours
        if (!empty($seoSettings['opening_hours'])) {
            $hoursLines = array_values(array_filter(array_map('trim', explode("\n", $seoSettings['opening_hours']))));
            if (!empty($hoursLines)) {
                $schema['openingHours'] = $hoursLines;
            }
        }

        // Area served
        $schema['areaServed'] = [
            '@type' => 'City',
            'name' => $seoSettings['area_served'] ?: ($seoSettings['city'] ?? 'Tashkent')
        ];

        // Contact point
        if (!empty($seoSettings['phone'])) {
            $schema['contactPoint'] = self::buildContactPoint($seoSettings);
        }

        // Brand
        $schema['brand'] = [
            '@type' => 'Brand',
            'name' => $schema['name']
        ];
        $logoDims = getPublicImageDimensions($schema['logo']['url'] ?? '');
        if ($logoDims) {
           $schema['logo']['width'] = $logoDims['width'];
           $schema['logo']['height'] = $logoDims['height'];
        }

        // Add page image if available
        if (!empty($pageId)) {
           $pageImage = self::getPageImageForSchema($pageId, $baseUrl);
           if ($pageImage) {
               $schema['image'] = $pageImage;
           }
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
                 unset($siteSchema['@context']);
                 return $siteSchema;
             }
        }

        return [
            '@type' => 'WebSite',
            '@id' => $baseUrl . '#website',
            'url' => $baseUrl,
            'name' => $seoSettings["site_name_$lang"] ?? '',
            'inLanguage' => $lang === 'ru' ? 'ru-RU' : 'uz-UZ',
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

    /**
     * Convert a MySQL timestamp (e.g. "2026-06-20 21:25:20") to ISO 8601
     * with timezone offset, as required by schema.org datePublished/dateModified.
     * Returns null on unparseable input instead of emitting a bad date.
     */
    private static function toIso8601($mysqlTimestamp) {
        try {
            $dt = new DateTime((string)$mysqlTimestamp, new DateTimeZone(date_default_timezone_get() ?: 'UTC'));
            return $dt->format('c');
        } catch (Exception $e) {
            return null;
        }
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
     * Get page image for schema.org representation
     * Retrieves the first image attached to a page and formats it as ImageObject
     */
    private static function getPageImageForSchema($pageId, $baseUrl) {
        if (empty($pageId)) {
            return null;
        }

        require_once BASE_PATH . '/models/PageMedia.php';
        $pageMediaModel = new PageMedia();
        $mediaItems = $pageMediaModel->getPageMedia($pageId);
        
        if (empty($mediaItems)) {
            return null;
        }
        
        // Get the first image
        $firstMedia = reset($mediaItems);
        
        if (empty($firstMedia['filename'])) {
            return null;
        }
        
        $imageUrl = absoluteUrl('/uploads/' . $firstMedia['filename'], $baseUrl);
        $imageObject = [
            '@type' => 'ImageObject',
            'url' => $imageUrl,
            'contentUrl' => $imageUrl
        ];
        
        // Add dimensions if available
        $dims = getPublicImageDimensions($imageUrl);
        if ($dims) {
            $imageObject['width'] = $dims['width'];
            $imageObject['height'] = $dims['height'];
        }
        
        // Add alt text if available
        $altText = $firstMedia['alt_text_ru'] ?? $firstMedia['alt_text_uz'] ?? '';
        if (!empty($altText)) {
            $imageObject['description'] = $altText;
        }
        
        return $imageObject;
    }

    private static function cleanSchemaDescription($text) {
        $text = self::cleanText($text);
        if ($text === '') return $text;
        // Remove phone numbers and call-to-action fragments.
        $text = preg_replace('/\\+?\\d[\\d\\s\\-\\(\\)]{7,}\\d/u', '', $text);
        $text = preg_replace('/(?:звоните|позвоните|call us?|телефон)[\\s:!\\.—\\-]*(?:\\s|$)/ui', '', $text);
        $text = preg_replace('/\\s{2,}/', ' ', trim($text));
        return $text;
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
    public static function generateServiceSchema($page, $lang, $baseUrl, $seo, $pageUrl, $parentService = null) {
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
            'description' => self::cleanSchemaDescription($description),
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

        // Real freshness signals, sourced directly from pages.created_at / pages.updated_at.
        // Never fabricate these; only emit when the DB actually has them.
        if (!empty($page['created_at'])) {
            $datePublished = self::toIso8601($page['created_at']);
            if ($datePublished) {
                $schema['datePublished'] = $datePublished;
            }
        }
        if (!empty($page['updated_at'])) {
            $dateModified = self::toIso8601($page['updated_at']);
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

    /**
     * Generate ImageObject schemas for page media
     */
    public static function generateMediaImageSchemas($pageId, $lang, $baseUrl) {
        require_once BASE_PATH . '/models/PageMedia.php';
        $pageMediaModel = new PageMedia();
        $mediaItems = $pageMediaModel->getPageMedia($pageId);
        
        $imageSchemas = [];
        $baseIndex = 0;
        
        foreach ($mediaItems as $index => $media) {
            if (empty($media['filename'])) continue;
            
            $imageUrl = absoluteUrl('/uploads/' . $media['filename'], $baseUrl);
            $caption = $media["caption_$lang"] ?? '';
            $altText = $media["alt_text_$lang"] ?? '';
            
            $schema = [
                '@type' => 'ImageObject',
                '@id' => $baseUrl . '/uploads/' . $media['filename'] . '#image-' . ($baseIndex + 1),
                'url' => $imageUrl,
                'contentUrl' => $imageUrl
            ];
            
            if (!empty($caption)) {
                $schema['caption'] = $caption;
            }
            
            if (!empty($altText)) {
                $schema['description'] = $altText;
            } elseif (!empty($caption)) {
                $schema['description'] = $caption;
            }
            
            $dims = getPublicImageDimensions($imageUrl);
            if ($dims) {
                $schema['width'] = $dims['width'];
                $schema['height'] = $dims['height'];
            }
            
            $imageSchemas[] = $schema;
            $baseIndex++;
        }
        
        return $imageSchemas;
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