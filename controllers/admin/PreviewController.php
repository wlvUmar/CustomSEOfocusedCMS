<?php
// path: ./controllers/admin/PreviewController.php
// IMPLEMENTATION: Place in controllers/admin/PreviewController.php

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/ContentRotation.php';
require_once BASE_PATH . '/models/SEO.php';
require_once BASE_PATH . '/models/FAQ.php';

class PreviewController extends Controller {
    private $pageModel;
    private $rotationModel;
    private $seoModel;
    private $faqModel;

    public function __construct() {
        parent::__construct();
        $this->pageModel = new Page();
        $this->rotationModel = new ContentRotation();
        $this->seoModel = new SEO();
        $this->faqModel = new FAQ();
    }

    /**
     * Preview page with specific rotation month
     * URL: /admin/preview/{pageId}?month={1-12}&lang={ru|uz}
     */
    public function show($pageId) {
        $this->requireAuth();
        
        $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
        $lang = isset($_GET['lang']) ? $_GET['lang'] : 'ru';
        
        if (!in_array($lang, ['ru', 'uz']) || $month < 1 || $month > 12) {
            $_SESSION['error'] = 'Invalid parameters';
            $this->redirect('/admin/pages');
            return;
        }
        
        // Get page
        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            $_SESSION['error'] = 'Page not found';
            $this->redirect('/admin/pages');
            return;
        }
        
        // Get rotation for this month if enabled
        $rotationContent = null;
        if ($page['enable_rotation']) {
            $sql = "SELECT * FROM content_rotations WHERE page_id = ? AND active_month = ? LIMIT 1";
            $rotationContent = $this->db->fetchOne($sql, [$pageId, $month]);
            
            if ($rotationContent) {
                // Override page content with rotation
                if (!empty($rotationContent["title_$lang"])) {
                    $page["title_$lang"] = $rotationContent["title_$lang"];
                }
                $page["content_$lang"] = $rotationContent["content_$lang"];
                
                // Override SEO if rotation has it
                $seoFields = [
                    'meta_title', 'meta_description', 'meta_keywords',
                    'og_title', 'og_description', 'og_image', 'jsonld'
                ];
                
                foreach ($seoFields as $field) {
                    if ($field === 'og_image') {
                        if (!empty($rotationContent[$field])) {
                            $page[$field] = $rotationContent[$field];
                        }
                    } else {
                        $fieldWithLang = "{$field}_{$lang}";
                        if (!empty($rotationContent[$fieldWithLang])) {
                            $page[$fieldWithLang] = $rotationContent[$fieldWithLang];
                        }
                    }
                }
            }
        }
        
        // Get global SEO settings
        $seoSettings = $this->seoModel->getSettings();
        
        // Get FAQs
        $faqs = $this->faqModel->getBySlug($page['slug']);
        
        // Build template data with preview date
        $previewDate = new DateTime();
        $previewDate->setDate(date('Y'), $month, 1);
        
        $templateData = [
            'page' => [
                'title' => $page["title_$lang"],
                'slug' => $page['slug'],
                'content' => $page["content_$lang"],
                'meta_title' => $page["meta_title_$lang"],
                'meta_description' => $page["meta_description_$lang"],
            ],
            'global' => [
                'phone' => $seoSettings['phone'] ?? '',
                'email' => $seoSettings['email'] ?? '',
                'address' => $seoSettings["address_$lang"] ?? '',
                'working_hours' => $seoSettings["working_hours_$lang"] ?? '',
                'site_name' => $seoSettings["site_name_$lang"] ?? '',
            ],
            'seo' => $seoSettings,
            'faqs' => $faqs,
            'lang' => $lang,
            'date' => [
                'year' => $previewDate->format('Y'),
                'month' => $month,
                'month_name' => $previewDate->format('F'),
                'day' => $previewDate->format('j'),
            ],
            'rotation' => [
                'active' => !empty($rotationContent),
                'month' => $month
            ]
        ];
        
        // Render content with template engine
        $page["content_$lang"] = renderTemplate($page["content_$lang"], $templateData);
        
        // Prepare data for view
        $data = [
            'page' => $page,
            'seo' => $seoSettings,
            'faqs' => $faqs,
            'lang' => $lang,
            'templateData' => $templateData,
            'previewMode' => true,
            'previewMonth' => $month,
            'hasRotation' => !empty($rotationContent)
        ];
        
        $this->view('templates/preview', $data);
    }
    
    /**
     * API endpoint to get preview content
     * Returns JSON for inline preview
     */
    public function getPreviewContent($pageId) {
        $this->requireAuth();
        
        $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
        $lang = isset($_GET['lang']) ? $_GET['lang'] : 'ru';
        
        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            $this->json(['error' => 'Page not found'], 404);
            return;
        }
        
        // Get rotation
        $rotationContent = null;
        if ($page['enable_rotation']) {
            $sql = "SELECT * FROM content_rotations WHERE page_id = ? AND active_month = ? LIMIT 1";
            $rotationContent = $this->db->fetchOne($sql, [$pageId, $month]);
        }
        
        // Process content
        $seoSettings = $this->seoModel->getSettings();
        $faqs = $this->faqModel->getBySlug($page['slug']);
        
        $previewDate = new DateTime();
        $previewDate->setDate(date('Y'), $month, 1);
        
        $templateData = [
            'page' => [
                'title' => $rotationContent["title_$lang"] ?? $page["title_$lang"],
                'slug' => $page['slug'],
            ],
            'global' => [
                'phone' => $seoSettings['phone'] ?? '',
                'email' => $seoSettings['email'] ?? '',
                'address' => $seoSettings["address_$lang"] ?? '',
                'working_hours' => $seoSettings["working_hours_$lang"] ?? '',
                'site_name' => $seoSettings["site_name_$lang"] ?? '',
            ],
            'date' => [
                'year' => $previewDate->format('Y'),
                'month' => $month,
                'month_name' => $previewDate->format('F'),
                'day' => $previewDate->format('j'),
            ]
        ];
        
        $content = $rotationContent["content_$lang"] ?? $page["content_$lang"];
        $content = renderTemplate($content, $templateData);
        
        $this->json([
            'success' => true,
            'content' => $content,
            'title' => $templateData['page']['title'],
            'hasRotation' => !empty($rotationContent),
            'month' => $month
        ]);
    }
}