<?php
// path: ./controllers/PageController.php

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/SEO.php';
require_once BASE_PATH . '/models/FAQ.php';
require_once BASE_PATH . '/models/ContentRotation.php';
require_once BASE_PATH . '/models/Analytics.php';
require_once BASE_PATH . '/models/JsonLdGenerator.php'; 
require_once BASE_PATH . '/models/BlogSchema.php';

class PageController extends Controller {
    private $pageModel;
    private $seoModel;
    private $faqModel;
    private $rotationModel;
    private $analyticsModel;
    private $blogSchemaModel;

    public function __construct() {
        parent::__construct();
        $this->pageModel = new Page();
        $this->seoModel = new SEO();
        $this->faqModel = new FAQ();
        $this->rotationModel = new ContentRotation();
        $this->analyticsModel = new Analytics();
        $this->blogSchemaModel = new BlogSchema();
    }

    public function show($slug = 'home', $lang = null) {
        if ($lang) {
            setLanguage($lang);
        }
        
        $currentLang = getCurrentLanguage();
        
        $page = $this->pageModel->getBySlug($slug);
        
        if (!$page) {
            showError(404);
        }
        
        $rotationUsed = false;
        $activeMonth = null;
        
        if ($page['enable_rotation']) {
            $rotationContent = $this->rotationModel->getCurrentMonth($page['id']);
            if ($rotationContent) {
                if (!empty($rotationContent["title_$currentLang"])) {
                    $page["title_$currentLang"] = $rotationContent["title_$currentLang"];
                }
                
                $page["content_$currentLang"] = $rotationContent["content_$currentLang"];
                
                $rotationUsed = true;
                $activeMonth = $rotationContent['active_month'];
                
                $seoFields = [
                    'meta_title', 'meta_description', 'meta_keywords',
                    'og_title', 'og_description', 'og_image',
                    'jsonld'
                ];
                
                foreach ($seoFields as $field) {
                    if ($field === 'og_image') {
                        if (!empty($rotationContent[$field])) {
                            $page[$field] = $rotationContent[$field];
                        }
                    } else {
                        $fieldWithLang = "{$field}_{$currentLang}";
                        if (!empty($rotationContent[$fieldWithLang])) {
                            $page[$fieldWithLang] = $rotationContent[$fieldWithLang];
                        }
                    }
                }
                
                if (!shouldSkipTracking() && !isBot()) {
                    $this->analyticsModel->trackRotationShown($slug, $activeMonth, $currentLang);
                }
            }
        }
        
        $seoSettings = $this->seoModel->getSettings();
        $faqs = $this->faqModel->getBySlug($slug);
        $blogSchema = $this->blogSchemaModel->get($slug);
        
        // Generate hero image schema
        // Note: generateHeroImageSchema returns JSON string for individual use, but we want the raw data or array for the graph.
        // Let's refactor usage. generateHeroImageSchema inside PageController creates an ImageObject.
        // We will call it, decode it, and add to graph.
        $heroImageSchemaJson = $this->generateHeroImageSchema($page['id'], $currentLang);
        $heroImageSchemaArray = !empty($heroImageSchemaJson) ? json_decode($heroImageSchemaJson, true) : null;
        $primaryImageId = $heroImageSchemaArray ? ($heroImageSchemaArray['@id'] ?? null) : null;
        
        // --- PREPARE DATA FOR SCHEMA (Clean Placeholders) ---
        $pageForSchema = $page;
        $pageForSchema["title_$currentLang"] = replacePlaceholders($page["title_$currentLang"], $page, $seoSettings);
        $pageForSchema["meta_description_$currentLang"] = replacePlaceholders($page["meta_description_$currentLang"] ?? '', $page, $seoSettings);
        
        // Generate Global Schema (Organization + WebSite)
        require_once BASE_PATH . '/models/GlobalJsonLdGenerator.php';
        $baseUrl = GlobalJsonLdGenerator::getBaseUrl();
        $orgSchema = GlobalJsonLdGenerator::generateOrganizationSchema($seoSettings, $currentLang, $baseUrl);
        $webSiteSchema = GlobalJsonLdGenerator::generateWebSiteSchema($seoSettings, $currentLang, $baseUrl);
        
        // Prepare FAQ Data (if any)
        $faqId = null;
        $faqSchemaArray = null;
        if (!empty($faqs)) {
            require_once BASE_PATH . '/models/JsonLdGenerator.php';
            // We use standard generator but want array?
            // Let's generate basic structure here for graph consistency or decode
            $pageUrl = $baseUrl . '/' . $page['slug'] . ($currentLang !== DEFAULT_LANGUAGE ? '/' . $currentLang : '');
            // FIX: generateFAQSchema is a helper function, not a class method
            $faqSchemaJson = generateFAQSchema($faqs, $currentLang, $pageUrl);
            $faqSchemaArray = json_decode($faqSchemaJson, true);
            if ($faqSchemaArray) {
                unset($faqSchemaArray['@context']); // Remove context for graph
                $faqId = $faqSchemaArray['@id'] ?? ($pageUrl . '#faq');
                $faqSchemaArray['@id'] = $faqId; // Ensure ID matches
            }
        }

        // Generate WebPage Schema (Pass Image ID and FAQ ID)
        $webPageSchema = GlobalJsonLdGenerator::generateWebPageSchema(
            $pageForSchema, 
            $currentLang, 
            $baseUrl, 
            $primaryImageId,
            $faqId
        );
        $pageCanonicalUrl = $webPageSchema['url'];
        
        // Generate BreadcrumbList Schema
        require_once BASE_PATH . '/models/JsonLdGenerator.php';
        $breadcrumbs = $this->getBreadcrumbData($page, $currentLang, $baseUrl, $seoSettings);
        $breadcrumbSchema = null;
        if (!empty($breadcrumbs)) {
            $items = [];
            foreach ($breadcrumbs as $index => $item) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url']
                ];
            }
            $breadcrumbSchema = [
                '@type' => 'BreadcrumbList',
                '@id' => $pageCanonicalUrl . '#breadcrumb',
                'itemListElement' => $items
            ];

