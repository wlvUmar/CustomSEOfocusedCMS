<?php
// path: ./controllers/admin/RotationSectionController.php

require_once BASE_PATH . '/models/ContentRotation.php';
require_once BASE_PATH . '/models/Page.php';

class RotationSectionController extends Controller {
    private $rotationModel;
    private $pageModel;

    public function __construct() {
        parent::__construct();
        $this->rotationModel = new ContentRotation();
        $this->pageModel = new Page();
    }

    public function index() {
        $this->requireAuth();
        
        // Load the main section page with tabs
        $this->view('admin/sections/rotation_section', [
            'activeTab' => 'overview'
        ]);
    }

    public function overviewContent() {
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
        
        $incompletePages = $this->rotationModel->getPagesWithIncompleteRotation();
        
        $this->view('admin/sections/rotation_overview_content', [
            'rotationStatus' => $rotationStatus,
            'incompletePages' => $incompletePages
        ]);
    }

    public function manageContent($pageId) {
        $this->requireAuth();
        
        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            echo json_encode(['error' => 'Page not found']);
            return;
        }
        
        $rotations = $this->rotationModel->getByPageId($pageId);
        $months = $this->rotationModel->getMonths();
        $stats = $this->rotationModel->getCoverageStats($pageId);
        
        $this->view('admin/sections/rotation_manage_content', [
            'page' => $page,
            'rotations' => $rotations,
            'months' => $months,
            'stats' => $stats
        ]);
    }
}