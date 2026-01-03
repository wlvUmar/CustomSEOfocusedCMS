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
            'availablePages' => $availablePages
        ]);
    }

    // Add link
    public function addLink() {
        $this->requireAuth();
        
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
        
        $pageId = intval($_POST['page_id'] ?? 0);
        $linkToPageId = intval($_POST['link_to_page_id'] ?? 0);

        if ($pageId && $linkToPageId) {
            $this->widgetModel->removeLink($pageId, $linkToPageId);
            $_SESSION['success'] = 'Link removed';
        }

        $this->redirect('/admin/link-widget/manage/' . $pageId);
    }

    // Reorder links (AJAX)
    public function reorder() {
        $this->requireAuth();
        
        $pageId = intval($_POST['page_id'] ?? 0);
        $linkIds = $_POST['link_ids'] ?? [];

        if ($pageId && is_array($linkIds)) {
            $this->widgetModel->updatePositions($pageId, $linkIds);
            $this->json(['success' => true]);
        } else {
            $this->json(['success' => false], 400);
        }
    }

    // Toggle widget visibility
    public function toggleWidget() {
        $this->requireAuth();
        
        $pageId = intval($_POST['page_id'] ?? 0);
        $show = isset($_POST['show']) && $_POST['show'] === '1';

        if ($pageId) {
            $this->widgetModel->toggleWidget($pageId, $show);
            $_SESSION['success'] = $show ? 'Widget enabled' : 'Widget disabled';
        }

        $this->redirect('/admin/link-widget/manage/' . $pageId);
    }
}