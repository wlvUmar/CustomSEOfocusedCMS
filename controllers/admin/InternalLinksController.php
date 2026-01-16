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
        
        // Sort pages hierarchically
        $pages = $this->sortPagesHierarchically($pages);
        
        // Enrich with hierarchy info
        foreach ($pages as &$page) {
            $page['children'] = $this->pageModel->getChildren($page['id'], false);
            $page['parent'] = $this->pageModel->getParent($page['id']);
            $page['siblings'] = $this->pageModel->getSiblings($page['id'], false);
        }
        
        // Get link matrix (which pages link to which)
        $linkMatrix = $this->getLinkMatrix();
        
        // Get network density stats
        $stats = $this->getNetworkStats();
        
        // Add hierarchy stats
        $hierarchyStats = $this->getHierarchyStats();
        $stats = array_merge($stats, $hierarchyStats);
        
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
        
        // Add hierarchy context
        $hierarchyContext = [
            'parent' => $this->pageModel->getParent($pageId),
            'children' => $this->pageModel->getChildren($pageId, false),
            'siblings' => $this->pageModel->getSiblings($pageId, false),
            'breadcrumbs' => $this->pageModel->getBreadcrumbs($pageId)
        ];

        $this->view('admin/internal_links/manage_page', [
            'page' => $page,
            'currentLinks' => $currentLinks,
            'availablePages' => $availablePages,
            'incomingLinks' => $incomingLinks,
            'hierarchyContext' => $hierarchyContext,
            'pageName' => 'internal_links/manage_page'
        ]);
    }

    /**
     * Auto-connect pages to create dense network
     */
    public function autoConnect() {
        $this->requireAuth();
        
        $strategy = $_POST['strategy'] ?? 'hierarchy-aware';
        $maxLinks = intval($_POST['max_links'] ?? 5);
        
        $pages = $this->pageModel->getAll();
        $created = 0;
        
        switch ($strategy) {
            case 'hierarchy-aware':
                // Connect parent to children, children to siblings, and related pages
                foreach ($pages as $page) {
                    $links = [];
                    
                    // 1. Link to parent
                    if ($page['parent_id']) {
                        $links[] = $page['parent_id'];
                    }
                    
                    // 2. Link to children
                    $children = $this->pageModel->getChildren($page['id']);
                    foreach ($children as $child) {
                        $links[] = $child['id'];
                    }
                    
                    // 3. Link to siblings
                    $siblings = $this->pageModel->getSiblings($page['id']);
                    foreach (array_slice($siblings, 0, 3) as $sibling) {
                        $links[] = $sibling['id'];
                    }
                    
                    // 4. Fill remaining slots with related pages
                    if (count($links) < $maxLinks) {
                        $related = $this->findRelatedPages($page['id'], $maxLinks - count($links));
                        $links = array_merge($links, $related);
                    }
                    
                    // Create links
                    $links = array_unique($links);
                    foreach (array_slice($links, 0, $maxLinks) as $targetId) {
                        if ($targetId != $page['id']) {
                            try {
                                $this->widgetModel->addLink($page['id'], $targetId);
                                $created++;
                            } catch (Exception $e) {
                                // Link might already exist
                            }
                        }
                    }
                }
                break;
                
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
                // Smart: Connect pages based on similar content/keywords
                foreach ($pages as $page) {
                    $related = $this->findRelatedPagesSmart($page, $maxLinks);
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
     * Smart algorithm to find related pages
     */
    private function findRelatedPagesSmart($sourcePage, $limit = 5) {
        $scores = [];
        
        // Prepare source tokens
        $sourceTokens = $this->extractTokens($sourcePage);
        
        // Get all other published pages
        $allPages = $this->pageModel->getAll(false);
        
        foreach ($allPages as $targetPage) {
            if ($targetPage['id'] == $sourcePage['id']) continue;
            
            $targetTokens = $this->extractTokens($targetPage);
            $score = $this->calculateRelevanceScore($sourceTokens, $targetTokens);
            
            if ($score > 0) {
                $scores[$targetPage['id']] = $score;
            }
        }
        
        // Sort by score desc
        arsort($scores);
        
        return array_slice(array_keys($scores), 0, $limit);
    }
    
    /**
     * Extract weighted tokens from page
     */
    private function extractTokens($page) {
        $tokens = [];
        
        // 1. Title (Weight: 3)
        $this->addTokens($tokens, $page['title_ru'], 3);
        
        // 2. Meta Keywords (Weight: 5) - Strongest signal
        if (!empty($page['meta_keywords_ru'])) {
            $this->addTokens($tokens, $page['meta_keywords_ru'], 5);
        }
        
        // 3. Content (Weight: 1) - Limit length to avoid noise
        $content = strip_tags($page['content_ru']);
        $content = substr($content, 0, 1000); // Analyze first 1000 chars
        $this->addTokens($tokens, $content, 1);
        
        return $tokens;
    }
    
    private function addTokens(&$tokens, $text, $weight) {
        if (empty($text)) return;
        
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $words = preg_split('/\s+/', $text);
        
        $stopWords = ['the', 'and', 'for', 'that', 'this', 'with', 'from', 'but', 'not', 'are', 'was', 'were', // EN
                      'ва', 'билан', 'учун', 'бу', 'шу', 'ҳам', 'деб', 'ки', // UZ
                      'и', 'в', 'во', 'не', 'что', 'он', 'на', 'я', 'с', 'со', 'как', 'а', 'то', 'все', 'она', 'так', 'его', 'но', 'да', 'ты', 'к', 'у', 'же', 'вы', 'за', 'бы', 'по', 'только', 'ее', 'мне', 'было', 'вот', 'от', 'меня', 'еще', 'нет', 'о', 'из', 'ему', 'теперь', 'когда', 'даже', 'ну', 'вдруг', 'ли', 'если', 'уже', 'или', 'ни', 'быть', 'был', 'него', 'до', 'вас', 'нибудь', 'опять', 'уж', 'вам', 'ведь', 'там', 'потом', 'себя', 'ничего', 'ей', 'может', 'они', 'тут', 'где', 'есть', 'надо', 'ней', 'для', 'мы', 'тебя', 'их', 'чем', 'была', 'сам', 'чтоб', 'без', 'будто', 'чего', 'раз', 'тоже', 'себе', 'под', 'будет', 'ж', 'тогда', 'кто', 'этот', 'того', 'потому', 'этого', 'какой', 'совсем', 'ним', 'здесь', 'этом', 'один', 'почти', 'мой', 'тем', 'чтобы', 'нее', 'сейчас', 'были', 'куда', 'зачем', 'всех', 'никогда', 'можно', 'при', 'наконец', 'два', 'об', 'другой', 'хоть', 'после', 'над', 'больше', 'тот', 'через', 'эти', 'нас', 'про', 'всего', 'них', 'какая', 'много', 'разве', 'три', 'эту', 'моя', 'впрочем', 'хорошо', 'свою', 'этой', 'перед', 'иногда', 'лучше', 'чуть', 'том', 'нельзя', 'такой', 'им', 'более', 'всегда', 'конечно', 'всю', 'между']; // RU
        
        foreach ($words as $word) {
            if (mb_strlen($word) < 3) continue;
            if (in_array($word, $stopWords)) continue;
            
            if (!isset($tokens[$word])) {
                $tokens[$word] = 0;
            }
            $tokens[$word] += $weight;
        }
    }
    
    private function calculateRelevanceScore($sourceTokens, $targetTokens) {
        $score = 0;
        foreach ($sourceTokens as $word => $weight) {
            if (isset($targetTokens[$word])) {
                // Score is product of weights (e.g. Title match Title = 3*3 = 9)
                $score += $weight * $targetTokens[$word];
            }
        }
        return $score;
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
        $sql = "SELECT p.id, p.slug, p.title_ru, p.title_uz, p.show_link_widget, p.parent_id,
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
    
    /**
     * Get hierarchy statistics
     */
    private function getHierarchyStats() {
        $totalPages = $this->db->fetchOne("SELECT COUNT(*) as count FROM pages WHERE is_published = 1")['count'];
        $rootPages = $this->db->fetchOne("SELECT COUNT(*) as count FROM pages WHERE is_published = 1 AND parent_id IS NULL")['count'];
        $pagesWithChildren = $this->db->fetchOne("SELECT COUNT(DISTINCT parent_id) as count FROM pages WHERE parent_id IS NOT NULL")['count'];
        
        return [
            'total_root_pages' => $rootPages,
            'total_parent_pages' => $pagesWithChildren,
            'total_child_pages' => $totalPages - $rootPages,
            'hierarchy_depth' => $this->getMaxDepth()
        ];
    }

    /**
     * Get maximum hierarchy depth
     */
    private function getMaxDepth() {
        $result = $this->db->fetchOne("SELECT MAX(depth) as max_depth FROM pages");
        return $result['max_depth'] ?? 0;
    }

    /**
     * Sort pages in hierarchical order (Parent -> Children)
     */
    private function sortPagesHierarchically($pages) {
        $grouped = [];
        foreach ($pages as $page) {
            $parentId = $page['parent_id'] ? $page['parent_id'] : 'root';
            $grouped[$parentId][] = $page;
        }
        
        $sorted = [];
        $this->flattenTree($grouped, 'root', 0, $sorted);
        
        // Append any pages that weren't reached (detached subtrees/circular refs)
        if (count($sorted) < count($pages)) {
            $sortedIds = array_flip(array_column($sorted, 'id'));
            foreach ($pages as $page) {
                if (!isset($sortedIds[$page['id']])) {
                    $page['level'] = 0; // Reset level for detached
                    $sorted[] = $page;
                }
            }
        }
        
        return $sorted;
    }

    /**
     * Recursive helper to flatten the page tree
     */
    private function flattenTree(&$grouped, $parentId, $depth, &$result) {
        if (!isset($grouped[$parentId])) return;
        
        // Sort siblings by title
        usort($grouped[$parentId], function($a, $b) {
            return strcmp($a['title_ru'], $b['title_ru']);
        });

        foreach ($grouped[$parentId] as $page) {
            $page['level'] = $depth; // using 'level' to be consistent with common naming, view will use this
            $result[] = $page;
            // Recursively add children
            $this->flattenTree($grouped, $page['id'], $depth + 1, $result);
        }
    }
}
