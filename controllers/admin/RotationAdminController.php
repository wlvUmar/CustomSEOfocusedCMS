<?php

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

    public function manage($pageId) {
        $this->requireAuth();
        
        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            $_SESSION['error'] = 'Page not found';
            $this->redirect('/admin/pages');
        }
        
        $rotations = $this->rotationModel->getByPageId($pageId);
        $months = $this->rotationModel->getMonths();
        
        $this->view('admin/rotations/manage', [
            'page' => $page,
            'rotations' => $rotations,
            'months' => $months
        ]);
    }

    public function edit($id = null, $pageId = null) {
        $this->requireAuth();
        
        $rotation = null;
        $page = null;
        
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
        $this->view('admin/rotations/edit', [
            'rotation' => $rotation,
            'page' => $page,
            'months' => $months
        ]);
    }

    public function save() {
        $this->requireAuth();
        
        $id = $_POST['id'] ?? null;
        $data = [
            'page_id' => intval($_POST['page_id']),
            'content_ru' => $_POST['content_ru'] ?? '',
            'content_uz' => $_POST['content_uz'] ?? '',
            'active_month' => intval($_POST['active_month']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        if ($id) {
            $this->rotationModel->update($id, $data);
            $_SESSION['success'] = 'Content rotation updated successfully';
        } else {
            $this->rotationModel->create($data);
            $_SESSION['success'] = 'Content rotation created successfully';
        }
        
        $this->redirect('/admin/rotations/manage/' . $data['page_id']);
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
}