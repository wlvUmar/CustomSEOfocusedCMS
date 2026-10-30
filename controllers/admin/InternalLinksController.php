<?php
// controllers/admin/InternalLinksController.php
require_once BASE_PATH . '/models/LinkWidget.php';
require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/InternalLinkRepository.php';
require_once BASE_PATH . '/models/InternalLinkScorer.php';
require_once BASE_PATH . '/models/PageTree.php';

class InternalLinksController extends Controller {
    private $widgetModel;
    private $pageModel;
    private $linkRepo;

    public function __construct() {
        parent::__construct();
        $this->widgetModel = new LinkWidget();
        $this->pageModel = new Page();
        $this->linkRepo = new InternalLinkRepository();
    }

    /**
     * Main overview - see all internal links across all pages
     */
    public function index() {
        $this->requireAuth();

        // Get all pages with their link counts
        $pages = $this->linkRepo->getAllPagesWithLinkStats();

        // Sort pages hierarchically
        $pages = PageTree::sortPagesHierarchically($pages);

        // Enrich with hierarchy info
        foreach ($pages as &$page) {
            $page['children'] = $this->pageModel->getChildren($page['id'], false);
            $page['parent'] = $this->pageModel->getParent($page['id']);
            $page['siblings'] = $this->pageModel->getSiblings($page['id'], false);
        }

        // Get link matrix (which pages link to which)
        $linkMatrix = $this->linkRepo->getLinkMatrix();

        // Get network density stats
        $stats = $this->linkRepo->getNetworkStats();

        // Add hierarchy stats
        $hierarchyStats = $this->linkRepo->getHierarchyStats();
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
        $incomingLinks = $this->linkRepo->getIncomingLinks($pageId);

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

        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/internal-links');
            return;
        }

        $strategy = $_POST['strategy'] ?? 'hierarchy-aware';
        $maxLinks = intval($_POST['max_links'] ?? 5);

        $pages = $this->pageModel->getAll();
        $created = 0;
        $enabledWidgets = 0;

        // Existing outgoing targets per page, to skip duplicates and count accurately.
        $existingMap = [];
        foreach ($pages as $p) {
            $rows = $this->widgetModel->getLinksForPage($p['id']);
            $existingMap[$p['id']] = array_map(function ($r) { return (int)($r['link_to_page_id'] ?? 0); }, $rows);
        }

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

                    // 4. Fill remaining slots with related pages (keyword-OR, excludes already-linked)
                    if (count($links) < $maxLinks) {
                        $srcPage = $this->pageModel->getById($page['id']);
                        $keywords = InternalLinkScorer::extractKeywords($srcPage['title_ru'] . ' ' . $srcPage['content_ru']);
                        if (!empty($keywords)) {
                            $exclude = $links;
                            $exclude[] = $page['id'];
                            $related = $this->linkRepo->findRelatedByKeywords($page['id'], $keywords, $maxLinks - count($links), $exclude);
                            $links = array_merge($links, $related);
                        }
                    }

                    // Create links (skip self + already-linked, enable widget so links are visible)
                    $links = array_unique(array_map('intval', $links));
                    $added = 0;
                    foreach (array_slice($links, 0, $maxLinks) as $targetId) {
                        if ($targetId == $page['id']) continue;
                        if (in_array($targetId, $existingMap[$page['id']] ?? [], true)) continue;
                        try {
                            $this->widgetModel->addLink($page['id'], $targetId);
                            $created++;
                            $added++;
                        } catch (Exception $e) {
                            // Link might already exist
                        }
                    }
                    if ($added > 0) {
                        $cur = $this->pageModel->getById($page['id']);
                        if (empty($cur['show_link_widget'])) {
                            $this->widgetModel->toggleWidget($page['id'], true);
                            $enabledWidgets++;
                        }
                    }
                }
                break;

            case 'all-to-all':
                // Connect every page to every other page (skip existing, enable widget)
                foreach ($pages as $page) {
                    $added = 0;
                    foreach ($pages as $targetPage) {
                        if ($page['id'] == $targetPage['id']) continue;
                        if (in_array((int)$targetPage['id'], $existingMap[$page['id']] ?? [], true)) continue;
                        try {
                            $this->widgetModel->addLink($page['id'], $targetPage['id']);
                            $created++;
                            $added++;
                        } catch (Exception $e) {
                            // Link might already exist
                        }
                    }
                    if ($added > 0) {
                        $cur = $this->pageModel->getById($page['id']);
                        if (empty($cur['show_link_widget'])) {
                            $this->widgetModel->toggleWidget($page['id'], true);
                            $enabledWidgets++;
                        }
                    }
                }
                break;

            case 'related':
                // Smart: Connect pages based on similar content/keywords
                $allPages = $this->pageModel->getAll(false);
                foreach ($pages as $page) {
                    $related = InternalLinkScorer::findRelatedPagesSmart($page, $allPages, $maxLinks);
                    $added = 0;
                    foreach ($related as $targetId) {
                        if (in_array((int)$targetId, $existingMap[$page['id']] ?? [], true)) continue;
                        try {
                            $this->widgetModel->addLink($page['id'], $targetId);
                            $created++;
                            $added++;
                        } catch (Exception $e) {
                            // Link might already exist
                        }
                    }
                    if ($added > 0) {
                        $cur = $this->pageModel->getById($page['id']);
                        if (empty($cur['show_link_widget'])) {
                            $this->widgetModel->toggleWidget($page['id'], true);
                            $enabledWidgets++;
                        }
                    }
                }
                break;

            case 'popular-to-all':
                // Connect all pages to most viewed pages
                $popularPages = $this->linkRepo->getPopularPages(3);
                foreach ($pages as $page) {
                    $added = 0;
                    foreach ($popularPages as $popular) {
                        if ($page['id'] == $popular['id']) continue;
                        if (in_array((int)$popular['id'], $existingMap[$page['id']] ?? [], true)) continue;
                        try {
                            $this->widgetModel->addLink($page['id'], $popular['id']);
                            $created++;
                            $added++;
                        } catch (Exception $e) {
                            // Link might already exist
                        }
                    }
                    if ($added > 0) {
                        $cur = $this->pageModel->getById($page['id']);
                        if (empty($cur['show_link_widget'])) {
                            $this->widgetModel->toggleWidget($page['id'], true);
                            $enabledWidgets++;
                        }
                    }
                }
                break;
        }

        $_SESSION['success'] = "Auto-connected pages! Created $created new links." . ($enabledWidgets > 0 ? " Enabled widget on $enabledWidgets page(s)." : "");
        $this->redirect('/admin/internal-links');
    }

    /**
     * Bulk add/remove links
     */
    public function bulkAction() {
        $this->requireAuth();

        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/internal-links');
            return;
        }

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
}
