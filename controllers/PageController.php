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
        $heroImageSchema = $this->generateHeroImageSchema($page['id'], $currentLang);
        
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
            'heroImageSchema' => $heroImageSchema,
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
        $baseUrl = BASE_URL;
        if (strpos($baseUrl, '://') === false) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . rtrim($host, '/');
        }
        $baseUrl = rtrim($baseUrl, '/');
        $canonicalUrl = $baseUrl . '/' . $page['slug'] . ($lang !== DEFAULT_LANGUAGE ? '/' . $lang : '');

        $heroImage = $heroMedia[0]; // Use first hero image
        // Ensure URL is absolute by using the calculated baseUrl
        $heroImageUrl = $baseUrl . '/uploads/' . $heroImage['filename'];
        
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
        $slug = $_POST['slug'] ?? '';
        $lang = $_POST['lang'] ?? getCurrentLanguage();
        
        if ($slug) {
            trackClick($slug, $lang);
            $this->json(['success' => true]);
        }
        
        $this->json(['success' => false], 400);
    }

    public function trackInternalLink() {
        $fromSlug = $_POST['from'] ?? '';
        $toSlug = $_POST['to'] ?? '';
        $lang = $_POST['lang'] ?? getCurrentLanguage();
        
        if ($fromSlug && $toSlug) {
            trackInternalLink($fromSlug, $toSlug, $lang);
            $this->json(['success' => true]);
        }
        
        $this->json(['success' => false], 400);
    }

    public function trackPhoneCall() {
        $slug = $_POST['slug'] ?? '';
        $lang = $_POST['lang'] ?? getCurrentLanguage();
        
        if ($slug) {
            trackPhoneCall($slug, $lang);
            $this->json(['success' => true]);
        }
        
        $this->json(['success' => false], 400);
    }
}
