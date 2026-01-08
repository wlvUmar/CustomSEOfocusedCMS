<?php
// controllers/admin/InternalLinksController.php
require_once BASE_PATH . '/models/LinkWidget.php';
require_once BASE_PATH . '/models/Page.php';

class InternalLinksController extends Controller {
    private $widgetModel;
    private $pageModel;

    public function __construct() {
        parent::__construct();
        $this->widgetModel = new LinkWidget();
        $this->pageModel = new Page();
    }

    /**
     * Main overview - see all internal links across all pages
     */
    public function index() {
        $this->requireAuth();
        
        // Get all pages with their link counts
        $pages = $this->getAllPagesWithLinkStats();
        
        // Get link matrix (which pages link to which)
        $linkMatrix = $this->getLinkMatrix();
        
        // Get network density stats
        $stats = $this->getNetworkStats();
        
        $this->view('admin/internal_links/index', [
            'pages' => $pages,
            'linkMatrix' => $linkMatrix,
            'stats' => $stats,
            'pageName' => 'internal_links/index'
        ]);
    }

    /**
     * Manage links for a specific page
     */
    public function managePage($pageId) {
        $this->requireAuth();
        
        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            $_SESSION['error'] = 'Page not found';
            $this->redirect('/admin/internal-links');
            return;
        }

        $currentLinks = $this->widgetModel->getLinksForPage($pageId);
        $availablePages = $this->widgetModel->getAvailablePages($pageId);
        $incomingLinks = $this->getIncomingLinks($pageId);

