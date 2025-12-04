<?php
require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/SEO.php';

class PageController extends Controller {
    private $pageModel;
    private $seoModel;

    public function __construct() {
        parent::__construct();
        $this->pageModel = new Page();
        $this->seoModel = new SEO();
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
        
        // Get global SEO settings
        $seoSettings = $this->seoModel->getSettings();
        
        // Track visit
        trackVisit($slug, $currentLang);
        
        // Prepare data for view
        $data = [
            'page' => $page,
            'seo' => $seoSettings,
            'lang' => $currentLang
        ];
        
        $this->view('templates/page', $data);
    }
}