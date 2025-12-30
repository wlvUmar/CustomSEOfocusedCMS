<?php
// path: ./controllers/admin/RotationAdminController.php

require_once BASE_PATH . '/models/ContentRotation.php';
require_once BASE_PATH . '/models/Page.php';

class RotationAdminController extends Controller {
    private $rotationModel;
    private $pageModel;

    public function __construct() {
        parent::__construct();
        $this->rotationModel = new ContentRotation();
        $this->pageModel = new Page();
    }

    /**
     * Overview of all pages with rotation status
     */
    public function overview() {
        $this->requireAuth();
        
        $pages = $this->pageModel->getAll(true);
        $rotationStatus = [];
        
        foreach ($pages as $page) {
            if ($page['enable_rotation']) {
                $stats = $this->rotationModel->getCoverageStats($page['id']);
                $rotationStatus[] = [
                    'page' => $page,
                    'stats' => $stats
                ];
            }
        }
        
        // Get pages with incomplete rotation
        $incompletePages = $this->rotationModel->getPagesWithIncompleteRotation();
        
        $this->view('admin/rotations/overview', [
            'rotationStatus' => $rotationStatus,
            'incompletePages' => $incompletePages
        ]);
    }

    public function manage($pageId) {
        $this->requireAuth();
        
        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            $_SESSION['error'] = 'Page not found';
            $this->redirect('/admin/pages');
        }
        
        $rotations = $this->rotationModel->getByPageId($pageId);
        $months = $this->rotationModel->getMonths();
        $stats = $this->rotationModel->getCoverageStats($pageId);
        
        $this->view('admin/rotations/manage', [
            'page' => $page,
            'rotations' => $rotations,
            'months' => $months,
            'stats' => $stats
        ]);
    }

    public function edit($id = null, $pageId = null) {
        $this->requireAuth();
        
        $rotation = null;
        $page = null;
        $suggestedMonth = $_GET['month'] ?? null;
        
        if ($id) {
            $rotation = $this->rotationModel->getById($id);
            if (!$rotation) {
                $_SESSION['error'] = 'Rotation not found';
                $this->redirect('/admin/pages');
            }
            $page = $this->pageModel->getById($rotation['page_id']);
        } else if ($pageId) {
            $page = $this->pageModel->getById($pageId);
        }
        
        if (!$page) {
            $_SESSION['error'] = 'Page not found';
            $this->redirect('/admin/pages');
        }
        
        $months = $this->rotationModel->getMonths();
        $stats = $this->rotationModel->getCoverageStats($page['id']);
        
        $this->view('admin/rotations/edit', [
            'rotation' => $rotation,
            'page' => $page,
            'months' => $months,
            'stats' => $stats,
            'suggestedMonth' => $suggestedMonth
        ]);
    }

    public function save() {
        $this->requireAuth();
        
        $id = $_POST['id'] ?? null;
        $pageId = intval($_POST['page_id']);
        $activeMonth = intval($_POST['active_month']);
        
        // Check for duplicate month (only if creating or changing month)
        if (!$id || ($id && $this->rotationModel->getById($id)['active_month'] != $activeMonth)) {
            if ($this->rotationModel->monthHasContent($pageId, $activeMonth)) {
                $_SESSION['error'] = 'This month already has content. Please edit the existing entry or choose a different month.';
                $this->redirect('/admin/rotations/manage/' . $pageId);
                return;
            }
        }
        
        $data = [
            'page_id' => $pageId,
            'content_ru' => $_POST['content_ru'] ?? '',
            'content_uz' => $_POST['content_uz'] ?? '',
            'active_month' => $activeMonth,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        if ($id) {
            $this->rotationModel->update($id, $data);
            $_SESSION['success'] = 'Content rotation updated successfully';
        } else {
            $this->rotationModel->create($data);
            $_SESSION['success'] = 'Content rotation created successfully';
        }
        
        $this->redirect('/admin/rotations/manage/' . $pageId);
    }

    /**
     * Clone rotation content to another month
     */
    public function clone() {
        $this->requireAuth();
        
        $sourceId = intval($_POST['source_id'] ?? 0);
        $targetMonth = intval($_POST['target_month'] ?? 0);
        
        if (!$sourceId || !$targetMonth) {
            $_SESSION['error'] = 'Invalid parameters';
            $this->redirect('/admin/rotations/overview');
            return;
        }
        
        $source = $this->rotationModel->getById($sourceId);
        if (!$source) {
            $_SESSION['error'] = 'Source rotation not found';
            $this->redirect('/admin/rotations/overview');
            return;
        }
        
        $result = $this->rotationModel->cloneToMonth($sourceId, $targetMonth);
        
        if ($result) {
            $_SESSION['success'] = 'Content cloned successfully to ' . $this->rotationModel->getMonthNameRu($targetMonth);
        } else {
            $_SESSION['error'] = 'Failed to clone content. Target month may already have content.';
        }
        
        $this->redirect('/admin/rotations/manage/' . $source['page_id']);
    }

    /**
     * Bulk operations
     */
    public function bulkAction() {
        $this->requireAuth();
        
        $action = $_POST['action'] ?? '';
        $ids = $_POST['ids'] ?? [];
        $pageId = $_POST['page_id'] ?? null;
        
        if (empty($ids) || !is_array($ids)) {
            $_SESSION['error'] = 'No items selected';
            $this->redirect($pageId ? '/admin/rotations/manage/' . $pageId : '/admin/rotations/overview');
            return;
        }
        
        $ids = array_map('intval', $ids);
        
        switch ($action) {
            case 'activate':
                $this->rotationModel->bulkUpdateStatus($ids, 1);
                $_SESSION['success'] = count($ids) . ' rotation(s) activated';
                break;
                
            case 'deactivate':
                $this->rotationModel->bulkUpdateStatus($ids, 0);
                $_SESSION['success'] = count($ids) . ' rotation(s) deactivated';
                break;
                
            case 'delete':
                $this->rotationModel->bulkDelete($ids);
                $_SESSION['success'] = count($ids) . ' rotation(s) deleted';
                break;
                
            default:
                $_SESSION['error'] = 'Invalid action';
        }
        
        $this->redirect($pageId ? '/admin/rotations/manage/' . $pageId : '/admin/rotations/overview');
    }

    public function delete() {
        $this->requireAuth();
        
        $id = $_POST['id'] ?? null;
        $pageId = $_POST['page_id'] ?? null;
        
        if ($id) {
            $this->rotationModel->delete($id);
            $_SESSION['success'] = 'Content rotation deleted successfully';
        }
        
        if ($pageId) {
            $this->redirect('/admin/rotations/manage/' . $pageId);
        } else {
            $this->redirect('/admin/pages');
        }
    }

    /**
     * Preview rotation content without saving
     */
    public function preview() {
        $this->requireAuth();
        
        $contentRu = $_POST['content_ru'] ?? '';
        $contentUz = $_POST['content_uz'] ?? '';
        $lang = $_POST['lang'] ?? 'ru';
        
        // Simple preview rendering
        $content = $lang === 'uz' ? $contentUz : $contentRu;
        
        echo '<html><head><meta charset="UTF-8"><style>
            body { font-family: sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
            h1, h2, h3 { color: #303034; }
        </style></head><body>';
        echo $content;
        echo '</body></html>';
        exit;
    }
}