        $this->view('admin/internal_links/manage_page', [
            'page' => $page,
            'currentLinks' => $currentLinks,
            'availablePages' => $availablePages,
            'incomingLinks' => $incomingLinks,
            'pageName' => 'internal_links/manage_page'
        ]);
    }

    /**
     * Auto-connect pages to create dense network
     */
    public function autoConnect() {
        $this->requireAuth();
        
        $strategy = $_POST['strategy'] ?? 'all-to-all';
        $maxLinks = intval($_POST['max_links'] ?? 5);
        
        $pages = $this->pageModel->getAll();
        $created = 0;
        
        switch ($strategy) {
            case 'all-to-all':
                // Connect every page to every other page
                foreach ($pages as $page) {
                    foreach ($pages as $targetPage) {
                        if ($page['id'] != $targetPage['id']) {
                            try {
                                $this->widgetModel->addLink($page['id'], $targetPage['id']);
                                $created++;
                            } catch (Exception $e) {
                                // Link might already exist
                            }
                        }
                    }
                }
                break;
                
            case 'related':
                // Connect pages based on similar content/keywords
                foreach ($pages as $page) {
                    $related = $this->findRelatedPages($page['id'], $maxLinks);
                    foreach ($related as $targetId) {
                        try {
                            $this->widgetModel->addLink($page['id'], $targetId);
                            $created++;
                        } catch (Exception $e) {
                            // Link might already exist
                        }
                    }
                }
                break;
                
            case 'popular-to-all':
                // Connect all pages to most viewed pages
                $popularPages = $this->getPopularPages(3);
                foreach ($pages as $page) {
                    foreach ($popularPages as $popular) {
                        if ($page['id'] != $popular['id']) {
                            try {
                                $this->widgetModel->addLink($page['id'], $popular['id']);
                                $created++;
                            } catch (Exception $e) {
                                // Link might already exist
                            }
                        }
                    }
                }
                break;
        }
        
        $_SESSION['success'] = "Auto-connected pages! Created $created new links.";
        $this->redirect('/admin/internal-links');
    }

    /**
     * Bulk add/remove links
     */
    public function bulkAction() {
        $this->requireAuth();
        
        $action = $_POST['action'] ?? '';
        $pageIds = $_POST['page_ids'] ?? [];
        $targetPageId = intval($_POST['target_page_id'] ?? 0);
        
        if (empty($pageIds) || !is_array($pageIds)) {
            $_SESSION['error'] = 'No pages selected';
            $this->redirect('/admin/internal-links');
            return;
        }
        
        $count = 0;
        
        foreach ($pageIds as $pageId) {
            $pageId = intval($pageId);
            if ($pageId === $targetPageId) continue;
            
            try {
                if ($action === 'add-links' && $targetPageId) {
                    $this->widgetModel->addLink($pageId, $targetPageId);
                    $count++;
                } elseif ($action === 'remove-links' && $targetPageId) {
                    $this->widgetModel->removeLink($pageId, $targetPageId);
                    $count++;
                } elseif ($action === 'enable-widget') {
                    $this->widgetModel->toggleWidget($pageId, true);
                    $count++;
                } elseif ($action === 'disable-widget') {
                    $this->widgetModel->toggleWidget($pageId, false);
                    $count++;
                }
            } catch (Exception $e) {
                // Continue with next
            }
        }
        
        $_SESSION['success'] = "Bulk action completed for $count page(s)";
        $this->redirect('/admin/internal-links');
    }

    // Helper methods
    
    private function getAllPagesWithLinkStats() {
        $sql = "SELECT p.id, p.slug, p.title_ru, p.title_uz, p.show_link_widget,
                       (SELECT COUNT(*) FROM page_link_widgets WHERE page_id = p.id) as outgoing_links,
                       (SELECT COUNT(*) FROM page_link_widgets WHERE link_to_page_id = p.id) as incoming_links
                FROM pages p
                WHERE p.is_published = 1
                ORDER BY p.title_ru ASC";
        return $this->db->fetchAll($sql);
    }
    
    private function getLinkMatrix() {
        $sql = "SELECT lw.page_id, lw.link_to_page_id, 
                       p1.slug as from_slug, p2.slug as to_slug
                FROM page_link_widgets lw
                JOIN pages p1 ON lw.page_id = p1.id
                JOIN pages p2 ON lw.link_to_page_id = p2.id
                WHERE lw.is_active = 1";
        return $this->db->fetchAll($sql);
    }
    
    private function getNetworkStats() {
        $totalPages = $this->db->fetchOne("SELECT COUNT(*) as count FROM pages WHERE is_published = 1")['count'];
        $totalLinks = $this->db->fetchOne("SELECT COUNT(*) as count FROM page_link_widgets WHERE is_active = 1")['count'];
        $maxPossibleLinks = $totalPages * ($totalPages - 1);
        $density = $maxPossibleLinks > 0 ? ($totalLinks / $maxPossibleLinks) * 100 : 0;
        
        $orphanPages = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM pages p 
             WHERE p.is_published = 1 
             AND NOT EXISTS (SELECT 1 FROM page_link_widgets WHERE page_id = p.id OR link_to_page_id = p.id)"
        )['count'];
        
        return [
            'total_pages' => $totalPages,
            'total_links' => $totalLinks,
            'max_possible_links' => $maxPossibleLinks,
            'density_percentage' => round($density, 2),
            'orphan_pages' => $orphanPages,
            'avg_links_per_page' => $totalPages > 0 ? round($totalLinks / $totalPages, 1) : 0
        ];
    }
    
    private function getIncomingLinks($pageId) {
        $sql = "SELECT lw.*, p.slug, p.title_ru, p.title_uz 
                FROM page_link_widgets lw
                JOIN pages p ON lw.page_id = p.id
                WHERE lw.link_to_page_id = ? AND lw.is_active = 1
                ORDER BY p.title_ru ASC";
        return $this->db->fetchAll($sql, [$pageId]);
    }
    
    private function findRelatedPages($pageId, $limit = 5) {
        // Simple implementation: find pages with similar titles or content
        $page = $this->pageModel->getById($pageId);
        $keywords = $this->extractKeywords($page['title_ru'] . ' ' . $page['content_ru']);
        
        if (empty($keywords)) {
            return [];
        }
        
        $sql = "SELECT id FROM pages 
                WHERE is_published = 1 
                AND id != ?
                AND (title_ru LIKE ? OR content_ru LIKE ?)
                LIMIT ?";
        
        $searchTerm = '%' . implode('%', array_slice($keywords, 0, 3)) . '%';
        $result = $this->db->fetchAll($sql, [$pageId, $searchTerm, $searchTerm, $limit]);
        
        return array_column($result, 'id');
    }
    
    private function extractKeywords($text) {
        // Basic keyword extraction
        $text = strip_tags($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $words = preg_split('/\s+/', mb_strtolower($text));
        $words = array_filter($words, function($w) { return mb_strlen($w) > 4; });
        return array_slice(array_unique($words), 0, 10);
    }
    
    private function getPopularPages($limit = 5) {
        $sql = "SELECT p.id, p.slug, p.title_ru, COUNT(pv.id) as view_count
                FROM pages p
                LEFT JOIN page_views pv ON p.slug = pv.slug
                WHERE p.is_published = 1
                GROUP BY p.id
                ORDER BY view_count DESC
                LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }
}
