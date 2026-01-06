<?php
// path: ./controllers/PageController.php
// INSTRUCTION: Replace the entire PageController.php file

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/SEO.php';
require_once BASE_PATH . '/models/FAQ.php';
require_once BASE_PATH . '/models/ContentRotation.php';
require_once BASE_PATH . '/models/Analytics.php';
require_once BASE_PATH . '/models/JsonLdGenerator.php'; // ADD THIS LINE

class PageController extends Controller {
    private $pageModel;
    private $seoModel;
    private $faqModel;
    private $rotationModel;
    private $analyticsModel;

    public function __construct() {
        parent::__construct();
        $this->pageModel = new Page();
        $this->seoModel = new SEO();
        $this->faqModel = new FAQ();
        $this->rotationModel = new ContentRotation();
        $this->analyticsModel = new Analytics();
    }

    public function show($slug = 'home', $lang = null) {
        // Set language
        if ($lang) {
            setLanguage($lang);
        }
        
        $currentLang = getCurrentLanguage();
        
        // Get page data
        $page = $this->pageModel->getBySlug($slug);
        
        if (!$page) {
            showError(404);
        }
        
        // Track which content is being shown
        $rotationUsed = false;
        $activeMonth = null;
        
        // Get content rotation if enabled AND apply SEO from rotation
        if ($page['enable_rotation']) {
            $rotationContent = $this->rotationModel->getCurrentMonth($page['id']);
            if ($rotationContent) {
                // Override title and description if provided
                if (!empty($rotationContent["title_$currentLang"])) {
                    $page["title_$currentLang"] = $rotationContent["title_$currentLang"];
                }
                
                // Override content
                $page["content_$currentLang"] = $rotationContent["content_$currentLang"];
                
                $rotationUsed = true;
                $activeMonth = $rotationContent['active_month'];
                
                // Override SEO settings if rotation has them
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
                
                // Track that this rotation was shown
                $this->analyticsModel->trackRotationShown($slug, $activeMonth, $currentLang);
            }
        }
        
        // Get global SEO settings
        $seoSettings = $this->seoModel->getSettings();
        
        // Get FAQs for this page
        $faqs = $this->faqModel->getBySlug($slug);
        
        // Track visit
        trackVisit($slug, $currentLang);
        
        // Build template data with full access to all info
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
        
        // Process media placeholders
        $page["content_$currentLang"] = processMediaPlaceholders($page["content_$currentLang"], $page['id']);
        
        $data = [
            'page' => $page,
            'seo' => $seoSettings,
            'faqs' => $faqs,
            'lang' => $currentLang,
            'templateData' => $templateData
        ];
        
        $this->view('templates/page', $data);
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
}