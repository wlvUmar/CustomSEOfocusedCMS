<?php
// path: ./controllers/ArticleController.php

require_once BASE_PATH . '/models/Article.php';
require_once BASE_PATH . '/models/SEO.php';
require_once BASE_PATH . '/models/ArticleJsonLdGenerator.php';

class ArticleController extends Controller {
    private $articleModel;
    private $seoModel;

    public function __construct() {
        parent::__construct();
        $this->articleModel = new Article();
        $this->seoModel = new SEO();
    }

    /**
     * Display single article
     */
    public function show($id, $lang = null) {
        if ($lang) {
            setLanguage($lang);
        }
        
        $currentLang = getCurrentLanguage();
        
        // Get article by ID
        $article = $this->articleModel->getById($id);
        
        if (!$article || !$article['is_published']) {
            showError(404);
        }
        
        // Get SEO settings
        $seoSettings = $this->seoModel->getSettings();
        
        // Track visit
        trackVisit('article-' . $id, $currentLang);
        
        // Get related articles
        $relatedArticles = [];
        if (!empty($article["category_$currentLang"])) {
            $relatedArticles = $this->articleModel->getRelatedArticles(
                $article['id'],
                $article["category_$currentLang"],
                $currentLang,
                3
            );
        }
        
        // Get internal linking suggestions
        $internalLinks = [];
        if (!empty($article["content_$currentLang"])) {
            $internalLinks = $this->articleModel->suggestInternalLinks(
                $article["content_$currentLang"],
                $currentLang
            );
        }
        
        // Get linked related service page
        $relatedPage = null;
        if (!empty($article['related_page_id'])) {
            require_once BASE_PATH . '/models/Page.php';
            $pageModel = new Page();
            $relatedPage = $pageModel->getById($article['related_page_id']);
        }
        
        // Generate JSON-LD if not already set
        // STRICT DATE SYNCHRONIZATION: Create authoritative date strings
        $datePublished = date('c', strtotime($article['created_at']));
        $dateModified = date('c', strtotime($article['updated_at']));

        // Always generate article JSON-LD at render time to keep schema consistent and avoid localhost leakage
        // from stored JSON-LD blobs in the database.
        $article["jsonld_$currentLang"] = ArticleJsonLdGenerator::generateArticleGraph(
            $article,
            $currentLang,
            $seoSettings,
            [], // FAQs
            $datePublished, // Pass explicit dates
            $dateModified
        );
        
        // Generate Global Schema
        require_once BASE_PATH . '/models/GlobalJsonLdGenerator.php';
        $baseUrl = GlobalJsonLdGenerator::getBaseUrl();
        $orgSchema = GlobalJsonLdGenerator::generateOrganizationSchema($seoSettings, $currentLang, $baseUrl);
        $webSiteSchema = GlobalJsonLdGenerator::generateWebSiteSchema($seoSettings, $currentLang, $baseUrl);
        
        $sitewideSchema = [
            '@context' => 'https://schema.org',
            '@graph' => [$orgSchema, $webSiteSchema]
        ];
        
        // Prepare template data
        $data = [
            'article' => $article,
            'seo' => $seoSettings,
            'lang' => $currentLang,
            'relatedArticles' => $relatedArticles,
            'internalLinks' => $internalLinks,
            'relatedPage' => $relatedPage,
            'sitewideSchema' => $sitewideSchema,
            'datePublished' => $datePublished, // Pass exact same string
            'dateModified' => $dateModified,   // Pass exact same string
            'msg' => []
        ];
        
        $this->view('templates/article', $data);
    }

    /**
     * List all published articles (for future articles index page)
     */
    public function index($lang = null) {
        if ($lang) {
            setLanguage($lang);
        }
        
        $currentLang = getCurrentLanguage();
        $seoSettings = $this->seoModel->getSettings();
        
        // Get all published articles
        $articles = $this->articleModel->getAll(true);
        
        $data = [
            'articles' => $articles,
            'seo' => $seoSettings,
            'lang' => $currentLang
        ];
        
        $this->view('templates/articles_index', $data);
    }
}
