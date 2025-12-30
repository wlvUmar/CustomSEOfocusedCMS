<?php

require_once BASE_PATH . '/models/FAQ.php';
require_once BASE_PATH . '/models/Page.php';

class FAQAdminController extends Controller {
    private $faqModel;
    private $pageModel;

    public function __construct() {
        parent::__construct();
        $this->faqModel = new FAQ();
        $this->pageModel = new Page();
    }

    public function index() {
        $this->requireAuth();
        
        $faqs = $this->faqModel->getAll();
        $pages = $this->pageModel->getAll(true);
        
        $this->view('admin/faqs/list', ['faqs' => $faqs, 'pages' => $pages]);
    }

    public function edit($id = null) {
        $this->requireAuth();
        
        $faq = null;
        if ($id) {
            $faq = $this->faqModel->getById($id);
            if (!$faq) {
                $_SESSION['error'] = 'FAQ not found';
                $this->redirect('/admin/faqs');
            }
        }
        
        $pages = $this->pageModel->getAll(true);
        $this->view('admin/faqs/edit', ['faq' => $faq, 'pages' => $pages]);
    }

    public function save() {
        $this->requireAuth();
        
        $id = $_POST['id'] ?? null;
        $data = [
            'page_slug' => trim($_POST['page_slug']),
            'question_ru' => trim($_POST['question_ru']),
            'question_uz' => trim($_POST['question_uz']),
            'answer_ru' => trim($_POST['answer_ru']),
            'answer_uz' => trim($_POST['answer_uz']),
            'sort_order' => intval($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        if ($id) {
            $this->faqModel->update($id, $data);
            $_SESSION['success'] = 'FAQ updated successfully';
        } else {
            $this->faqModel->create($data);
            $_SESSION['success'] = 'FAQ created successfully';
        }
        
        $this->redirect('/admin/faqs');
    }

    public function delete() {
        $this->requireAuth();
        
        $id = $_POST['id'] ?? null;
        if ($id) {
            $this->faqModel->delete($id);
            $_SESSION['success'] = 'FAQ deleted successfully';
        }
        
        $this->redirect('/admin/faqs');
    }
}