            // Only reference breadcrumb from WebPage when we actually include a BreadcrumbList node.
            $webPageSchema['breadcrumb'] = [
                '@id' => $pageCanonicalUrl . '#breadcrumb'
            ];
        }
        
        // Start graph
        $graph = [$orgSchema, $webSiteSchema];
        
        // SERVICE SCHEMA LOGIC
        if (!in_array($slug, ['home', 'main'])) {
             // Generate Service with Page URL for ID
             $serviceSchema = GlobalJsonLdGenerator::generateServiceSchema(
                 $pageForSchema, 
                 $currentLang, 
                 $baseUrl, 
                 $seoSettings, 
                 $pageCanonicalUrl // Use canonical URL for ID base
             );
             
             // Link WebPage -> Service (mainEntity) via ID
             $webPageSchema['mainEntity'] = [
                 '@id' => $serviceSchema['@id']
             ];
             
             // Add Service to graph
             $graph[] = $serviceSchema;
        }

        // Add WebPage to graph
        $graph[] = $webPageSchema;
        
        // Add BreadcrumbList to graph
        if ($breadcrumbSchema) {
            $graph[] = $breadcrumbSchema;
        }
        
        // Add Hero Image Object to graph
        if ($heroImageSchemaArray) {
            unset($heroImageSchemaArray['@context']);
            $graph[] = $heroImageSchemaArray;
        }
        
        // Add FAQPage to graph
        if ($faqSchemaArray) {
            $graph[] = $faqSchemaArray;
        }

        $sitewideSchema = [
            '@context' => 'https://schema.org',
            '@graph' => $graph
        ];
        
        trackVisit($slug, $currentLang);
        $templateData = [
            'page' => [
                'title' => $page["title_$currentLang"],
                'slug' => $page['slug'],
                'content' => $page["content_$currentLang"],
                'meta_title' => $page["meta_title_$currentLang"],
                'meta_description' => $page["meta_description_$currentLang"],
            ],
            'global' => [
                'phone' => $seoSettings['phone'] ?? '',
                'email' => $seoSettings['email'] ?? '',
                'address' => $seoSettings["address_$currentLang"] ?? '',
                'working_hours' => $seoSettings["working_hours_$currentLang"] ?? '',
                'site_name' => $seoSettings["site_name_$currentLang"] ?? '',
            ],
            'seo' => $seoSettings,
            'faqs' => $faqs,
            'lang' => $currentLang,
            'date' => [
                'year' => date('Y'),
                'month' => date('n'),
                'month_name' => date('F'),
                'day' => date('j'),
            ],
            'rotation' => [
                'active' => $rotationUsed,
                'month' => $activeMonth
            ]
        ];
        
        $page["content_$currentLang"] = renderTemplate($page["content_$currentLang"], $templateData);
        $page["content_$currentLang"] = processMediaPlaceholders($page["content_$currentLang"], $page['id']);
        
        $data = [
            'page' => $page,
            'seo' => $seoSettings,
            'faqs' => $faqs,
            'blogSchema' => $blogSchema,
            'heroImageSchema' => '', // Cleared because it is now in sitewideSchema
            'sitewideSchema' => $sitewideSchema,
            'lang' => $currentLang,
            'templateData' => $templateData
        ];
        
        $this->view('templates/page', $data);
    }

    /**
     * Generate ImageObject schema for hero banner image
     */
    private function generateHeroImageSchema($pageId, $lang) {
        require_once BASE_PATH . '/models/PageMedia.php';
        $pageMediaModel = new PageMedia();
        $heroMedia = $pageMediaModel->getPageMedia($pageId, 'hero');
        
        if (empty($heroMedia)) {
            return '';
        }
        
        // Get page for canonical URL
        $page = $this->pageModel->getById($pageId);
        $baseUrl = siteBaseUrl();
        $canonicalUrl = canonicalUrlForPage($page['slug'] ?? '', $lang);

        $heroImage = $heroMedia[0]; // Use first hero image
        // Ensure URL is absolute by using the calculated baseUrl
        $heroImageUrl = absoluteUrl('/uploads/' . $heroImage['filename'], $baseUrl);
        
        $imageData = [
            'id' => $canonicalUrl . '#primaryimage',
            'url' => $heroImageUrl,
            'contentUrl' => $heroImageUrl,
            'name' => $heroImage["alt_text_$lang"] ?? $page["title_$lang"] ?? '',
            'caption' => $heroImage["caption_$lang"] ?? '',
            'description' => $heroImage["alt_text_$lang"] ?? $page["meta_description_$lang"] ?? ''
        ];
        
        // Add dimensions if available
        if (!empty($heroImage['width'])) {
            $imageData['width'] = $heroImage['width'];
        }
        if (!empty($heroImage['height'])) {
            $imageData['height'] = $heroImage['height'];
        }
        
        return JsonLdGenerator::generateImage($imageData);
    }

    public function trackClick() {
        $slug = trim((string)($_POST['slug'] ?? ''));
        $lang = normalizeTrackingLanguage($_POST['lang'] ?? getCurrentLanguage());

        if (!trackingRateLimit('click', 200, 300)) {
            $this->json(['success' => false, 'message' => 'Rate limit'], 429);
        }

        if (!$slug || !isValidAnalyticsSlug($slug)) {
            $this->json(['success' => false, 'message' => 'Invalid slug'], 400);
        }

        trackClick($slug, $lang);
        $this->json(['success' => true]);
    }

    public function trackInternalLink() {
        $fromSlug = trim((string)($_POST['from'] ?? ''));
        $toSlug = trim((string)($_POST['to'] ?? ''));
        $lang = normalizeTrackingLanguage($_POST['lang'] ?? getCurrentLanguage());

        if (!trackingRateLimit('internal_link', 500, 300)) {
            $this->json(['success' => false, 'message' => 'Rate limit'], 429);
        }

        if (!$fromSlug || !$toSlug) {
            $this->json(['success' => false, 'message' => 'Missing params'], 400);
        }
        if (!isValidAnalyticsInternalLinkSlug($fromSlug) || !isValidAnalyticsInternalLinkSlug($toSlug)) {
            $this->json(['success' => false, 'message' => 'Invalid params'], 400);
        }

        trackInternalLink($fromSlug, $toSlug, $lang);
        $this->json(['success' => true]);
    }

    public function trackPhoneCall() {
        $slug = trim((string)($_POST['slug'] ?? ''));
        $lang = normalizeTrackingLanguage($_POST['lang'] ?? getCurrentLanguage());

        if (!trackingRateLimit('phone_call', 50, 300)) {
            $this->json(['success' => false, 'message' => 'Rate limit'], 429);
        }

        if (!$slug || !isValidAnalyticsSlug($slug)) {
            $this->json(['success' => false, 'message' => 'Invalid slug'], 400);
        }

        trackPhoneCall($slug, $lang);
        $this->json(['success' => true]);
    }
    
    private function getBreadcrumbData($page, $lang, $baseUrl, $seoSettings) {
        $breadcrumbs = [
            ['name' => $seoSettings["site_name_$lang"] ?? 'Home', 'url' => $baseUrl . '/']
        ];
        
        if (in_array($page['slug'], ['home', 'main'])) {
            return []; // No breadcrumbs for home
        }
        
        // Use Page model to get hierarchy
        $breadcrumbPages = $this->pageModel->getBreadcrumbs($page['id']);
        
        // Reverse because getBreadcrumbs might return Leaf -> Root? Let's check model.
        // Model getBreadcrumbs: returns [current, parent, grandparent...]. So yes, we need to reverse or iterate correctly.
        // Wait, logic says: array_unshift($breadcrumbs, $parent); so it builds [Grandparent, Parent, Current].
        // Let's assume it returns Root -> ... -> Current.
        
        foreach ($breadcrumbPages as $bPage) {
            if (in_array($bPage['slug'], ['home', 'main'])) continue;
            
            $url = $baseUrl . '/' . $bPage['slug'];
            if ($lang !== DEFAULT_LANGUAGE) {
                $url .= '/' . $lang;
            }

            $rawName = replacePlaceholders($bPage["title_$lang"], $bPage, $seoSettings);
            $name = $this->cleanBreadcrumbLabel($rawName, $lang);
            
            $breadcrumbs[] = [
                'name' => $name,
                'url' => $url
            ];
        }
        
        return $breadcrumbs;
    }

    private function cleanBreadcrumbLabel($text, $lang) {
        $text = trim(strip_tags((string)($text ?? '')));
        $text = preg_replace('/\s{2,}/', ' ', $text);

        // Prefer a short human label for service pages: "Продать <предмет>".
        if ($lang === 'ru') {
            if (preg_match('/(?:продать|скупка|выкуп)\\s+([а-яёa-z0-9\\s\\-]{2,60})/ui', $text, $m)) {
                $appliance = trim($m[1]);
                // Remove common geo/marketing tails if captured.
                $appliance = preg_replace('/\\s+(в\\s+ташкенте|в\\s+узбекистане|быстро|срочно|недорого|дорого).*$/ui', '', $appliance);
                // Trim after separators that often start SEO tails.
                $appliance = preg_split('/\\s*[\\|\\-—:]+\\s*/u', $appliance)[0] ?? $appliance;
                $appliance = trim($appliance);
                if ($appliance !== '') return 'Продать ' . $appliance;
            }
        }

        // Fallback: keep the first chunk before separators.
        $parts = preg_split('/\\s*[\\|\\-—:]+\\s*/u', $text);
        if (is_array($parts) && !empty($parts[0])) {
            $text = trim($parts[0]);
        }

        // Final hard cap (Breadcrumb item names should be short).
        if (mb_strlen($text) > 70) {
            $text = trim(mb_substr($text, 0, 67)) . '...';
        }

        return $text;
    }
}
