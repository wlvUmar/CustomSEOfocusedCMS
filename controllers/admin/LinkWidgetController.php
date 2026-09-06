<?php
// controllers/admin/LinkWidgetController.php
require_once BASE_PATH . '/models/LinkWidget.php';
require_once BASE_PATH . '/models/Page.php';

class LinkWidgetController extends Controller {
    private $widgetModel;
    private $pageModel;

    public function __construct() {
        parent::__construct();
        $this->widgetModel = new LinkWidget();
        $this->pageModel = new Page();
    }

    // Main management page
    public function manage($pageId) {
        $this->requireAuth();
        
        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            $_SESSION['error'] = 'Page not found';
            $this->redirect('/admin/pages');
            return;
        }

        $currentLinks = $this->widgetModel->getLinksForPage($pageId);
        $availablePages = $this->widgetModel->getAvailablePages($pageId);

        $this->view('admin/link_widget/manage', [
            'page' => $page,
            'currentLinks' => $currentLinks,
            'availablePages' => $availablePages,
            'pageName' => 'link_widget/manage'
        ]);
    }

    // Add link
    public function addLink() {
        $this->requireAuth();
        
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/pages');
            return;
        }
        
        $pageId = intval($_POST['page_id'] ?? 0);
        $linkToPageId = intval($_POST['link_to_page_id'] ?? 0);

        if ($pageId && $linkToPageId) {
            $this->widgetModel->addLink($pageId, $linkToPageId);
            $_SESSION['success'] = 'Link added';
        }

        $this->redirect('/admin/link-widget/manage/' . $pageId);
    }

    // Remove link
    public function removeLink() {
        $this->requireAuth();
        
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/pages');
            return;
        }
        
        $pageId = intval($_POST['page_id'] ?? 0);
        $linkToPageId = intval($_POST['link_to_page_id'] ?? 0);

        if ($pageId && $linkToPageId) {
            $this->widgetModel->removeLink($pageId, $linkToPageId);
            $_SESSION['success'] = 'Link removed';
        }

        $this->redirect('/admin/link-widget/manage/' . $pageId);
    }

    // Reorder links (AJAX) — instant save on drag
    public function reorder() {
        $this->requireAuth();
        
        // Accept CSRF from POST body or X-CSRF-Token header (fetch FormData case)
        $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? '';
        // Also support JSON body: {page_id, link_ids, csrf_token}
        $rawJson = null;
        if (empty($_POST['page_id']) && empty($_POST['link_ids'])) {
            $raw = file_get_contents('php://input');
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $rawJson = $decoded;
                    if (empty($csrf) && !empty($decoded['csrf_token'])) $csrf = $decoded['csrf_token'];
                }
            }
        }
        if (!validateCSRFToken($csrf)) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }
        
        $pageId = intval($_POST['page_id'] ?? ($rawJson['page_id'] ?? 0));
        $linkIds = $_POST['link_ids'] ?? ($rawJson['link_ids'] ?? null);

        // FormData sends link_ids[] ; JSON sends link_ids
        if ($pageId && is_array($linkIds) && !empty($linkIds)) {
            // sanitize to ints, preserve order, drop empties
            $linkIds = array_values(array_filter(array_map('intval', $linkIds), fn($v)=>$v>0));
            if (empty($linkIds)) {
                $this->json(['success' => false, 'message' => 'No link ids'], 400);
                return;
            }
            $this->widgetModel->updatePositions($pageId, $linkIds);
            $this->json(['success' => true]);
        } else {
            $this->json(['success' => false, 'message' => 'Missing page_id or link_ids'], 400);
        }
    }

    // Toggle widget visibility
    public function toggleWidget() {
        $this->requireAuth();
        
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/pages');
            return;
        }
        
        $pageId = intval($_POST['page_id'] ?? 0);
        $show = isset($_POST['show']) && $_POST['show'] === '1';

        if ($pageId) {
            $this->widgetModel->toggleWidget($pageId, $show);
            $_SESSION['success'] = $show ? 'Widget enabled' : 'Widget disabled';
        }

        $this->redirect('/admin/link-widget/manage/' . $pageId);
    }
}