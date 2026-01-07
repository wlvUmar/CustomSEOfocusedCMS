<?php
// path: ./controllers/admin/SchemaController.php
require_once BASE_PATH . '/models/BlogSchema.php';
require_once BASE_PATH . '/models/Page.php';

class SchemaController extends Controller {
    private $schemaModel;
    private $pageModel;
    
    public function __construct() {
        parent::__construct();
        $this->requireAuth();
        $this->schemaModel = new BlogSchema();
        $this->pageModel = new Page();
    }
    
    public function index() {
        $schemas = $this->schemaModel->getAll();
        $pages = $this->pageModel->getAll();
        
        // Enhance pages with has_schema flag
        foreach ($pages as &$page) {
            $page['has_schema'] = isset($schemas[$page['slug']]);
        }
        
        $this->view('admin/schemas/index', [
            'schemas' => $schemas,
            'pages' => $pages,
            'pageName' => 'schemas'
        ]);
    }
    
    public function save() {
        $slug = $_POST['slug'] ?? '';
        $json = $_POST['json'] ?? '';
        
        if (empty($slug)) {
            $_SESSION['error'] = 'Slug is required';
            $this->redirect('/admin/schemas');
        }
        
        if (empty($json)) {
            $this->schemaModel->delete($slug);
            $_SESSION['success'] = 'Schema deleted';
            $this->redirect('/admin/schemas');
        }
        
        $result = $this->schemaModel->save($slug, $json);
        
        if ($result) {
            $_SESSION['success'] = 'Schema saved successfully';
        } else {
            $_SESSION['error'] = 'Invalid JSON format';
        }
        
        $this->redirect('/admin/schemas');
    }
    
    public function delete() {
        $slug = $_POST['slug'] ?? '';
        
        if ($slug) {
            $this->schemaModel->delete($slug);
            $_SESSION['success'] = 'Schema deleted';
        }
        
        $this->redirect('/admin/schemas');
    }
    
    public function bulkImport() {
        $json = $_POST['json'] ?? '';
        
        if (empty($json)) {
            $_SESSION['error'] = 'JSON data required';
            $this->redirect('/admin/schemas');
        }
        
        $result = $this->schemaModel->bulkImport($json);
        
        if ($result) {
            $_SESSION['success'] = 'Bulk import successful';
        } else {
            $_SESSION['error'] = 'Bulk import failed. Check JSON format.';
        }
        
        $this->redirect('/admin/schemas');
    }
}
