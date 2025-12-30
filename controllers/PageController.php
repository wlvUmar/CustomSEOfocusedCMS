<?php
// path: ./controllers/PageController.php

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/SEO.php';
require_once BASE_PATH . '/models/FAQ.php';
require_once BASE_PATH . '/models/ContentRotation.php';

class PageController extends Controller {
    private $pageModel;
    private $seoModel;
    private $faqModel;
    private $rotationModel;

    public function __construct() {
        parent::__construct();
        $this->pageModel = new Page();
        $this->seoModel = new SEO();
        $this->faqModel = new FAQ();
        $this->rotationModel = new ContentRotation();
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
            http_response_code(404);
            die('Page not found');
        }
        
        // Get content rotation if enabled
        if ($page['enable_rotation']) {
            $rotationContent = $this->rotationModel->getCurrentMonth($page['id']);
            if ($rotationContent) {
                $page["content_$currentLang"] = $rotationContent["content_$currentLang"];
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
            ]
        ];
        
        // Render content with template engine
        $page["content_$currentLang"] = renderTemplate($page["content_$currentLang"], $templateData);
        
        // Prepare data for view
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
}