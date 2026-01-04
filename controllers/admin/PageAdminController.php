<?php
// path: ./controllers/admin/PageAdminController.php

require_once BASE_PATH . '/models/Page.php';

class PageAdminController extends Controller {
    private $pageModel;

    public function __construct() {
        parent::__construct();
        $this->pageModel = new Page();
    }

    public function index() {
        $this->requireAuth();
        $pages = $this->pageModel->getAll(true);
        $this->view('admin/pages/list', ['pages' => $pages]);
    }

    public function edit($id = null) {
        $this->requireAuth();
        
        $page = null;
        if ($id) {
            $page = $this->pageModel->getById($id);
            if (!$page) {
                $_SESSION['error'] = 'Page not found';
                $this->redirect('/admin/pages');
            }
        }
        
        $this->view('admin/pages/edit', ['page' => $page]);
    }

    public function save() {
        $this->requireAuth();
        
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/pages');
        }
        
        $id = $_POST['id'] ?? null;
        $data = [
            'slug' => trim($_POST['slug']),
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
            'enable_rotation' => isset($_POST['enable_rotation']) ? 1 : 0,
            'sort_order' => intval($_POST['sort_order'] ?? 0)
        ];
        
        if ($id) {
            $this->pageModel->update($id, $data);
            $_SESSION['success'] = 'Page updated successfully';
        } else {
            $this->pageModel->create($data);
            $_SESSION['success'] = 'Page created successfully';
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