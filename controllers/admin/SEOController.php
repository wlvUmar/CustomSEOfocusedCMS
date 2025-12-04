<?php
require_once BASE_PATH . '/models/SEO.php';

class SEOController extends Controller {
    private $seoModel;

    public function __construct() {
        parent::__construct();
        $this->seoModel = new SEO();
    }

    public function index() {
        $this->requireAuth();
        $settings = $this->seoModel->getSettings();
        $this->view('admin/seo/settings', ['settings' => $settings]);
    }

    public function save() {
        $this->requireAuth();
        
        $data = [
            'site_name_ru' => trim($_POST['site_name_ru']),
            'site_name_uz' => trim($_POST['site_name_uz']),
            'meta_keywords_ru' => trim($_POST['meta_keywords_ru']),
            'meta_keywords_uz' => trim($_POST['meta_keywords_uz']),
            'meta_description_ru' => trim($_POST['meta_description_ru']),
            'meta_description_uz' => trim($_POST['meta_description_uz']),
            'phone' => trim($_POST['phone']),
            'email' => trim($_POST['email']),
            'address_ru' => trim($_POST['address_ru']),
            'address_uz' => trim($_POST['address_uz']),
            'working_hours_ru' => trim($_POST['working_hours_ru']),
            'working_hours_uz' => trim($_POST['working_hours_uz'])
        ];
        
        $this->seoModel->updateSettings($data);
        $_SESSION['success'] = 'SEO settings updated successfully';
        
        $this->redirect('/admin/seo');
    }
}