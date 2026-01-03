<?php
// NEW FILE: controllers/admin/InternalLinksController.php

require_once BASE_PATH . '/models/InternalLinks.php';
require_once BASE_PATH . '/models/Page.php';

class InternalLinksController extends Controller {
    private $linksModel;
    private $pageModel;

    public function __construct() {
        parent::__construct();
        $this->linksModel = new InternalLinks();
        $this->pageModel = new Page();
    }

    /**
     * Main internal links management page
     */
    public function index() {
        $this->requireAuth();
        
        $pages = $this->pageModel->getAll(false); // Only published
        $suggestions = $this->linksModel->generateSuggestions();
        
        // Group suggestions by source page
        $groupedSuggestions = [];
        foreach ($suggestions as $suggestion) {
            $fromId = $suggestion['from_page_id'];
            if (!isset($groupedSuggestions[$fromId])) {
                $groupedSuggestions[$fromId] = [
                    'page' => [
                        'id' => $fromId,
                        'slug' => $suggestion['from_slug'],
                        'title' => $suggestion['from_title']
                    ],
                    'suggestions' => []
                ];
            }
            $groupedSuggestions[$fromId]['suggestions'][] = $suggestion;
        }
        
        $this->view('admin/internal_links/index', [
            'pages' => $pages,
            'groupedSuggestions' => $groupedSuggestions
        ]);
    }

    /**
     * Page-specific link management
     */
    public function managePage($pageId) {
        $this->requireAuth();
        
        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            $_SESSION['error'] = 'Page not found';
            $this->redirect('/admin/internal-links');
            return;
        }
        
        $suggestions = array_filter(
            $this->linksModel->generateSuggestions(),
            function($s) use ($pageId) {
                return $s['from_page_id'] == $pageId;
            }
        );
        
        $existingLinksRu = $this->linksModel->getExistingLinks($pageId, 'ru');
        $existingLinksUz = $this->linksModel->getExistingLinks($pageId, 'uz');
        
        $this->view('admin/internal_links/manage', [
            'page' => $page,
            'suggestions' => $suggestions,
            'existingLinksRu' => $existingLinksRu,
            'existingLinksUz' => $existingLinksUz
        ]);
    }

    /**
     * Auto-insert links for a page
     */
    public function autoInsert() {
        $this->requireAuth();
        
        $pageId = $_POST['page_id'] ?? null;
        $maxLinks = intval($_POST['max_links'] ?? 3);
        $language = $_POST['language'] ?? 'ru';
        
        if (!$pageId) {
            $this->json(['success' => false, 'message' => 'Page ID required'], 400);
            return;
        }
        
        $insertedCount = $this->linksModel->autoInsertLinks($pageId, $maxLinks, $language);
        
        if ($insertedCount > 0) {
            $_SESSION['success'] = "Inserted $insertedCount internal link(s) in $language content";
            $this->json(['success' => true, 'inserted' => $insertedCount]);
        } else {
            $_SESSION['error'] = 'No suitable positions found for links';
            $this->json(['success' => false, 'message' => 'No links inserted']);
        }
    }

    /**
     * Remove all internal links from a page
     */
    public function removeLinks() {
        $this->requireAuth();
        
        $pageId = $_POST['page_id'] ?? null;
        $language = $_POST['language'] ?? 'ru';
        
        if (!$pageId) {
            $this->json(['success' => false, 'message' => 'Page ID required'], 400);
            return;
        }
        
        $result = $this->linksModel->removeAllLinks($pageId, $language);
        
        if ($result) {
            $_SESSION['success'] = "Removed all internal links from $language content";
            $this->json(['success' => true]);
        } else {
            $_SESSION['error'] = 'Failed to remove links';
            $this->json(['success' => false]);
        }
    }

    /**
     * Bulk auto-insert for all pages
     */
    public function bulkAutoInsert() {
        $this->requireAuth();
        
        $pages = $this->pageModel->getAll(false);
        $totalInserted = 0;
        $processedPages = 0;
        
        foreach ($pages as $page) {
            $insertedRu = $this->linksModel->autoInsertLinks($page['id'], 3, 'ru');
            $insertedUz = $this->linksModel->autoInsertLinks($page['id'], 3, 'uz');
            
            if ($insertedRu > 0 || $insertedUz > 0) {
                $processedPages++;
                $totalInserted += $insertedRu + $insertedUz;
            }
        }
        
        $_SESSION['success'] = "Processed $processedPages pages. Inserted $totalInserted links total.";
        $this->redirect('/admin/internal-links');
    }

    /**
     * Get suggestions API
     */
    public function getSuggestions() {
        $this->requireAuth();
        
        $pageId = $_GET['page_id'] ?? null;
        $suggestions = $this->linksModel->generateSuggestions();
        
        if ($pageId) {
            $suggestions = array_filter($suggestions, function($s) use ($pageId) {
                return $s['from_page_id'] == $pageId;
            });
        }
        
        $this->json([
            'success' => true,
            'suggestions' => array_values($suggestions)
        ]);
    }
}