<?php
// path: ./controllers/admin/PageAdminController.php

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/IndexNow.php';
require_once BASE_PATH . '/models/ContentRotation.php';


class PageAdminController extends Controller {
    private $pageModel;
    private $rotationModel;


    private function sanitizeSlug($slug) {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        if (empty($slug)) {
            throw new Exception('Invalid slug: cannot be empty');
        }

        return $slug;
    }

    public function __construct() {
        parent::__construct();
        $this->pageModel = new Page();
        $this->rotationModel = new ContentRotation();
    }

    public function index() {
        $this->requireAuth();
        $hierarchy = $this->pageModel->getHierarchy(false);
        $allPages = $this->pageModel->getAll(true);
        $this->view('admin/pages/list', ['pages' => $allPages, 'hierarchy' => $hierarchy]);
    }

    public function edit($id = null) {
        $this->requireAuth();
        
        $page = null;
        $availableRotations = [];
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        
        if ($id) {
            $page = $this->pageModel->getById($id);
            if (!$page) {
                $_SESSION['error'] = 'Page not found';
                $this->redirect('/admin/pages');
            }
            // Load available rotations for this page
            $availableRotations = $this->rotationModel->getByPageId($id);
        }
        
        // Get all pages for parent selector, excluding self and descendants
        $allPages = [];
        if ($id) {
            $allPagesRaw = $this->pageModel->getAll(true);
            $descendants = $this->pageModel->getDescendantIds($id);
            
            foreach ($allPagesRaw as $p) {
                if ($p['id'] != $id && !in_array($p['id'], $descendants)) {
                    $allPages[] = $p;
                }
            }
        } else {
            $allPages = $this->pageModel->getAll(true);
        }
        
        $this->view('admin/pages/edit', ['page' => $page, 'allPages' => $allPages, 'availableRotations' => $availableRotations, 'months' => $months]);
    }

    public function save() {
        $this->requireAuth();
        
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/pages');
        }
        
        $id = $_POST['id'] ?? null;
        
        // parent_id: if not sent or empty string, set to null (root level)
        // otherwise convert to int
        $parentId = null;
        if (!empty($_POST['parent_id'])) {
            $parentId = intval($_POST['parent_id']);
        }
        
        $data = [
            'slug' => $this->sanitizeSlug($_POST['slug']),
            'title_ru' => trim($_POST['title_ru']),
            'title_uz' => trim($_POST['title_uz']),
            'content_ru' => $_POST['content_ru'] ?? '',
            'content_uz' => $_POST['content_uz'] ?? '',
            'meta_title_ru' => trim($_POST['meta_title_ru']) ?: null,
            'meta_title_uz' => trim($_POST['meta_title_uz']) ?: null,
            'meta_keywords_ru' => trim($_POST['meta_keywords_ru']) ?: null,
            'meta_keywords_uz' => trim($_POST['meta_keywords_uz']) ?: null,
            'meta_description_ru' => trim($_POST['meta_description_ru']) ?: null,
            'meta_description_uz' => trim($_POST['meta_description_uz']) ?: null,
            'og_title_ru' => trim($_POST['og_title_ru']) ?: null,
            'og_title_uz' => trim($_POST['og_title_uz']) ?: null,
            'og_description_ru' => trim($_POST['og_description_ru']) ?: null,
            'og_description_uz' => trim($_POST['og_description_uz']) ?: null,
            'og_image' => trim($_POST['og_image']) ?: null,
            'canonical_url' => trim($_POST['canonical_url']) ?: null,
            'jsonld_ru' => trim($_POST['jsonld_ru']) ?: null,
            'jsonld_uz' => trim($_POST['jsonld_uz']) ?: null,
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
            'rotation_mode' => $_POST['rotation_mode'] ?? 'auto',
            'selected_rotation_id' => !empty($_POST['selected_rotation_id']) ? intval($_POST['selected_rotation_id']) : null,
            'sort_order' => intval($_POST['sort_order'] ?? 0),
            'parent_id' => $parentId
        ];
        
        if ($id) {
            $this->pageModel->update($id, $data);
            $_SESSION['success'] = 'Page updated successfully';
            

        } else {
            $this->pageModel->create($data);
            $_SESSION['success'] = 'Page created successfully';
            
        }

        // Trigger IndexNow submission
        try {
            $fullUrl = BASE_URL . '/' . $data['slug'];
            // If it's a multilingual site, we might want to submit both language versions if they have different URLs
            // Assuming the router handles /ru/slug and /uz/slug or just /slug depending on setup.
            // Based on code, it seems just /slug.
            
            // Also need to handle nested pages if slug doesn't include parent structure
            // But PageAdminController sanitizeSlug suggests flat slugs or manual handling.
            // Let's assume standard BASE_URL + / + slug for now.
            
            IndexNow::submit($fullUrl);
        } catch (Exception $e) {
            logDebug("IndexNow submission error: " . $e->getMessage());
        }
        
        $this->redirect('/admin/pages');
    }

    public function delete() {
        $this->requireAuth();
        
        $id = $_POST['id'] ?? null;
        if ($id) {
            $this->pageModel->delete($id);
            $_SESSION['success'] = 'Page deleted successfully';
        }
        
        $this->redirect('/admin/pages');
    }
}